<?php

use App\Ai\AdviceDrafter;
use App\Filament\Resources\AdviceDrafts\Pages\ManageAdviceDrafts;
use App\Models\AdviceDraft;
use App\Models\Generation;
use App\Models\User;
use App\Site\AdviceWriter;
use App\Site\StatsService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    $this->generation = Generation::where('slug', 'b8')->first();
});

it('shows a table in which every number comes from the statistics', function () {
    $rows = app(AdviceWriter::class)->rows($this->generation);
    $facts = app(AdviceWriter::class)->facts($this->generation);
    $text = collect($rows)->pluck('lines')->flatten()->implode(' ');

    expect(collect($rows)->pluck('label')->all())->toContain('Voertuigen', 'Vaakst voorkomend', 'Vaker dan gemiddeld');
    expect($text)->toContain(number_format($facts['aantal_voertuigen'], 0, ',', '.'));
    expect($text)->toContain(number_format($facts['meest_voorkomend'][0]['percentage'], 1, ',', '.').'%');

    // the same checker that guards the AI: no number in the table may be missing from the facts
    expect(AdviceDrafter::unknownNumbers($text, $facts))->toBe([]);
});

it('names the clearly weak and strong points, with the exact ratio', function () {
    // make one defect clearly worse and one clearly better than the other models
    $top = app(StatsService::class)->topDefects($this->generation)['defects'];
    $worse = $top[0]->code;
    DB::table('defect_stats')->where('generation_id', $this->generation->id)->where('defect_code', $worse)->update(['vehicles_with_defect' => DB::raw('vehicles_with_defect * 4')]);
    DB::table('defect_stats')->where('car_model_id', '!=', $this->generation->car_model_id)->where('defect_code', $worse)->update(['vehicles_with_defect' => DB::raw('floor(vehicles_with_defect / 2)')]);
    Cache::flush();

    $comparison = app(StatsService::class)->comparison($this->generation);

    expect($comparison['enough_data'])->toBeTrue();
    expect($comparison['weak'][0]['code'])->toBe((string) $worse);
    expect($comparison['weak'][0]['ratio'])->toBeGreaterThanOrEqual(1.5);
    // the ratio in the data is the percentage of this model divided by the percentage of the others
    expect($comparison['weak'][0]['ratio'])->toBe(round($comparison['weak'][0]['percentage'] / $comparison['weak'][0]['average'], 1));

    $weakRow = collect(app(AdviceWriter::class)->rows($this->generation))->firstWhere('label', 'Vaker dan gemiddeld');
    expect($weakRow['tone'])->toBe('bad');
    expect(implode(' ', $weakRow['lines']))->toContain(number_format($comparison['weak'][0]['ratio'], 1, ',', '.').' keer vaker');
});

it('gives the same table for the same page every time', function () {
    $writer = app(AdviceWriter::class);

    expect($writer->rows($this->generation))->toBe($writer->rows($this->generation));
    expect($writer->rows($this->generation, 2010))->not->toBe($writer->rows($this->generation));   // a single year has its own numbers
});

it('shows nothing when there are too few cars', function () {
    config(['site.min_sample' => 10_000_000]);
    Cache::flush();

    expect(app(AdviceWriter::class)->rows($this->generation))->toBe([]);
});

it('shows the table in a dropdown on the generation page and the year page', function () {
    foreach (['/audi/a4/b8', '/audi/a4/b8/2010'] as $url) {
        $html = $this->get($url)->assertOk()->assertSee('Wat de cijfers zeggen')->getContent();

        // a <details> dropdown that is closed at first and works without JavaScript, with the figures in a table
        expect($html)->toContain('<details class="group');
        expect($html)->not->toMatch('/<details[^>]* open/');
        expect($html)->toMatch('/<th scope="row"[^>]*>\s*Vaakst voorkomend\s*<\/th>/');
    }
});

it('shows the new price only when the RDW has prices for the model', function () {
    expect(app(AdviceWriter::class)->facts($this->generation)['nieuwprijs_gemiddeld_euro'])->toBeNull();

    DB::table('model_prices')->insert([
        ['car_model_id' => $this->generation->car_model_id, 'build_year' => 2010, 'vehicles' => 30, 'average_price' => 40000],
        ['car_model_id' => $this->generation->car_model_id, 'build_year' => 2011, 'vehicles' => 10, 'average_price' => 50000],
    ]);
    Cache::flush();

    expect(app(AdviceWriter::class)->facts($this->generation)['nieuwprijs_gemiddeld_euro'])->toBe(42500);   // weighted: (30*40000 + 10*50000) / 40

    // "Nieuwprijs" is the first row, with a bold label on the left and the amount next to it
    $price = app(AdviceWriter::class)->rows($this->generation)[0];
    expect($price['label'])->toBe('Nieuwprijs')->and(implode(' ', $price['lines']))->toContain('€ 42.500');
    $this->get('/audi/a4/b8')->assertSee('€ 42.500')->assertSee('Nieuwprijs');
});

it('finds numbers in an AI text that are not in the facts', function () {
    $facts = ['aantal_voertuigen' => 32404, 'percentage' => 25.5, 'bouwjaren' => '2008–2015', 'auto' => 'Audi A4 B8'];

    expect(AdviceDrafter::unknownNumbers('Bij 25,5% van de 32.404 auto\'s, gebouwd van 2008 tot 2015. De top 3 telt drie gebreken.', $facts))->toBe([]);
    expect(AdviceDrafter::unknownNumbers('Na 150.000 km is de distributieketting aan vervanging toe, bij 26% van de auto\'s.', $facts))->toBe(['150.000', '26']);
});

it('does nothing when the AI link is off', function () {
    config(['site.ai.enabled' => false]);
    Http::fake();

    expect(app(AdviceDrafter::class)->draft($this->generation))->toBeString();
    Http::assertNothingSent();
    $this->artisan('advice:draft')->assertFailed();
});

it('makes a draft from the facts, and rejects one with an invented number', function () {
    config(['site.ai.enabled' => true, 'site.ai.api_key' => 'test-key']);
    $facts = app(AdviceWriter::class)->facts($this->generation);
    $good = 'Bij de '.$facts['auto'].' zijn '.number_format($facts['aantal_voertuigen'], 0, ',', '.')." voertuigen gevolgd.\n\nHet is een indicatie.";

    Http::fakeSequence('api.anthropic.com/*')
        ->push(['content' => [['type' => 'text', 'text' => $good]]])
        ->push(['content' => [['type' => 'text', 'text' => 'Na 150.000 km gaat de distributieketting stuk.']]]);

    $draft = app(AdviceDrafter::class)->draft($this->generation);
    expect($draft->status)->toBe('concept')->and($draft->body)->toBe($good)->and($draft->facts['aantal_voertuigen'])->toBe($facts['aantal_voertuigen']);

    $bad = app(AdviceDrafter::class)->draft($this->generation);
    expect($bad->status)->toBe('rejected')->and($bad->note)->toContain('150.000');

    // the request only contains our own numbers and the instruction not to add facts
    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
        && str_contains($request['system'], 'UITSLUITEND')
        && str_contains($request['messages'][0]['content'], (string) $facts['aantal_voertuigen']));
});

it('puts a draft on the site only after approval, and a draft never overwrites silently', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));

    $draft = AdviceDraft::create(['generation_id' => $this->generation->id, 'body' => 'Concepttekst.', 'facts' => [], 'status' => 'concept']);

    $this->get('/audi/a4/b8')->assertDontSee('Concepttekst.');   // a concept is not live

    Livewire::test(ManageAdviceDrafts::class)
        ->callAction(TestAction::make('edit')->table($draft), ['body' => 'Aangepaste tekst.'])
        ->callAction(TestAction::make('approve')->table($draft));

    expect($this->generation->fresh()->buying_advice)->toBe('Aangepaste tekst.');
    expect($draft->fresh()->status)->toBe('approved');
    $this->get('/audi/a4/b8')->assertSee('Aangepaste tekst.');

    $rejected = AdviceDraft::create(['generation_id' => $this->generation->id, 'body' => 'Andere tekst.', 'facts' => [], 'status' => 'concept']);
    Livewire::test(ManageAdviceDrafts::class)->callAction(TestAction::make('reject')->table($rejected));
    expect($this->generation->fresh()->buying_advice)->toBe('Aangepaste tekst.');
});

it('writes drafts with the command only for generations without advice or open drafts', function () {
    config(['site.ai.enabled' => true, 'site.ai.api_key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Een indicatie voor de Audi A4 B8.']]])]);
    Generation::query()->update(['buying_advice' => 'Mijn eigen tekst']);
    Generation::where('slug', 'b8')->update(['buying_advice' => null]);

    $this->artisan('advice:draft')->assertSuccessful();
    expect(AdviceDraft::count())->toBe(1);

    $this->artisan('advice:draft')->assertSuccessful();   // the open concept is not drafted again
    expect(AdviceDraft::count())->toBe(1);
});

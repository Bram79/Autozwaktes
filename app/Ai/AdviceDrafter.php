<?php

namespace App\Ai;

use App\Models\AdviceDraft;
use App\Models\Generation;
use App\Site\AdviceWriter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks Claude to turn OUR statistics into a fluent buying advice.
 *
 * The AI only gets the numbers from the database. Afterwards every number in its answer is checked against those facts:
 * a text with a number that was not in the data is rejected. The result is a draft that a person must approve.
 */
class AdviceDrafter
{
    private const SYSTEM = <<<'TEXT'
        Je schrijft een kort koopadvies in het Nederlands voor mensen die een gebruikte auto overwegen.
        Je krijgt een JSON-bestand met cijfers uit de APK-gegevens van de RDW. Gebruik UITSLUITEND die gegevens.

        Regels:
        - Voeg geen feiten toe die niet in de gegevens staan: geen motorproblemen, geen onderhoudskosten, geen prijzen die niet genoemd zijn, geen reputatie van het merk, geen eigen kennis over de auto.
        - Noem getallen precies zoals ze in de gegevens staan, zonder af te ronden of nieuwe getallen te berekenen.
        - Schrijf over wat de cijfers laten zien: welke gebreken vaak voorkomen, wat vaker of minder vaak dan gemiddeld is, en waar een koper bij een bezichtiging op kan letten.
        - Zeg erbij dat het om een indicatie uit APK-cijfers gaat en niet over een individuele auto.
        - 120 tot 220 woorden, gewone alinea's zonder opsommingstekens, zonder kopjes, zonder reclametaal.
        - Antwoord alleen met de tekst van het advies.
        TEXT;

    public function __construct(private AdviceWriter $writer) {}

    public function enabled(): bool
    {
        return config('site.ai.enabled') && filled(config('site.ai.api_key'));
    }

    /**
     * Write a draft for a generation.
     *
     * @return AdviceDraft|string the saved draft, or the reason why none was made
     */
    public function draft(Generation $generation): AdviceDraft|string
    {
        if (! $this->enabled()) {
            return 'De AI-koppeling staat uit (AI_ADVICE_ENABLED en ANTHROPIC_API_KEY in .env).';
        }

        $facts = $this->writer->facts($generation);
        if ($facts === null) {
            return 'Te weinig voertuigen voor betrouwbare cijfers.';
        }

        try {
            $response = Http::withHeaders(['x-api-key' => config('site.ai.api_key'), 'anthropic-version' => '2023-06-01'])
                ->timeout(60)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('site.ai.model'),
                    'max_tokens' => 800,
                    'system' => self::SYSTEM,
                    'messages' => [['role' => 'user', 'content' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
                ])->throw();
        } catch (Throwable $e) {
            return 'De AI gaf geen antwoord: '.$e->getMessage();
        }

        $text = trim((string) $response->json('content.0.text'));
        if ($text === '') {
            return 'De AI gaf een lege tekst.';
        }

        $unknown = self::unknownNumbers($text, $facts);

        return AdviceDraft::create([
            'generation_id' => $generation->id,
            'body' => $text,
            'facts' => $facts,
            'ai_model' => config('site.ai.model'),
            'status' => $unknown === [] ? AdviceDraft::CONCEPT : AdviceDraft::REJECTED,
            'note' => $unknown === [] ? null : 'Getallen die niet in de cijfers staan: '.implode(', ', $unknown),
        ]);
    }

    /**
     * Numbers in the text that are not in the facts. Small whole numbers (1 to 10) are allowed: "drie gebreken", "top 3".
     *
     * @return string[]
     */
    public static function unknownNumbers(string $text, array $facts): array
    {
        $allowed = array_map([self::class, 'parse'], self::numbersIn(json_encode($facts, JSON_UNESCAPED_UNICODE)));
        $unknown = [];

        foreach (self::numbersIn($text) as $number) {
            $value = self::parse($number);

            if (! in_array($value, $allowed, true) && ! ($value === floor($value) && $value >= 0 && $value <= 10)) {
                $unknown[] = $number;
            }
        }

        return array_values(array_unique($unknown));
    }

    /** @return string[] */
    private static function numbersIn(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)*/', $text, $matches);

        return $matches[0];
    }

    /** "1.250" => 1250.0, "25,5" => 25.5, "25.5" => 25.5 */
    private static function parse(string $number): float
    {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $number)) {
            $number = str_replace('.', '', $number);
        }

        return round((float) str_replace(',', '.', $number), 2);
    }
}

<?php

use App\Models\ApkReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

/** The RDW knows the plate, with or without an APK date. */
function rdwAnswers(?string $apk): void
{
    Http::fake(['opendata.rdw.nl/*' => Http::response([array_filter([
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto',
        'vervaldatum_apk_dt' => $apk ? $apk.'T00:00:00.000' : null,
    ])])]);
}

/** A confirmed reminder without an APK date, last touched $daysAgo days ago, confirmed $confirmedDaysAgo days ago. */
function reminderWithoutDate(int $daysAgo, int $confirmedDaysAgo = 20): ApkReminder
{
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => now()->subDays($confirmedDaysAgo)]);
    ApkReminder::whereKey($reminder->id)->update(['updated_at' => now()->subDays($daysAgo)]);

    return $reminder;
}

it('does not ask the RDW every day for a reminder that has no APK date yet', function () {
    rdwAnswers(null);
    reminderWithoutDate(daysAgo: 1);

    $this->artisan('apk:remind')->assertSuccessful();

    Http::assertNothingSent();
});

it('asks again after a week, and keeps waiting when there is still no date', function () {
    rdwAnswers(null);
    $reminder = reminderWithoutDate(daysAgo: 8);

    $this->artisan('apk:remind')->assertSuccessful();

    Http::assertSentCount(1);
    expect(ApkReminder::find($reminder->id))->not->toBeNull();
    expect(ApkReminder::find($reminder->id)->updated_at->isAfter(now()->subDay()))->toBeTrue();   // so tomorrow it is not asked again
});

it('deletes a reminder that still has no APK date three months after it was confirmed', function () {
    rdwAnswers(null);
    reminderWithoutDate(daysAgo: 8, confirmedDaysAgo: 91);

    $this->artisan('apk:remind')->assertSuccessful();

    expect(ApkReminder::count())->toBe(0);
});

it('saves the date when the RDW has one by now', function () {
    $in = Carbon::today()->addDays(200)->toDateString();
    rdwAnswers($in);
    $reminder = reminderWithoutDate(daysAgo: 8);

    $this->artisan('apk:remind')->assertSuccessful();

    expect(ApkReminder::find($reminder->id)->apk_expires_at->toDateString())->toBe($in);
});

it('tells in the privacy statement that a reminder without an APK date is deleted after three months', function () {
    $this->get('/privacy')->assertSee('drie maanden na je bevestiging', false);
});

<?php

use App\Mail\ConfirmApkReminder;
use App\Models\ApkReminder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
});

it('runs in Dutch, so framework texts and dates are Dutch too', function () {
    expect(app()->getLocale())->toBe('nl');
    expect(now()->setDate(2027, 6, 13)->translatedFormat('j F Y'))->toBe('13 juni 2027');
});

it('shows Dutch error pages for expired pages, too many requests and no access', function (int $status, string $text) {
    Route::get("/_fout/{$status}", fn () => abort($status))->middleware('web');

    $this->get("/_fout/{$status}")->assertStatus($status)->assertSee($text);
})->with([
    [419, 'Pagina verlopen'],
    [429, 'Te veel verzoeken'],
    [403, 'Geen toegang'],
]);

it('has a Dutch footer in the mails', function () {
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1']);

    $html = (new ConfirmApkReminder($reminder))->render();

    expect($html)->toContain('Alle rechten voorbehouden')->not->toContain('All rights reserved');
});

it('explains in Dutch when an e-mail address is too long for the APK reminder', function () {
    $this->post('/apk-herinnering', ['email' => str_repeat('a', 200).'@example.com', 'kenteken' => 'AB-123-C', 'akkoord' => '1'])
        ->assertSessionHasErrors(['email' => 'Dit e-mailadres is te lang.']);
});

it('explains in Dutch when the license plate field is too long', function () {
    $this->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => str_repeat('A', 30), 'akkoord' => '1'])
        ->assertSessionHasErrors(['kenteken' => 'Dit is geen geldig kenteken. Een kenteken heeft 6 tekens, bijvoorbeeld AB-123-C.']);
});

<?php

namespace App\Mail;

use App\Models\ApkReminder;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/** The reminder itself. A partner block can be switched on in the admin (Inkomsten > Affiliate-instellingen). */
class ApkReminderMail extends Mailable
{
    public function __construct(public ApkReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Je APK verloopt op '.$this->reminder->apk_expires_at->translatedFormat('j F Y'));
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    public function content(): Content
    {
        $partner = Setting::affiliate('apk_mail');
        $showPartner = $partner && ($partner['enabled'] ?? false) && filled($partner['url'] ?? null);

        return new Content(markdown: 'mail.apk-reminder', with: [
            'plate' => $this->reminder->license_plate,
            'expires' => $this->reminder->apk_expires_at->translatedFormat('j F Y'),
            'days' => (int) now()->startOfDay()->diffInDays($this->reminder->apk_expires_at, false),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
            'partner' => $showPartner ? $partner : null,
            // the click is counted by /go, like everywhere else
            'partnerUrl' => $showPartner ? route('go', ['partner' => 'apk_mail', 'plek' => 'apk-mail', 'p' => '/mail']) : null,
            'homeUrl' => url('/'),
        ]);
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('apk.unsubscribe', ['token' => $this->reminder->token]);
    }
}

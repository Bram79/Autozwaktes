<?php

namespace App\Mail;

use App\Models\ApkReminder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** Double opt-in: nothing is sent until the owner of the address clicks the link in this mail. */
class ConfirmApkReminder extends Mailable
{
    public function __construct(public ApkReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bevestig je APK-herinnering');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.apk-confirm', with: [
            'confirmUrl' => URL::temporarySignedRoute('apk.confirm', now()->addDays(7), ['token' => $this->reminder->token]),
            'unsubscribeUrl' => URL::signedRoute('apk.unsubscribe', ['token' => $this->reminder->token]),
            'plate' => $this->reminder->license_plate,
        ]);
    }
}

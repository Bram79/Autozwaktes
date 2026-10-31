<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmApkReminder;
use App\Models\ApkReminder;
use App\Rdw\RdwClient;
use App\Rdw\RdwUnavailableException;
use App\Site\PlateLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ApkReminderController extends Controller
{
    public function show()
    {
        return view('pages.apk-reminder');
    }

    /** Save the request and send the confirmation mail. The answer is the same whether or not the address is already known. */
    public function store(Request $request, RdwClient $rdw)
    {
        $key = 'apk-reminder:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->except('kenteken'))->withErrors(['email' => 'Je probeert het te vaak. Wacht een minuut en probeer het opnieuw.']);
        }
        RateLimiter::hit($key, 60);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'kenteken' => ['required', 'string', 'max:12'],
            'akkoord' => ['accepted'],
        ], [
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Dit is geen geldig e-mailadres.',
            'kenteken.required' => 'Vul je kenteken in.',
            'akkoord.accepted' => 'Vink aan dat je akkoord gaat met de herinneringsmail.',
        ]);

        $plate = PlateLookup::normalize($data['kenteken']);
        if (! preg_match('/^[A-Z0-9]{6}$/', $plate)) {
            return back()->withInput($request->except('kenteken'))->withErrors(['kenteken' => 'Dit is geen geldig kenteken. Een kenteken heeft 6 tekens, bijvoorbeeld AB-123-C.']);
        }

        try {
            $vehicle = $rdw->findVehicle($plate);
        } catch (RdwUnavailableException) {
            return back()->withInput($request->except('kenteken'))->withErrors(['kenteken' => 'De RDW is nu niet bereikbaar. Probeer het straks nog eens.']);
        }

        if ($vehicle === null) {
            return back()->withInput($request->except('kenteken'))->withErrors(['kenteken' => 'We vonden geen auto met dit kenteken.']);
        }

        $email = Str::lower(trim($data['email']));
        $reminder = ApkReminder::firstOrNew(['email' => $email, 'license_plate' => $plate]);

        // Nobody may fill someone else's inbox: a few mails per address per hour. The answer stays the same either way.
        // The key holds a hash, not the address itself.
        $mailKey = 'apk-mail:'.hash_hmac('sha256', $email, (string) config('app.key'));
        $mayMail = ! RateLimiter::tooManyAttempts($mailKey, (int) config('site.limits.apk_mail_per_hour'));

        if (! $reminder->isConfirmed() && $mayMail) {
            RateLimiter::hit($mailKey, 3600);

            $reminder->fill([
                'apk_expires_at' => self::expiry($vehicle),
                'token' => $reminder->token ?: Str::random(48),
            ])->save();

            Mail::to($email)->send(new ConfirmApkReminder($reminder));
        }

        return view('pages.apk-message', [
            'title' => 'Check je mail',
            'text' => 'We hebben een e-mail gestuurd om je aanvraag te bevestigen. Pas na jouw klik op de link in die mail sturen we een herinnering. Zie je niets? Kijk dan ook in je spam.',
        ]);
    }

    /** The link in the mail: only shows a button. Nothing changes until the visitor presses it. */
    public function confirm(Request $request, string $token)
    {
        if (! ApkReminder::where('token', $token)->exists()) {
            return $this->invalidLink();
        }

        return view('pages.apk-action', [
            'title' => 'Bevestig je herinnering',
            'text' => 'Klik op de knop om je APK-herinnering aan te zetten.',
            'button' => 'Bevestig mijn herinnering',
            'action' => URL::temporarySignedRoute('apk.confirm.store', now()->addDays(7), ['token' => $token]),
        ]);
    }

    public function confirmStore(string $token)
    {
        $reminder = ApkReminder::where('token', $token)->first();

        if ($reminder === null) {
            return $this->invalidLink();
        }

        $reminder->confirmed_at ??= now();
        $reminder->save();

        return view('pages.apk-message', [
            'title' => 'Je herinnering staat aan',
            'text' => 'Elk jaar sturen we je een e-mail ongeveer 30 dagen voordat de APK van '.$reminder->license_plate.' verloopt'
                .($reminder->apk_expires_at ? ' (nu: '.$reminder->apk_expires_at->translatedFormat('j F Y').')' : '').'. Onderaan elke mail staat een link om je uit te schrijven.',
        ]);
    }

    /** The link in the mail: only shows a button, like the confirmation link. */
    public function unsubscribe(string $token)
    {
        return view('pages.apk-action', [
            'title' => 'Uitschrijven',
            'text' => 'Wil je geen APK-herinneringen meer? Dan verwijderen we je e-mailadres en kenteken.',
            'button' => 'Ja, schrijf me uit',
            'action' => URL::signedRoute('apk.unsubscribe.store', ['token' => $token]),
        ]);
    }

    /** Remove the reminder, and with it the e-mail address and the license plate. */
    public function unsubscribeStore(string $token)
    {
        ApkReminder::where('token', $token)->delete();

        return view('pages.apk-message', ['title' => 'Je bent uitgeschreven', 'text' => 'We hebben je e-mailadres en kenteken verwijderd. Je krijgt geen herinneringen meer.']);
    }

    private function invalidLink()
    {
        return view('pages.apk-message', ['title' => 'Link niet meer geldig', 'text' => 'Deze link is niet meer geldig. Vraag de herinnering gerust opnieuw aan.']);
    }

    /** The APK expiry date from an RDW record, or null. */
    public static function expiry(array $vehicle): ?Carbon
    {
        $date = $vehicle['vervaldatum_apk_dt'] ?? null;

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }
}

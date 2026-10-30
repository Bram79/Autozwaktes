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
            return back()->withInput()->withErrors(['email' => 'Je probeert het te vaak. Wacht een minuut en probeer het opnieuw.']);
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
            return back()->withInput()->withErrors(['kenteken' => 'Dit is geen geldig kenteken. Een kenteken heeft 6 tekens, bijvoorbeeld AB-123-C.']);
        }

        try {
            $vehicle = $rdw->findVehicle($plate);
        } catch (RdwUnavailableException) {
            return back()->withInput()->withErrors(['kenteken' => 'De RDW is nu niet bereikbaar. Probeer het straks nog eens.']);
        }

        if ($vehicle === null) {
            return back()->withInput()->withErrors(['kenteken' => 'We vonden geen auto met dit kenteken.']);
        }

        $email = Str::lower(trim($data['email']));
        $reminder = ApkReminder::firstOrNew(['email' => $email, 'license_plate' => $plate]);

        if (! $reminder->isConfirmed()) {
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

    public function confirm(string $token)
    {
        $reminder = ApkReminder::where('token', $token)->first();

        if ($reminder === null) {
            return view('pages.apk-message', ['title' => 'Link niet meer geldig', 'text' => 'Deze link is niet meer geldig. Vraag de herinnering gerust opnieuw aan.']);
        }

        $reminder->confirmed_at ??= now();
        $reminder->save();

        return view('pages.apk-message', [
            'title' => 'Je herinnering staat aan',
            'text' => 'We sturen je een e-mail ongeveer 30 dagen voordat de APK van '.$reminder->license_plate.' verloopt'
                .($reminder->apk_expires_at ? ' (nu: '.$reminder->apk_expires_at->translatedFormat('j F Y').')' : '').'. Onderaan elke mail staat een link om je uit te schrijven.',
        ]);
    }

    /** Remove the reminder, and with it the e-mail address and the license plate. */
    public function unsubscribe(string $token)
    {
        ApkReminder::where('token', $token)->delete();

        return view('pages.apk-message', ['title' => 'Je bent uitgeschreven', 'text' => 'We hebben je e-mailadres en kenteken verwijderd. Je krijgt geen herinneringen meer.']);
    }

    /** The APK expiry date from an RDW record, or null. */
    public static function expiry(array $vehicle): ?Carbon
    {
        $date = $vehicle['vervaldatum_apk_dt'] ?? null;

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }
}

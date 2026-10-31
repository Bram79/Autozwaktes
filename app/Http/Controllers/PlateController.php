<?php

namespace App\Http\Controllers;

use App\Site\PlateLookup;
use App\Site\PlateVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;

class PlateController extends Controller
{
    private const MESSAGES = [
        'invalid' => 'Dit is geen geldig kenteken. Een kenteken heeft 6 tekens, bijvoorbeeld AB-123-C.',
        'not_found' => 'We vonden geen auto met dit kenteken.',
        'not_supported' => 'We tonen alleen cijfers voor personenauto\'s. Dit kenteken hoort bij een ander voertuig.',
        'unavailable' => 'De RDW is nu niet bereikbaar. Probeer het straks nog eens.',
        'unknown_model' => 'We hebben deze auto gevonden, maar we hebben (nog) geen cijfers voor dit model.',
    ];

    /** A plate goes in with POST so that it does not end up in the URL or in server logs. */
    public function lookup(Request $request, PlateLookup $lookup)
    {
        $key = 'plate:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return redirect()->route('home')->with('plate_error', 'Je zoekt te snel. Probeer het over een minuut opnieuw.');
        }
        RateLimiter::hit($key, 60);

        $result = $lookup->find((string) $request->input('kenteken'));

        // "no_stats": no numbers for that year, so the visitor lands on the model page instead
        if (in_array($result['status'], ['found', 'no_stats'], true)) {
            PlateVisit::mark($result['model_id'] ?? null, $result['year'] ?? null, $result['price'] ?? null);

            return redirect($result['url']);
        }

        $redirect = redirect()->route('home')->with('plate_error', self::MESSAGES[$result['status']]);

        // for a car we have no numbers for, the home page still offers the vehicle report (make, model and year only)
        if ($result['status'] === 'unknown_model' && $result['brand'] !== '' && $result['model'] !== '') {
            $redirect->with('plate_unknown_car', Arr::only($result, ['brand', 'model', 'year', 'price']));
        }

        return $redirect;
    }
}

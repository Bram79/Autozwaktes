<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Site\RankingService;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankings) {}

    /** /ranglijsten: all the lists. */
    public function index()
    {
        return view('pages.rankings', [
            'brands' => Brand::orderBy('name')->get(),
            'fuels' => $this->rankings->fuels(),
            'years' => $this->rankings->years(),
        ]);
    }

    /** /ranglijst/alles, /ranglijst/merk/audi, /ranglijst/brandstof/diesel, /ranglijst/bouwjaar/2015 */
    public function show(string $scope, ?string $value = null)
    {
        $brand = $fuel = $year = null;

        match ($scope) {
            'alles' => abort_unless($value === null, 404),
            'merk' => $brand = Brand::where('slug', $value)->firstOrFail(),
            'brandstof' => $fuel = $this->rankings->fuels()[$value] ?? abort(404),
            'bouwjaar' => $year = in_array((int) $value, $this->rankings->years(), true) ? (int) $value : abort(404),
            default => abort(404),
        };

        $rows = $this->rankings->scores($brand?->id, $fuel, $year);
        $label = match (true) {
            $brand !== null => $brand->name,
            $fuel !== null => mb_strtolower($fuel),
            $year !== null => "uit {$year}",
            default => '',
        };

        return view('pages.ranking', [
            'rows' => $rows,
            'best' => array_slice($rows, 0, 20),
            'worst' => array_reverse(array_slice($rows, -10)),
            'scope' => $scope,
            'brand' => $brand,
            'fuel' => $fuel,
            'year' => $year,
            'label' => $label,
            'brands' => Brand::orderBy('name')->get(),
            'fuels' => $this->rankings->fuels(),
            'years' => $this->rankings->years(),
            'url' => url()->current(),
        ]);
    }
}

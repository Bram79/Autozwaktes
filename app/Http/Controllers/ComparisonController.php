<?php

namespace App\Http\Controllers;

use App\Site\ComparisonService;

class ComparisonController extends Controller
{
    public function __construct(private ComparisonService $comparisons) {}

    public function show(string $pair)
    {
        $models = $this->comparisons->find($pair);
        abort_if($models === null, 404);

        [$a, $b] = $models;
        $result = $this->comparisons->compare($a, $b);
        abort_if($result === null, 404);

        return view('pages.comparison', ['a' => $a, 'b' => $b, 'result' => $result, 'url' => route('comparison', $pair)]);
    }
}

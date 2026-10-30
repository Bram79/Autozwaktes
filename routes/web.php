<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PlateController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [PageController::class, 'sitemap']);
Route::get('/robots.txt', [PageController::class, 'robots']);

// A brand slug can't be one of these (they belong to the framework or the build).
$reserved = 'admin|filament|livewire(-[a-z0-9]+)?|up|storage|build|sitemap\.xml|robots\.txt|favicon\.ico|kenteken|privacy';

Route::get('/', [PageController::class, 'home'])->name('home');
Route::post('/kenteken', [PlateController::class, 'lookup'])->name('plate');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

Route::get('/{brand}', [PageController::class, 'brand'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('brand');
Route::get('/{brand}/{model}', [PageController::class, 'model'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('model');
Route::get('/{brand}/{model}/{generation}', [PageController::class, 'generation'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('generation');
Route::get('/{brand}/{model}/{generation}/{year}', [PageController::class, 'year'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->where('year', '[0-9]{4}')->name('year');

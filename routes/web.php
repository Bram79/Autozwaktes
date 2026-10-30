<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ApkReminderController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlateController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [PageController::class, 'sitemap']);
Route::get('/robots.txt', [PageController::class, 'robots']);

// A brand slug can't be one of these (they belong to the framework or the build).
$reserved = 'admin|filament|livewire(-[a-z0-9]+)?|up|storage|build|sitemap\.xml|robots\.txt|favicon\.ico|kenteken|privacy|go|apk-herinnering|ranglijsten|ranglijst|vergelijk';

Route::get('/', [PageController::class, 'home'])->name('home');
Route::post('/kenteken', [PlateController::class, 'lookup'])->name('plate');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/apk-herinnering', [ApkReminderController::class, 'show'])->name('apk');
Route::post('/apk-herinnering', [ApkReminderController::class, 'store'])->name('apk.store');
Route::get('/apk-herinnering/bevestig/{token}', [ApkReminderController::class, 'confirm'])->middleware('signed')->name('apk.confirm');
Route::get('/apk-herinnering/uitschrijven/{token}', [ApkReminderController::class, 'unsubscribe'])->middleware('signed')->name('apk.unsubscribe');
Route::get('/ranglijsten', [RankingController::class, 'index'])->name('rankings');
Route::get('/ranglijst/{scope}/{value?}', [RankingController::class, 'show'])->where('scope', 'alles|merk|brandstof|bouwjaar')->name('ranking');
Route::get('/vergelijk/{pair}', [ComparisonController::class, 'show'])->where('pair', '[a-z0-9-]+-vs-[a-z0-9-]+')->name('comparison');
Route::get('/go/{partner}', [AffiliateController::class, 'go'])->where('partner', '[a-z_]+')->name('go');

Route::get('/{brand}', [PageController::class, 'brand'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('brand');
Route::get('/{brand}/{model}', [PageController::class, 'model'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('model');
Route::get('/{brand}/{model}/{generation}', [PageController::class, 'generation'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->name('generation');
Route::get('/{brand}/{model}/{generation}/{year}', [PageController::class, 'year'])
    ->where('brand', "^(?!({$reserved})$)[a-z0-9-]+$")->where('year', '[0-9]{4}')->name('year');

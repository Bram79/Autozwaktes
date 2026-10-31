<?php

namespace App\Filament\Widgets;

use App\Models\AffiliateClick;
use App\Models\CarModel;
use App\Models\Generation;
use App\Models\ImportRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** The numbers an admin wants to see first: what is online and what still needs work. */
class SiteStatus extends StatsOverviewWidget
{
    protected ?string $heading = 'Hoe staat de site ervoor?';

    protected function getStats(): array
    {
        $total = CarModel::count();
        $online = CarModel::withStats()->count();

        $withoutAdvice = Generation::where(fn ($q) => $q->whereNull('buying_advice')->orWhere('buying_advice', ''))->count();

        $lastImport = ImportRun::where('status', 'finished')->latest('finished_at')->first();

        // a failed attempt that is newer than the last good import: the numbers on the site are older than they should be
        $failed = ImportRun::where('status', 'failed')->latest('id')->first();
        if ($failed && $lastImport && $lastImport->id > $failed->id) {
            $failed = null;
        }

        $clicks = AffiliateClick::where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Modellen online', $online)
                ->description($total - $online > 0 ? ($total - $online).' van de '.$total.' hebben nog geen cijfers' : 'Alle modellen hebben cijfers')
                ->descriptionIcon('heroicon-m-truck')
                ->color($total - $online > 0 ? 'warning' : 'success'),
            Stat::make('Generaties zonder koopadvies', $withoutAdvice)
                ->description($withoutAdvice > 0 ? 'Hier mist nog jouw tekst' : 'Alles is ingevuld')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($withoutAdvice > 0 ? 'warning' : 'success'),
            Stat::make('Affiliate-klikken (30 dagen)', $clicks)
                ->description('Zie Inkomsten › Klikken')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays'),
            $failed
                ? Stat::make('Laatste poging mislukt', Carbon::parse($failed->finished_at)->diffForHumans())
                    ->description(Str::limit((string) $failed->error, 80))
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger')
                : Stat::make('Laatste import', $lastImport?->finished_at ? Carbon::parse($lastImport->finished_at)->diffForHumans() : 'nog niet')
                    ->description('Elke maandag om 03:00 haalt de site nieuwe RDW-data op')
                    ->descriptionIcon('heroicon-m-arrow-path'),
        ];
    }
}

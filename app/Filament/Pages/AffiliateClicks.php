<?php

namespace App\Filament\Pages;

use App\Models\AffiliateClick;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Which affiliate links get clicked: per partner, per page and per spot on the page. */
class AffiliateClicks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|UnitEnum|null $navigationGroup = 'Inkomsten';

    protected static ?string $navigationLabel = 'Klikken';

    protected static ?string $title = 'Klikken op affiliate-links';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.affiliate-clicks';

    /** Number of days to look back. */
    public int $days = 30;

    public function getViewData(): array
    {
        $days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
        $since = now()->subDays($days);

        $count = fn (string $column, int $limit = 15) => AffiliateClick::where('created_at', '>=', $since)
            ->select($column, DB::raw('count(*) as clicks'))->groupBy($column)->orderByDesc('clicks')->limit($limit)->get();

        return [
            'total' => AffiliateClick::where('created_at', '>=', $since)->count(),
            'partners' => $count('partner'),
            'pages' => $count('page'),
            'positions' => $count('position'),
            'labels' => collect(config('site.affiliates'))->map(fn ($a) => $a['title'])->all(),
        ];
    }
}

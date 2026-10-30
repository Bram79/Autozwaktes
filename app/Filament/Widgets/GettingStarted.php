<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** A short explanation at the top of the dashboard, for someone who has not used the admin before. */
class GettingStarted extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.getting-started';
}

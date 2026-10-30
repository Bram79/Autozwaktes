<?php

namespace App\Filament\Resources\ModelAliases\Pages;

use App\Filament\Resources\ModelAliases\ModelAliasResource;
use App\Jobs\CalculateStatisticsJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageModelAliases extends ManageRecords
{
    protected static string $resource = ModelAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Statistieken herberekenen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Nodig nadat je patronen hebt gewijzigd: voertuigen worden dan opnieuw aan modellen gekoppeld. Dit draait op de achtergrond; er moet een queue worker draaien.')
                ->action(function () {
                    CalculateStatisticsJob::dispatch();
                    Notification::make()->title('Herberekening staat in de wachtrij')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}

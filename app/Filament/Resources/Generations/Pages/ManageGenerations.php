<?php

namespace App\Filament\Resources\Generations\Pages;

use App\Filament\Resources\Generations\GenerationResource;
use App\Jobs\CalculateStatisticsJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageGenerations extends ManageRecords
{
    protected static string $resource = GenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Statistieken herberekenen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Nodig nadat je de bouwjaren van een generatie hebt gewijzigd. Dit draait op de achtergrond; er moet een queue worker draaien.')
                ->action(function () {
                    CalculateStatisticsJob::dispatch();
                    Notification::make()->title('Herberekening staat in de wachtrij')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}

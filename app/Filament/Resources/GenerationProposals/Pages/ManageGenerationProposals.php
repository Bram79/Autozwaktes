<?php

namespace App\Filament\Resources\GenerationProposals\Pages;

use App\Filament\Resources\GenerationProposals\GenerationProposalResource;
use App\Models\CarModel;
use App\Models\GenerationProposal;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageGenerationProposals extends ManageRecords
{
    protected static string $resource = GenerationProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveHigh')
                ->label('Zekere voorstellen goedkeuren')
                ->icon('heroicon-o-check-badge')
                ->modalDescription('Keurt alle openstaande voorstellen goed met minstens deze zekerheid. Er verandert nog niets op de site tot je ze toepast.')
                ->schema([
                    TextInput::make('minimum')->label('Minimale zekerheid (%)')->numeric()->minValue(0)->maxValue(100)->default(70)->required(),
                ])
                ->action(function (array $data) {
                    $count = GenerationProposal::where('status', GenerationProposal::PENDING)->where('confidence', '>=', (int) $data['minimum'])
                        ->update(['status' => GenerationProposal::APPROVED]);

                    Notification::make()->title("{$count} voorstellen goedgekeurd")->success()->send();
                }),

            Action::make('apply')
                ->label('Goedgekeurde voorstellen toepassen')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Zet voor elk model zonder openstaande voorstellen de goedgekeurde generaties op de site. De oude generaties van dat model worden vervangen. Draai daarna `php artisan rdw:calculate` om de cijfers opnieuw te berekenen.')
                ->action(function () {
                    $done = 0;
                    $skipped = [];

                    $modelIds = GenerationProposal::where('status', GenerationProposal::APPROVED)->distinct()->pluck('car_model_id');
                    foreach (CarModel::with('brand')->whereIn('id', $modelIds)->get() as $model) {
                        $reason = GenerationProposal::applyFor($model);
                        $reason ? $skipped[] = "{$model->brand->name} {$model->name}: {$reason}" : $done++;
                    }

                    Notification::make()
                        ->title("{$done} modellen bijgewerkt")
                        ->body($skipped === [] ? 'Draai nu `php artisan rdw:calculate`.' : 'Overgeslagen: '.implode(' | ', $skipped))
                        ->color($skipped === [] ? 'success' : 'warning')
                        ->persistent()
                        ->send();
                }),
        ];
    }
}

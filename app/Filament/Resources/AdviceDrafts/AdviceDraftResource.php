<?php

namespace App\Filament\Resources\AdviceDrafts;

use App\Filament\Resources\AdviceDrafts\Pages\ManageAdviceDrafts;
use App\Models\AdviceDraft;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AdviceDraftResource extends Resource
{
    protected static ?string $model = AdviceDraft::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Inhoud';

    protected static ?string $navigationLabel = 'Koopadvies-concepten';

    protected static ?string $modelLabel = 'concept';

    protected static ?string $pluralModelLabel = 'koopadvies-concepten';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $open = AdviceDraft::where('status', AdviceDraft::CONCEPT)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')->label('Tekst')->rows(12)->required()->columnSpanFull()
                ->helperText('Pas de tekst aan zoals jij wilt. Bij "Goedkeuren" wordt dit het koopadvies van de generatie.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Claude schrijft deze concepten alleen uit onze eigen cijfers. Lees ze goed, pas aan en keur goed: pas dan komen ze op de site. Een concept dat getallen bevat die niet in de cijfers staan, is automatisch afgewezen.')
            ->emptyStateHeading('Geen concepten')
            ->emptyStateDescription('Zet AI_ADVICE_ENABLED=true en ANTHROPIC_API_KEY in .env en draai `php artisan advice:draft`.')
            ->columns([
                TextColumn::make('generation.carModel.brand.name')->label('Merk'),
                TextColumn::make('generation.carModel.name')->label('Model'),
                TextColumn::make('generation.code')->label('Generatie'),
                TextColumn::make('body')->label('Tekst')->limit(120)->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => ['concept' => 'Concept', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen'][$state] ?? $state)
                    ->color(fn ($state) => ['concept' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('note')->label('Opmerking')->wrap(),
                TextColumn::make('created_at')->label('Gemaakt')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')
                    ->options(['concept' => 'Concept', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen'])
                    ->default('concept'),
            ])
            ->recordActions([
                Action::make('approve')->label('Goedkeuren')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (AdviceDraft $record) => $record->status !== AdviceDraft::APPROVED)
                    ->requiresConfirmation()
                    ->modalDescription(fn (AdviceDraft $record) => filled($record->generation->buying_advice)
                        ? 'Let op: deze generatie heeft al een koopadvies. Dat wordt vervangen door deze tekst.'
                        : 'De tekst komt als koopadvies op de site.')
                    ->action(fn (AdviceDraft $record) => $record->approve()),
                Action::make('reject')->label('Afwijzen')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (AdviceDraft $record) => $record->status === AdviceDraft::CONCEPT)
                    ->action(fn (AdviceDraft $record) => $record->update(['status' => AdviceDraft::REJECTED])),
                EditAction::make()->label('Aanpassen'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAdviceDrafts::route('/')];
    }
}

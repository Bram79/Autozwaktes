<?php

namespace App\Filament\Resources\GenerationProposals;

use App\Filament\Resources\GenerationProposals\Pages\ManageGenerationProposals;
use App\Models\GenerationProposal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class GenerationProposalResource extends Resource
{
    protected static ?string $model = GenerationProposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Inhoud';

    protected static ?string $navigationLabel = 'Generatievoorstellen';

    protected static ?string $modelLabel = 'generatievoorstel';

    protected static ?string $pluralModelLabel = 'generatievoorstellen';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $open = GenerationProposal::where('status', GenerationProposal::PENDING)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Naam (bijv. Mk5 of B8)')->required()->maxLength(50),
            TextInput::make('year_from')->label('Bouwjaar vanaf')->required()->numeric()->minValue(1950)->maxValue(2100),
            TextInput::make('year_to')->label('Bouwjaar tot en met (leeg = nog in productie)')->numeric()->minValue(1950)->maxValue(2100)->gte('year_from'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('De site stelt generaties voor op basis van RDW-typecodes en Wikidata. Keur ze goed of pas ze aan, en klik bovenaan op "Goedgekeurde voorstellen toepassen". Hoe hoger de zekerheid, hoe meer de twee bronnen het eens zijn.')
            ->emptyStateHeading('Geen voorstellen')
            ->emptyStateDescription('Draai `php artisan rdw:types` en daarna `php artisan generations:propose`.')
            ->groups([Group::make('carModel.name')->label('Model')->collapsible()])
            ->defaultGroup('carModel.name')
            ->columns([
                TextColumn::make('carModel.brand.name')->label('Merk'),
                TextColumn::make('code')->label('Generatie')->weight('bold'),
                TextColumn::make('year_from')->label('Van'),
                TextColumn::make('year_to')->label('Tot')->placeholder('heden'),
                TextColumn::make('confidence')->label('Zekerheid')->suffix('%')->badge()->sortable()
                    ->color(fn (int $state) => $state >= 80 ? 'success' : ($state >= 55 ? 'warning' : 'danger')),
                TextColumn::make('source')->label('Bron')
                    ->formatStateUsing(fn ($state) => ['rdw+wikidata' => 'RDW + Wikidata', 'rdw' => 'Alleen RDW', 'wikidata' => 'Alleen Wikidata'][$state] ?? $state),
                TextColumn::make('vehicles')->label("Auto's")->numeric(thousandsSeparator: '.', decimalSeparator: ',')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => ['pending' => 'Open', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen'][$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'gray', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('notes')->label('Toelichting')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('year_from')
            ->filters([
                SelectFilter::make('status')->label('Status')
                    ->options(['pending' => 'Open', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen'])
                    ->default('pending'),
                SelectFilter::make('car_model_id')->label('Model')->relationship('carModel', 'name')->searchable(),
            ])
            ->recordActions([
                Action::make('approve')->label('Goedkeuren')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (GenerationProposal $record) => $record->status !== GenerationProposal::APPROVED)
                    ->action(fn (GenerationProposal $record) => $record->update(['status' => GenerationProposal::APPROVED])),
                Action::make('reject')->label('Afwijzen')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (GenerationProposal $record) => $record->status !== GenerationProposal::REJECTED)
                    ->action(fn (GenerationProposal $record) => $record->update(['status' => GenerationProposal::REJECTED])),
                // after a change by hand the proposal is a decision of a person, so it counts as approved
                EditAction::make()->label('Aanpassen')->mutateDataUsing(fn (array $data) => $data + ['status' => GenerationProposal::APPROVED, 'confidence' => 100]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')->label('Geselecteerde goedkeuren')->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->update(['status' => GenerationProposal::APPROVED]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGenerationProposals::route('/')];
    }
}

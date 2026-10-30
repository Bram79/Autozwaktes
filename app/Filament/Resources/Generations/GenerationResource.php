<?php

namespace App\Filament\Resources\Generations;

use App\Filament\Resources\Generations\Pages\ManageGenerations;
use App\Models\Generation;
use BackedEnum;
use UnitEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GenerationResource extends Resource
{
    protected static ?string $model = Generation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Inhoud';

    protected static ?string $navigationLabel = 'Generaties en koopadvies';

    protected static ?string $modelLabel = 'generatie';

    protected static ?string $pluralModelLabel = 'generaties';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('car_model_id')
                ->label('Model')
                ->relationship('carModel', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->brand->name} {$record->name}")
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('code')
                ->label('Code (bijv. B8)')
                ->required()
                ->maxLength(50)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('slug')
                ->label('Slug (in de URL)')
                ->required()
                ->alphaDash()
                ->maxLength(50),
            TextInput::make('year_from')
                ->label('Bouwjaar vanaf')
                ->required()
                ->numeric()
                ->minValue(1950)
                ->maxValue(2100),
            TextInput::make('year_to')
                ->label('Bouwjaar tot en met (leeg = nog in productie)')
                ->numeric()
                ->minValue(1950)
                ->maxValue(2100)
                ->gte('year_from'),
            Textarea::make('buying_advice')
                ->label('Koopadvies')
                ->helperText('Wordt getoond op de generatiepagina. Een lege regel begint een nieuwe alinea.')
                ->rows(10)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carModel.brand.name')->label('Merk')->sortable(),
                TextColumn::make('carModel.name')->label('Model')->sortable()->searchable(),
                TextColumn::make('code')->label('Generatie')->searchable(),
                TextColumn::make('year_from')->label('Van')->sortable(),
                TextColumn::make('year_to')->label('Tot')->placeholder('heden')->sortable(),
                TextColumn::make('buying_advice')->label('Koopadvies')
                    ->formatStateUsing(fn ($state) => filled($state) ? 'Ingevuld' : 'Leeg')
                    ->badge()
                    ->color(fn ($state) => $state === 'Ingevuld' ? 'success' : 'gray'),
            ])
            ->description('Hier staan de generaties van elk model. Vul bij elke generatie je koopadvies in: dat verschijnt op de site.')
            ->emptyStateHeading('Nog geen generaties')
            ->defaultSort('car_model_id')
            ->filters([
                SelectFilter::make('car_model_id')->label('Model')->relationship('carModel', 'name')->searchable(),
                TernaryFilter::make('buying_advice')->label('Koopadvies')
                    ->placeholder('Alle')->trueLabel('Ingevuld')->falseLabel('Nog leeg')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('buying_advice')->where('buying_advice', '!=', ''),
                        false: fn ($query) => $query->where(fn ($q) => $q->whereNull('buying_advice')->orWhere('buying_advice', '')),
                    ),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGenerations::route('/')];
    }
}

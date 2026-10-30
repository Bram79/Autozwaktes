<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Inhoud';

    protected static ?string $navigationLabel = 'Merken';

    protected static ?string $modelLabel = 'merk';

    protected static ?string $pluralModelLabel = 'merken';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('slug')->label('Slug (in de URL)')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('rdw_name')
                ->label('Naam in RDW-data')
                ->helperText('Hoofdletters, precies zoals in de RDW-data, bijv. VOLKSWAGEN of MERCEDES-BENZ. Alleen deze merken worden geïmporteerd.')
                ->required()
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => Str::upper(trim($state))),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('rdw_name')->label('RDW-naam'),
                TextColumn::make('car_models_count')->label('Modellen')->counts('carModels'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrands::route('/')];
    }
}

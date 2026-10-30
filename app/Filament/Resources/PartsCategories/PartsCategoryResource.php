<?php

namespace App\Filament\Resources\PartsCategories;

use App\Filament\Resources\PartsCategories\Pages\ManagePartsCategories;
use App\Models\PartsCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PartsCategoryResource extends Resource
{
    protected static ?string $model = PartsCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Inkomsten';

    protected static ?string $navigationLabel = 'Onderdelen per gebrek';

    protected static ?string $modelLabel = 'onderdelencategorie';

    protected static ?string $pluralModelLabel = 'onderdelencategorieën';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Categorie (bijv. Remmen)')->required()->maxLength(80),
            TextInput::make('keywords')->label('Trefwoorden')->required()->maxLength(300)
                ->helperText('Gescheiden door komma\'s. Staat een trefwoord in de naam van een gebrek, dan krijgt dat gebrek een onderdelenlink. Bijvoorbeeld: rem, remschijf, remblok.'),
            TextInput::make('url')->label('Affiliate-URL van de onderdelenwinkel')->required()->url()->maxLength(1000)->columnSpanFull()
                ->helperText('Je mag {merk}, {model} en {jaar} gebruiken.'),
            Toggle::make('enabled')->label('Actief')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Bij elk gebrek in de tabel verschijnt een link naar de juiste onderdelen, als een trefwoord past. De eerste categorie die past wint. Zo wijst "remleiding poreus" naar remonderdelen.')
            ->emptyStateHeading('Nog geen categorieën')
            ->columns([
                TextColumn::make('name')->label('Categorie')->searchable(),
                TextColumn::make('keywords')->label('Trefwoorden')->wrap(),
                TextColumn::make('url')->label('URL')->limit(40),
                IconColumn::make('enabled')->label('Actief')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartsCategories::route('/')];
    }
}

<?php

namespace App\Filament\Resources\ModelAliases;

use App\Filament\Resources\ModelAliases\Pages\ManageModelAliases;
use App\Models\ModelAlias;
use App\Rdw\ModelNormalizer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ModelAliasResource extends Resource
{
    protected static ?string $model = ModelAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Geavanceerd';

    protected static ?string $navigationLabel = 'Modelnamen koppelen';

    protected static ?string $modelLabel = 'modelnaam-patroon';

    protected static ?string $pluralModelLabel = 'modelnaam-patronen';

    protected static ?int $navigationSort = 4;

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
            TextInput::make('pattern')
                ->label('Patroon (regex)')
                ->helperText('Wordt vergeleken met de RDW-handelsbenaming in hoofdletters, zonder merknaam. Voorbeeld: ^A4( |$) past op "A4", "A4 AVANT" en "A4 74 KW", maar niet op "A40".')
                ->required()
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (@preg_match(ModelNormalizer::regex($value), '') === false) {
                        $fail('Dit is geen geldig regex-patroon.');
                    }
                }),
            TextInput::make('priority')
                ->label('Prioriteit')
                ->helperText('Laag nummer wordt eerst gecontroleerd. Gebruik dit als een specifiek patroon vóór een algemeen patroon moet gaan.')
                ->numeric()
                ->default(100)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carModel.brand.name')->label('Merk'),
                TextColumn::make('carModel.name')->label('Model')->searchable(),
                TextColumn::make('pattern')->label('Patroon')->fontFamily('mono'),
                TextColumn::make('priority')->label('Prioriteit')->sortable(),
            ])
            ->defaultSort('priority')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageModelAliases::route('/')];
    }
}

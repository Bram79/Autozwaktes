<?php

namespace App\Filament\Resources\CarModels;

use App\Filament\Resources\CarModels\Pages\ManageCarModels;
use App\Models\CarModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CarModelResource extends Resource
{
    protected static ?string $model = CarModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Inhoud';

    protected static ?string $navigationLabel = 'Modellen';

    protected static ?string $modelLabel = 'model';

    protected static ?string $pluralModelLabel = 'modellen';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('brand_id')->label('Merk')->relationship('brand', 'name')->required(),
            TextInput::make('name')
                ->label('Naam (bijv. A4)')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('slug')->label('Slug (in de URL)')->required()->alphaDash(),
            Repeater::make('aliases')
                ->label('Aliassen (hoe de RDW dit model noemt)')
                ->helperText('Een regex op de RDW-naam in hoofdletters, zonder merknaam. Bijvoorbeeld ^A4( |$) past op "A4" en "A4 AVANT".')
                ->relationship('aliases')
                ->schema([
                    TextInput::make('pattern')->label('Patroon')->required(),
                    TextInput::make('priority')->label('Prioriteit (laag eerst)')->numeric()->default(100)->required(),
                ])
                ->columns(2)
                ->addActionLabel('Alias toevoegen')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')->label('Merk')->sortable(),
                TextColumn::make('name')->label('Model')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('generations_count')->label('Generaties')->counts('generations'),
            ])
            ->description('Elk model hoort bij een merk. Bij een model beheer je ook de aliassen: hoe de RDW het model noemt.')
            ->defaultSort('brand_id')
            ->filters([SelectFilter::make('brand_id')->label('Merk')->relationship('brand', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCarModels::route('/')];
    }
}

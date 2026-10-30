<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliateSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static ?string $navigationLabel = 'Affiliate-instellingen';

    protected static ?string $title = 'Affiliate-instellingen';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.affiliate-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $data = [];
        foreach (array_keys(config('site.affiliates')) as $type) {
            $data[$type] = Setting::affiliate($type);
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (config('site.affiliates') as $type => $default) {
            $sections[] = Section::make($default['title'])
                ->description('Zonder URL wordt dit blok niet getoond. Je kunt {merk}, {model} en {jaar} gebruiken in de URL en de teksten.')
                ->schema([
                    Toggle::make("{$type}.enabled")->label('Blok tonen'),
                    TextInput::make("{$type}.url")->label('Affiliate-URL')->url()->maxLength(1000),
                    TextInput::make("{$type}.title")->label('Titel')->required()->maxLength(120),
                    Textarea::make("{$type}.text")->label('Tekst')->rows(2)->maxLength(300),
                    TextInput::make("{$type}.button")->label('Knoptekst')->required()->maxLength(50),
                ])
                ->columns(2)
                ->collapsible();
        }

        return $schema->components($sections)->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $type => $values) {
            Setting::put("affiliate.{$type}", $values);
        }

        Notification::make()->title('Opgeslagen')->success()->send();
    }
}

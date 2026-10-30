<?php

namespace App\Filament\Resources\PartsCategories\Pages;

use App\Filament\Resources\PartsCategories\PartsCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePartsCategories extends ManageRecords
{
    protected static string $resource = PartsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

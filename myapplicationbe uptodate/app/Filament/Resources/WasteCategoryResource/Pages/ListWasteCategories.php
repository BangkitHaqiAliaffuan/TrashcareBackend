<?php

namespace App\Filament\Resources\WasteCategoryResource\Pages;

use App\Filament\Resources\WasteCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWasteCategories extends ListRecords
{
    protected static string $resource = WasteCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}

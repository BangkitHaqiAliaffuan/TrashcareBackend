<?php

namespace App\Filament\Resources\WasteCategoryResource\Pages;

use App\Filament\Resources\WasteCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWasteCategory extends EditRecord
{
    protected static string $resource = WasteCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}

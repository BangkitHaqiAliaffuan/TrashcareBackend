<?php

namespace App\Filament\Resources\PickupRequestResource\Pages;

use App\Filament\Resources\PickupRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPickupRequest extends EditRecord
{
    protected static string $resource = PickupRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}

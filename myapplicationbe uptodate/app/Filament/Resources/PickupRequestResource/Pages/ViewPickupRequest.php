<?php

namespace App\Filament\Resources\PickupRequestResource\Pages;

use App\Filament\Resources\PickupRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPickupRequest extends ViewRecord
{
    protected static string $resource = PickupRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}

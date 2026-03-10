<?php

namespace App\Filament\Resources\MarketplaceListingResource\Pages;

use App\Filament\Resources\MarketplaceListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceListing extends ViewRecord
{
    protected static string $resource = MarketplaceListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}

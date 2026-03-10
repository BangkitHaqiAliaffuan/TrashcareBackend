<?php

namespace App\Filament\Resources\MarketplaceListingResource\Pages;

use App\Filament\Resources\MarketplaceListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceListings extends ListRecords
{
    protected static string $resource = MarketplaceListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}

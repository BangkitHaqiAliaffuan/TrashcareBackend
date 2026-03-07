<?php

namespace App\Filament\Resources\MarketplaceListingResource\Pages;

use App\Filament\Resources\MarketplaceListingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceListing extends EditRecord
{
    protected static string $resource = MarketplaceListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}

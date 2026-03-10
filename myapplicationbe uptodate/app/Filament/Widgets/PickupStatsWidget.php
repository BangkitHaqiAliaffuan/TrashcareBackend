<?php

namespace App\Filament\Widgets;

use App\Models\PickupRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PickupStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Pickup Terbaru — Perlu Ditangani';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PickupRequest::query()
                    ->with(['user', 'courier'])
                    ->whereIn('status', ['searching', 'pending'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengguna'),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->limit(40),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'searching',
                        'warning' => 'pending',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'searching' => 'Mencari Kurir',
                        'pending'   => 'Dikonfirmasi',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('pickup_date')
                    ->label('Jadwal')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('courier.name')
                    ->label('Kurir')
                    ->placeholder('Belum assigned'),
            ]);
    }
}

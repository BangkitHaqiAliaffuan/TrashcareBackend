<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = [
            [
                'name'          => 'Budi Santoso',
                'email'         => 'budi@trashcare.id',
                'phone'         => '081234567001',
                'password'      => Hash::make('courier123'),
                'vehicle_type'  => 'Motor',
                'vehicle_plate' => 'B 1234 ABC',
                'status'        => 'active',
                'is_available'  => true,
                'rating'        => 4.80,
            ],
            [
                'name'          => 'Siti Rahayu',
                'email'         => 'siti@trashcare.id',
                'phone'         => '081234567002',
                'password'      => Hash::make('courier123'),
                'vehicle_type'  => 'Motor',
                'vehicle_plate' => 'B 5678 DEF',
                'status'        => 'active',
                'is_available'  => true,
                'rating'        => 4.65,
            ],
            [
                'name'          => 'Ahmad Fauzi',
                'email'         => 'ahmad@trashcare.id',
                'phone'         => '081234567003',
                'password'      => Hash::make('courier123'),
                'vehicle_type'  => 'Mobil Pick-up',
                'vehicle_plate' => 'B 9012 GHI',
                'status'        => 'active',
                'is_available'  => true,
                'rating'        => 4.90,
            ],
            [
                'name'          => 'Dewi Kusuma',
                'email'         => 'dewi@trashcare.id',
                'phone'         => '081234567004',
                'password'      => Hash::make('courier123'),
                'vehicle_type'  => 'Motor',
                'vehicle_plate' => 'B 3456 JKL',
                'status'        => 'active',
                'is_available'  => true,
                'rating'        => 4.50,
            ],
            [
                'name'          => 'Rizky Pratama',
                'email'         => 'rizky@trashcare.id',
                'phone'         => '081234567005',
                'password'      => Hash::make('courier123'),
                'vehicle_type'  => 'Motor',
                'vehicle_plate' => 'B 7890 MNO',
                'status'        => 'active',
                'is_available'  => true,
                'rating'        => 4.75,
            ],
        ];

        foreach ($couriers as $data) {
            Courier::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\WasteCategory;
use Illuminate\Database\Seeder;

class WasteCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'type'          => 'organic',
                'label'         => 'Organic',
                'emoji'         => '🌿',
                'description'   => 'Sampah organik seperti sisa makanan, daun, dan bahan-bahan alami lainnya.',
                'points_per_kg' => 5,
                'is_active'     => true,
            ],
            [
                'type'          => 'plastic',
                'label'         => 'Plastic',
                'emoji'         => '♻️',
                'description'   => 'Sampah plastik seperti botol, kantong, dan kemasan plastik.',
                'points_per_kg' => 15,
                'is_active'     => true,
            ],
            [
                'type'          => 'electronic',
                'label'         => 'Electronic',
                'emoji'         => '💻',
                'description'   => 'Sampah elektronik seperti perangkat komputer, ponsel, dan baterai.',
                'points_per_kg' => 30,
                'is_active'     => true,
            ],
            [
                'type'          => 'glass',
                'label'         => 'Glass',
                'emoji'         => '🫙',
                'description'   => 'Sampah kaca seperti botol kaca, cermin, dan perabotan kaca.',
                'points_per_kg' => 10,
                'is_active'     => true,
            ],
        ];

        foreach ($categories as $category) {
            WasteCategory::updateOrCreate(
                ['type' => $category['type']],
                $category
            );
        }
    }
}

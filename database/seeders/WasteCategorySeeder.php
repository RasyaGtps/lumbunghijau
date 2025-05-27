<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WasteCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kardus', 'type' => 'inorganic', 'price_per_kg' => 1700],
            ['name' => 'Botol Campur', 'type' => 'inorganic', 'price_per_kg' => 1000],
            ['name' => 'Botol Warna', 'type' => 'inorganic', 'price_per_kg' => 1000],
            ['name' => 'Botol Bening', 'type' => 'inorganic', 'price_per_kg' => 2500],
            ['name' => 'Tutup Botol', 'type' => 'inorganic', 'price_per_kg' => 2000],
            ['name' => 'Kardus Kering', 'type' => 'inorganic', 'price_per_kg' => 1000],
            ['name' => 'Plastik Campur', 'type' => 'inorganic', 'price_per_kg' => 200],
            ['name' => 'Plastik Bening', 'type' => 'inorganic', 'price_per_kg' => 500],
            ['name' => 'Plastik Warna', 'type' => 'inorganic', 'price_per_kg' => 200],
            ['name' => 'Limbah Nabati', 'type' => 'organic', 'price_per_kg' => 500],
            ['name' => 'Limbah Hewani', 'type' => 'organic', 'price_per_kg' => 200],
        ];

        foreach ($categories as $category) {
            DB::table('waste_categories')->insert([
                'name' => $category['name'],
                'type' => $category['type'],
                'price_per_kg' => $category['price_per_kg'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
} 
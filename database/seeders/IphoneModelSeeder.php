<?php

namespace Database\Seeders;

use App\Models\IphoneModel;
use Illuminate\Database\Seeder;

class IphoneModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max',
            'iPhone 12', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
            'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max',
            'iPhone 14', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
            'iPhone 15', 'iPhone 15 Pro', 'iPhone 15 Pro Max',
        ];

        foreach ($models as $index => $name) {
            IphoneModel::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
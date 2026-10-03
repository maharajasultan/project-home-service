<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\IphoneModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private const INITIAL_STOCK = 20;

    /** Pengali harga per grade. */
    private const GRADE_MULTIPLIER = [
        'standar' => 1.0,
        'premium' => 1.4,
        'original' => 1.9,
    ];

    public function run(): void
    {
        $models = IphoneModel::active()->get();

        // Harga dasar iPhone 11 (grade Standar). Bisa diubah admin lewat panel (B9).
        $spareparts = [
            ['name' => 'Baterai', 'base_price' => 250000, 'description' => 'Penggantian baterai iPhone. Kesehatan baterai kembali optimal.'],
            ['name' => 'LCD', 'base_price' => 650000, 'description' => 'Penggantian layar LCD/OLED iPhone dengan touch responsif.'],
            ['name' => 'Backglass', 'base_price' => 400000, 'description' => 'Penggantian kaca belakang iPhone yang retak atau pecah.'],
            ['name' => 'Housing', 'base_price' => 800000, 'description' => 'Penggantian housing / bodi iPhone.'],
        ];

        foreach ($spareparts as $item) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($item['name'])],
                [
                    'name' => $item['name'],
                    'type' => ProductType::Sparepart,
                    'description' => $item['description'],
                    'images' => [
                        'https://picsum.photos/seed/'.Str::slug($item['name']).'-1/800/600',
                        'https://picsum.photos/seed/'.Str::slug($item['name']).'-2/800/600',
                    ],
                    'base_fee' => 0,
                    'is_active' => true,
                ]
            );

            foreach ($models as $index => $model) {
                foreach (self::GRADE_MULTIPLIER as $grade => $multiplier) {
                    $price = $item['base_price'] * (1 + $index * 0.06) * $multiplier;
                    $this->createVariant($product, [
                        'iphone_model_id' => $model->id,
                        'grade' => $grade,
                    ], (int) (round($price / 1000) * 1000));
                }
            }
        }

        // Service Matot: hanya biaya ongkir/pengecekan (base_fee). Estimasi perbaikan tidak dihitung di awal.
        $matot = Product::updateOrCreate(
            ['slug' => 'service-matot'],
            [
                'name' => 'Service Matot',
                'type' => ProductType::Matot,
                'description' => 'Teknisi datang ke rumah untuk mencari kerusakan. Biaya awal hanya ongkir dan pengecekan. Estimasi perbaikan ditentukan teknisi setelah pengecekan.',
                'images' => ['https://picsum.photos/seed/service-matot/800/600'],
                'base_fee' => 75000,
                'is_active' => true,
            ]
        );

        foreach ($models as $model) {
            $this->createVariant($matot, ['iphone_model_id' => $model->id], 0, 999);
        }

        // Unlock IMEI: pilihan durasi bulan.
        $unlock = Product::updateOrCreate(
            ['slug' => 'unlock-imei'],
            [
                'name' => 'Unlock IMEI',
                'type' => ProductType::UnlockImei,
                'description' => 'Layanan unlock IMEI iPhone agar dapat digunakan dengan semua operator.',
                'images' => ['https://picsum.photos/seed/unlock-imei/800/600'],
                'base_fee' => 0,
                'is_active' => true,
            ]
        );

        foreach ([1 => 350000, 3 => 900000, 6 => 1600000, 12 => 2800000] as $months => $price) {
            $this->createVariant($unlock, ['duration_months' => $months], $price, 999);
        }
    }

    private function createVariant(Product $product, array $attributes, int $price, int $stock = self::INITIAL_STOCK): void
    {
        $variant = ProductVariant::firstOrCreate(
            ['product_id' => $product->id] + $attributes,
            ['price' => $price, 'stock' => $stock, 'is_active' => true]
        );

        // Catat stok awal sebagai "barang masuk" (hanya untuk sparepart fisik) untuk laporan inventory.
        if ($variant->wasRecentlyCreated && $product->type === ProductType::Sparepart) {
            StockMovement::create([
                'product_variant_id' => $variant->id,
                'type' => 'in',
                'qty' => $stock,
                'note' => 'Stok awal',
            ]);
        }
    }
}
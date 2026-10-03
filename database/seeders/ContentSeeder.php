<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            ['title' => 'Home Service iPhone Bekasi', 'image' => 'https://picsum.photos/seed/reaple-banner-1/1200/500'],
            ['title' => 'Garansi Service 3 Bulan', 'image' => 'https://picsum.photos/seed/reaple-banner-2/1200/500'],
            ['title' => 'Sparepart Standar, Premium, Original', 'image' => 'https://picsum.photos/seed/reaple-banner-3/1200/500'],
        ];

        foreach ($banners as $index => $banner) {
            Banner::updateOrCreate(
                ['title' => $banner['title']],
                ['image' => $banner['image'], 'sort_order' => $index + 1, 'is_active' => true]
            );
        }

        CompanyProfile::firstOrCreate(
            ['name' => 'reaple.id'],
            [
                'address' => 'Jl. Contoh No. 1, Bekasi, Jawa Barat',
                'description' => 'reaple.id adalah layanan home service perbaikan iPhone. Teknisi kami datang langsung ke rumah Anda di area Bekasi.',
                'email' => 'halo@reaple.id',
                'instagram' => 'https://instagram.com/reaple.id',
                'facebook' => 'https://facebook.com/reaple.id',
                'tiktok' => 'https://tiktok.com/@reaple.id',
                'whatsapp' => 'https://wa.me/6281200000001',
            ]
        );
    }
}
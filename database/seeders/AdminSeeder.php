<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@reaple.id'],
            [
                'name' => 'Admin reaple.id',
                'phone' => '081200000001',
                'password' => 'Admin#12345',
                'role' => UserRole::Admin,
            ]
        );

        $technicians = [
            ['name' => 'Budi Santoso', 'email' => 'budi@reaple.id', 'phone' => '081200000011'],
            ['name' => 'Dimas Pratama', 'email' => 'dimas@reaple.id', 'phone' => '081200000012'],
            ['name' => 'Rizky Hidayat', 'email' => 'rizky@reaple.id', 'phone' => '081200000013'],
        ];

        foreach ($technicians as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data + ['password' => 'Teknisi#12345', 'role' => UserRole::Technician]
            );

            TechnicianProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['bio' => 'Teknisi iPhone berpengalaman reaple.id.', 'is_active' => true]
            );
        }

        User::updateOrCreate(
            ['email' => 'user@reaple.id'],
            [
                'name' => 'Pelanggan Uji',
                'phone' => '081200000099',
                'password' => 'User#12345',
                'role' => UserRole::User,
            ]
        );
    }
}
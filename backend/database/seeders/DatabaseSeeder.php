<?php

namespace Database\Seeders;

use App\Enums\SalonUserRole;
use App\Models\Admin;
use App\Models\Salon;
use App\Models\SalonUser;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->call(TemplateSeeder::class);

        // Demo salon yalnız geliştirme/test ortamında.
        if (! app()->isProduction()) {
            $this->seedDemoSalon();
        }
    }

    private function seedAdmin(): void
    {
        $password = env('SEED_ADMIN_PASSWORD');

        if (blank($password)) {
            if (app()->isProduction()) {
                throw new RuntimeException('Üretimde SEED_ADMIN_PASSWORD tanımlanmalı.');
            }

            $password = 'password';
        }

        Admin::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@zarf.test')],
            ['name' => 'Yönetici', 'password' => $password],
        );
    }

    private function seedDemoSalon(): void
    {
        $salon = Salon::firstOrCreate(
            ['name' => 'Demo Düğün Salonu'],
            [
                'phone' => '+903422220000',
                'email' => 'demo-salon@zarf.test',
                'address' => 'İncilipınar Mah. Demo Cad. No: 1',
                'city' => 'Gaziantep',
                'district' => 'Şehitkamil',
                'latitude' => 37.0662,
                'longitude' => 37.3833,
                'capacity' => 600,
                'is_active' => true,
            ],
        );

        SalonUser::firstOrCreate(
            ['email' => 'salon@zarf.test'],
            [
                'salon_id' => $salon->id,
                'name' => 'Demo Salon Sahibi',
                'phone' => '+905330000001',
                'password' => 'password',
                'role' => SalonUserRole::Owner,
            ],
        );
    }
}

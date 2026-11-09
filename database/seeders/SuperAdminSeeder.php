<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * php artisan db:seed --class=SuperAdminSeeder
 * Igual a "php artisan super-admin:create" (usa SUPER_ADMIN_* do .env).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('super-admin:create', [], $this->command?->getOutput());
    }
}

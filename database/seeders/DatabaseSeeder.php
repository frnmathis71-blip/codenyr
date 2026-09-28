<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No default account or password. Use php artisan codenyr:admin.
        // Demo content is explicitly opt-in: php artisan db:seed --class=DemoSeeder.
    }
}

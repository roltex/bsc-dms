<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionBootstrapSeeder extends Seeder
{
    /**
     * Create the initial production administrator from environment variables.
     * Run once on fresh Azure deploy: php artisan db:seed --class=ProductionBootstrapSeeder
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->info('Users already exist — skipping production bootstrap.');

            return;
        }

        $email = env('PRODUCTION_ADMIN_EMAIL');
        $password = env('PRODUCTION_ADMIN_PASSWORD');
        $name = env('PRODUCTION_ADMIN_NAME', 'Administrator');

        if (empty($email) || empty($password)) {
            $this->command?->warn('PRODUCTION_ADMIN_EMAIL and PRODUCTION_ADMIN_PASSWORD must be set.');

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Administrator,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Production admin created: {$email}");
    }
}

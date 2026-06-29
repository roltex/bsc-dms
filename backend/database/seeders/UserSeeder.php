<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            // Administrators
            ['name' => 'Giorgi Kapanadze', 'email' => 'admin@bsc.ge', 'role' => UserRole::Administrator],
            ['name' => 'Nino Javakhishvili', 'email' => 'nino.j@bsc.ge', 'role' => UserRole::Administrator],

            // Managers
            ['name' => 'Levan Mikhelidze', 'email' => 'manager@bsc.ge', 'role' => UserRole::Manager],
            ['name' => 'Tamar Gelashvili', 'email' => 'tamar.g@bsc.ge', 'role' => UserRole::Manager],
            ['name' => 'Davit Tsiklauri', 'email' => 'davit.t@bsc.ge', 'role' => UserRole::Manager],

            // Lawyers
            ['name' => 'Irakli Beridze', 'email' => 'lawyer@bsc.ge', 'role' => UserRole::Lawyer],
            ['name' => 'Maia Kvaratskhelia', 'email' => 'maia.k@bsc.ge', 'role' => UserRole::Lawyer],
            ['name' => 'Zurab Gogichaishvili', 'email' => 'zurab.g@bsc.ge', 'role' => UserRole::Lawyer],

            // Initiators
            ['name' => 'Nikoloz Dvalishvili', 'email' => 'initiator@bsc.ge', 'role' => UserRole::Initiator],
            ['name' => 'Ketevan Merabishvili', 'email' => 'ketevan.m@bsc.ge', 'role' => UserRole::Initiator],
            ['name' => 'Giga Chkheidze', 'email' => 'giga.ch@bsc.ge', 'role' => UserRole::Initiator],
            ['name' => 'Salome Tsereteli', 'email' => 'salome.t@bsc.ge', 'role' => UserRole::Initiator],
            ['name' => 'Tornike Lomidze', 'email' => 'tornike.l@bsc.ge', 'role' => UserRole::Initiator],
            ['name' => 'Ana Kipiani', 'email' => 'ana.k@bsc.ge', 'role' => UserRole::Initiator],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $password,
                    'role' => $userData['role'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}

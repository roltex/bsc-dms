<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::where('role', UserRole::Manager)->get();
        $lawyers = User::where('role', UserRole::Lawyer)->get();
        $initiators = User::where('role', UserRole::Initiator)->get();

        // Active substitution: manager on leave
        if ($managers->count() >= 2) {
            Substitution::firstOrCreate(
                [
                    'user_id' => $managers[0]->id,
                    'substitute_user_id' => $managers[1]->id,
                ],
                [
                    'from_date' => now()->subDays(2),
                    'to_date' => now()->addDays(12),
                ]
            );
        }

        // Active substitution: lawyer on business trip
        if ($lawyers->count() >= 2) {
            Substitution::firstOrCreate(
                [
                    'user_id' => $lawyers[0]->id,
                    'substitute_user_id' => $lawyers[1]->id,
                ],
                [
                    'from_date' => now(),
                    'to_date' => now()->addDays(5),
                ]
            );
        }

        // Past substitution (historical record)
        if ($initiators->count() >= 2) {
            Substitution::firstOrCreate(
                [
                    'user_id' => $initiators[0]->id,
                    'substitute_user_id' => $initiators[1]->id,
                ],
                [
                    'from_date' => now()->subMonths(2),
                    'to_date' => now()->subMonths(2)->addDays(14),
                ]
            );
        }

        // Future substitution (planned vacation)
        if ($managers->count() >= 2 && $lawyers->count() >= 1) {
            Substitution::firstOrCreate(
                [
                    'user_id' => $managers->last()->id,
                    'substitute_user_id' => $managers->first()->id,
                ],
                [
                    'from_date' => now()->addWeeks(2),
                    'to_date' => now()->addWeeks(4),
                ]
            );
        }
    }
}

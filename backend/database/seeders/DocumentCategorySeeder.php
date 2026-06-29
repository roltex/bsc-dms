<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $lawyers = User::where('role', UserRole::Lawyer)->pluck('id')->all();

        $categories = [
            ['name' => 'Supply Agreements', 'code' => 'supply'],
            ['name' => 'Service Contracts', 'code' => 'services'],
            ['name' => 'Marketing & Sponsorship', 'code' => 'marketing'],
            ['name' => 'Powers of Attorney', 'code' => 'powers_of_attorney'],
            ['name' => 'Labels & Packaging', 'code' => 'labels'],
            ['name' => 'Bank Guarantees', 'code' => 'bank_guarantees'],
            ['name' => 'Licenses & Permits', 'code' => 'licenses'],
            ['name' => 'Claims & Disputes', 'code' => 'claims'],
            ['name' => 'Lawsuits & Litigation', 'code' => 'lawsuits'],
            ['name' => 'Other', 'code' => 'other'],
        ];

        foreach ($categories as $i => $cat) {
            DocumentCategory::firstOrCreate(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'default_lawyer_id' => $lawyers[$i % count($lawyers)] ?? null,
                ]
            );
        }
    }
}

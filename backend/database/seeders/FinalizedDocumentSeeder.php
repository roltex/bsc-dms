<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\FinalizedDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinalizedDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $lawyers = User::where('role', UserRole::Lawyer)->pluck('id')->all();
        $admins = User::where('role', UserRole::Administrator)->pluck('id')->all();
        $uploaders = array_merge($lawyers, $admins);

        $documents = [
            // Licenses
            [
                'category' => 'licenses',
                'name' => 'Alcohol Production License No. KZ-ALC-2026-0412',
                'notes' => 'Issued by Committee for Regulation of Alcohol Market. Valid until 31 Dec 2028.',
                'created_at' => now()->subMonths(3),
            ],
            [
                'category' => 'licenses',
                'name' => 'Environmental Emissions Permit EP-2026-KAR-078',
                'notes' => 'Karaganda region. Annual renewal required.',
                'created_at' => now()->subMonths(1),
            ],
            [
                'category' => 'licenses',
                'name' => 'Fire Safety Certificate FSC-ALM-2026-155',
                'notes' => 'Almaty production facility. Inspection passed Feb 2026.',
                'created_at' => now()->subWeeks(6),
            ],

            // Court materials
            [
                'category' => 'court_materials',
                'name' => 'Court Decision — Case No. 7103-26-00-2К/42',
                'notes' => 'Favorable ruling in supplier dispute. Damages awarded: 4,200,000 KZT.',
                'created_at' => now()->subMonths(2),
            ],
            [
                'category' => 'court_materials',
                'name' => 'Arbitration Award — AIFC No. ARB-2025-089',
                'notes' => 'Settlement reached. No further action required.',
                'created_at' => now()->subMonths(4),
            ],

            // Corporate docs
            [
                'category' => 'corporate_docs',
                'name' => 'Updated Charter — Efes Kazakhstan LLP (2026 edition)',
                'notes' => 'Approved by shareholders meeting on 15 Jan 2026.',
                'created_at' => now()->subMonths(2),
            ],
            [
                'category' => 'corporate_docs',
                'name' => 'Board Resolution No. 2026-03 — Capital Increase',
                'notes' => 'Authorized capital increased to 500,000,000 KZT.',
                'created_at' => now()->subWeeks(3),
            ],
            [
                'category' => 'corporate_docs',
                'name' => 'Shareholders Agreement — Amendment No. 4',
                'notes' => 'Updated voting rights and dividend distribution policy.',
                'created_at' => now()->subMonths(1),
            ],

            // Government inspections
            [
                'category' => 'government_inspections',
                'name' => 'Tax Audit Report — FY2025',
                'notes' => 'No material findings. Minor discrepancies in VAT reporting resolved.',
                'created_at' => now()->subMonths(1),
            ],
            [
                'category' => 'government_inspections',
                'name' => 'Sanitary Inspection Certificate — Production Line B',
                'notes' => 'All parameters within acceptable range. Next inspection: Sep 2026.',
                'created_at' => now()->subWeeks(2),
            ],
            [
                'category' => 'government_inspections',
                'name' => 'Labor Inspection Protocol — Almaty Facility',
                'notes' => 'Compliance confirmed. Recommendation: update employee safety manual.',
                'created_at' => now()->subWeeks(4),
            ],

            // Other
            [
                'category' => 'other',
                'name' => 'Insurance Policy — Property & Liability (2026)',
                'notes' => 'Coverage: 2,000,000,000 KZT. Provider: Eurasia Insurance Company.',
                'created_at' => now()->subMonths(2),
            ],
            [
                'category' => 'other',
                'name' => 'Trademark Registration Certificate — "Efes Pilsener KZ"',
                'notes' => 'Registered in Class 32 (beer). Valid until 2031.',
                'created_at' => now()->subMonths(5),
            ],
        ];

        foreach ($documents as $doc) {
            FinalizedDocument::firstOrCreate(
                ['name' => $doc['name']],
                [
                    'user_id' => fake()->randomElement($uploaders),
                    'category' => $doc['category'],
                    'path' => 'finalized-documents/'.str($doc['name'])->slug()->limit(60).'.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => rand(100000, 5000000),
                    'notes' => $doc['notes'],
                    'created_at' => $doc['created_at'],
                    'updated_at' => $doc['created_at'],
                ]
            );
        }
    }
}

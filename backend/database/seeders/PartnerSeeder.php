<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\PartnerDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $lawyer = User::where('role', UserRole::Lawyer)->first();

        $partners = [
            [
                'name' => 'ТОО "АлтынДан Агро"',
                'bin_iin' => '120540003456',
                'bank_details' => 'АО "Kaspi Bank", БИК CASPKZKA, ИИК KZ76722S000003456789',
                'email' => 'info@altyndan.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'A', 'years_active' => 12],
            ],
            [
                'name' => 'АО "КазПивТрейд"',
                'bin_iin' => '081040012345',
                'bank_details' => 'АО "Halyk Bank", БИК HSBKKZKX, ИИК KZ349470398765432100',
                'email' => 'procurement@kazpivtrade.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'AA', 'years_active' => 18],
            ],
            [
                'name' => 'ИП Нурланов А.Б.',
                'bin_iin' => '850415300123',
                'bank_details' => 'АО "ForteBank", БИК IRTYKZKA, ИИК KZ12141G000001234567',
                'email' => 'nurlanov.ab@mail.kz',
                'reliability_data' => ['risk_level' => 'medium', 'credit_rating' => 'BBB', 'years_active' => 3],
            ],
            [
                'name' => 'ТОО "Glass Solutions KZ"',
                'bin_iin' => '150740008888',
                'bank_details' => 'АО "Bank CenterCredit", БИК KCJBKZKX, ИИК KZ56826F000008888000',
                'email' => 'sales@glasskz.com',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'A+', 'years_active' => 8],
            ],
            [
                'name' => 'ТОО "МаркетПро Медиа"',
                'bin_iin' => '180340005555',
                'bank_details' => 'АО "Jusan Bank", БИК TSABORGS, ИИК KZ43826T000005555000',
                'email' => 'hello@marketpro.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'A', 'years_active' => 5],
            ],
            [
                'name' => 'АО "ТрансЛогистик Центр"',
                'bin_iin' => '100840019876',
                'bank_details' => 'АО "Kaspi Bank", БИК CASPKZKA, ИИК KZ23722S000019876543',
                'email' => 'logistics@translc.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'AA', 'years_active' => 15],
            ],
            [
                'name' => 'ТОО "ЭкоПак Индустри"',
                'bin_iin' => '160240007777',
                'bank_details' => 'АО "Halyk Bank", БИК HSBKKZKX, ИИК KZ669470307777000000',
                'email' => 'info@ecopak.kz',
                'reliability_data' => ['risk_level' => 'medium', 'credit_rating' => 'BB+', 'years_active' => 6],
            ],
            [
                'name' => 'ИП Сагынбаев К.М.',
                'bin_iin' => '790812300456',
                'bank_details' => 'АО "ForteBank", БИК IRTYKZKA, ИИК KZ45141G000004560000',
                'email' => 'sagynbaev@inbox.kz',
                'reliability_data' => ['risk_level' => 'high', 'credit_rating' => 'C', 'years_active' => 1],
            ],
            [
                'name' => 'ТОО "ColdChain Logistics"',
                'bin_iin' => '140640009999',
                'bank_details' => 'АО "Bank CenterCredit", БИК KCJBKZKX, ИИК KZ88826F000009999000',
                'email' => 'ops@coldchain.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'A', 'years_active' => 9],
            ],
            [
                'name' => 'АО "СтройМонтаж Алматы"',
                'bin_iin' => '050940022222',
                'bank_details' => 'АО "Halyk Bank", БИК HSBKKZKX, ИИК KZ779470302222200000',
                'email' => 'office@stroymontazh.kz',
                'reliability_data' => ['risk_level' => 'low', 'credit_rating' => 'A+', 'years_active' => 20],
            ],
            // Blacklisted partners
            [
                'name' => 'ТОО "Бракованные Поставки"',
                'bin_iin' => '170340001111',
                'bank_details' => 'АО "Kaspi Bank", БИК CASPKZKA, ИИК KZ11722S000001111000',
                'email' => 'contact@brakovannye.kz',
                'reliability_data' => ['risk_level' => 'critical', 'credit_rating' => 'D', 'years_active' => 2],
                'blacklisted_at' => now()->subMonths(3),
                'blacklist_reason' => 'Systematic delivery of substandard raw materials. Three consecutive shipments failed quality control. Contract terminated per clause 8.2.',
                'blacklisted_by' => $lawyer?->id,
            ],
            [
                'name' => 'ИП Жумабеков Т.Р.',
                'bin_iin' => '880225300789',
                'bank_details' => 'АО "ForteBank", БИК IRTYKZKA, ИИК KZ67141G000007890000',
                'email' => 'zhumabekov@mail.kz',
                'reliability_data' => ['risk_level' => 'critical', 'credit_rating' => 'D', 'years_active' => 1],
                'blacklisted_at' => now()->subWeeks(6),
                'blacklist_reason' => 'Fraudulent invoicing detected during Q4 2025 audit. Case referred to internal investigations.',
                'blacklisted_by' => $lawyer?->id,
            ],
        ];

        foreach ($partners as $data) {
            Partner::firstOrCreate(
                ['bin_iin' => $data['bin_iin']],
                $data
            );
        }

        $this->seedPartnerDocuments();
    }

    private function seedPartnerDocuments(): void
    {
        $partners = Partner::all();
        $docTypes = [
            ['name' => 'Certificate of State Registration', 'mime_type' => 'application/pdf'],
            ['name' => 'Tax Registration Certificate', 'mime_type' => 'application/pdf'],
            ['name' => 'Charter / Articles of Association', 'mime_type' => 'application/pdf'],
            ['name' => 'Director ID Copy', 'mime_type' => 'image/jpeg'],
            ['name' => 'Bank Account Confirmation Letter', 'mime_type' => 'application/pdf'],
        ];

        foreach ($partners->take(8) as $partner) {
            $count = rand(2, min(4, count($docTypes)));
            $keys = (array) array_rand($docTypes, $count);
            $docsToSeed = array_map(fn ($k) => $docTypes[$k], $keys);

            foreach ($docsToSeed as $doc) {
                PartnerDocument::firstOrCreate(
                    ['partner_id' => $partner->id, 'name' => $doc['name']],
                    [
                        'path' => 'partner-documents/'.$partner->id.'/'.str($doc['name'])->slug().'.pdf',
                        'mime_type' => $doc['mime_type'],
                        'size' => rand(50000, 2000000),
                    ]
                );
            }
        }
    }
}

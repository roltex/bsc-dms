<?php

namespace Database\Seeders;

use App\Models\PlaceholderVariable;
use Illuminate\Database\Seeder;

class PlaceholderVariableSeeder extends Seeder
{
    public function run(): void
    {
        $placeholders = [
            // Partner data
            ['key' => 'CONTRACTOR_NAME',         'label' => 'Contractor Name',         'description' => 'Full name of the partner/contractor',                  'source' => 'partner',   'is_system' => true,  'sort_order' => 10],
            ['key' => 'CONTRACTOR_BIN_IIN',      'label' => 'Contractor BIN/IIN',      'description' => 'Tax identification number of the partner',              'source' => 'partner',   'is_system' => true,  'sort_order' => 11],
            ['key' => 'CONTRACTOR_BANK_DETAILS', 'label' => 'Contractor Bank Details', 'description' => 'Bank account details of the partner',                  'source' => 'partner',   'is_system' => true,  'sort_order' => 12],
            ['key' => 'CONTRACTOR_EMAIL',        'label' => 'Contractor Email',        'description' => 'Email address of the partner',                         'source' => 'partner',   'is_system' => true,  'sort_order' => 13],

            // Task / form fields
            ['key' => 'TASK_NUMBER',       'label' => 'Document Number',     'description' => 'Unique document registration number (auto-generated)',  'source' => 'auto',      'is_system' => true,  'sort_order' => 20],
            ['key' => 'TASK_CATEGORY',     'label' => 'Task Category',       'description' => 'Name of the selected document category',                'source' => 'task',      'is_system' => true,  'sort_order' => 21],
            ['key' => 'COMMERCIAL_TERMS',  'label' => 'Commercial Terms',    'description' => 'Payment terms, conditions, and other commercial terms', 'source' => 'task',      'is_system' => true,  'sort_order' => 22],
            ['key' => 'VALIDITY_FROM',     'label' => 'Valid From',          'description' => 'Contract validity start date',                          'source' => 'task',      'is_system' => true,  'sort_order' => 23],
            ['key' => 'VALIDITY_TO',       'label' => 'Valid To',            'description' => 'Contract validity end date',                            'source' => 'task',      'is_system' => true,  'sort_order' => 24],
            ['key' => 'DEADLINE',          'label' => 'Deadline',            'description' => 'Task deadline date',                                    'source' => 'task',      'is_system' => true,  'sort_order' => 25],

            // Date / time
            ['key' => 'CURRENT_DATE',      'label' => 'Current Date',        'description' => 'Today\'s date at the time of document creation',        'source' => 'date',      'is_system' => true,  'sort_order' => 30],

            // User
            ['key' => 'INITIATOR_NAME',    'label' => 'Initiator Name',      'description' => 'Name of the user who created the task',                 'source' => 'user',      'is_system' => true,  'sort_order' => 40],

            // System settings
            ['key' => 'COMPANY_NAME',      'label' => 'Company Name',        'description' => 'Company name from system settings',                     'source' => 'settings',  'is_system' => true,  'sort_order' => 50],
            ['key' => 'COMPANY_APP_NAME',  'label' => 'Application Name',    'description' => 'Application name from system settings',                 'source' => 'settings',  'is_system' => true,  'sort_order' => 51],

            // Signatures
            ['key' => 'COMPANY_SIGN',      'label' => 'Company Signature',   'description' => 'Company-side signature placeholder (filled when signed)', 'source' => 'signature', 'is_system' => true,  'sort_order' => 60],
            ['key' => 'PARTNER_SIGN',      'label' => 'Partner Signature',   'description' => 'Partner-side signature placeholder (filled when signed)', 'source' => 'signature', 'is_system' => true,  'sort_order' => 61],
        ];

        foreach ($placeholders as $data) {
            PlaceholderVariable::updateOrCreate(
                ['key' => $data['key']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}

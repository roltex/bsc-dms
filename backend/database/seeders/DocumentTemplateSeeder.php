<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templatesByCode = [
            'supply' => [
                'Standard Supply Agreement',
                'Framework Supply Contract',
                'Raw Materials Purchase Agreement',
                'Exclusive Distribution Agreement',
            ],
            'services' => [
                'General Service Agreement',
                'IT Consulting Services Contract',
                'Maintenance & Support Agreement',
                'Professional Services SOW Template',
            ],
            'marketing' => [
                'Sponsorship Agreement',
                'Advertising Services Contract',
                'Brand License Agreement',
                'Event Partnership Contract',
            ],
            'powers_of_attorney' => [
                'General Power of Attorney',
                'Limited Power of Attorney',
                'Power of Attorney for Tax Matters',
            ],
            'labels' => [
                'Label Approval Request Form',
                'Packaging Design Agreement',
                'Trademark Usage License',
            ],
            'bank_guarantees' => [
                'Bank Guarantee Request Form',
                'Performance Bond Template',
                'Advance Payment Guarantee',
            ],
            'licenses' => [
                'License Application Checklist',
                'Alcohol Production License Renewal',
                'Environmental Permit Application',
            ],
            'claims' => [
                'Formal Claim Letter Template',
                'Debt Recovery Notice',
                'Quality Dispute Notification',
            ],
            'lawsuits' => [
                'Statement of Claim Template',
                'Response to Lawsuit Template',
                'Appeal Filing Template',
            ],
            'other' => [
                'Non-Disclosure Agreement (NDA)',
                'Memorandum of Understanding (MOU)',
                'Letter of Intent',
            ],
        ];

        $categories = DocumentCategory::all()->keyBy('code');

        foreach ($templatesByCode as $code => $templates) {
            $category = $categories->get($code);
            if (! $category) {
                continue;
            }

            foreach ($templates as $templateName) {
                DocumentTemplate::firstOrCreate(
                    [
                        'document_category_id' => $category->id,
                        'name' => $templateName,
                    ],
                    [
                        'path' => 'templates/'.str($templateName)->slug().'.docx',
                        'editable_sections' => $this->generateSections($code),
                    ]
                );
            }
        }
    }

    private function generateSections(string $categoryCode): array
    {
        $commonSections = ['parties', 'subject_matter', 'term_and_termination', 'signatures'];

        return match ($categoryCode) {
            'supply' => [...$commonSections, 'delivery_terms', 'pricing', 'quality_requirements', 'force_majeure'],
            'services' => [...$commonSections, 'scope_of_work', 'payment_schedule', 'service_levels', 'liability'],
            'marketing' => [...$commonSections, 'campaign_details', 'budget', 'intellectual_property', 'reporting'],
            'powers_of_attorney' => ['principal', 'attorney_in_fact', 'scope_of_authority', 'effective_dates'],
            'labels' => [...$commonSections, 'design_specifications', 'approval_process', 'compliance'],
            'bank_guarantees' => ['beneficiary', 'guarantor', 'amount', 'expiry_date', 'conditions'],
            'licenses' => ['applicant', 'license_type', 'activities', 'validity_period', 'conditions'],
            'claims' => ['claimant', 'respondent', 'claim_basis', 'demanded_remedy', 'deadline'],
            'lawsuits' => ['plaintiff', 'defendant', 'court', 'claim_basis', 'relief_sought', 'evidence_list'],
            default => $commonSections,
        };
    }
}

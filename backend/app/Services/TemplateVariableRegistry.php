<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Task;

class TemplateVariableRegistry
{
    public const SIGNATURE_KEYS = ['COMPANY_SIGN', 'PARTNER_SIGN'];

    public static function all(): array
    {
        return [
            'partner' => [
                'label' => 'Contractor / Partner',
                'variables' => [
                    ['key' => 'CONTRACTOR_NAME', 'label' => 'Partner company name', 'example' => 'BSC Ltd.'],
                    ['key' => 'CONTRACTOR_BIN_IIN', 'label' => 'Partner BIN/IIN', 'example' => '123456789012'],
                    ['key' => 'CONTRACTOR_BANK_DETAILS', 'label' => 'Partner bank details', 'example' => 'KZ123456789...'],
                    ['key' => 'CONTRACTOR_EMAIL', 'label' => 'Partner email', 'example' => 'partner@company.kz'],
                ],
            ],
            'company' => [
                'label' => 'Company (your organization)',
                'variables' => [
                    ['key' => 'COMPANY_NAME', 'label' => 'Company legal name', 'example' => 'Efes Kazakhstan'],
                    ['key' => 'COMPANY_APP_NAME', 'label' => 'Application / brand name', 'example' => 'EFES DMS'],
                ],
            ],
            'task' => [
                'label' => 'Task / Document',
                'variables' => [
                    ['key' => 'TASK_NUMBER', 'label' => 'Document registration number (auto-generated)', 'example' => 'BSC-SER-2026-0042'],
                    ['key' => 'TASK_CATEGORY', 'label' => 'Document category name', 'example' => 'Supply Agreement'],
                    ['key' => 'COMMERCIAL_TERMS', 'label' => 'Commercial terms text', 'example' => 'Net 30 days'],
                    ['key' => 'VALIDITY_FROM', 'label' => 'Validity start date', 'example' => '01.01.2026'],
                    ['key' => 'VALIDITY_TO', 'label' => 'Validity end date', 'example' => '31.12.2026'],
                    ['key' => 'DEADLINE', 'label' => 'Task deadline', 'example' => '15.04.2026'],
                    ['key' => 'CURRENT_DATE', 'label' => 'Today\'s date', 'example' => '12.03.2026'],
                    ['key' => 'INITIATOR_NAME', 'label' => 'Name of person who created the task', 'example' => 'Alikhan Nursultanov'],
                ],
            ],
            'signatures' => [
                'label' => 'Signatures (image placeholders)',
                'variables' => [
                    ['key' => 'COMPANY_SIGN', 'label' => 'Company representative signature (replaced with image when signed)', 'example' => '[signature image]'],
                    ['key' => 'PARTNER_SIGN', 'label' => 'Partner/contractor signature (replaced with image when signed)', 'example' => '[signature image]'],
                ],
            ],
        ];
    }

    public static function allKeys(): array
    {
        $keys = [];
        foreach (static::all() as $group) {
            foreach ($group['variables'] as $var) {
                $keys[] = $var['key'];
            }
        }

        return $keys;
    }

    public static function isSignatureKey(string $key): bool
    {
        return in_array($key, self::SIGNATURE_KEYS, true);
    }

    public static function resolve(Task $task): array
    {
        $task->loadMissing(['partner', 'category', 'initiator']);

        $partner = $task->partner;
        $fmt = fn ($date) => $date ? $date->format('d.m.Y') : '';

        return [
            '{{CONTRACTOR_NAME}}' => $partner?->name ?? '',
            '{{CONTRACTOR_BIN_IIN}}' => $partner?->bin_iin ?? '',
            '{{CONTRACTOR_BANK_DETAILS}}' => $partner?->bank_details ?? '',
            '{{CONTRACTOR_EMAIL}}' => $partner?->email ?? '',

            '{{COMPANY_NAME}}' => (string) Setting::get('company_name', ''),
            '{{COMPANY_APP_NAME}}' => (string) Setting::get('app_name', ''),

            '{{TASK_NUMBER}}' => $task->registration_number ?: (string) $task->id,
            '{{TASK_CATEGORY}}' => $task->category?->name ?? '',
            '{{COMMERCIAL_TERMS}}' => $task->commercial_terms ?? '',
            '{{VALIDITY_FROM}}' => $fmt($task->validity_from),
            '{{VALIDITY_TO}}' => $fmt($task->validity_to),
            '{{DEADLINE}}' => $fmt($task->deadline),
            '{{CURRENT_DATE}}' => now()->format('d.m.Y'),
            '{{INITIATOR_NAME}}' => $task->initiator?->name ?? '',

            // Signature placeholders are NOT replaced with text.
            // They remain in the document until the actual signing action.
        ];
    }

    public static function resolvePreview(array $partnerData, array $taskData): array
    {
        $result = [];
        foreach (static::allKeys() as $key) {
            $result[$key] = match ($key) {
                'CONTRACTOR_NAME' => $partnerData['name'] ?? '',
                'CONTRACTOR_BIN_IIN' => $partnerData['bin_iin'] ?? '',
                'CONTRACTOR_BANK_DETAILS' => $partnerData['bank_details'] ?? '',
                'CONTRACTOR_EMAIL' => $partnerData['email'] ?? '',
                'COMPANY_NAME' => (string) Setting::get('company_name', ''),
                'COMPANY_APP_NAME' => (string) Setting::get('app_name', ''),
                'TASK_NUMBER' => '',
                'TASK_CATEGORY' => $taskData['category_name'] ?? '',
                'COMMERCIAL_TERMS' => $taskData['commercial_terms'] ?? '',
                'VALIDITY_FROM' => $taskData['validity_from'] ?? '',
                'VALIDITY_TO' => $taskData['validity_to'] ?? '',
                'DEADLINE' => $taskData['deadline'] ?? '',
                'CURRENT_DATE' => now()->format('d.m.Y'),
                'INITIATOR_NAME' => $taskData['initiator_name'] ?? '',
                'COMPANY_SIGN' => '[Company Signature — auto-filled when signed]',
                'PARTNER_SIGN' => '[Partner Signature — auto-filled when signed]',
                default => '',
            };
        }

        return $result;
    }
}

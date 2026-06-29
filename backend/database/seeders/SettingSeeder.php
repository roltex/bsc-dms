<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['group' => 'general', 'key' => 'app_name', 'value' => 'EFES Document Management', 'type' => 'string', 'label' => 'Application Name', 'description' => 'Displayed in the header and emails.'],
            ['group' => 'general', 'key' => 'company_name', 'value' => 'Efes Kazakhstan', 'type' => 'string', 'label' => 'Company Name', 'description' => 'Legal entity name for documents.'],
            ['group' => 'general', 'key' => 'default_deadline_days', 'value' => '14', 'type' => 'integer', 'label' => 'Default Deadline (days)', 'description' => 'Default number of days for task deadlines.'],
            ['group' => 'general', 'key' => 'registration_number_prefix', 'value' => 'EF', 'type' => 'string', 'label' => 'Registration Number Prefix', 'description' => 'Prefix for approved document registration numbers (e.g. EF-2026-0001).'],

            // Notifications
            ['group' => 'notifications', 'key' => 'email_notifications_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Email Notifications', 'description' => 'Send email notifications on workflow transitions.'],
            ['group' => 'notifications', 'key' => 'deadline_reminder_days', 'value' => '3', 'type' => 'integer', 'label' => 'Deadline Reminder (days before)', 'description' => 'Send reminders this many days before a task deadline.'],
            ['group' => 'notifications', 'key' => 'overdue_notification_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'Overdue Notifications', 'description' => 'Send notifications when tasks pass their deadline.'],
            ['group' => 'notifications', 'key' => 'admin_email', 'value' => 'admin@efes.kz', 'type' => 'string', 'label' => 'Admin Notification Email', 'description' => 'Email address for system alerts and reports.'],

            // Integrations
            ['group' => 'integrations', 'key' => 'adata_api_url', 'value' => 'https://api.adata.kz/v1', 'type' => 'string', 'label' => 'ADATA API URL', 'description' => 'Base URL for ADATA partner reliability checks.'],
            ['group' => 'integrations', 'key' => 'adata_api_key', 'value' => '', 'type' => 'string', 'label' => 'ADATA API Key', 'description' => 'Authentication key for ADATA service.'],
            ['group' => 'integrations', 'key' => 'paragraph_api_url', 'value' => 'https://online.zakon.kz/api', 'type' => 'string', 'label' => 'Paragraph API URL', 'description' => 'Base URL for Paragraph legal search service.'],
            ['group' => 'integrations', 'key' => 'paragraph_api_key', 'value' => '', 'type' => 'string', 'label' => 'Paragraph API Key', 'description' => 'Authentication key for Paragraph service.'],
            ['group' => 'integrations', 'key' => 'ai_comparison_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'AI Document Comparison', 'description' => 'Enable AI-powered document comparison on signed uploads.'],
            ['group' => 'integrations', 'key' => 'ai_service_url', 'value' => '', 'type' => 'string', 'label' => 'AI Service URL', 'description' => 'Endpoint for the AI comparison microservice.'],

            // Workflow
            ['group' => 'workflow', 'key' => 'allow_simplified_route', 'value' => '1', 'type' => 'boolean', 'label' => 'Allow Simplified Route', 'description' => 'Allow initiators to use the 2-step simplified approval route.'],
            ['group' => 'workflow', 'key' => 'require_signature_on_upload', 'value' => '1', 'type' => 'boolean', 'label' => 'Require Electronic Signature', 'description' => 'Require initiators to draw a signature when uploading signed documents.'],
            ['group' => 'workflow', 'key' => 'auto_archive_after_days', 'value' => '365', 'type' => 'integer', 'label' => 'Auto-Archive After (days)', 'description' => 'Automatically archive approved tasks after this many days. Set 0 to disable.'],
            ['group' => 'workflow', 'key' => 'max_upload_size_mb', 'value' => '20', 'type' => 'integer', 'label' => 'Max Upload Size (MB)', 'description' => 'Maximum file upload size in megabytes.'],

            // GM Approval
            ['group' => 'workflow', 'key' => 'gm_approval_threshold', 'value' => '50000000', 'type' => 'integer', 'label' => 'GM Approval Threshold', 'description' => 'Contract amount threshold requiring GM approval (in base currency).'],
            ['group' => 'workflow', 'key' => 'gm_user_id', 'value' => '', 'type' => 'integer', 'label' => 'GM User ID', 'description' => 'User ID of the General Manager for high-value approvals.'],

            // PDF Protection
            ['group' => 'workflow', 'key' => 'pdf_protection_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'PDF Protection', 'description' => 'Protect final approved PDFs with permissions (no-edit).'],

            // AI / OpenAI
            ['group' => 'integrations', 'key' => 'openai_api_key', 'value' => '', 'type' => 'string', 'label' => 'OpenAI API Key', 'description' => 'API key for AI document analysis and comparison.'],
            ['group' => 'integrations', 'key' => 'openai_model', 'value' => 'gpt-4o', 'type' => 'string', 'label' => 'OpenAI Model', 'description' => 'Model to use for AI analysis (e.g., gpt-4o, gpt-4o-mini).'],

            // NCALayer
            ['group' => 'integrations', 'key' => 'ncalayer_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'NCALayer EDS', 'description' => 'Enable NCALayer digital signature support.'],

            // SAP
            ['group' => 'integrations', 'key' => 'sap_api_url', 'value' => '', 'type' => 'string', 'label' => 'SAP API URL', 'description' => 'Base URL for SAP ERP integration.'],
            ['group' => 'integrations', 'key' => 'sap_api_key', 'value' => '', 'type' => 'string', 'label' => 'SAP API Key', 'description' => 'Authentication key for SAP integration.'],
            ['group' => 'integrations', 'key' => 'sap_sync_enabled', 'value' => '0', 'type' => 'boolean', 'label' => 'SAP Sync Enabled', 'description' => 'Enable automatic partner and document sync with SAP.'],

            // Storage
            ['group' => 'storage', 'key' => 'storage_driver', 'value' => 'local', 'type' => 'string', 'label' => 'Storage Driver', 'description' => 'File storage driver: local, s3, or sharepoint.'],
            ['group' => 'storage', 'key' => 's3_bucket', 'value' => '', 'type' => 'string', 'label' => 'S3 Bucket Name', 'description' => 'S3-compatible bucket name for document storage.'],
            ['group' => 'storage', 'key' => 's3_region', 'value' => '', 'type' => 'string', 'label' => 'S3 Region', 'description' => 'AWS/S3-compatible region.'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}

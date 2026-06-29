<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'System Settings';

    protected string $view = 'filament.pages.system-settings';

    public array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Fieldset::make('General')
                    ->schema([
                        TextInput::make('app_name')->label('Application Name')->required(),
                        TextInput::make('company_name')->label('Company Name')->required(),
                        TextInput::make('default_deadline_days')->label('Default Deadline (days)')->numeric()->minValue(1),
                        TextInput::make('registration_number_prefix')->label('Registration Number Prefix'),
                    ])
                    ->columns(2),

                Fieldset::make('Notifications')
                    ->schema([
                        Toggle::make('email_notifications_enabled')->label('Email Notifications Enabled'),
                        TextInput::make('deadline_reminder_days')->label('Reminder Days Before Deadline')->numeric()->minValue(1),
                        Toggle::make('overdue_notification_enabled')->label('Overdue Notifications'),
                        TextInput::make('admin_email')->label('Admin Email')->email(),
                    ])
                    ->columns(2),

                Fieldset::make('Workflow')
                    ->schema([
                        Toggle::make('allow_simplified_route')->label('Allow Simplified Approval Route'),
                        Toggle::make('require_signature_on_upload')->label('Require Electronic Signature'),
                        TextInput::make('auto_archive_after_days')->label('Auto-Archive After (days)')->numeric()->minValue(0),
                        TextInput::make('max_upload_size_mb')->label('Max Upload Size (MB)')->numeric()->minValue(1),
                        TextInput::make('gm_approval_threshold')->label('GM Approval Threshold')->numeric()->minValue(0),
                        TextInput::make('gm_user_id')->label('GM User ID')->numeric(),
                        Toggle::make('pdf_protection_enabled')->label('Protect Approved PDFs'),
                    ])
                    ->columns(2),

                Fieldset::make('Integrations')
                    ->schema([
                        TextInput::make('adata_api_url')->label('ADATA API Base URL')
                            ->placeholder('https://api.adata.kz/api')
                            ->url(),
                        TextInput::make('adata_api_key')->label('ADATA Auth Token (tokenAuth)')
                            ->password()->revealable()
                            ->helperText('Authorization token from your Adata account'),
                        TextInput::make('paragraph_api_url')->label('Paragraph API URL')->url(),
                        TextInput::make('paragraph_api_key')->label('Paragraph API Key')->password()->revealable(),
                        Toggle::make('ai_comparison_enabled')->label('AI Document Comparison'),
                        TextInput::make('ai_service_url')->label('AI Service URL')->url(),
                        TextInput::make('openai_api_key')->label('OpenAI API Key')->password()->revealable(),
                        TextInput::make('openai_model')->label('OpenAI Model'),
                        Toggle::make('ncalayer_enabled')->label('NCALayer EDS Enabled'),
                        TextInput::make('sap_api_url')->label('SAP API URL')->url(),
                        TextInput::make('sap_api_key')->label('SAP API Key')->password()->revealable(),
                        Toggle::make('sap_sync_enabled')->label('SAP Sync Enabled'),
                    ])
                    ->columns(2),

                Fieldset::make('Google Docs Integration')
                    ->schema([
                        Toggle::make('google_drive_enabled')->label('Enable Google Docs Editing'),
                        TextInput::make('google_client_id')
                            ->label('OAuth Client ID')
                            ->placeholder('xxxx.apps.googleusercontent.com')
                            ->helperText('From Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client IDs'),
                        TextInput::make('google_client_secret')
                            ->label('OAuth Client Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('GOCSPX-...')
                            ->helperText('Authorize via the frontend Settings page after saving these credentials.'),
                    ])
                    ->columns(1),

                Fieldset::make('Storage')
                    ->schema([
                        TextInput::make('storage_driver')->label('Storage Driver'),
                        TextInput::make('s3_bucket')->label('S3 Bucket'),
                        TextInput::make('s3_region')->label('S3 Region'),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->color('primary'),
        ];
    }
}

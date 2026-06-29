<?php

namespace App\Filament\Pages;

use App\Services\DocLogixImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocLogixImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'DocLogix Import';

    protected string $view = 'filament.pages.doclogix-import';

    public ?string $importType = 'partners';

    public ?array $file = [];

    public ?array $importResult = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('importType')
                    ->label('Import Type')
                    ->options([
                        'partners' => 'Partners (vendors, clients)',
                        'documents' => 'Finalized Documents',
                    ])
                    ->required()
                    ->default('partners'),
                FileUpload::make('file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                    ->maxSize(51200)
                    ->required()
                    ->directory('doclogix-imports'),
            ]);
    }

    public function runImport(): void
    {
        $data = $this->form->getState();

        if (empty($data['file'])) {
            Notification::make()->title('Please upload a file.')->danger()->send();
            return;
        }

        $filePath = is_array($data['file']) ? reset($data['file']) : $data['file'];
        $absPath = Storage::disk('local')->path($filePath);

        if (! file_exists($absPath)) {
            Notification::make()->title('Uploaded file not found.')->danger()->send();
            return;
        }

        $handle = fopen($absPath, 'r');
        if (! $handle) {
            Notification::make()->title('Could not open file.')->danger()->send();
            return;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            Notification::make()->title('Empty CSV file.')->danger()->send();
            return;
        }

        $headers = array_map('trim', $headers);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $mapped = [];
            foreach ($headers as $idx => $header) {
                $mapped[$header] = $row[$idx] ?? '';
            }
            $rows[] = $mapped;
        }
        fclose($handle);

        $importer = app(DocLogixImporter::class);
        $type = $data['importType'] ?? 'partners';
        $userId = auth()->id();

        $this->importResult = match ($type) {
            'partners' => $importer->importPartners($rows),
            'documents' => $importer->importDocuments($rows, $userId),
            default => ['status' => 'error', 'message' => 'Unknown import type.'],
        };

        $status = $this->importResult['status'] ?? 'error';
        $stats = $this->importResult['stats'] ?? [];

        if ($status === 'success') {
            Notification::make()->title('Import completed successfully.')->success()->send();
        } elseif ($status === 'partial') {
            Notification::make()->title('Import completed with some errors.')->warning()->send();
        } else {
            Notification::make()->title('Import failed.')->danger()->send();
        }
    }
}

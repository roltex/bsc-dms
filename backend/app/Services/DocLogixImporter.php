<?php

namespace App\Services;

use App\Models\FinalizedDocument;
use App\Models\Partner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocLogixImporter
{
    private array $stats = [
        'partners_created' => 0,
        'partners_skipped' => 0,
        'partners_errors' => 0,
        'documents_created' => 0,
        'documents_skipped' => 0,
        'documents_errors' => 0,
    ];

    private array $errors = [];

    public function importPartners(array $rows): array
    {
        foreach ($rows as $i => $row) {
            try {
                $binIin = trim($row['bin_iin'] ?? $row['BIN'] ?? $row['IIN'] ?? '');
                $name = trim($row['name'] ?? $row['company_name'] ?? $row['Name'] ?? '');

                if (empty($binIin) || empty($name)) {
                    $this->stats['partners_skipped']++;
                    continue;
                }

                if (Partner::where('bin_iin', $binIin)->exists()) {
                    $this->stats['partners_skipped']++;
                    continue;
                }

                Partner::create([
                    'name' => $name,
                    'bin_iin' => $binIin,
                    'email' => $row['email'] ?? $row['Email'] ?? null,
                    'bank_details' => $row['bank_details'] ?? $row['bank'] ?? $row['Bank Details'] ?? null,
                ]);

                $this->stats['partners_created']++;
            } catch (\Throwable $e) {
                $this->stats['partners_errors']++;
                $this->errors[] = "Row {$i}: " . $e->getMessage();
            }
        }

        return $this->getResult();
    }

    public function importDocuments(array $rows, int $userId): array
    {
        foreach ($rows as $i => $row) {
            try {
                $name = trim($row['name'] ?? $row['document_name'] ?? $row['Name'] ?? '');
                $category = trim($row['category'] ?? $row['Category'] ?? 'other');

                if (empty($name)) {
                    $this->stats['documents_skipped']++;
                    continue;
                }

                FinalizedDocument::create([
                    'name' => $name,
                    'path' => $row['path'] ?? '',
                    'mime_type' => $row['mime_type'] ?? 'application/pdf',
                    'size' => (int) ($row['size'] ?? 0),
                    'category' => $category,
                    'notes' => $row['notes'] ?? $row['description'] ?? null,
                    'user_id' => $userId,
                ]);

                $this->stats['documents_created']++;
            } catch (\Throwable $e) {
                $this->stats['documents_errors']++;
                $this->errors[] = "Row {$i}: " . $e->getMessage();
            }
        }

        return $this->getResult();
    }

    public function importFromCsv(UploadedFile $file, string $type, int $userId): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['status' => 'error', 'message' => 'Could not open file.'];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return ['status' => 'error', 'message' => 'Empty CSV file.'];
        }

        $headers = array_map('trim', $headers);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $data[$idx] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return match ($type) {
            'partners' => $this->importPartners($rows),
            'documents' => $this->importDocuments($rows, $userId),
            default => ['status' => 'error', 'message' => 'Unknown import type: ' . $type],
        };
    }

    private function getResult(): array
    {
        return [
            'status' => empty($this->errors) ? 'success' : 'partial',
            'stats' => $this->stats,
            'errors' => array_slice($this->errors, 0, 50),
        ];
    }
}

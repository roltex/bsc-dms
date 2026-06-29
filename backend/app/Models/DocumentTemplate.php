<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'document_category_id',
        'name',
        'path',
        'editable_sections',
        'detected_variables',
        'extra_variables',
        'table_schema',
        'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'editable_sections' => 'array',
            'detected_variables' => 'array',
            'extra_variables' => 'array',
            'table_schema' => 'array',
            'is_custom' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function templateTables(): HasMany
    {
        return $this->hasMany(TemplateTable::class)->orderBy('sort_order');
    }

    /**
     * Detect {{VARIABLE}} and {{TABLE.COLUMN}} placeholders in the DOCX file.
     *
     * Simple variables: {{CONTRACTOR_NAME}} -> detected_variables
     * Table variables:  {{ITEMS.DESCRIPTION}} -> table_schema
     */
    public function detectVariables(): array
    {
        if (! $this->path || ! Storage::disk('local')->exists($this->path)) {
            return [];
        }

        try {
            $absPath = Storage::disk('local')->path($this->path);
            $xml = self::readDocxXml($absPath);
            if (! $xml) {
                return [];
            }

            $plainText = strip_tags($xml);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_XML1, 'UTF-8');

            $vars = [];
            if (preg_match_all('/\{\{([A-Za-z][A-Za-z0-9_]*)\}\}/', $plainText, $matches)) {
                $vars = array_unique($matches[1]);
            }

            sort($vars);

            return array_values($vars);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Detect table variables like {{TABLE_NAME.COLUMN}} and return schema with labels.
     *
     * Parses the DOCX XML to find Word tables (<w:tbl>) containing template placeholders,
     * then reads the header row above to extract human-readable column labels.
     *
     * Returns: ['ITEMS' => {'columns': ['#','DESCRIPTION',...], 'labels': {'#':'#','DESCRIPTION':'აღწერა',...}}]
     */
    public function detectTableSchema(): array
    {
        if (! $this->path || ! Storage::disk('local')->exists($this->path)) {
            return [];
        }

        try {
            $absPath = Storage::disk('local')->path($this->path);
            $xml = self::readDocxXml($absPath);
            if (! $xml) {
                return [];
            }

            $plainText = strip_tags($xml);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_XML1, 'UTF-8');

            $columnsByTable = [];
            if (preg_match_all('/\{\{([A-Z][A-Z0-9_]*)\.([A-Z0-9_#]+)\}\}/', $plainText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $tableName = $m[1];
                    $column = $m[2];
                    if (! isset($columnsByTable[$tableName])) {
                        $columnsByTable[$tableName] = [];
                    }
                    if (! in_array($column, $columnsByTable[$tableName])) {
                        $columnsByTable[$tableName][] = $column;
                    }
                }
            }

            if (empty($columnsByTable)) {
                return [];
            }

            $result = [];
            foreach ($columnsByTable as $tableName => $columns) {
                $labels = $this->extractHeaderLabels($xml, $tableName, $columns);
                $result[$tableName] = [
                    'columns' => $columns,
                    'labels' => $labels,
                ];
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Find the Word table containing {{TABLE_NAME.xxx}} placeholders,
     * then read the header row (row before the template row) to get labels.
     */
    private function extractHeaderLabels(string $xml, string $tableName, array $columns): array
    {
        $placeholder = '{{'.$tableName.'.';
        $labels = array_combine($columns, $columns);

        if (! preg_match_all('/<w:tbl\b[^>]*>.*?<\/w:tbl>/s', $xml, $tblMatches)) {
            return $labels;
        }

        foreach ($tblMatches[0] as $tbl) {
            $tblText = strip_tags($tbl);
            $tblText = html_entity_decode($tblText, ENT_QUOTES | ENT_XML1, 'UTF-8');

            if (! str_contains($tblText, $placeholder)) {
                continue;
            }

            if (! preg_match_all('/<w:tr\b[^>]*>.*?<\/w:tr>/s', $tbl, $trMatches)) {
                continue;
            }

            $templateRowIdx = null;
            foreach ($trMatches[0] as $idx => $tr) {
                $trText = strip_tags($tr);
                $trText = html_entity_decode($trText, ENT_QUOTES | ENT_XML1, 'UTF-8');
                if (str_contains($trText, $placeholder)) {
                    $templateRowIdx = $idx;
                    break;
                }
            }

            if ($templateRowIdx === null || $templateRowIdx === 0) {
                continue;
            }

            $headerRow = $trMatches[0][$templateRowIdx - 1];
            $templateRow = $trMatches[0][$templateRowIdx];

            $headerCells = $this->extractCellTexts($headerRow);
            $templateCells = $this->extractCellTexts($templateRow);

            foreach ($templateCells as $cellIdx => $cellText) {
                if (preg_match('/\{\{'.$tableName.'\.([A-Z0-9_#]+)\}\}/', $cellText, $cm)) {
                    $col = $cm[1];
                    if (isset($headerCells[$cellIdx]) && trim($headerCells[$cellIdx]) !== '') {
                        $labels[$col] = trim($headerCells[$cellIdx]);
                    }
                }
            }

            break;
        }

        return $labels;
    }

    private function extractCellTexts(string $rowXml): array
    {
        $cells = [];
        if (preg_match_all('/<w:tc\b[^>]*>.*?<\/w:tc>/s', $rowXml, $tcMatches)) {
            foreach ($tcMatches[0] as $tc) {
                $text = strip_tags($tc);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $cells[] = trim($text);
            }
        }

        return $cells;
    }

    public function refreshDetectedVariables(): void
    {
        $this->update([
            'detected_variables' => $this->detectVariables(),
            'table_schema' => $this->detectTableSchema(),
        ]);
    }

    public static function readDocxXml(string $absPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml ?: null;
    }
}

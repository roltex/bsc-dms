<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\TemplateTable;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function afterCreate(): void
    {
        $this->record->refreshDetectedVariables();
        $this->saveTemplateTables();
    }

    protected function saveTemplateTables(): void
    {
        $tablesData = $this->data['template_tables_data'] ?? [];
        $this->record->templateTables()->delete();

        foreach (array_values($tablesData) as $idx => $tableRow) {
            if (empty($tableRow['name']) || empty($tableRow['shortcode'])) {
                continue;
            }
            TemplateTable::create([
                'document_template_id' => $this->record->id,
                'name' => $tableRow['name'],
                'shortcode' => strtoupper($tableRow['shortcode']),
                'columns' => array_values($tableRow['columns'] ?? []),
                'sort_order' => $idx,
            ]);
        }
    }
}

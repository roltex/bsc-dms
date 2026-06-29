<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\Task;
use App\Models\TaskDocument;
use App\Services\DocxTableGenerator;
use App\Services\TaskMainDocumentCommentMigrationService;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TemplateDocumentGenerator
{
    public function generate(DocumentTemplate $template, Task $task, array $extraVariables = [], array $tableData = []): TaskDocument
    {
        $absPath = Storage::disk('local')->path($template->path);

        $variables = TemplateVariableRegistry::resolve($task);

        foreach ($extraVariables as $key => $value) {
            $variables['{{'.$key.'}}'] = (string) $value;
        }

        $editableSections = $template->editable_sections;
        if (!empty($editableSections)) {
            $filteredVars = [];
            foreach ($variables as $placeholder => $value) {
                $key = trim($placeholder, '{}');
                if (in_array($key, $editableSections) || in_array($key, TemplateVariableRegistry::SIGNATURE_KEYS)) {
                    $filteredVars[$placeholder] = $value;
                }
            }
            $variables = $filteredVars;
        }

        $dir = "tasks/{$task->id}";
        Storage::disk('local')->makeDirectory($dir);

        $filename = 'generated-'.str_replace(' ', '-', strtolower($template->name)).'-'.time().'.docx';
        $outputRelPath = "{$dir}/{$filename}";
        $outputAbsPath = Storage::disk('local')->path($outputRelPath);

        copy($absPath, $outputAbsPath);

        $this->replaceVariablesInDocx($outputAbsPath, $variables, $tableData, $template);

        $converter = app(DocToPdfConverter::class);
        $pdfAbsPath = $converter->convertIfNeeded($outputRelPath);

        $pdfRelPath = preg_replace('/\.docx$/i', '.pdf', $outputRelPath);

        $lastVersion = $task->documents()->max('version') ?? 0;

        $doc = $task->documents()->create([
            'path' => $pdfRelPath,
            'mime_type' => 'application/pdf',
            'version' => $lastVersion + 1,
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        return $doc;
    }

    /**
     * Replace variables and table shortcodes in an existing DOCX file (e.g. downloaded from Google Docs).
     */
    public function replaceVariablesInDocxForTask(string $docxPath, Task $task, array $extraVariables = [], array $tableData = [], ?DocumentTemplate $template = null): void
    {
        $variables = TemplateVariableRegistry::resolve($task);

        foreach ($extraVariables as $key => $value) {
            $variables['{{'.$key.'}}'] = (string) $value;
        }

        $this->replaceVariablesInDocx($docxPath, $variables, $tableData, $template);
    }

    private function replaceVariablesInDocx(string $docxPath, array $variables, array $tableData = [], ?DocumentTemplate $template = null): void
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $templateTables = [];
        if ($template) {
            $templateTables = $template->templateTables()->get()->keyBy(fn ($t) => strtoupper($t->shortcode))->toArray();
        }

        $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];

        foreach ($xmlFiles as $xmlFile) {
            $xml = $zip->getFromName($xmlFile);
            if ($xml === false) {
                continue;
            }

            $xml = $this->cleanXmlRuns($xml);

            if (! empty($tableData)) {
                $xml = DocxTableReplacer::replaceTables($xml, $tableData);
            }

            $xml = $this->replaceTableShortcodes($xml, $templateTables, $tableData);

            foreach ($variables as $placeholder => $value) {
                $safeValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml = str_replace($placeholder, $safeValue, $xml);
            }

            $zip->addFromString($xmlFile, $xml);
        }

        $zip->close();
    }

    private function replaceTableShortcodes(string $xml, array $templateTables, array $tableData): string
    {
        if (empty($templateTables)) {
            return $xml;
        }

        foreach ($templateTables as $shortcode => $tableDef) {
            $placeholder = '{{TABLE:'.$shortcode.'}}';
            if (! str_contains($xml, $placeholder)) {
                continue;
            }

            $columns = $tableDef['columns'] ?? [];
            if (empty($columns)) {
                continue;
            }

            $rows = $tableData[$shortcode] ?? [];
            $tableXml = DocxTableGenerator::generate($columns, $rows);

            $xml = str_replace(
                $placeholder,
                '</w:t></w:r></w:p>'.$tableXml.'<w:p><w:r><w:t xml:space="preserve">',
                $xml
            );
        }

        return $xml;
    }

    private function cleanXmlRuns(string $xml): string
    {
        $plainText = strip_tags($xml);
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_XML1, 'UTF-8');

        if (! preg_match_all('/\{\{(?:TABLE:)?[A-Za-z0-9_.#]+\}\}/', $plainText, $found)) {
            return $xml;
        }

        $signatureKeys = TemplateVariableRegistry::SIGNATURE_KEYS;

        $splitPlaceholders = [];
        foreach (array_unique($found[0]) as $ph) {
            $inner = trim($ph, '{}');
            if (in_array($inner, $signatureKeys, true)) {
                continue;
            }

            $escaped = htmlspecialchars($ph, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            if (! str_contains($xml, $escaped)) {
                $splitPlaceholders[] = $ph;
            }
        }

        if (empty($splitPlaceholders)) {
            return $xml;
        }

        return preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function ($match) use ($splitPlaceholders) {
                $paragraph = $match[0];

                preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $paragraph, $textMatches);
                if (empty($textMatches[1])) {
                    return $paragraph;
                }

                $texts = array_map(
                    fn ($t) => html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $textMatches[1]
                );
                $combinedText = implode('', $texts);

                $needsFix = false;
                foreach ($splitPlaceholders as $ph) {
                    if (str_contains($combinedText, $ph)) {
                        $needsFix = true;
                        break;
                    }
                }

                if (! $needsFix) {
                    return $paragraph;
                }

                $pPr = '';
                if (preg_match('/<w:pPr>.*?<\/w:pPr>/s', $paragraph, $pPrMatch)) {
                    $pPr = $pPrMatch[0];
                }

                $rPr = '';
                if (preg_match('/<w:rPr>(.*?)<\/w:rPr>/s', $paragraph, $rPrMatch)) {
                    $rPr = '<w:rPr>' . $rPrMatch[1] . '</w:rPr>';
                }

                preg_match('/<w:p\b[^>]*>/', $paragraph, $openTag);
                $openingTag = $openTag[0] ?? '<w:p>';

                return $openingTag . $pPr
                    . '<w:r>' . $rPr . '<w:t xml:space="preserve">'
                    . htmlspecialchars($combinedText, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    . '</w:t></w:r></w:p>';
            },
            $xml
        ) ?? $xml;
    }
}

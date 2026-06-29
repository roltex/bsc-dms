<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\Setting;
use App\Services\DocToPdfConverter;
use App\Services\DocxTableGenerator;
use App\Services\DocxTableReplacer;
use App\Services\DocumentContentService;
use App\Services\GoogleDriveService;
use App\Services\HtmlToOoxmlConverter;
use App\Services\TemplateVariableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DocumentTemplate::query()->with('category:id,name,code')->withCount('templateTables');

        if ($request->filled('document_category_id')) {
            $query->where('document_category_id', $request->input('document_category_id'));
        }

        $templates = $query->orderBy('name')->get();

        return response()->json($templates);
    }

    public function uploadCustom(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'document_category_id' => 'required|exists:document_categories,id',
            'name' => 'nullable|string|max:255',
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['docx', 'doc'])) {
            return response()->json([
                'message' => 'The file must be a Word document (.docx or .doc).',
                'errors' => ['file' => ['Only .docx and .doc files are allowed.']],
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('templates', 'local');

        $name = $request->input('name')
            ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $template = DocumentTemplate::create([
            'document_category_id' => $request->input('document_category_id'),
            'name' => $name,
            'path' => $path,
            'is_custom' => true,
        ]);

        $template->refreshDetectedVariables();
        $template->load('category:id,name,code');

        return response()->json($template, 201);
    }

    public function tables(DocumentTemplate $template): JsonResponse
    {
        $tables = $template->templateTables()->orderBy('sort_order')->get();

        return response()->json($tables);
    }

    public function download(DocumentTemplate $template): mixed
    {
        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            abort(404, 'Template file not found.');
        }

        $path = Storage::disk('local')->path($template->path);

        return response()->download($path, $template->name.'.docx');
    }

    public function content(Request $request, DocumentTemplate $template): JsonResponse
    {
        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $absPath = Storage::disk('local')->path($template->path);
        $tempPath = null;

        $variables = array_filter(
            $request->input('variables', []),
            fn ($v) => $v !== '' && $v !== '(from settings)'
        );
        $extraVariables = $request->input('extra_variables', []);
        $tableData = $request->input('table_data', []);

        if (! empty($variables) || ! empty($extraVariables) || ! empty($tableData)) {
            $serverVars = [
                'COMPANY_NAME' => (string) Setting::get('company_name', ''),
                'COMPANY_APP_NAME' => (string) Setting::get('app_name', ''),
                'CURRENT_DATE' => now()->format('d.m.Y'),
            ];
            $variables = array_merge($serverVars, $variables);

            $tempPath = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
            copy($absPath, $tempPath);
            $this->replaceVariablesInDocx($tempPath, $variables, $extraVariables, is_array($tableData) ? $tableData : [], $template);
            $absPath = $tempPath;
        }

        $service = app(DocumentContentService::class);
        $html = $service->extractHtmlFromPath($absPath);

        if ($tempPath) {
            @unlink($tempPath);
        }

        return response()->json(['html' => $html ?? '']);
    }

    /**
     * Generate a live PDF preview of a template with variables filled in.
     * Accepts known variable values + extra_variables for custom placeholders.
     */
    public function preview(Request $request, DocumentTemplate $template): mixed
    {
        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $absPath = Storage::disk('local')->path($template->path);

        $serverVars = [
            'COMPANY_NAME' => (string) Setting::get('company_name', ''),
            'COMPANY_APP_NAME' => (string) Setting::get('app_name', ''),
            'CURRENT_DATE' => now()->format('d.m.Y'),
        ];

        $variables = array_merge($serverVars, array_filter(
            $request->input('variables', []),
            fn ($v) => $v !== '' && $v !== '(from settings)'
        ));
        $extraVariables = $request->input('extra_variables', []);
        $tableData = $request->input('table_data', []);

        $previewDir = storage_path('app/private/preview-cache');
        if (! is_dir($previewDir)) {
            mkdir($previewDir, 0775, true);
        }

        $hash = md5($template->id.json_encode($variables).json_encode($extraVariables).json_encode($tableData));
        $docxPath = "{$previewDir}/preview-{$hash}.docx";
        $pdfPath = "{$previewDir}/preview-{$hash}.pdf";

        if (file_exists($pdfPath) && filemtime($pdfPath) > time() - 30) {
            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview.pdf"',
                'Cache-Control' => 'no-cache',
            ]);
        }

        copy($absPath, $docxPath);

        $this->replaceVariablesInDocx($docxPath, $variables, $extraVariables, is_array($tableData) ? $tableData : [], $template);

        $converter = app(DocToPdfConverter::class);
        $resultPath = $converter->convertFromAbsPath($docxPath, $pdfPath);

        if (! file_exists($resultPath)) {
            \Log::error('Preview: conversion failed', ['template' => $template->id]);

            return response()->json(['message' => 'Could not generate preview.'], 500);
        }

        \Log::info('Preview: generated', ['template' => $template->id, 'size' => filesize($resultPath)]);

        return response()->file($resultPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function previewHtml(Request $request, DocumentTemplate $template): mixed
    {
        $request->validate(['html' => 'required|string']);

        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $absPath = Storage::disk('local')->path($template->path);
        $previewDir = storage_path('app/private/preview-cache');
        if (! is_dir($previewDir)) {
            mkdir($previewDir, 0775, true);
        }

        $hash = md5($template->id . $request->input('html'));
        $docxPath = "{$previewDir}/preview-html-{$hash}.docx";
        $pdfPath = "{$previewDir}/preview-html-{$hash}.pdf";

        if (file_exists($pdfPath) && filemtime($pdfPath) > time() - 30) {
            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview.pdf"',
                'Cache-Control' => 'no-cache',
            ]);
        }

        copy($absPath, $docxPath);

        $htmlConverter = app(HtmlToOoxmlConverter::class);
        $ooxmlBody = $htmlConverter->convert($request->input('html'));
        $images = $htmlConverter->getImages();

        $contentService = app(DocumentContentService::class);
        $contentService->injectIntoDocxPublic($docxPath, $ooxmlBody, $images);

        $pdfConverter = app(DocToPdfConverter::class);
        $resultPath = $pdfConverter->convertFromAbsPath($docxPath, $pdfPath);

        if (! file_exists($resultPath)) {
            return response()->json(['message' => 'Could not generate preview.'], 500);
        }

        return response()->file($resultPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function googleEdit(Request $request, DocumentTemplate $template): JsonResponse
    {
        $drive = app(GoogleDriveService::class);
        if (! $drive->isConfigured()) {
            return response()->json(['message' => 'Google Docs integration is not configured. Set it up in Admin Settings.'], 422);
        }

        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $absPath = Storage::disk('local')->path($template->path);

        $serverVars = [
            'COMPANY_NAME' => (string) Setting::get('company_name', ''),
            'COMPANY_APP_NAME' => (string) Setting::get('app_name', ''),
            'CURRENT_DATE' => now()->format('d.m.Y'),
        ];

        $variables = array_merge($serverVars, array_filter(
            $request->input('variables', []),
            fn ($v) => $v !== '' && $v !== '(from settings)'
        ));
        $extraVariables = $request->input('extra_variables', []);
        $tableData = $request->input('table_data', []);

        $tempPath = tempnam(sys_get_temp_dir(), 'gdocs_') . '.docx';
        copy($absPath, $tempPath);

        if (! empty($variables) || ! empty($extraVariables) || ! empty($tableData)) {
            $this->replaceVariablesInDocx($tempPath, $variables, $extraVariables, is_array($tableData) ? $tableData : [], $template);
        }

        try {
            $result = $drive->uploadDocx($tempPath, $template->name . ' - Edit');
        } finally {
            @unlink($tempPath);
        }

        return response()->json($result);
    }

    public function googleSync(Request $request, DocumentTemplate $template): mixed
    {
        $request->validate([
            'file_id' => 'required|string',
            'delete_after' => 'nullable|boolean',
            'variables' => 'nullable|array',
            'extra_variables' => 'nullable|array',
            'table_data' => 'nullable|array',
        ]);

        $drive = app(GoogleDriveService::class);
        if (! $drive->isConfigured()) {
            return response()->json(['message' => 'Google Docs integration is not configured.'], 422);
        }

        if (! $template->path || ! Storage::disk('local')->exists($template->path)) {
            return response()->json(['message' => 'Template file not found.'], 404);
        }

        $fileId = $request->input('file_id');
        $tableData = $request->input('table_data', []);
        $variables = array_filter(
            $request->input('variables', []),
            fn ($v) => $v !== '' && $v !== '(from settings)'
        );
        $extraVariables = $request->input('extra_variables', []);

        $previewDir = storage_path('app/private/preview-cache');
        if (! is_dir($previewDir)) {
            mkdir($previewDir, 0775, true);
        }

        $uid = $fileId . '-' . substr(md5(microtime(true) . json_encode($tableData)), 0, 8);
        $docxPath = "{$previewDir}/gsync-{$uid}.docx";
        $pdfPath = "{$previewDir}/gsync-{$uid}.pdf";

        $drive->downloadDocx($fileId, $docxPath);

        if (! empty($variables) || ! empty($extraVariables) || ! empty($tableData) || $template->templateTables()->exists()) {
            $this->replaceVariablesInDocx($docxPath, $variables, $extraVariables, is_array($tableData) ? $tableData : [], $template);
        }

        $converter = app(DocToPdfConverter::class);
        $resultPath = $converter->convertFromAbsPath($docxPath, $pdfPath);

        if (! file_exists($resultPath)) {
            return response()->json(['message' => 'Failed to convert synced document to PDF.'], 500);
        }

        if ($request->boolean('delete_after')) {
            $drive->deleteFile($fileId);
        }

        return response()->file($resultPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function replaceVariablesInDocx(string $docxPath, array $variables, array $extraVariables, array $tableData = [], ?DocumentTemplate $template = null): void
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $allReplacements = [];
        foreach ($variables as $key => $value) {
            $allReplacements['{{'.$key.'}}'] = (string) $value;
        }
        foreach ($extraVariables as $key => $value) {
            $allReplacements['{{'.$key.'}}'] = (string) $value;
        }

        foreach (TemplateVariableRegistry::SIGNATURE_KEYS as $sigKey) {
            unset($allReplacements['{{'.$sigKey.'}}']);
        }

        $templateTables = [];
        if ($template) {
            $templateTables = $template->templateTables()->get()->keyBy(fn ($t) => strtoupper($t->shortcode))->toArray();
        }

        $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'];

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

            foreach ($allReplacements as $placeholder => $value) {
                $safeValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml = str_replace($placeholder, $safeValue, $xml);
            }

            $zip->addFromString($xmlFile, $xml);
        }

        $zip->close();
    }

    /**
     * Replace {{TABLE:NAME}} shortcodes with generated OOXML tables.
     * Works even when the shortcode is inside a larger paragraph (e.g. Google Docs
     * puts everything in one <w:p> with <w:br> line breaks).
     */
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
        $phPattern = '/\{\{(?:TABLE:)?[A-Za-z0-9_]+\}\}/';

        return preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function ($match) use ($phPattern) {
                $paragraph = $match[0];

                preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $paragraph, $tm);
                $allText = implode('', array_map(
                    fn ($t) => html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $tm[1]
                ));

                if (! preg_match($phPattern, $allText)) {
                    return $paragraph;
                }

                preg_match_all($phPattern, $allText, $found);
                $allIntact = true;
                foreach (($found[0] ?? []) as $ph) {
                    if (! str_contains($paragraph, $ph)) {
                        $allIntact = false;
                        break;
                    }
                }
                if ($allIntact) {
                    return $paragraph;
                }

                preg_match('/<w:p\b[^>]*>/', $paragraph, $openTag);
                $openingTag = $openTag[0] ?? '<w:p>';

                $inner = substr($paragraph, strlen($openingTag), -strlen('</w:p>'));

                $pPr = '';
                if (preg_match('/^(<w:pPr>.*?<\/w:pPr>)/s', $inner, $pp)) {
                    $pPr = $pp[1];
                    $inner = substr($inner, strlen($pPr));
                }

                $chunks = preg_split('/(<w:r\b(?:\s[^>]*)?>.*?<\/w:r>)/s', $inner, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                $result = [];
                $buf = [];
                $bufText = '';

                foreach ($chunks as $chunk) {
                    $isTextRun = preg_match('/^<w:r\b/', $chunk)
                        && preg_match('/<w:t[^>]*>([^<]*)<\/w:t>/u', $chunk, $cm)
                        && ! preg_match('/<w:br\b/', $chunk);

                    if ($isTextRun) {
                        $text = html_entity_decode($cm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $buf[] = $chunk;
                        $bufText .= $text;

                        if (substr_count($bufText, '{{') <= substr_count($bufText, '}}')) {
                            $this->flushRunBuffer($result, $buf, $bufText, $phPattern);
                            $buf = [];
                            $bufText = '';
                        }
                    } else {
                        if (! empty($buf)) {
                            $this->flushRunBuffer($result, $buf, $bufText, $phPattern);
                            $buf = [];
                            $bufText = '';
                        }
                        $result[] = $chunk;
                    }
                }

                if (! empty($buf)) {
                    $this->flushRunBuffer($result, $buf, $bufText, $phPattern);
                }

                return $openingTag.$pPr.implode('', $result).'</w:p>';
            },
            $xml
        ) ?? $xml;
    }

    private function flushRunBuffer(array &$result, array $buffer, string $text, string $pattern): void
    {
        if (count($buffer) > 1 && preg_match($pattern, $text)) {
            $safe = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $result[] = '<w:r><w:t xml:space="preserve">'.$safe.'</w:t></w:r>';
        } else {
            array_push($result, ...$buffer);
        }
    }
}

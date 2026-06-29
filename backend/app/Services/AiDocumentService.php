<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiDocumentService
{
    public function analyzeDocument(TaskDocument $document): array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return ['status' => 'disabled', 'message' => 'OpenAI API key not configured.'];
        }

        $text = $this->extractText($document);
        if (! $text) {
            return ['status' => 'error', 'message' => 'Could not extract text from document.'];
        }

        $prompt = "Analyze this legal/business document. Identify:\n"
            . "1. Key terms and obligations\n"
            . "2. Potential risks or unusual clauses\n"
            . "3. Missing standard clauses\n"
            . "4. Financial terms summary\n"
            . "5. Compliance concerns\n\n"
            . "Document text:\n" . mb_substr($text, 0, 12000);

        return $this->callOpenAi($prompt, 'document_analysis');
    }

    public function compareDocuments(TaskDocument $original, TaskDocument $signed): array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return ['status' => 'disabled', 'message' => 'OpenAI API key not configured.'];
        }

        $textOriginal = $this->extractText($original);
        $textSigned = $this->extractText($signed);

        if (! $textOriginal || ! $textSigned) {
            return ['status' => 'error', 'message' => 'Could not extract text from one or both documents.'];
        }

        $prompt = "Compare these two versions of a document. The first is the approved version, the second is the signed version.\n"
            . "Identify any differences, additions, or removals. Flag any changes that could be concerning.\n\n"
            . "APPROVED VERSION:\n" . mb_substr($textOriginal, 0, 6000) . "\n\n"
            . "SIGNED VERSION:\n" . mb_substr($textSigned, 0, 6000);

        return $this->callOpenAi($prompt, 'document_comparison');
    }

    public function validateDocument(TaskDocument $document, string $templateName = ''): array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return ['status' => 'disabled', 'message' => 'OpenAI API key not configured.'];
        }

        $text = $this->extractText($document);
        if (! $text) {
            return ['status' => 'error', 'message' => 'Could not extract text from document.'];
        }

        $prompt = "Validate this business document" . ($templateName ? " (template: {$templateName})" : "") . ".\n"
            . "Check for:\n"
            . "1. Completeness - are all standard fields filled?\n"
            . "2. Date consistency - are dates logical?\n"
            . "3. Amount formatting - are financial values properly stated?\n"
            . "4. Legal language accuracy\n"
            . "5. Missing signatures or placeholders\n\n"
            . "Document text:\n" . mb_substr($text, 0, 12000);

        return $this->callOpenAi($prompt, 'document_validation');
    }

    private function callOpenAi(string $prompt, string $type): array
    {
        $apiKey = $this->getApiKey();
        $model = Setting::get('openai_model', 'gpt-4o');

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a legal document analyst for a corporate document management system. Respond in structured JSON format with keys: summary, findings (array of {type, severity, description}), recommendations (array of strings).'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 2000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content', '{}');
                $parsed = json_decode($content, true) ?: ['raw' => $content];

                return [
                    'status' => 'success',
                    'type' => $type,
                    'analysis' => $parsed,
                    'model' => $model,
                ];
            }

            Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['status' => 'error', 'message' => 'AI service returned status ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'AI service error: ' . $e->getMessage()];
        }
    }

    private function extractText(TaskDocument $document): ?string
    {
        $absPath = Storage::disk('local')->path($document->path);

        // Try the companion DOCX first (always has extractable text)
        $docxPath = preg_replace('/\.pdf$/i', '.docx', $absPath);
        if ($docxPath !== $absPath && file_exists($docxPath)) {
            $text = $this->extractDocxText($docxPath);
            if ($text && strlen(trim($text)) > 50) {
                return $text;
            }
        }

        if (! file_exists($absPath)) {
            Log::debug('AI extract: file not found', ['path' => $absPath]);
            return null;
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $this->extractPdfText($absPath);
        }

        if (in_array($ext, ['docx', 'doc'])) {
            return $this->extractDocxText($absPath);
        }

        return null;
    }

    private function extractPdfText(string $path): ?string
    {
        // Method 1: smalot/pdfparser (if installed)
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $text = $pdf->getText();
                if ($text && strlen(trim($text)) > 50) {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::debug('smalot/pdfparser failed', ['error' => $e->getMessage()]);
            }
        }

        // Method 2: pdftotext CLI (poppler-utils)
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
        if ($pdftotext) {
            try {
                $tmpFile = tempnam(sys_get_temp_dir(), 'pdftext');
                exec(escapeshellcmd($pdftotext) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($tmpFile) . ' 2>/dev/null', $output, $code);
                if ($code === 0 && file_exists($tmpFile)) {
                    $text = file_get_contents($tmpFile);
                    @unlink($tmpFile);
                    if ($text && strlen(trim($text)) > 50) {
                        return $text;
                    }
                }
                @unlink($tmpFile);
            } catch (\Throwable $e) {
                Log::debug('pdftotext failed', ['error' => $e->getMessage()]);
            }
        }

        // Method 3: Try companion DOCX
        $docxPath = preg_replace('/\.pdf$/i', '.docx', $path);
        if ($docxPath !== $path && file_exists($docxPath)) {
            return $this->extractDocxText($docxPath);
        }

        Log::debug('PDF text extraction: all methods failed', ['path' => $path]);
        return null;
    }

    private function extractDocxText(string $path): ?string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if (! $xml) return null;

            $text = strip_tags($xml);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        } catch (\Throwable $e) {
            Log::debug('DOCX text extraction failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function getApiKey(): ?string
    {
        $key = Setting::get('openai_api_key', '');
        return $key && strlen($key) > 10 ? $key : null;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class AiChatController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:2000',
        ]);

        $service = app(AiChatService::class);

        if (! $service->isAvailable()) {
            return response()->json([
                'reply' => 'AI assistant is not configured. An administrator needs to set the OpenAI API key in System Settings.',
            ], 422);
        }

        $result = $service->chat(
            $request->user(),
            $request->input('message'),
            $request->input('history', []),
        );

        return response()->json(['reply' => $result['reply']]);
    }

    public function generateDocument(Request $request): mixed
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:50000',
        ]);

        $title = $request->input('title');
        $content = $request->input('content');

        $phpWord = new PhpWord();

        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $phpWord->addParagraphStyle('Heading1', ['alignment' => Jc::CENTER, 'spaceBefore' => 240, 'spaceAfter' => 120]);
        $phpWord->addParagraphStyle('Heading2', ['spaceBefore' => 200, 'spaceAfter' => 80]);
        $phpWord->addParagraphStyle('Normal', ['spaceBefore' => 60, 'spaceAfter' => 60, 'lineHeight' => 1.15]);
        $phpWord->addFontStyle('Heading1Font', ['bold' => true, 'size' => 16]);
        $phpWord->addFontStyle('Heading2Font', ['bold' => true, 'size' => 13]);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        $lines = preg_split('/\r?\n/', $content);
        $i = 0;
        $lineCount = count($lines);

        while ($i < $lineCount) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $i++;
                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                $text = ltrim($trimmed, '# ');
                $section->addText($text, 'Heading2Font', 'Heading2');
                $i++;
                continue;
            }

            if (str_starts_with($trimmed, '# ')) {
                $text = ltrim($trimmed, '# ');
                $section->addText($text, 'Heading1Font', 'Heading1');
                $i++;
                continue;
            }

            if (preg_match('/^\d+\.\s+/', $trimmed)) {
                $listText = preg_replace('/^\d+\.\s+/', '', $trimmed);
                $listText = $this->stripMarkdownInline($listText);
                $section->addListItem($listText, 0, null, 'Normal');
                $i++;
                continue;
            }

            if (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
                $listText = ltrim($trimmed, '-* ');
                $listText = $this->stripMarkdownInline($listText);
                $section->addListItem($listText, 0, null, 'Normal');
                $i++;
                continue;
            }

            if ($trimmed === '---' || $trimmed === '***') {
                $i++;
                continue;
            }

            $this->addFormattedParagraph($section, $trimmed);
            $i++;
        }

        $tmpDir = storage_path('app/private/ai-documents');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $filename = str($title)->slug() . '-' . time() . '.docx';
        $filepath = "{$tmpDir}/{$filename}";

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return response()->download($filepath, "{$title}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    private function addFormattedParagraph($section, string $text): void
    {
        $textRun = $section->addTextRun('Normal');
        $parts = preg_split('/(\*\*[^*]+\*\*|\*[^*]+\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $m)) {
                $textRun->addText($m[1], ['bold' => true]);
            } elseif (preg_match('/^\*(.+)\*$/', $part, $m)) {
                $textRun->addText($m[1], ['italic' => true]);
            } else {
                $textRun->addText($part);
            }
        }
    }

    private function stripMarkdownInline(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);

        return $text;
    }
}

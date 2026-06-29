<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class SignatureStamper
{
    /**
     * Replace a sign placeholder in the source DOCX, then convert to PDF via LibreOffice.
     * Falls back to stampPdf() at bottom-right if DOCX is missing or has no placeholder.
     */
    public function stampAtPlaceholderAndConvert(
        string $pdfPath,
        string $signaturePngPath,
        string $placeholder
    ): ?string {
        return $this->stampPdf($pdfPath, $signaturePngPath, $placeholder);
    }

    /**
     * Stamp signature PNG onto a PDF at the placeholder text position,
     * or bottom-right of the last page as a fallback.
     */
    public function stampPdf(string $pdfPath, string $signaturePngPath, string $placeholder = ''): ?string
    {
        try {
            $targetPage = null;
            $targetX = null;
            $targetY = null;

            if ($placeholder) {
                $pos = $this->findTextPositionInPdf($pdfPath, $placeholder);

                if (! $pos || ($pos['x'] === null && $pos['y'] === null)) {
                    $cleanPath = preg_replace('/-signed[^.]*\.pdf$/i', '.pdf', $pdfPath);
                    if ($cleanPath !== $pdfPath && file_exists($cleanPath)) {
                        $fallbackPos = $this->findTextPositionInPdf($cleanPath, $placeholder);
                        if ($fallbackPos && $fallbackPos['x'] !== null) {
                            $pos = $fallbackPos;
                        }
                    }
                }

                if ($pos) {
                    $targetPage = $pos['page'];
                    $targetX = $pos['x'];
                    $targetY = $pos['y'];
                }
            }

            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);

            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

                $shouldStamp = $targetPage ? ($i === $targetPage) : ($i === $pageCount);

                if ($shouldStamp) {
                    $sigWidth = 45;
                    $sigHeight = 18;

                    if ($targetX !== null && $targetY !== null) {
                        $x = max(0, $targetX);
                        $textHeight = 4;
                        $y = max(0, $targetY - $sigHeight + $textHeight);

                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->Rect($targetX - 1, $targetY - $textHeight - 1, 55, $textHeight + 3, 'F');
                    } else {
                        $margin = 15;
                        $x = $size['width'] - $sigWidth - $margin;
                        $y = $size['height'] - $sigHeight - $margin;
                    }

                    $pdf->Image($signaturePngPath, $x, $y, $sigWidth, $sigHeight);
                }
            }

            $stampedPath = $this->stampedFilePath($pdfPath, 'pdf');
            $pdf->Output($stampedPath, 'F');

            return $stampedPath;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('stampPdf failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Find approximate position of text in a PDF using smalot/pdfparser.
     * Returns ['page' => int, 'x' => float, 'y' => float] or null.
     */
    private function findTextPositionInPdf(string $pdfPath, string $searchText): ?array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $document = $parser->parseFile($pdfPath);
            $pages = $document->getPages();

            $cleanSearch = str_replace(['{{', '}}'], '', $searchText);

            foreach ($pages as $pageIdx => $page) {
                $text = $page->getText();
                if (! str_contains($text, $searchText) && ! str_contains($text, $cleanSearch)) {
                    continue;
                }

                $dataTm = null;
                try {
                    $dataTm = $page->getDataTm();
                } catch (\Throwable) {
                    // getDataTm can fail on FPDI-generated PDFs with embedded Image XObjects
                }

                if (is_array($dataTm)) {
                    foreach ($dataTm as $item) {
                        $itemText = $item[1] ?? '';
                        if (str_contains($itemText, $searchText) || str_contains($itemText, $cleanSearch)) {
                            return $this->convertPdfCoords($item[0] ?? [], $page, $pageIdx);
                        }
                    }
                }

                return ['page' => $pageIdx + 1, 'x' => null, 'y' => null];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('findTextPositionInPdf failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function convertPdfCoords(array $coords, $page, int $pageIdx): array
    {
        $pdfX = (float) ($coords[4] ?? 0);
        $pdfY = (float) ($coords[5] ?? 0);

        $pageDetails = $page->getDetails();
        $mediaBox = $pageDetails['MediaBox'] ?? null;
        $pageHeight = 842;
        if (is_array($mediaBox) && isset($mediaBox[3])) {
            $pageHeight = (float) $mediaBox[3];
        }

        return [
            'page' => $pageIdx + 1,
            'x' => $pdfX * 25.4 / 72,
            'y' => ($pageHeight - $pdfY) * 25.4 / 72,
        ];
    }

    /**
     * Stamp signature PNG at the bottom of a DOCX document (fallback).
     */
    public function stampDocx(string $docxPath, string $signaturePngPath): ?string
    {
        try {
            $phpWord = WordIOFactory::load($docxPath, 'Word2007');

            $sections = $phpWord->getSections();
            $lastSection = end($sections);

            if (! $lastSection) {
                return null;
            }

            $lastSection->addTextBreak(1);
            $lastSection->addImage($signaturePngPath, [
                'width' => 150,
                'height' => 60,
                'alignment' => Jc::END,
                'wrappingStyle' => 'inline',
            ]);
            $lastSection->addText(
                'Electronically signed on '.now()->format('d M Y, H:i'),
                ['size' => 8, 'color' => '666666', 'italic' => true],
                ['alignment' => Jc::END]
            );

            $stampedPath = $this->stampedFilePath($docxPath, 'docx');

            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($stampedPath);

            return $stampedPath;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Replace a {{PLACEHOLDER}} in a DOCX with an inline signature image.
     * Returns the path to the new stamped DOCX, or null on failure.
     */
    public function stampAtPlaceholder(string $docxPath, string $signaturePngPath, string $placeholder): ?string
    {
        try {
            $stampedPath = $this->stampedFilePath($docxPath, 'docx', $placeholder);
            copy($docxPath, $stampedPath);

            $zip = new ZipArchive();
            if ($zip->open($stampedPath) !== true) {
                return null;
            }

            $imageData = file_get_contents($signaturePngPath);
            if ($imageData === false) {
                $zip->close();
                return null;
            }

            $imageInfo = @getimagesize($signaturePngPath);
            $imgWidthPx = $imageInfo[0] ?? 300;
            $imgHeightPx = $imageInfo[1] ?? 120;

            $maxWidthEmu = 1905000; // ~2 inches = 50mm
            $scale = $maxWidthEmu / max($imgWidthPx, 1);
            $cxEmu = (int) ($imgWidthPx * $scale);
            $cyEmu = (int) ($imgHeightPx * $scale);

            $mediaName = 'image_sig_'.md5($placeholder).'.png';
            $zip->addFromString("word/media/{$mediaName}", $imageData);

            $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
            if ($relsXml === false) {
                $zip->close();
                return null;
            }

            $rId = 'rIdSig' . abs(crc32($placeholder));
            $newRel = '<Relationship Id="'.$rId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$mediaName.'"/>';
            $relsXml = str_replace('</Relationships>', $newRel.'</Relationships>', $relsXml);
            $zip->addFromString('word/_rels/document.xml.rels', $relsXml);

            $contentTypes = $zip->getFromName('[Content_Types].xml');
            if ($contentTypes && strpos($contentTypes, 'Extension="png"') === false) {
                $contentTypes = str_replace(
                    '</Types>',
                    '<Default Extension="png" ContentType="image/png"/></Types>',
                    $contentTypes
                );
                $zip->addFromString('[Content_Types].xml', $contentTypes);
            }

            $drawingXml = $this->buildInlineImageXml($rId, $cxEmu, $cyEmu, $placeholder);

            $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'];

            foreach ($xmlFiles as $xmlFile) {
                $xml = $zip->getFromName($xmlFile);
                if ($xml === false) {
                    continue;
                }

                $xml = $this->cleanPlaceholderRuns($xml, $placeholder);

                $escapedPlaceholder = htmlspecialchars($placeholder, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                if (strpos($xml, $placeholder) !== false || strpos($xml, $escapedPlaceholder) !== false) {
                    $runPattern = '/<w:r\b[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t[^>]*>[^<]*'.preg_quote($escapedPlaceholder, '/').'[^<]*<\/w:t><\/w:r>/s';
                    $xml = preg_replace($runPattern, $drawingXml, $xml, 1);

                    if (strpos($xml, $escapedPlaceholder) !== false) {
                        $xml = str_replace($escapedPlaceholder, '</w:t></w:r>'.$drawingXml.'<w:r><w:t xml:space="preserve">', $xml);
                    }
                    if (strpos($xml, $placeholder) !== false) {
                        $xml = str_replace($placeholder, '</w:t></w:r>'.$drawingXml.'<w:r><w:t xml:space="preserve">', $xml);
                    }

                    $zip->addFromString($xmlFile, $xml);
                }
            }

            $zip->close();

            return $stampedPath;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check if a DOCX contains a specific placeholder text.
     */
    public function docxHasPlaceholder(string $docxPath, string $placeholder): bool
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($docxPath, ZipArchive::RDONLY) !== true) {
                return false;
            }
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml === false) {
                return false;
            }
            $plainText = strip_tags($xml);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_XML1, 'UTF-8');

            return str_contains($plainText, $placeholder);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Merge runs that have a split placeholder across multiple <w:r> elements.
     * Processes each paragraph independently to avoid corrupting the rest of the document.
     */
    private function cleanPlaceholderRuns(string $xml, string $placeholder): string
    {
        $escapedInXml = htmlspecialchars($placeholder, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if (str_contains($xml, $escapedInXml)) {
            return $xml;
        }

        return preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function ($match) use ($placeholder, $escapedInXml) {
                $paragraph = $match[0];

                if (str_contains($paragraph, $escapedInXml)) {
                    return $paragraph;
                }

                preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $paragraph, $textMatches);
                $texts = array_map(
                    fn ($t) => html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $textMatches[1]
                );
                $combinedText = implode('', $texts);

                if (! str_contains($combinedText, $placeholder)) {
                    return $paragraph;
                }

                $pPr = '';
                if (preg_match('/<w:pPr>.*?<\/w:pPr>/s', $paragraph, $pPrMatch)) {
                    $pPr = $pPrMatch[0];
                }

                preg_match('/<w:p\b[^>]*>/', $paragraph, $openTag);
                $openingTag = $openTag[0] ?? '<w:p>';

                return $openingTag.$pPr
                    .'<w:r><w:t xml:space="preserve">'
                    .htmlspecialchars($combinedText, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</w:t></w:r></w:p>';
            },
            $xml
        ) ?? $xml;
    }

    private function buildInlineImageXml(string $rId, int $cx, int $cy, string $name): string
    {
        $safeName = 'Signature_'.md5($name);
        $uniqueId = abs(crc32($name));

        return '<w:r>'
            .'<w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:docPr id="'.$uniqueId.'" name="'.$safeName.'"/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr>'
            .'<pic:cNvPr id="'.$uniqueId.'" name="'.$safeName.'"/>'
            .'<pic:cNvPicPr/>'
            .'</pic:nvPicPr>'
            .'<pic:blipFill>'
            .'<a:blip r:embed="'.$rId.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            .'<a:stretch><a:fillRect/></a:stretch>'
            .'</pic:blipFill>'
            .'<pic:spPr>'
            .'<a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            .'</pic:spPr>'
            .'</pic:pic>'
            .'</a:graphicData>'
            .'</a:graphic>'
            .'</wp:inline>'
            .'</w:drawing>'
            .'</w:r>';
    }

    private function stampedFilePath(string $originalPath, string $ext, string $suffix = ''): string
    {
        $dir = pathinfo($originalPath, PATHINFO_DIRNAME);
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $tag = $suffix ? '-'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $suffix)) : '';

        return $dir.DIRECTORY_SEPARATOR.$name.'-signed'.$tag.'.'.$ext;
    }
}

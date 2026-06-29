<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use ZipArchive;

class HtmlToOoxmlConverter
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const PIC_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    private array $images = [];
    private int $imageCounter = 0;
    private int $relIdCounter = 100;

    public function convert(string $html): string
    {
        $this->images = [];
        $this->imageCounter = 0;
        $this->relIdCounter = 100;

        $html = '<div>' . $html . '</div>';
        $dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        $ooxml = '';
        $root = $dom->documentElement;
        if ($root) {
            $ooxml = $this->processChildren($root);
        }

        if (trim($ooxml) === '') {
            $ooxml = '<w:p><w:r><w:t> </w:t></w:r></w:p>';
        }

        return $ooxml;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    private function processChildren(DOMNode $parent): string
    {
        $ooxml = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $ooxml .= $this->processElement($node);
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                $text = $node->textContent;
                if (trim($text) !== '') {
                    $ooxml .= $this->makeParagraph($this->makeRun($text));
                }
            }
        }
        return $ooxml;
    }

    private function processElement(DOMElement $el): string
    {
        $tag = strtolower($el->nodeName);

        return match (true) {
            in_array($tag, ['p', 'div']) => $this->handleParagraph($el),
            in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) => $this->handleHeading($el, $tag),
            $tag === 'table' => $this->handleTable($el),
            $tag === 'ul' => $this->handleList($el, false),
            $tag === 'ol' => $this->handleList($el, true),
            $tag === 'blockquote' => $this->handleBlockquote($el),
            $tag === 'hr' => $this->handleHorizontalRule(),
            $tag === 'br' => '<w:p><w:r><w:t> </w:t></w:r></w:p>',
            $tag === 'img' => $this->handleImage($el),
            default => $this->processChildren($el),
        };
    }

    private function handleParagraph(DOMElement $el): string
    {
        $pPr = $this->extractParagraphProps($el);
        $runs = $this->extractRuns($el);

        if (trim($runs) === '') {
            $runs = '<w:r><w:t> </w:t></w:r>';
        }

        return "<w:p>{$pPr}{$runs}</w:p>";
    }

    private function handleHeading(DOMElement $el, string $tag): string
    {
        $level = (int) substr($tag, 1);
        $styleId = 'Heading' . $level;
        $pPr = '<w:pPr><w:pStyle w:val="' . $styleId . '"/>';

        $align = $this->getAlignFromStyle($el);
        if ($align) {
            $pPr .= '<w:jc w:val="' . $align . '"/>';
        }
        $pPr .= '</w:pPr>';

        $runs = $this->extractRuns($el);
        if (trim($runs) === '') {
            $runs = '<w:r><w:t> </w:t></w:r>';
        }

        return "<w:p>{$pPr}{$runs}</w:p>";
    }

    private function handleTable(DOMElement $table): string
    {
        $xml = '<w:tbl>';
        $xml .= '<w:tblPr>';
        $xml .= '<w:tblStyle w:val="TableGrid"/>';
        $xml .= '<w:tblW w:w="0" w:type="auto"/>';
        $xml .= '<w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $xml .= '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
        }
        $xml .= '</w:tblBorders>';
        $xml .= '<w:tblLook w:val="04A0"/>';
        $xml .= '</w:tblPr>';

        $tbody = $this->findChild($table, 'tbody') ?? $table;
        $thead = $this->findChild($table, 'thead');

        if ($thead) {
            foreach ($thead->childNodes as $tr) {
                if ($tr instanceof DOMElement && strtolower($tr->nodeName) === 'tr') {
                    $xml .= $this->handleTableRow($tr, true);
                }
            }
        }

        foreach ($tbody->childNodes as $tr) {
            if ($tr instanceof DOMElement && strtolower($tr->nodeName) === 'tr') {
                $xml .= $this->handleTableRow($tr, false);
            }
        }

        $xml .= '</w:tbl>';
        return $xml;
    }

    private function handleTableRow(DOMElement $tr, bool $isHeader): string
    {
        $xml = '<w:tr>';
        foreach ($tr->childNodes as $cell) {
            if (!($cell instanceof DOMElement)) continue;
            $cellTag = strtolower($cell->nodeName);
            if (!in_array($cellTag, ['td', 'th'])) continue;

            $xml .= '<w:tc>';
            $xml .= '<w:tcPr><w:tcW w:w="0" w:type="auto"/>';

            $colspan = $cell->getAttribute('colspan');
            if ($colspan && (int) $colspan > 1) {
                $xml .= '<w:gridSpan w:val="' . (int) $colspan . '"/>';
            }

            if ($isHeader || $cellTag === 'th') {
                $xml .= '<w:shd w:val="clear" w:color="auto" w:fill="E7E6E6"/>';
            }
            $xml .= '</w:tcPr>';

            $cellContent = $this->processCellContent($cell);
            if (trim($cellContent) === '') {
                $cellContent = '<w:p><w:r><w:t> </w:t></w:r></w:p>';
            }
            $xml .= $cellContent;
            $xml .= '</w:tc>';
        }
        $xml .= '</w:tr>';
        return $xml;
    }

    private function processCellContent(DOMElement $cell): string
    {
        $xml = '';
        $hasParagraph = false;

        foreach ($cell->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->nodeName);
                if (in_array($tag, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $xml .= $this->processElement($node);
                    $hasParagraph = true;
                } elseif ($tag === 'table') {
                    $xml .= $this->handleTable($node);
                    $hasParagraph = true;
                } elseif ($tag === 'ul' || $tag === 'ol') {
                    $xml .= $this->processElement($node);
                    $hasParagraph = true;
                } else {
                    $runs = $this->elementToRuns($node);
                    if ($runs) {
                        $xml .= $this->makeParagraph($runs);
                        $hasParagraph = true;
                    }
                }
            } elseif ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') {
                $xml .= $this->makeParagraph($this->makeRun($node->textContent));
                $hasParagraph = true;
            }
        }

        if (!$hasParagraph) {
            $xml = '<w:p><w:r><w:t> </w:t></w:r></w:p>';
        }

        return $xml;
    }

    private function handleList(DOMElement $list, bool $ordered): string
    {
        $xml = '';
        $index = 1;
        foreach ($list->childNodes as $li) {
            if (!($li instanceof DOMElement) || strtolower($li->nodeName) !== 'li') continue;

            $marker = $ordered ? ($index++ . '.') : "\u{2022}";
            $runs = $this->extractRuns($li);
            if (trim($runs) === '') {
                $runs = '<w:r><w:t> </w:t></w:r>';
            }

            $pPr = '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>';
            $markerRun = '<w:r><w:t xml:space="preserve">' . $this->esc($marker) . ' </w:t></w:r>';
            $xml .= "<w:p>{$pPr}{$markerRun}{$runs}</w:p>";

            foreach ($li->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $childTag = strtolower($child->nodeName);
                    if ($childTag === 'ul') {
                        $xml .= $this->handleNestedList($child, false, 2);
                    } elseif ($childTag === 'ol') {
                        $xml .= $this->handleNestedList($child, true, 2);
                    }
                }
            }
        }
        return $xml;
    }

    private function handleNestedList(DOMElement $list, bool $ordered, int $level): string
    {
        $xml = '';
        $index = 1;
        $indent = $level * 360;
        foreach ($list->childNodes as $li) {
            if (!($li instanceof DOMElement) || strtolower($li->nodeName) !== 'li') continue;

            $marker = $ordered ? ($index++ . '.') : "\u{2013}";
            $runs = $this->extractRuns($li);

            $pPr = '<w:pPr><w:ind w:left="' . ($indent + 360) . '" w:hanging="360"/></w:pPr>';
            $markerRun = '<w:r><w:t xml:space="preserve">' . $this->esc($marker) . ' </w:t></w:r>';
            $xml .= "<w:p>{$pPr}{$markerRun}{$runs}</w:p>";
        }
        return $xml;
    }

    private function handleBlockquote(DOMElement $el): string
    {
        $xml = '';
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->nodeName);
                if ($tag === 'p') {
                    $pPr = '<w:pPr><w:ind w:left="720"/><w:pBdr><w:left w:val="single" w:sz="18" w:space="4" w:color="CCCCCC"/></w:pBdr></w:pPr>';
                    $runs = $this->extractRuns($node);
                    $xml .= "<w:p>{$pPr}{$runs}</w:p>";
                } else {
                    $xml .= $this->processElement($node);
                }
            } elseif ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') {
                $pPr = '<w:pPr><w:ind w:left="720"/></w:pPr>';
                $xml .= "<w:p>{$pPr}" . $this->makeRun($node->textContent) . '</w:p>';
            }
        }
        return $xml;
    }

    private function handleHorizontalRule(): string
    {
        return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="auto"/></w:pBdr></w:pPr></w:p>';
    }

    private function handleImage(DOMElement $img): string
    {
        $src = $img->getAttribute('src');
        if (!$src) return '';

        $imageData = null;
        $mime = 'image/png';
        $ext = 'png';

        if (preg_match('/^data:(image\/\w+);base64,(.+)$/s', $src, $m)) {
            $mime = $m[1];
            $imageData = base64_decode($m[2], true);
            $ext = match ($mime) {
                'image/jpeg', 'image/jpg' => 'jpeg',
                'image/gif' => 'gif',
                default => 'png',
            };
        }

        if (!$imageData) return '';

        $this->imageCounter++;
        $relId = 'rId' . ($this->relIdCounter++);
        $filename = 'image' . $this->imageCounter . '.' . $ext;

        $this->images[] = [
            'relId' => $relId,
            'filename' => $filename,
            'data' => $imageData,
            'mime' => $mime,
        ];

        $widthEmu = 5000000;
        $heightEmu = 3500000;

        if ($imageData) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmpFile, $imageData);
            $info = @getimagesize($tmpFile);
            @unlink($tmpFile);
            if ($info) {
                $w = $info[0];
                $h = $info[1];
                $maxWidthEmu = 5500000;
                $emuPerPx = 9525;
                $widthEmu = min($w * $emuPerPx, $maxWidthEmu);
                $heightEmu = (int) ($h * ($widthEmu / ($w * $emuPerPx)) * $h * $emuPerPx / $h);
                $heightEmu = (int) ($widthEmu * $h / $w);
            }
        }

        $cx = $widthEmu;
        $cy = $heightEmu;
        $drawingId = $this->imageCounter;

        return '<w:p><w:r><w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $drawingId . '" name="Picture ' . $drawingId . '"/>'
            . '<a:graphic xmlns:a="' . self::A_NS . '">'
            . '<a:graphicData uri="' . self::PIC_NS . '">'
            . '<pic:pic xmlns:pic="' . self::PIC_NS . '">'
            . '<pic:nvPicPr><pic:cNvPr id="' . $drawingId . '" name="image' . $drawingId . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $relId . '" xmlns:r="' . self::R_NS . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic>'
            . '</a:graphicData>'
            . '</a:graphic>'
            . '</wp:inline>'
            . '</w:drawing></w:r></w:p>';
    }

    // --- Run extraction ---

    private function extractRuns(DOMElement $el): string
    {
        $runs = '';
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->nodeName);
                if (in_array($tag, ['ul', 'ol', 'table', 'div', 'blockquote'])) {
                    continue;
                }
                if ($tag === 'br') {
                    $runs .= '<w:r><w:br/></w:r>';
                    continue;
                }
                if ($tag === 'img') {
                    continue;
                }
                $runs .= $this->elementToRuns($node);
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                $text = $node->textContent;
                if ($text !== '') {
                    $runs .= $this->makeRun($text);
                }
            }
        }
        return $runs;
    }

    private function elementToRuns(DOMElement $el, array $parentProps = []): string
    {
        $tag = strtolower($el->nodeName);
        $props = $parentProps;

        if (in_array($tag, ['strong', 'b'])) $props['bold'] = true;
        if (in_array($tag, ['em', 'i'])) $props['italic'] = true;
        if ($tag === 'u') $props['underline'] = true;
        if (in_array($tag, ['s', 'del', 'strike'])) $props['strike'] = true;
        if ($tag === 'sub') $props['vertAlign'] = 'subscript';
        if ($tag === 'sup') $props['vertAlign'] = 'superscript';
        if ($tag === 'code') $props['font'] = 'Courier New';

        if ($tag === 'span' || $tag === 'mark') {
            $style = $el->getAttribute('style');
            if ($style) {
                if (preg_match('/font-weight\s*:\s*bold/i', $style)) $props['bold'] = true;
                if (preg_match('/font-style\s*:\s*italic/i', $style)) $props['italic'] = true;
                if (preg_match('/text-decoration\s*:\s*underline/i', $style)) $props['underline'] = true;
                if (preg_match('/font-size\s*:\s*([\d.]+)\s*pt/i', $style, $m)) $props['fontSize'] = (float) $m[1];
                if (preg_match('/font-size\s*:\s*([\d.]+)\s*px/i', $style, $m)) $props['fontSize'] = round((float) $m[1] * 0.75, 1);
                if (preg_match('/(?<![a-z-])color\s*:\s*#([0-9a-fA-F]{3,6})/i', $style, $m)) $props['color'] = $this->normalizeHex($m[1]);
                if (preg_match('/background-color\s*:\s*#([0-9a-fA-F]{3,6})/i', $style, $m)) $props['highlight'] = $this->normalizeHex($m[1]);
            }
            $dataColor = $el->getAttribute('data-color');
            if ($dataColor && preg_match('/^#?([0-9a-fA-F]{3,6})$/', $dataColor, $m)) {
                $props['color'] = $this->normalizeHex($m[1]);
            }
        }

        if ($tag === 'mark') {
            if (empty($props['highlight'])) {
                $props['highlight'] = 'FFFF00';
            }
        }

        $runs = '';
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $childTag = strtolower($node->nodeName);
                if ($childTag === 'br') {
                    $runs .= '<w:r><w:br/></w:r>';
                } else {
                    $runs .= $this->elementToRuns($node, $props);
                }
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                $text = $node->textContent;
                if ($text !== '') {
                    $runs .= $this->makeRunWithProps($text, $props);
                }
            }
        }

        return $runs;
    }

    // --- Helpers ---

    private function extractParagraphProps(DOMElement $el): string
    {
        $parts = [];
        $align = $this->getAlignFromStyle($el);
        if ($align) {
            $parts[] = '<w:jc w:val="' . $align . '"/>';
        }

        $style = $el->getAttribute('style') ?: '';
        if (preg_match('/margin-left\s*:\s*([\d.]+)\s*pt/i', $style, $m)) {
            $twips = (int) round((float) $m[1] * 20);
            $parts[] = '<w:ind w:left="' . $twips . '"/>';
        }
        if (preg_match('/text-indent\s*:\s*([\d.]+)\s*pt/i', $style, $m)) {
            $twips = (int) round((float) $m[1] * 20);
            $parts[] = '<w:ind w:firstLine="' . $twips . '"/>';
        }

        if (empty($parts)) return '';
        return '<w:pPr>' . implode('', $parts) . '</w:pPr>';
    }

    private function getAlignFromStyle(DOMElement $el): ?string
    {
        $style = $el->getAttribute('style') ?: '';
        if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $m)) {
            return $m[1] === 'justify' ? 'both' : strtolower($m[1]);
        }
        return null;
    }

    private function makeRun(string $text, array $props = []): string
    {
        return $this->makeRunWithProps($text, $props);
    }

    private function makeRunWithProps(string $text, array $props = []): string
    {
        $rPr = $this->buildRunProps($props);
        $escaped = $this->esc($text);
        return '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $escaped . '</w:t></w:r>';
    }

    private function buildRunProps(array $props): string
    {
        if (empty($props)) return '';

        $parts = [];
        if (!empty($props['font'])) $parts[] = '<w:rFonts w:ascii="' . $this->esc($props['font']) . '" w:hAnsi="' . $this->esc($props['font']) . '"/>';
        if (!empty($props['bold'])) $parts[] = '<w:b/>';
        if (!empty($props['italic'])) $parts[] = '<w:i/>';
        if (!empty($props['underline'])) $parts[] = '<w:u w:val="single"/>';
        if (!empty($props['strike'])) $parts[] = '<w:strike/>';
        if (!empty($props['vertAlign'])) $parts[] = '<w:vertAlign w:val="' . $props['vertAlign'] . '"/>';
        if (!empty($props['fontSize'])) {
            $half = (int) round($props['fontSize'] * 2);
            $parts[] = '<w:sz w:val="' . $half . '"/><w:szCs w:val="' . $half . '"/>';
        }
        if (!empty($props['color'])) {
            $parts[] = '<w:color w:val="' . $props['color'] . '"/>';
        }
        if (!empty($props['highlight'])) {
            $parts[] = '<w:shd w:val="clear" w:color="auto" w:fill="' . $props['highlight'] . '"/>';
        }

        if (empty($parts)) return '';
        return '<w:rPr>' . implode('', $parts) . '</w:rPr>';
    }

    private function makeParagraph(string $runs, string $pPr = ''): string
    {
        return '<w:p>' . $pPr . $runs . '</w:p>';
    }

    private function findChild(DOMElement $parent, string $tag): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && strtolower($node->nodeName) === $tag) {
                return $node;
            }
        }
        return null;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function normalizeHex(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return strtoupper($hex);
    }
}

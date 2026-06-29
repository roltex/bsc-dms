<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDocument;
use App\Models\User;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentContentService
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function extractHtml(Task $task): ?string
    {
        $docxPath = $this->findCompanionDocx($task);
        if (!$docxPath || !file_exists($docxPath)) {
            return null;
        }

        return $this->extractHtmlFromPath($docxPath);
    }

    public function extractHtmlFromPath(string $docxPath): ?string
    {
        if (!file_exists($docxPath)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        $converter = app(DocToPdfConverter::class);
        return $this->extractBodyHtml($converter, $xml, $docxPath);
    }

    public function saveHtml(Task $task, string $html, User $actor): TaskDocument
    {
        $docxPath = $this->findCompanionDocx($task);
        if (!$docxPath || !file_exists($docxPath)) {
            throw new \RuntimeException('No companion DOCX found for this task.');
        }

        $lastVersion = $task->documents()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;

        $slug = \Illuminate\Support\Str::slug($task->category?->name ?? 'doc');
        $newDocxRelPath = 'tasks/' . $task->id . '/final-' . $slug . '-v' . $newVersion . '-' . time() . '.docx';
        $newDocxAbsPath = Storage::disk('local')->path($newDocxRelPath);

        $dir = dirname($newDocxAbsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($docxPath, $newDocxAbsPath);

        $converter = app(HtmlToOoxmlConverter::class);
        $ooxmlBody = $converter->convert($html);
        $images = $converter->getImages();

        $this->injectIntoDocx($newDocxAbsPath, $ooxmlBody, $images);

        $pdfConverter = app(DocToPdfConverter::class);
        $pdfAbsPath = preg_replace('/\.docx$/i', '.pdf', $newDocxAbsPath);
        $pdfAbsPath = $pdfConverter->convertFromAbsPath($newDocxAbsPath, $pdfAbsPath);

        $root = rtrim(str_replace('\\', '/', Storage::disk('local')->path('')), '/');
        $pdfNorm = str_replace('\\', '/', $pdfAbsPath);
        $pdfRelPath = ltrim(str_replace($root, '', $pdfNorm), '/');

        $doc = $task->documents()->create([
            'path' => $pdfRelPath,
            'mime_type' => 'application/pdf',
            'version' => $newVersion,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'final_version_edited',
            'comment' => 'Final version created via online editor',
            'meta' => ['document_id' => $doc->id, 'version' => $newVersion],
        ]);

        app(TaskMainDocumentCommentMigrationService::class)->apply($task, $doc);

        return $doc;
    }

    private function findCompanionDocx(Task $task): ?string
    {
        $latestDoc = $task->documents()->orderByDesc('version')->first();
        if (!$latestDoc) {
            return null;
        }

        $pdfPath = Storage::disk('local')->path($latestDoc->path);
        $docxPath = preg_replace('/\.pdf$/i', '.docx', $pdfPath);

        if (file_exists($docxPath)) {
            return $docxPath;
        }

        $dir = dirname($pdfPath);
        $docxFiles = glob($dir . '/*.docx');
        if (!empty($docxFiles)) {
            usort($docxFiles, fn ($a, $b) => filemtime($b) - filemtime($a));
            return $docxFiles[0];
        }

        return null;
    }

    private function extractBodyHtml(DocToPdfConverter $converter, string $xml, string $docxPath): string
    {
        $zip = new ZipArchive();
        $zip->open($docxPath);

        $rels = [];
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml !== false) {
            $relsDom = new DOMDocument();
            @$relsDom->loadXML($relsXml);
            foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
                $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }
        }

        $styleMap = $this->parseStylesXml($zip);

        $dom = new DOMDocument();
        if (@$dom->loadXML($xml) === false) {
            $zip->close();
            return $this->fallbackExtract($xml);
        }

        $body = $dom->getElementsByTagNameNS(self::W_NS, 'body')->item(0);
        if (!$body) {
            $zip->close();
            return $this->fallbackExtract($xml);
        }

        $html = '';
        foreach ($body->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $local = $node->localName;
                if ($local === 'sectPr') continue;
                $html .= $this->nodeToHtml($node, $zip, $rels, $styleMap);
            }
        }

        $zip->close();
        return $html;
    }

    private function parseStylesXml(ZipArchive $zip): array
    {
        $stylesXml = $zip->getFromName('word/styles.xml');
        if ($stylesXml === false) return ['_defaults' => $this->emptyStyleProps()];

        $dom = new DOMDocument();
        if (@$dom->loadXML($stylesXml) === false) return ['_defaults' => $this->emptyStyleProps()];

        $defaults = $this->emptyStyleProps();

        $docDefaults = $dom->getElementsByTagNameNS(self::W_NS, 'docDefaults');
        if ($docDefaults->length > 0) {
            $dd = $docDefaults->item(0);
            $rPrDefault = null;
            $pPrDefault = null;
            foreach ($dd->childNodes as $ch) {
                if (!($ch instanceof DOMElement)) continue;
                if ($ch->localName === 'rPrDefault') $rPrDefault = $ch;
                if ($ch->localName === 'pPrDefault') $pPrDefault = $ch;
            }
            if ($rPrDefault) {
                $rPr = $this->getChild($rPrDefault, 'rPr');
                if ($rPr) $defaults = array_merge($defaults, $this->extractRunProps($rPr));
            }
            if ($pPrDefault) {
                $pPr = $this->getChild($pPrDefault, 'pPr');
                if ($pPr) $defaults = array_merge($defaults, $this->extractParaProps($pPr));
            }
        }

        $rawStyles = [];
        $styles = $dom->getElementsByTagNameNS(self::W_NS, 'style');
        foreach ($styles as $style) {
            $id = $style->getAttributeNS(self::W_NS, 'styleId');
            $type = $style->getAttributeNS(self::W_NS, 'type');
            if (!$id) continue;

            $basedOn = null;
            $bo = $this->getChild($style, 'basedOn');
            if ($bo) $basedOn = $bo->getAttributeNS(self::W_NS, 'val');

            $props = [];
            $pPr = $this->getChild($style, 'pPr');
            if ($pPr) $props = array_merge($props, $this->extractParaProps($pPr));

            $rPr = $this->getChild($style, 'rPr');
            if ($rPr) $props = array_merge($props, $this->extractRunProps($rPr));

            $rawStyles[$id] = ['type' => $type, 'basedOn' => $basedOn, 'props' => $props];
        }

        $resolved = ['_defaults' => $defaults];
        foreach ($rawStyles as $id => $raw) {
            $resolved[$id] = $this->resolveStyle($id, $rawStyles, $defaults, []);
        }

        return $resolved;
    }

    private function resolveStyle(string $id, array $rawStyles, array $defaults, array $visited): array
    {
        if (in_array($id, $visited)) return $defaults;
        $visited[] = $id;

        if (!isset($rawStyles[$id])) return $defaults;

        $raw = $rawStyles[$id];
        $base = $defaults;
        if ($raw['basedOn'] && isset($rawStyles[$raw['basedOn']])) {
            $base = $this->resolveStyle($raw['basedOn'], $rawStyles, $defaults, $visited);
        }

        foreach ($raw['props'] as $k => $v) {
            if ($v !== null) $base[$k] = $v;
        }

        return $base;
    }

    private function emptyStyleProps(): array
    {
        return [
            'bold' => false, 'italic' => false, 'fontSize' => null,
            'fontFamily' => null, 'alignment' => null,
            'indentLeft' => null, 'indentFirstLine' => null,
            'spacingBefore' => null, 'spacingAfter' => null,
        ];
    }

    private function extractRunProps(DOMElement $rPr): array
    {
        $props = [];
        $b = $this->getChild($rPr, 'b');
        if ($b) {
            $v = $b->getAttributeNS(self::W_NS, 'val');
            $props['bold'] = ($v === '' || $v === '1' || $v === 'true');
        }
        $i = $this->getChild($rPr, 'i');
        if ($i) {
            $v = $i->getAttributeNS(self::W_NS, 'val');
            $props['italic'] = ($v === '' || $v === '1' || $v === 'true');
        }
        $sz = $this->getChild($rPr, 'sz');
        if ($sz) {
            $v = $sz->getAttributeNS(self::W_NS, 'val');
            if ($v) $props['fontSize'] = round((int) $v / 2, 1);
        }
        $fonts = $this->getChild($rPr, 'rFonts');
        if ($fonts) {
            $ascii = $fonts->getAttributeNS(self::W_NS, 'ascii')
                  ?: $fonts->getAttributeNS(self::W_NS, 'hAnsi')
                  ?: $fonts->getAttributeNS(self::W_NS, 'cs');
            if ($ascii) $props['fontFamily'] = $ascii;
        }
        return $props;
    }

    private function extractParaProps(DOMElement $pPr): array
    {
        $props = [];
        $jc = $this->getChild($pPr, 'jc');
        if ($jc) {
            $v = $jc->getAttributeNS(self::W_NS, 'val');
            $map = ['left' => 'left', 'center' => 'center', 'right' => 'right', 'both' => 'justify', 'start' => 'left', 'end' => 'right'];
            if (isset($map[$v])) $props['alignment'] = $map[$v];
        }
        $ind = $this->getChild($pPr, 'ind');
        if ($ind) {
            $left = $ind->getAttributeNS(self::W_NS, 'left') ?: $ind->getAttributeNS(self::W_NS, 'start');
            if ($left) $props['indentLeft'] = round((int) $left / 20, 1);
            $fl = $ind->getAttributeNS(self::W_NS, 'firstLine');
            if ($fl && (int) $fl > 0) $props['indentFirstLine'] = round((int) $fl / 20, 1);
        }
        $spacing = $this->getChild($pPr, 'spacing');
        if ($spacing) {
            $before = $spacing->getAttributeNS(self::W_NS, 'before');
            $after = $spacing->getAttributeNS(self::W_NS, 'after');
            if ($before) $props['spacingBefore'] = round((int) $before / 20, 1);
            if ($after) $props['spacingAfter'] = round((int) $after / 20, 1);
        }
        return $props;
    }

    private function nodeToHtml(DOMElement $el, ZipArchive $zip, array $rels, array $styleMap): string
    {
        $local = $el->localName;

        if ($local === 'p') {
            return $this->paragraphToHtml($el, $zip, $rels, $styleMap);
        }
        if ($local === 'tbl') {
            return $this->tableToHtml($el, $zip, $rels, $styleMap);
        }

        return '';
    }

    private function paragraphToHtml(DOMElement $p, ZipArchive $zip, array $rels, array $styleMap): string
    {
        $pPr = $this->getChild($p, 'pPr');
        $tag = 'p';
        $css = [];

        $defaults = $styleMap['_defaults'] ?? $this->emptyStyleProps();
        $styleName = null;

        if ($pPr) {
            $pStyle = $this->getChild($pPr, 'pStyle');
            if ($pStyle) {
                $styleName = $pStyle->getAttributeNS(self::W_NS, 'val');
                if (isset($styleMap[$styleName])) {
                    $defaults = array_merge($defaults, array_filter($styleMap[$styleName], fn ($v) => $v !== null));
                }
                if (preg_match('/Heading\s*(\d)/i', $styleName, $m)) {
                    $tag = 'h' . min(6, max(1, (int) $m[1]));
                }
            }
        }

        $runDefaults = [
            'bold' => $defaults['bold'] ?? false,
            'italic' => $defaults['italic'] ?? false,
            'fontSize' => $defaults['fontSize'] ?? null,
            'fontFamily' => $defaults['fontFamily'] ?? null,
        ];

        $alignment = $defaults['alignment'] ?? null;
        $indentLeft = $defaults['indentLeft'] ?? null;
        $indentFirstLine = $defaults['indentFirstLine'] ?? null;
        $spacingBefore = $defaults['spacingBefore'] ?? null;
        $spacingAfter = $defaults['spacingAfter'] ?? null;

        $tabs = [];
        if ($pPr) {
            $jc = $this->getChild($pPr, 'jc');
            if ($jc) {
                $v = $jc->getAttributeNS(self::W_NS, 'val');
                $map = ['left' => 'left', 'center' => 'center', 'right' => 'right', 'both' => 'justify', 'start' => 'left', 'end' => 'right'];
                if (isset($map[$v])) $alignment = $map[$v];
            }

            $ind = $this->getChild($pPr, 'ind');
            if ($ind) {
                $left = $ind->getAttributeNS(self::W_NS, 'left') ?: $ind->getAttributeNS(self::W_NS, 'start');
                if ($left) $indentLeft = round((int) $left / 20, 1);
                $fl = $ind->getAttributeNS(self::W_NS, 'firstLine');
                if ($fl && (int) $fl > 0) $indentFirstLine = round((int) $fl / 20, 1);
            }

            $spacing = $this->getChild($pPr, 'spacing');
            if ($spacing) {
                $before = $spacing->getAttributeNS(self::W_NS, 'before');
                $after = $spacing->getAttributeNS(self::W_NS, 'after');
                if ($before) $spacingBefore = round((int) $before / 20, 1);
                if ($after) $spacingAfter = round((int) $after / 20, 1);
            }

            $rPrParagraph = $this->getChild($pPr, 'rPr');
            if ($rPrParagraph) {
                $overrides = $this->extractRunProps($rPrParagraph);
                foreach ($overrides as $k => $v) {
                    if ($v !== null && isset($runDefaults[$k])) $runDefaults[$k] = $v;
                }
            }

            $tabsEl = $this->getChild($pPr, 'tabs');
            if ($tabsEl) {
                foreach ($tabsEl->childNodes as $tab) {
                    if ($tab instanceof DOMElement && $tab->localName === 'tab') {
                        $tabs[] = [
                            'val' => $tab->getAttributeNS(self::W_NS, 'val'),
                            'pos' => (int) $tab->getAttributeNS(self::W_NS, 'pos'),
                        ];
                    }
                }
            }
        }

        if ($alignment) $css[] = 'text-align:' . $alignment;
        if ($indentLeft && $indentLeft > 0) $css[] = 'margin-left:' . $indentLeft . 'pt';
        if ($indentFirstLine && $indentFirstLine > 0) $css[] = 'text-indent:' . $indentFirstLine . 'pt';
        if ($spacingBefore && $spacingBefore > 0) $css[] = 'margin-top:' . $spacingBefore . 'pt';
        if ($spacingAfter && $spacingAfter > 0) $css[] = 'margin-bottom:' . $spacingAfter . 'pt';

        $hasTabRun = false;
        foreach ($p->childNodes as $node) {
            if (!($node instanceof DOMElement) || $node->localName !== 'r') continue;
            foreach ($node->childNodes as $cn) {
                if ($cn instanceof DOMElement && $cn->localName === 'tab') {
                    $hasTabRun = true;
                    break 2;
                }
            }
        }

        if (!empty($tabs) && $hasTabRun) {
            return $this->tabParagraphToTable($p, $tabs, $runDefaults, $zip, $rels);
        }

        $inner = '';
        $hasContent = false;

        foreach ($p->childNodes as $node) {
            if (!($node instanceof DOMElement)) continue;
            if ($node->localName === 'r') {
                $t = $this->runToHtml($node, $zip, $rels, $runDefaults);
                if ($t !== '') {
                    $inner .= $t;
                    $hasContent = true;
                }
            } elseif ($node->localName === 'hyperlink') {
                foreach ($node->childNodes as $ch) {
                    if ($ch instanceof DOMElement && $ch->localName === 'r') {
                        $inner .= $this->runToHtml($ch, $zip, $rels, $runDefaults);
                        $hasContent = true;
                    }
                }
            }
        }

        if (!$hasContent) {
            $inner = '<br>';
        }

        $attrStr = $css ? ' style="' . implode(';', $css) . '"' : '';
        return "<{$tag}{$attrStr}>{$inner}</{$tag}>";
    }

    private function tabParagraphToTable(DOMElement $p, array $tabs, array $runDefaults, ZipArchive $zip, array $rels): string
    {
        $segments = [[]];
        $segIdx = 0;

        foreach ($p->childNodes as $node) {
            if (!($node instanceof DOMElement)) continue;
            if ($node->localName === 'pPr') continue;

            if ($node->localName === 'r') {
                $isTab = false;
                foreach ($node->childNodes as $cn) {
                    if ($cn instanceof DOMElement && $cn->localName === 'tab') {
                        $isTab = true;
                        break;
                    }
                }
                if ($isTab) {
                    $segIdx++;
                    if (!isset($segments[$segIdx])) $segments[$segIdx] = [];
                } else {
                    $segments[$segIdx][] = $this->runToHtml($node, $zip, $rels, $runDefaults);
                }
            } elseif ($node->localName === 'hyperlink') {
                foreach ($node->childNodes as $ch) {
                    if ($ch instanceof DOMElement && $ch->localName === 'r') {
                        $segments[$segIdx][] = $this->runToHtml($ch, $zip, $rels, $runDefaults);
                    }
                }
            }
        }

        $html = '<table style="width:100%"><tr>';
        $numSegs = count($segments);
        foreach ($segments as $i => $runs) {
            $align = 'left';
            if ($i > 0 && isset($tabs[$i - 1])) {
                $v = $tabs[$i - 1]['val'];
                if ($v === 'center') $align = 'center';
                elseif ($v === 'right') $align = 'right';
            }
            $content = implode('', $runs);
            if (!$content) $content = '&nbsp;';
            $w = $numSegs > 1 ? round(100 / $numSegs, 1) : 100;
            $html .= '<td style="width:' . $w . '%;text-align:' . $align . '"><p style="text-align:' . $align . '">' . $content . '</p></td>';
        }
        $html .= '</tr></table>';
        return $html;
    }

    private function runToHtml(DOMElement $r, ZipArchive $zip, array $rels, array $runDefaults = []): string
    {
        $rPr = $this->getChild($r, 'rPr');
        $html = '';

        foreach ($r->childNodes as $node) {
            if (!($node instanceof DOMElement)) continue;
            $ln = $node->localName;
            if ($ln === 't') {
                $html .= htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
            } elseif ($ln === 'tab') {
                $html .= "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0";
            } elseif ($ln === 'br') {
                $type = $node->getAttributeNS(self::W_NS, 'type');
                $html .= $type === 'page' ? '<hr>' : '<br>';
            } elseif ($ln === 'drawing') {
                $html .= $this->drawingToHtml($node, $zip, $rels);
            }
        }

        if ($html === '') return '';

        $bold = $runDefaults['bold'] ?? false;
        $italic = $runDefaults['italic'] ?? false;
        $underline = false;
        $strike = false;
        $fontSize = $runDefaults['fontSize'] ?? null;
        $fontFamily = $runDefaults['fontFamily'] ?? null;
        $colorVal = null;
        $bgVal = null;

        if ($rPr) {
            $bEl = $this->getChild($rPr, 'b');
            if ($bEl) {
                $bVal = $bEl->getAttributeNS(self::W_NS, 'val');
                $bold = ($bVal === '' || $bVal === '1' || $bVal === 'true');
            }
            $iEl = $this->getChild($rPr, 'i');
            if ($iEl) {
                $iVal = $iEl->getAttributeNS(self::W_NS, 'val');
                $italic = ($iVal === '' || $iVal === '1' || $iVal === 'true');
            }
            $u = $this->getChild($rPr, 'u');
            if ($u && $u->getAttributeNS(self::W_NS, 'val') !== 'none') $underline = true;
            if ($this->getChild($rPr, 'strike')) $strike = true;

            $sz = $this->getChild($rPr, 'sz');
            if ($sz) {
                $v = $sz->getAttributeNS(self::W_NS, 'val');
                if ($v) $fontSize = round((int) $v / 2, 1);
            }
            $fonts = $this->getChild($rPr, 'rFonts');
            if ($fonts) {
                $ff = $fonts->getAttributeNS(self::W_NS, 'ascii')
                    ?: $fonts->getAttributeNS(self::W_NS, 'hAnsi')
                    ?: $fonts->getAttributeNS(self::W_NS, 'cs');
                if ($ff) $fontFamily = $ff;
            }
            $color = $this->getChild($rPr, 'color');
            if ($color) {
                $v = $color->getAttributeNS(self::W_NS, 'val');
                if ($v && $v !== 'auto' && $v !== '000000') $colorVal = $v;
            }
            $shd = $this->getChild($rPr, 'shd');
            if ($shd) {
                $fill = $shd->getAttributeNS(self::W_NS, 'fill');
                if ($fill && $fill !== 'auto' && $fill !== 'FFFFFF') $bgVal = $fill;
            }
        }

        $spanStyle = [];
        if ($fontSize && $fontSize != 12.0) {
            $spanStyle[] = 'font-size:' . $fontSize . 'pt';
        }
        if ($fontFamily) {
            $spanStyle[] = 'font-family:' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8');
        }
        if ($colorVal) $spanStyle[] = 'color:#' . $colorVal;
        if ($bgVal) $spanStyle[] = 'background-color:#' . $bgVal;

        if ($spanStyle) {
            $html = '<span style="' . implode(';', $spanStyle) . '">' . $html . '</span>';
        }

        if ($strike) $html = "<s>{$html}</s>";
        if ($underline) $html = "<u>{$html}</u>";
        if ($italic) $html = "<em>{$html}</em>";
        if ($bold) $html = "<strong>{$html}</strong>";

        return $html;
    }

    private function drawingToHtml(DOMElement $drawing, ZipArchive $zip, array $rels): string
    {
        $blips = $drawing->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip');
        if ($blips->length === 0) return '';

        $rId = $blips->item(0)->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
        if (!$rId || !isset($rels[$rId])) return '';

        $target = $rels[$rId];
        $imageData = $zip->getFromName('word/' . $target);
        if ($imageData === false) return '';

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        $b64 = base64_encode($imageData);
        return '<img src="data:' . $mime . ';base64,' . $b64 . '" style="max-width:100%">';
    }

    private function tableToHtml(DOMElement $tbl, ZipArchive $zip, array $rels, array $styleMap): string
    {
        $tblCss = 'width:100%';
        $tblPr = $this->getChild($tbl, 'tblPr');
        if ($tblPr) {
            $tblW = $this->getChild($tblPr, 'tblW');
            if ($tblW) {
                $w = $tblW->getAttributeNS(self::W_NS, 'w');
                $type = $tblW->getAttributeNS(self::W_NS, 'type');
                if ($type === 'pct' && $w) {
                    $tblCss = 'width:' . round((int) $w / 50, 1) . '%';
                } elseif ($type === 'dxa' && $w) {
                    $tblCss = 'width:' . round((int) $w / 20, 1) . 'pt';
                }
            }
        }

        $html = '<table style="' . $tblCss . '">';

        $rowIdx = 0;
        foreach ($tbl->childNodes as $node) {
            if (!($node instanceof DOMElement) || $node->localName !== 'tr') continue;
            $html .= '<tr>';

            foreach ($node->childNodes as $cell) {
                if (!($cell instanceof DOMElement) || $cell->localName !== 'tc') continue;

                $tcPr = $this->getChild($cell, 'tcPr');
                $tdAttrs = [];
                $cellCss = [];
                $isHeader = ($rowIdx === 0);

                if ($tcPr) {
                    $gs = $this->getChild($tcPr, 'gridSpan');
                    if ($gs) {
                        $v = (int) $gs->getAttributeNS(self::W_NS, 'val');
                        if ($v > 1) $tdAttrs[] = 'colspan="' . $v . '"';
                    }

                    $tcW = $this->getChild($tcPr, 'tcW');
                    if ($tcW) {
                        $w = $tcW->getAttributeNS(self::W_NS, 'w');
                        $type = $tcW->getAttributeNS(self::W_NS, 'type');
                        if ($type === 'pct' && $w) {
                            $cellCss[] = 'width:' . round((int) $w / 50, 1) . '%';
                        } elseif ($type === 'dxa' && $w) {
                            $cellCss[] = 'width:' . round((int) $w / 20, 1) . 'pt';
                        }
                    }

                    $vAlign = $this->getChild($tcPr, 'vAlign');
                    if ($vAlign) {
                        $v = $vAlign->getAttributeNS(self::W_NS, 'val');
                        $vMap = ['top' => 'top', 'center' => 'middle', 'bottom' => 'bottom'];
                        if (isset($vMap[$v])) {
                            $cellCss[] = 'vertical-align:' . $vMap[$v];
                        }
                    }

                    $vMerge = $this->getChild($tcPr, 'vMerge');
                    if ($vMerge) {
                        $val = $vMerge->getAttributeNS(self::W_NS, 'val');
                        if ($val !== 'restart' && $val !== '') {
                            continue;
                        }
                    }
                }

                if ($cellCss) {
                    $tdAttrs[] = 'style="' . implode(';', $cellCss) . '"';
                }

                $inner = '';
                foreach ($cell->childNodes as $cn) {
                    if ($cn instanceof DOMElement) {
                        $inner .= $this->nodeToHtml($cn, $zip, $rels, $styleMap);
                    }
                }

                $cellTag = $isHeader ? 'th' : 'td';
                $attrStr = $tdAttrs ? ' ' . implode(' ', $tdAttrs) : '';
                $html .= '<' . $cellTag . $attrStr . '>' . ($inner ?: '<p><br></p>') . '</' . $cellTag . '>';
            }
            $html .= '</tr>';
            $rowIdx++;
        }
        $html .= '</table>';
        return $html;
    }

    public function injectIntoDocxPublic(string $docxPath, string $ooxmlBody, array $images): void
    {
        $this->injectIntoDocx($docxPath, $ooxmlBody, $images);
    }

    private function injectIntoDocx(string $docxPath, string $ooxmlBody, array $images): void
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Cannot open DOCX for editing.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new \RuntimeException('No document.xml in DOCX.');
        }

        $sectPr = '';
        if (preg_match('/<w:sectPr\b.*?<\/w:sectPr>/s', $xml, $m)) {
            $sectPr = $m[0];
        }

        $nsDeclarations = '';
        if (preg_match('/<w:document\b([^>]*)>/s', $xml, $m)) {
            $nsDeclarations = $m[1];
        }

        $newXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document' . $nsDeclarations . '>'
            . '<w:body>'
            . $ooxmlBody
            . $sectPr
            . '</w:body>'
            . '</w:document>';

        $zip->addFromString('word/document.xml', $newXml);

        if (!empty($images)) {
            $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
            if ($relsXml === false) {
                $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
            }

            $contentTypes = $zip->getFromName('[Content_Types].xml') ?: '';

            foreach ($images as $img) {
                $zip->addFromString('word/media/' . $img['filename'], $img['data']);

                $relEntry = '<Relationship Id="' . $img['relId'] . '"'
                    . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                    . ' Target="media/' . $img['filename'] . '"/>';
                $relsXml = str_replace('</Relationships>', $relEntry . '</Relationships>', $relsXml);

                $ext = pathinfo($img['filename'], PATHINFO_EXTENSION);
                $ctEntry = '<Default Extension="' . $ext . '" ContentType="' . $img['mime'] . '"/>';
                if (strpos($contentTypes, 'Extension="' . $ext . '"') === false) {
                    $contentTypes = str_replace('<Types ', '<Types ' . "\n" . $ctEntry . "\n", $contentTypes);
                }
            }

            $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
            if ($contentTypes) {
                $zip->addFromString('[Content_Types].xml', $contentTypes);
            }
        }

        $zip->close();
    }

    private function fallbackExtract(string $xml): string
    {
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $paragraphs = array_filter(array_map('trim', explode("\n", wordwrap($text, 200))));
        return implode("\n", array_map(fn ($p) => '<p>' . htmlspecialchars($p) . '</p>', $paragraphs));
    }

    private function getChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return $node;
            }
        }
        return null;
    }
}

<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocToPdfConverter
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private ?ZipArchive $zip = null;
    private array $rels = [];
    private array $pageMargins = ['top' => 1440, 'right' => 1440, 'bottom' => 1440, 'left' => 1440];

    public function convertIfNeeded(string $localPath): string
    {
        $absPath = Storage::disk('local')->path($localPath);
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $absPath;
        }

        $pdfCachePath = preg_replace('/\.(docx?|rtf)$/i', '.pdf', $localPath);
        $pdfAbsPath = Storage::disk('local')->path($pdfCachePath);

        if (file_exists($pdfAbsPath)) {
            return $pdfAbsPath;
        }

        if (in_array($ext, ['doc', 'docx'])) {
            return $this->convert($absPath, $pdfAbsPath);
        }

        return $absPath;
    }

    public function convertFromAbsPath(string $docxAbsPath, string $pdfAbsPath): string
    {
        if (file_exists($pdfAbsPath)) {
            @unlink($pdfAbsPath);
        }

        return $this->convert($docxAbsPath, $pdfAbsPath);
    }

    private function convert(string $docxPath, string $pdfPath): string
    {
        $dir = dirname($pdfPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $result = $this->convertWithLibreOffice($docxPath, $pdfPath);
            if ($result) {
                \Log::info('DocToPdfConverter: LibreOffice OK', ['src' => basename($docxPath), 'size' => filesize($result)]);

                return $result;
            }
            \Log::warning('DocToPdfConverter: LibreOffice returned null, falling back to PHP', ['src' => basename($docxPath)]);
        } catch (\Throwable $e) {
            \Log::warning('DocToPdfConverter: LibreOffice exception, falling back to PHP', ['src' => basename($docxPath), 'error' => $e->getMessage()]);
        }

        $result = $this->convertWithPhp($docxPath, $pdfPath);
        \Log::info('DocToPdfConverter: PHP fallback used', ['src' => basename($docxPath), 'size' => filesize($result)]);

        return $result;
    }

    // ─── LibreOffice Headless ───

    private function getLibreOfficeBinary(): ?string
    {
        $home = getenv('HOME') ?: ('/home/'.get_current_user());
        $candidates = [
            $home.'/squashfs-root/opt/libreoffice25.8/program/soffice',
            $home.'/libreoffice/program/soffice',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/lib64/libreoffice/program/soffice',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function convertWithLibreOffice(string $docxPath, string $pdfPath): ?string
    {
        $soffice = $this->getLibreOfficeBinary();
        if (! $soffice) {
            return null;
        }

        $outDir = dirname($pdfPath);
        $expectedName = pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        $expectedPath = $outDir.'/'.$expectedName;

        $home = getenv('HOME') ?: ('/home/'.get_current_user());
        $profileDir = sys_get_temp_dir().'/lo_profile_'.getmypid().'_'.mt_rand();

        $cmd = sprintf(
            'HOME=%s %s --headless --norestore --nofirststartwizard -env:UserInstallation=file://%s --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($home),
            escapeshellarg($soffice),
            escapeshellarg($profileDir),
            escapeshellarg($outDir),
            escapeshellarg($docxPath)
        );

        $process = @\proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (\is_resource($process)) {
            \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
            \stream_get_contents($pipes[2]);
            \fclose($pipes[2]);
            \proc_close($process);
        }

        @$this->removeDir($profileDir);

        if (file_exists($expectedPath)) {
            if ($expectedPath !== $pdfPath) {
                rename($expectedPath, $pdfPath);
            }
            return $pdfPath;
        }

        return null;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    // ─── PHP Fallback (OOXML → HTML → PDF) ───

    private function convertWithPhp(string $docxPath, string $pdfPath): string
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($docxPath) !== true) {
            throw new \RuntimeException('Cannot open DOCX');
        }

        $this->loadRels();
        $xml = $this->zip->getFromName('word/document.xml');
        if ($xml === false) {
            $this->zip->close();
            throw new \RuntimeException('No document.xml');
        }

        $html = $this->ooxmlToHtml($xml);
        $this->zip->close();
        $this->zip = null;

        $fullHtml = $this->wrapHtml($html ?: '<p>&nbsp;</p>');

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($fullHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dir = dirname($pdfPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($pdfPath, $dompdf->output());

        return $pdfPath;
    }

    private function loadRels(): void
    {
        $this->rels = [];
        $relsXml = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml === false) {
            return;
        }
        $dom = new DOMDocument();
        @$dom->loadXML($relsXml);
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $this->rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }

    private function ooxmlToHtml(string $xml): string
    {
        $dom = new DOMDocument();
        if (@$dom->loadXML($xml) === false) {
            return $this->ooxmlToHtmlFallback($xml);
        }

        $body = $dom->getElementsByTagNameNS(self::W_NS, 'body')->item(0);
        if (! $body) {
            return $this->ooxmlToHtmlFallback($xml);
        }

        $this->extractPageLayout($body);

        $html = '';
        foreach ($body->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $html .= $this->processBodyElement($node);
            }
        }

        return $html;
    }

    private function extractPageLayout(DOMElement $body): void
    {
        $sectPr = $this->getChild($body, 'sectPr');
        if (! $sectPr) {
            return;
        }
        $pgMar = $this->getChild($sectPr, 'pgMar');
        if ($pgMar) {
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $val = $pgMar->getAttributeNS(self::W_NS, $side);
                if ($val) {
                    $this->pageMargins[$side] = (int) $val;
                }
            }
        }
    }

    private function processBodyElement(DOMElement $el): string
    {
        $local = $el->localName;
        if ($local === 'p') {
            return $this->processParagraph($el);
        }
        if ($local === 'tbl') {
            return $this->processTable($el);
        }

        return '';
    }

    private function processParagraph(DOMElement $p): string
    {
        $pPr = $this->getChild($p, 'pPr');
        $style = $this->paragraphCss($pPr);
        $inner = '';
        $hasContent = false;

        foreach ($p->childNodes as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }
            if ($node->localName === 'r') {
                $t = $this->processRun($node);
                if ($t !== '') {
                    $inner .= $t;
                    $hasContent = true;
                }
            } elseif ($node->localName === 'hyperlink') {
                foreach ($node->childNodes as $ch) {
                    if ($ch instanceof DOMElement && $ch->localName === 'r') {
                        $inner .= $this->processRun($ch);
                        $hasContent = true;
                    }
                }
            }
        }

        if (! $hasContent) {
            $inner = '&nbsp;';
        }

        $attr = $style ? " style=\"{$style}\"" : '';

        return "<p{$attr}>{$inner}</p>\n";
    }

    private function paragraphCss(?DOMElement $pPr): string
    {
        if (! $pPr) {
            return '';
        }
        $css = [];

        $jc = $this->getChild($pPr, 'jc');
        if ($jc) {
            $v = $jc->getAttributeNS(self::W_NS, 'val');
            $map = ['left' => 'left', 'center' => 'center', 'right' => 'right', 'both' => 'justify'];
            if (isset($map[$v])) {
                $css[] = "text-align:{$map[$v]}";
            }
        }

        $sp = $this->getChild($pPr, 'spacing');
        if ($sp) {
            $b = $sp->getAttributeNS(self::W_NS, 'before');
            $a = $sp->getAttributeNS(self::W_NS, 'after');
            $l = $sp->getAttributeNS(self::W_NS, 'line');
            if ($b) {
                $css[] = 'margin-top:'.round((int) $b / 20, 1).'pt';
            }
            if ($a) {
                $css[] = 'margin-bottom:'.round((int) $a / 20, 1).'pt';
            }
            if ($l && (int) $l > 0) {
                $lr = $sp->getAttributeNS(self::W_NS, 'lineRule');
                $css[] = $lr === 'auto'
                    ? 'line-height:'.round((int) $l / 240, 2)
                    : 'line-height:'.round((int) $l / 20, 1).'pt';
            }
        }

        $ind = $this->getChild($pPr, 'ind');
        if ($ind) {
            $v = $ind->getAttributeNS(self::W_NS, 'left');
            if ($v) {
                $css[] = 'margin-left:'.round((int) $v / 20, 1).'pt';
            }
            $v = $ind->getAttributeNS(self::W_NS, 'right');
            if ($v) {
                $css[] = 'margin-right:'.round((int) $v / 20, 1).'pt';
            }
            $v = $ind->getAttributeNS(self::W_NS, 'firstLine');
            if ($v) {
                $css[] = 'text-indent:'.round((int) $v / 20, 1).'pt';
            }
        }

        $rPr = $this->getChild($pPr, 'rPr');
        if ($rPr) {
            $css = array_merge($css, $this->runCss($rPr));
        }

        return implode(';', $css);
    }

    private function processRun(DOMElement $r): string
    {
        $rPr = $this->getChild($r, 'rPr');
        $html = '';

        foreach ($r->childNodes as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }
            $ln = $node->localName;
            if ($ln === 't') {
                $html .= htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
            } elseif ($ln === 'tab') {
                $html .= '<span style="display:inline-block;width:36pt"></span>';
            } elseif ($ln === 'br') {
                $type = $node->getAttributeNS(self::W_NS, 'type');
                $html .= $type === 'page' ? '<div style="page-break-after:always"></div>' : '<br>';
            } elseif ($ln === 'drawing') {
                $html .= $this->processDrawing($node);
            }
        }

        if ($html === '') {
            return '';
        }

        $css = $rPr ? $this->runCss($rPr) : [];

        return $css
            ? '<span style="'.implode(';', $css).'">'.$html.'</span>'
            : $html;
    }

    private function runCss(?DOMElement $rPr): array
    {
        if (! $rPr) {
            return [];
        }
        $css = [];
        if ($this->getChild($rPr, 'b')) {
            $css[] = 'font-weight:bold';
        }
        if ($this->getChild($rPr, 'i')) {
            $css[] = 'font-style:italic';
        }
        $u = $this->getChild($rPr, 'u');
        if ($u && $u->getAttributeNS(self::W_NS, 'val') !== 'none') {
            $css[] = 'text-decoration:underline';
        }
        $sz = $this->getChild($rPr, 'sz');
        if ($sz) {
            $v = $sz->getAttributeNS(self::W_NS, 'val');
            if ($v) {
                $css[] = 'font-size:'.round((int) $v / 2, 1).'pt';
            }
        }
        $color = $this->getChild($rPr, 'color');
        if ($color) {
            $v = $color->getAttributeNS(self::W_NS, 'val');
            if ($v && $v !== 'auto' && $v !== '000000') {
                $css[] = "color:#{$v}";
            }
        }

        return $css;
    }

    private function processTable(DOMElement $tbl): string
    {
        $tblPr = $this->getChild($tbl, 'tblPr');
        $tblGrid = $this->getChild($tbl, 'tblGrid');
        $borderStyle = $this->tableBorderCss($tblPr);

        $colWidths = [];
        if ($tblGrid) {
            foreach ($tblGrid->childNodes as $col) {
                if ($col instanceof DOMElement && $col->localName === 'gridCol') {
                    $w = $col->getAttributeNS(self::W_NS, 'w');
                    $colWidths[] = $w ? (int) $w : 0;
                }
            }
        }

        $html = '<table style="border-collapse:collapse;width:100%;margin:6pt 0">';
        if ($colWidths) {
            $total = array_sum($colWidths) ?: 1;
            $html .= '<colgroup>';
            foreach ($colWidths as $w) {
                $html .= '<col style="width:'.round($w / $total * 100, 1).'%">';
            }
            $html .= '</colgroup>';
        }

        foreach ($tbl->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'tr') {
                $html .= '<tr>';
                foreach ($node->childNodes as $cell) {
                    if ($cell instanceof DOMElement && $cell->localName === 'tc') {
                        $html .= $this->processCell($cell, $borderStyle);
                    }
                }
                $html .= '</tr>';
            }
        }

        return $html.'</table>';
    }

    private function tableBorderCss(?DOMElement $tblPr): string
    {
        if (! $tblPr) {
            return '1px solid #999';
        }
        $borders = $this->getChild($tblPr, 'tblBorders');
        if (! $borders) {
            return '1px solid #999';
        }
        $top = $this->getChild($borders, 'top');
        if ($top) {
            $v = $top->getAttributeNS(self::W_NS, 'val');
            if ($v === 'none' || $v === 'nil') {
                return 'none';
            }
            $sz = $top->getAttributeNS(self::W_NS, 'sz');
            $c = $top->getAttributeNS(self::W_NS, 'color') ?: '000000';

            return max(1, round((int) ($sz ?: 4) / 8)).'px solid #'.$c;
        }

        return '1px solid #999';
    }

    private function processCell(DOMElement $tc, string $borderStyle): string
    {
        $tcPr = $this->getChild($tc, 'tcPr');
        $css = ['padding:3pt 5pt', 'vertical-align:top'];
        if ($borderStyle !== 'none') {
            $css[] = "border:{$borderStyle}";
        }

        $attrs = '';
        if ($tcPr) {
            $shd = $this->getChild($tcPr, 'shd');
            if ($shd) {
                $fill = $shd->getAttributeNS(self::W_NS, 'fill');
                if ($fill && $fill !== 'auto' && $fill !== 'FFFFFF') {
                    $css[] = "background-color:#{$fill}";
                }
            }
            $gs = $this->getChild($tcPr, 'gridSpan');
            if ($gs) {
                $v = (int) $gs->getAttributeNS(self::W_NS, 'val');
                if ($v > 1) {
                    $attrs .= " colspan=\"{$v}\"";
                }
            }
            $vMerge = $this->getChild($tcPr, 'vMerge');
            if ($vMerge && $vMerge->getAttributeNS(self::W_NS, 'val') !== 'restart') {
                return '';
            }
        }

        $inner = '';
        foreach ($tc->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $inner .= $this->processBodyElement($node);
            }
        }

        return "<td{$attrs} style=\"".implode(';', $css)."\">{$inner}</td>";
    }

    private function processDrawing(DOMElement $drawing): string
    {
        $inline = $drawing->getElementsByTagNameNS(self::WP_NS, 'inline')->item(0)
            ?? $drawing->getElementsByTagNameNS(self::WP_NS, 'anchor')->item(0);
        if (! $inline) {
            return '';
        }

        $extent = $inline->getElementsByTagNameNS(self::WP_NS, 'extent')->item(0);
        $widthPt = $extent ? round((int) $extent->getAttribute('cx') / 12700, 1) : 0;

        $blips = $drawing->getElementsByTagNameNS(self::A_NS, 'blip');
        if ($blips->length === 0) {
            return '';
        }
        $rId = $blips->item(0)->getAttributeNS(self::R_NS, 'embed');
        if (! $rId || ! isset($this->rels[$rId]) || ! $this->zip) {
            return '';
        }

        $target = $this->rels[$rId];
        $imageData = $this->zip->getFromName('word/'.$target);
        if ($imageData === false) {
            return '';
        }

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', default => 'image/png',
        };
        $b64 = base64_encode($imageData);
        $maxW = $widthPt > 0 ? min($widthPt, 500) : 200;

        return "<img src=\"data:{$mime};base64,{$b64}\" style=\"width:{$maxW}pt;max-width:100%;height:auto\">";
    }

    // ─── Regex fallback for malformed XML ───

    private function ooxmlToHtmlFallback(string $xml): string
    {
        $html = '';
        $segments = preg_split('/(<w:tbl\b.*?<\/w:tbl>|<w:p\b[^>]*>.*?<\/w:p>)/s', $xml, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as $seg) {
            if (preg_match('/^<w:tbl\b/', $seg)) {
                $html .= $this->fbTable($seg);
            } elseif (preg_match('/^<w:p\b/', $seg)) {
                $html .= $this->fbPara($seg);
            }
        }

        return $html;
    }

    private function fbPara(string $pXml): string
    {
        $css = [];
        if (preg_match('/<w:jc\s+w:val="([^"]+)"/', $pXml, $m)) {
            $map = ['left' => 'left', 'center' => 'center', 'right' => 'right', 'both' => 'justify'];
            if (isset($map[$m[1]])) {
                $css[] = "text-align:{$map[$m[1]]}";
            }
        }
        if (preg_match('/<w:spacing\b[^>]*w:before="(\d+)"/', $pXml, $m)) {
            $css[] = 'margin-top:'.round((int) $m[1] / 20, 1).'pt';
        }
        if (preg_match('/<w:spacing\b[^>]*w:after="(\d+)"/', $pXml, $m)) {
            $css[] = 'margin-bottom:'.round((int) $m[1] / 20, 1).'pt';
        }
        if (preg_match('/<w:ind\b[^>]*w:left="(\d+)"/', $pXml, $m)) {
            $css[] = 'margin-left:'.round((int) $m[1] / 20, 1).'pt';
        }

        $runs = '';
        preg_match_all('/<w:r\b[^>]*>.*?<\/w:r>/s', $pXml, $rms);
        foreach ($rms[0] as $rXml) {
            $t = '';
            if (strpos($rXml, '<w:tab') !== false) {
                $t .= '<span style="display:inline-block;width:36pt"></span>';
            }
            preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $rXml, $txts);
            foreach ($txts[1] as $tx) {
                $t .= htmlspecialchars(html_entity_decode($tx, ENT_QUOTES | ENT_XML1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            }
            if ($t === '') {
                continue;
            }
            $sc = [];
            if (preg_match('/<w:b\s*\/>/', $rXml)) {
                $sc[] = 'font-weight:bold';
            }
            if (preg_match('/<w:i\s*\/>/', $rXml)) {
                $sc[] = 'font-style:italic';
            }
            if (preg_match('/<w:sz\s+w:val="(\d+)"/', $rXml, $m)) {
                $sc[] = 'font-size:'.round((int) $m[1] / 2, 1).'pt';
            }
            $runs .= $sc ? '<span style="'.implode(';', $sc).'">'.$t.'</span>' : $t;
        }

        $attr = $css ? ' style="'.implode(';', $css).'"' : '';

        return '<p'.$attr.'>'.($runs ?: '&nbsp;')."</p>\n";
    }

    private function fbTable(string $tblXml): string
    {
        $html = '<table style="border-collapse:collapse;width:100%;margin:6pt 0">';

        preg_match_all('/<w:tr\b[^>]*>(.*?)<\/w:tr>/s', $tblXml, $rows);
        foreach ($rows[1] as $rowXml) {
            $html .= '<tr>';
            preg_match_all('/<w:tc\b[^>]*>(.*?)<\/w:tc>/s', $rowXml, $cells);
            foreach ($cells[1] as $cellXml) {
                $inner = '';
                preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $cellXml, $ps);
                foreach ($ps[0] as $pXml) {
                    $inner .= $this->fbPara($pXml);
                }
                $html .= '<td style="border:1px solid #999;padding:3pt 5pt;vertical-align:top">'.$inner.'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    // ─── Helpers ───

    private function getChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return $node;
            }
        }

        return null;
    }

    private function wrapHtml(string $body): string
    {
        $mt = round($this->pageMargins['top'] / 20, 1);
        $mr = round($this->pageMargins['right'] / 20, 1);
        $mb = round($this->pageMargins['bottom'] / 20, 1);
        $ml = round($this->pageMargins['left'] / 20, 1);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: {$mt}pt {$mr}pt {$mb}pt {$ml}pt; }
  body { font-family: DejaVu Sans, Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.15; color: #000; margin: 0; padding: 0; }
  p { margin: 0 0 4pt 0; orphans: 2; widows: 2; }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; }
  td p { margin: 1pt 0; }
  img { max-width: 100%; }
</style>
</head>
<body>{$body}</body>
</html>
HTML;
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use setasign\Fpdi\Fpdi;

class PdfProtector
{
    public function protect(string $pdfAbsPath, string $registrationNumber = ''): ?string
    {
        if (! Setting::get('pdf_protection_enabled', true)) {
            return $pdfAbsPath;
        }

        try {
            $outputPath = preg_replace('/\.pdf$/i', '-protected.pdf', $pdfAbsPath);

            $pdf = new \TCPDF();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pageCount = $this->getPageCount($pdfAbsPath);
            if ($pageCount === 0) {
                return $pdfAbsPath;
            }

            $fpdi = new Fpdi();

            $importedPageCount = $fpdi->setSourceFile($pdfAbsPath);
            for ($i = 1; $i <= $importedPageCount; $i++) {
                $templateId = $fpdi->importPage($i);
                $size = $fpdi->getTemplateSize($templateId);
                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $fpdi->useTemplate($templateId);

                if ($registrationNumber) {
                    $fpdi->SetFont('Helvetica', '', 8);
                    $fpdi->SetTextColor(180, 180, 180);
                    $fpdi->SetXY($size['width'] - 60, $size['height'] - 10);
                    $fpdi->Cell(55, 5, $registrationNumber, 0, 0, 'R');
                }
            }

            $fpdi->SetProtection(
                ['print', 'copy'],
                '',
                null,
                0
            );

            $fpdi->Output($outputPath, 'F');

            if (file_exists($outputPath) && filesize($outputPath) > 0) {
                rename($outputPath, $pdfAbsPath);
                return $pdfAbsPath;
            }

            return $pdfAbsPath;
        } catch (\Throwable $e) {
            \Log::warning('PDF protection failed: ' . $e->getMessage());
            return $pdfAbsPath;
        }
    }

    private function getPageCount(string $pdfPath): int
    {
        try {
            $fpdi = new Fpdi();
            return $fpdi->setSourceFile($pdfPath);
        } catch (\Throwable) {
            return 0;
        }
    }
}

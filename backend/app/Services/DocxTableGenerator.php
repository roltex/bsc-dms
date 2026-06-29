<?php

namespace App\Services;

class DocxTableGenerator
{
    /**
     * Generate OOXML <w:tbl> markup from a table definition and filled rows.
     *
     * @param  array  $columns  [["key" => "title", "label" => "Item Name", "source" => "inventory:title"], ...]
     * @param  array  $rows     [["title" => "Laptop", "price" => "1200", ...], ...]
     * @return string  OOXML fragment
     */
    public static function generate(array $columns, array $rows): string
    {
        if (empty($columns)) {
            return '';
        }

        $colCount = count($columns);
        $cellWidthTwips = intdiv(9000, max($colCount, 1));

        $xml = '<w:tbl>';

        $xml .= '<w:tblPr>';
        $xml .= '<w:tblStyle w:val="TableGrid"/>';
        $xml .= '<w:tblW w:w="0" w:type="auto"/>';
        $xml .= '<w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $border) {
            $xml .= '<w:'.$border.' w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
        }
        $xml .= '</w:tblBorders>';
        $xml .= '<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>';
        $xml .= '</w:tblPr>';

        $xml .= '<w:tblGrid>';
        for ($i = 0; $i < $colCount; $i++) {
            $xml .= '<w:gridCol w:w="'.$cellWidthTwips.'"/>';
        }
        $xml .= '</w:tblGrid>';

        $xml .= '<w:tr>';
        foreach ($columns as $col) {
            $label = htmlspecialchars($col['label'] ?? $col['key'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<w:tc>';
            $xml .= '<w:tcPr><w:tcW w:w="'.$cellWidthTwips.'" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="D9E2F3"/></w:tcPr>';
            $xml .= '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>';
            $xml .= '<w:r><w:rPr><w:b/><w:sz w:val="20"/></w:rPr>';
            $xml .= '<w:t xml:space="preserve">'.$label.'</w:t>';
            $xml .= '</w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';

        foreach ($rows as $rowIdx => $row) {
            $xml .= '<w:tr>';
            foreach ($columns as $col) {
                $key = $col['key'] ?? '';
                $value = '';
                if ($key === '#') {
                    $value = (string) ($rowIdx + 1);
                } elseif (isset($row[$key])) {
                    $value = (string) $row[$key];
                }
                $safeVal = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<w:tc>';
                $xml .= '<w:tcPr><w:tcW w:w="'.$cellWidthTwips.'" w:type="dxa"/></w:tcPr>';
                $xml .= '<w:p><w:r><w:rPr><w:sz w:val="20"/></w:rPr>';
                $xml .= '<w:t xml:space="preserve">'.$safeVal.'</w:t>';
                $xml .= '</w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        $xml .= '</w:tbl>';

        return $xml;
    }
}

<?php

namespace App\Services;

class DocxTableReplacer
{
    /**
     * @param  string  $xml       The raw word/document.xml content
     * @param  array   $tableData ['TABLE_NAME' => [['COL1' => 'val', ...], ...]]
     * @return string  The modified XML
     */
    public static function replaceTables(string $xml, array $tableData): string
    {
        foreach ($tableData as $tableName => $rows) {
            if (! is_array($rows) || empty($rows)) {
                continue;
            }

            $xml = self::replaceTable($xml, strtoupper(trim($tableName)), $rows);
        }

        return $xml;
    }

    private static function replaceTable(string $xml, string $tableName, array $rows): string
    {
        $placeholder = '{{'.$tableName.'.';

        if (! preg_match_all('/<w:tr\b[^>]*>.*?<\/w:tr>/s', $xml, $trMatches)) {
            return $xml;
        }

        $templateRow = null;
        foreach ($trMatches[0] as $tr) {
            $trText = self::extractText($tr);
            if (str_contains($trText, $placeholder)) {
                $templateRow = $tr;
                break;
            }
        }

        if (! $templateRow) {
            return $xml;
        }

        $mergedTemplate = self::mergeCellPlaceholders($templateRow, $tableName);

        $newRows = '';
        foreach ($rows as $rowIdx => $rowData) {
            $newRow = $mergedTemplate;
            $rowNum = $rowIdx + 1;

            $newRow = str_replace(
                '{{'.$tableName.'.#}}',
                htmlspecialchars((string) $rowNum, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $newRow
            );

            if (is_array($rowData)) {
                foreach ($rowData as $col => $val) {
                    $colUpper = strtoupper(trim($col));
                    $colPlaceholder = '{{'.$tableName.'.'.$colUpper.'}}';
                    $safeVal = htmlspecialchars((string) $val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $newRow = str_replace($colPlaceholder, $safeVal, $newRow);
                }
            }

            $newRow = preg_replace('/\{\{'.preg_quote($tableName, '/').'\.[A-Z0-9_#]+\}\}/', '', $newRow);

            $newRows .= $newRow;
        }

        $xml = str_replace($templateRow, $newRows, $xml);

        return $xml;
    }

    /**
     * For each table cell in the row, if the cell's text contains a placeholder
     * for this table, collapse all runs in that cell into a single clean run.
     */
    private static function mergeCellPlaceholders(string $rowXml, string $tableName): string
    {
        $prefix = '{{'.$tableName.'.';

        return preg_replace_callback(
            '/<w:tc\b[^>]*>.*?<\/w:tc>/s',
            function ($cellMatch) use ($prefix) {
                $cellXml = $cellMatch[0];
                $cellText = self::extractText($cellXml);

                if (! str_contains($cellText, $prefix)) {
                    return $cellXml;
                }

                // Extract the cell properties (<w:tcPr>...</w:tcPr>)
                $tcPr = '';
                if (preg_match('/(<w:tcPr\b.*?<\/w:tcPr>)/s', $cellXml, $tcPrMatch)) {
                    $tcPr = $tcPrMatch[1];
                }

                // Extract the first run's formatting (<w:rPr>...</w:rPr>) to preserve style
                $rPr = '';
                if (preg_match('/<w:rPr>(.*?)<\/w:rPr>/s', $cellXml, $rPrMatch)) {
                    $rPr = '<w:rPr>'.$rPrMatch[1].'</w:rPr>';
                }

                $safeText = htmlspecialchars($cellText, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                return '<w:tc>'.$tcPr
                    .'<w:p><w:r>'.$rPr
                    .'<w:t xml:space="preserve">'.$safeText.'</w:t>'
                    .'</w:r></w:p></w:tc>';
            },
            $rowXml
        ) ?? $rowXml;
    }

    private static function extractText(string $xml): string
    {
        $text = strip_tags($xml);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

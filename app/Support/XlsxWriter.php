<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Penulis berkas Excel (.xlsx / SpreadsheetML) minimal berbasis ZipArchive,
 * tanpa dependensi tambahan (FR-04).
 */
class XlsxWriter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     * @return string Path berkas sementara .xlsx
     */
    public static function create(string $sheetTitle, array $headings, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas Excel.');
        }

        $xmlHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $zip->addFromString('[Content_Types].xml', $xmlHeader
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', $xmlHeader
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', $xmlHeader
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape(mb_substr($sheetTitle, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', $xmlHeader
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', $xmlHeader
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCEEF8"/></patternFill></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>');

        $sheet = $xmlHeader
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .self::row(1, $headings, 1);

        $rowNumber = 2;

        foreach ($rows as $row) {
            $sheet .= self::row($rowNumber++, $row);
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private static function row(int $rowNumber, array $values, int $style = 0): string
    {
        $cells = '';
        $styleAttribute = $style ? ' s="'.$style.'"' : '';

        foreach (array_values($values) as $index => $value) {
            $reference = self::column($index).$rowNumber;

            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'"'.$styleAttribute.'><v>'.$value.'</v></c>';
            } elseif ($value !== null && $value !== '') {
                $cells .= '<c r="'.$reference.'" t="inlineStr"'.$styleAttribute.'><is><t xml:space="preserve">'.self::escape((string) $value).'</t></is></c>';
            }
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private static function column(int $index): string
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal writer for real .xlsx workbooks.
 *
 * The project deliberately ships without a spreadsheet library, so this writes the
 * handful of OOXML parts Excel and LibreOffice actually require. Cells are emitted
 * as inline strings, which keeps the format simple at the cost of a shared-string
 * table — fine for report exports, which are read once and discarded.
 */
final class XlsxWriter
{
    /**
     * Write a single-sheet workbook to $path.
     *
     * @param  list<string>  $headers
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public static function write(string $path, string $sheetName, array $headers, iterable $rows): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف Excel في المسار: '.$path);
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($headers, $rows));

        $zip->close();
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    private static function sheet(array $headers, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // rightToLeft makes the sheet open in the reading direction of the data.
            .'<sheetViews><sheetView rightToLeft="1" workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .self::columnWidths(count($headers))
            .'<sheetData>';

        $xml .= self::row(1, $headers, styleIndex: 1);

        $rowNumber = 2;

        foreach ($rows as $row) {
            $xml .= self::row($rowNumber, $row, styleIndex: 0);
            $rowNumber++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, string|int|float|null>  $values
     */
    private static function row(int $rowNumber, array $values, int $styleIndex): string
    {
        $cells = '';
        $columnIndex = 0;

        foreach ($values as $value) {
            $reference = self::columnName($columnIndex).$rowNumber;
            $style = $styleIndex > 0 ? ' s="'.$styleIndex.'"' : '';

            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
            } else {
                $text = self::escape((string) ($value ?? ''));
                $cells .= '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }

            $columnIndex++;
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private static function columnWidths(int $columns): string
    {
        if ($columns === 0) {
            return '';
        }

        return '<cols><col min="1" max="'.$columns.'" width="22" customWidth="1"/></cols>';
    }

    /**
     * 0-based index to a spreadsheet column name (0 => A, 26 => AA).
     */
    private static function columnName(int $index): string
    {
        $name = '';

        do {
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $name;
    }

    /**
     * Strip the control characters XML forbids, then escape the rest.
     */
    private static function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::sheetName($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    /**
     * Excel rejects sheet names over 31 characters or containing []:*?/\
     */
    private static function sheetName(string $name): string
    {
        $name = str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name);
        $name = mb_substr(trim($name), 0, 31);

        return self::escape($name === '' ? 'Sheet1' : $name);
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * Two cell formats: index 0 is the default, index 1 is the bold header row.
     */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F6F5C"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'<dxfs count="0"/>'
            .'<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            .'</styleSheet>';
    }
}

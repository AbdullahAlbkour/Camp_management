<?php

namespace App\Reports;

use App\Support\XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a report definition into a downloadable file.
 *
 * Rows are pulled with a lazy cursor and, for CSV, written straight to the output
 * stream, so a large export does not have to fit in memory all at once.
 */
class ReportExporter
{
    /**
     * Excel has to be assembled on disk before it can be sent, so it is bounded.
     */
    private const XLSX_ROW_LIMIT = 50000;

    public function csv(ReportDefinition $report): StreamedResponse
    {
        $filename = $report->filename().'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'wb');

            // UTF-8 BOM, without which Excel on Windows renders Arabic as mojibake.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $report->headings());

            foreach ($report->query->cursor() as $row) {
                fputcsv($handle, $report->rowText($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function xlsx(ReportDefinition $report): BinaryFileResponse
    {
        $filename = $report->filename().'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'report_');

        XlsxWriter::write(
            $path,
            $report->label,
            $report->headings(),
            $this->rows($report)
        );

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @return \Generator<int, list<string|int|float|null>>
     */
    private function rows(ReportDefinition $report): \Generator
    {
        $emitted = 0;

        foreach ($report->query->cursor() as $row) {
            if ($emitted >= self::XLSX_ROW_LIMIT) {
                return;
            }

            yield $report->rowValues($row);
            $emitted++;
        }
    }
}

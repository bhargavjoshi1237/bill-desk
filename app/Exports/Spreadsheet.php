<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one or more sheets as an .xlsx workbook or a .csv file.
 * CSV has no sheets, so only the first sheet is written in that format.
 */
final class Spreadsheet
{
    public const FORMATS = ['xlsx', 'csv'];

    /**
     * @param  array<string, array{headings: array<string>, rows: iterable<array<mixed>>}>  $sheets
     */
    public static function download(string $filename, array $sheets, string $format = 'xlsx'): StreamedResponse
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'xlsx';

        return response()->streamDownload(
            fn () => self::write($sheets, $format),
            "{$filename}.{$format}",
            ['Content-Type' => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  array<string, array{headings: array<string>, rows: iterable<array<mixed>>}>  $sheets
     */
    public static function write(array $sheets, string $format, string $path = 'php://output'): void
    {
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);

        $headingStyle = (new Style)->setFontBold();
        $first = true;

        foreach ($sheets as $name => $sheet) {
            if (! $first) {
                if ($writer instanceof CsvWriter) {
                    break;
                }

                $writer->addNewSheetAndMakeItCurrent();
            }

            if ($writer instanceof XlsxWriter) {
                // Excel sheet names are limited to 31 characters and can't contain []:*?/\
                $writer->getCurrentSheet()->setName(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', '', $name), 0, 31));
            }

            $writer->addRow(Row::fromValues($sheet['headings'], $headingStyle));

            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values(array_map(self::cell(...), $row))));
            }

            $first = false;
        }

        $writer->close();
    }

    private static function cell(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum && method_exists($value, 'getLabel') => $value->getLabel(),
            $value instanceof \DateTimeInterface => $value->format('d-m-Y'),
            is_bool($value) => $value ? __('Yes') : __('No'),
            default => $value,
        };
    }
}

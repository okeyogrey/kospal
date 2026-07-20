<?php

namespace App\Support\Productivity;

use App\Enums\SpreadsheetFormat;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

final class SpreadsheetWriter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function writeToPath(string $path, SpreadsheetFormat $format, array $headers, array $rows): void
    {
        $writer = match ($format) {
            SpreadsheetFormat::Csv => new CsvWriter,
            SpreadsheetFormat::Xlsx => new XlsxWriter,
        };

        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function stream(SpreadsheetFormat $format, array $headers, array $rows): void
    {
        $writer = match ($format) {
            SpreadsheetFormat::Csv => new CsvWriter,
            SpreadsheetFormat::Xlsx => new XlsxWriter,
        };

        $writer->openToFile('php://output');

        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function toString(SpreadsheetFormat $format, array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kospal-export-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary export file.');
        }

        try {
            $this->writeToPath($path, $format, $headers, $rows);

            return (string) file_get_contents($path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}

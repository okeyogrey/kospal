<?php

namespace App\Support\Productivity;

use App\Enums\SpreadsheetFormat;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

final class SpreadsheetReader
{
    /**
     * @return array{headers: list<string>, rows: list<array<string, string|null>>}
     */
    public function read(UploadedFile $file, SpreadsheetFormat $format): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $reader = match ($format) {
            SpreadsheetFormat::Csv => new CsvReader,
            SpreadsheetFormat::Xlsx => new XlsxReader,
        };

        $reader->open($path);

        $headers = [];
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = array_map(
                    static fn ($cell) => self::normalizeCell($cell?->getValue()),
                    $row->getCells(),
                );

                if ($rowIndex === 1) {
                    $headers = $this->normalizeHeaders($values);

                    continue;
                }

                if ($this->rowIsEmpty($values)) {
                    continue;
                }

                $rows[] = $this->combineRow($headers, $values);
            }

            break;
        }

        $reader->close();

        if ($headers === []) {
            throw new InvalidArgumentException('The file must include a header row.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string|null>  $values
     * @return list<string>
     */
    private function normalizeHeaders(array $values): array
    {
        return array_map(
            static fn (?string $value) => strtolower(trim((string) $value)),
            $values,
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $values): array
    {
        $combined = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $combined[$header] = $values[$index] ?? null;
        }

        return $combined;
    }

    /**
     * @param  list<string|null>  $values
     */
    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }
}

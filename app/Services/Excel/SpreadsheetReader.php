<?php

namespace App\Services\Excel;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetReader
{
    /**
     * Read the first worksheet into rows keyed by normalized header.
     * Row 1 is the header. Blank rows are skipped. `_row` holds the 1-based sheet row.
     *
     * @return Collection<int, array<string, string|int|null>>
     */
    public function read(string $path): Collection
    {
        $reader = IOFactory::createReaderForFile($path);
        $sheet = $reader->load($path)->getSheet(0);

        $raw = $sheet->toArray(null, true, true, false); // formatted values, 0-indexed
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $raw[0] ?? []);

        return collect(array_slice($raw, 1, null, true))
            ->map(function (array $cells, int $index) use ($headers) {
                $row = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $value = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
                    $row[$header] = $value === '' ? null : $value;
                }
                $row['_row'] = $index + 1;

                return $row;
            })
            ->reject(fn (array $row) => collect($row)->except('_row')->filter(fn ($v) => $v !== null)->isEmpty())
            ->values();
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)->trim()->lower()->replaceMatches('/[\s\-]+/', '_');
    }
}

<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait ReadsSeedCsv
{
    /** @return array<int, array<string, string>> */
    protected function readCsv(string $file): array
    {
        $path = database_path("seeders/data/{$file}");
        $handle = fopen($path, 'r') ?: throw new RuntimeException("Cannot open {$path}");

        $header = fgetcsv($handle, 0, ',', '"', '');
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF"); // strip a UTF-8 BOM if Excel added one

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue; // blank line
            }
            if (count($line) !== count($header)) {
                throw new RuntimeException("{$file}: expected ".count($header).' columns, got '.count($line).': '.implode(',', $line));
            }
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        return $rows;
    }
}

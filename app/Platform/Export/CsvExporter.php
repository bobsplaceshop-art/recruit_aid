<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Export;

use CorporateAid\Core\Response;

/**
 * Builds a CSV from a list of rows and returns it as a Response
 * ready to be streamed to the browser.
 *
 * Rows may be either associative arrays (from a raw DB select) or
 * objects (Model instances). Object properties are read directly.
 *
 * Usage:
 *   return $exporter->export('candidates', $rows, [
 *       'code'       => 'Code',
 *       'first_name' => 'First Name',
 *       'email'      => 'Email',
 *   ]);
 */
final class CsvExporter
{
    /**
     * @param string                                     $entity      Slug used in the filename
     * @param array<int, array<string, mixed>|object>    $rows
     * @param array<string, string>                      $columns     key => display label
     */
    public function export(string $entity, array $rows, array $columns): Response
    {
        $csv = $this->buildCsv($rows, $columns);

        return Response::text($csv)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->filename($entity) . '"')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }

    /**
     * @param array<int, array<string, mixed>|object> $rows
     * @param array<string, string>                   $columns
     */
    public function buildCsv(array $rows, array $columns): string
    {
        $out = fopen('php://temp', 'w+');
        if ($out === false) return '';

        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        fputcsv($out, array_values($columns));

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = $this->cell($row, $key);
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** @param array<string, mixed>|object $row */
    private function cell(array|object $row, string $key): string
    {
        $value = is_array($row)
            ? ($row[$key] ?? '')
            : (property_exists($row, $key) ? $row->$key : '');

        if ($value === null) return '';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_scalar($value)) return (string) $value;
        return '';
    }

    private function filename(string $entity): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '-', $entity) ?? 'export';
        return $safe . '-' . date('Y-m-d-His') . '.csv';
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportService
{
    public function xlsx(string $filename, string $sheetTitle, array $headings, array $rows)
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($sheetTitle));
        $this->writeSheet($sheet, $headings, $rows);
        return $this->downloadXlsx($book, $filename);
    }

    /** @param list<array{title:string,headings:array,rows:array}> $sheets */
    public function xlsxSheets(string $filename, array $sheets)
    {
        if ($sheets === []) throw new \InvalidArgumentException('At least one worksheet is required.');
        $book = new Spreadsheet();
        $first = true;
        $usedTitles = [];
        foreach ($sheets as $item) {
            if ($first) {
                $sheet = $book->getActiveSheet();
                $first = false;
            } else {
                $sheet = $book->createSheet();
            }
            $title = $this->sheetTitle((string) ($item['title'] ?? 'Report'));
            $base = $title;
            $suffix = 2;
            while (isset($usedTitles[mb_strtolower($title)])) {
                $tail = '-' . $suffix++;
                $title = mb_substr($base, 0, 31 - mb_strlen($tail)) . $tail;
            }
            $usedTitles[mb_strtolower($title)] = true;
            $sheet->setTitle($title);
            $this->writeSheet($sheet, $item['headings'] ?? [], $item['rows'] ?? []);
        }
        return $this->downloadXlsx($book, $filename);
    }

    public function pdf(string $filename, string $title, array $rows, array $filters = [], array $charts = [])
    {
        $html = view('pdf/report', [
            'title' => $title,
            'rows' => $rows,
            'filters' => $filters,
            'charts' => $charts,
            'centerName' => setting('ChildCare.center_name') ?? 'Barangay ChildCare',
            'centerLogo' => setting('ChildCare.center_logo'),
            'printedAt' => date('F j, Y g:i A'),
        ]);
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();
        return service('response')->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . basename($filename) . '"')
            ->setBody($dompdf->output());
    }

    private function writeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headings, array $rows): void
    {
        $sheet->freezePane('A2');
        foreach (array_values($headings) as $columnIndex => $value) {
            $sheet->setCellValueExplicit([$columnIndex + 1, 1], (string) $value, DataType::TYPE_STRING);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        foreach (array_values($rows) as $rowIndex => $row) {
            foreach (array_values((array) $row) as $columnIndex => $value) {
                $coordinate = [$columnIndex + 1, $rowIndex + 2];
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                } else {
                    // Explicit string cells prevent spreadsheet formula injection from names and notes.
                    $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
                }
            }
        }
        foreach (range(1, max(1, count($headings))) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }
    }

    private function downloadXlsx(Spreadsheet $book, string $filename)
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Excel export requires the PHP ZIP extension. Enable ext-zip and retry.');
        }
        $temporaryPath = tempnam(sys_get_temp_dir(), 'childcare-xlsx-');
        if ($temporaryPath === false) throw new \RuntimeException('Unable to prepare the Excel export.');
        try {
            (new Xlsx($book))->save($temporaryPath);
            $response = service('response')->download($temporaryPath, null)->setFileName(basename($filename));
            register_shutdown_function(static fn () => @unlink($temporaryPath));
            return $response;
        } catch (\Throwable $e) {
            @unlink($temporaryPath);
            throw $e;
        }
    }

    private function sheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/u', '-', trim($title)) ?: 'Report';
        return mb_substr($title, 0, 31);
    }
}

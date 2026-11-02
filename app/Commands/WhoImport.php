<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class WhoImport extends BaseCommand
{
    protected $group = 'ChildCare';
    protected $name = 'who:import';
    protected $description = 'Import official WHO LMS growth reference tables from writable/who.';

    public function run(array $params): int
    {
        $directory = WRITEPATH . 'who';
        $files = array_merge(glob($directory . '/*.csv') ?: [], glob($directory . '/*.txt') ?: []);
        if ($files === []) {
            CLI::error('No WHO TXT/CSV files found. Place official WHO expanded LMS tables in writable/who/ and include indicator, sex, x_value (or month/age/length/height), L, M, and S columns.');
            CLI::write('The importer does not create or approximate reference values.');
            return EXIT_ERROR;
        }
        $db = db_connect(); $imported = 0; $now = date('Y-m-d H:i:s');
        $invalidFiles = 0;
        foreach ($files as $file) {
            $handle = fopen($file, 'rb');
            if ($handle === false) { CLI::error('Cannot read ' . basename($file)); continue; }
            $first = fgets($handle); rewind($handle);
            $delimiter = substr_count((string) $first, "\t") > substr_count((string) $first, ',') ? "\t" : ',';
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false) { fclose($handle); continue; }
            $headers = array_map(static fn ($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h))), $headers);
            $index = array_flip($headers); $fileMeta = $this->metadataFromFilename(basename($file));
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($row) !== count($headers)) continue;
                $data = array_combine($headers, $row);
                $indicator = strtolower((string) ($data['indicator'] ?? $fileMeta['indicator'] ?? ''));
                $sex = strtolower((string) ($data['sex'] ?? $fileMeta['sex'] ?? ''));
                $x = $data['x_value'] ?? $data['age_days'] ?? $data['month'] ?? $data['months'] ?? $data['age'] ?? $data['length'] ?? $data['height'] ?? null;
                $l = $data['l'] ?? $data['l_value'] ?? null; $m = $data['m'] ?? $data['m_value'] ?? null; $s = $data['s'] ?? $data['s_value'] ?? null;
                if (! in_array($indicator, ['wfa', 'lhfa', 'wfl', 'wfh', 'bfa'], true) || ! in_array($sex, ['male', 'female'], true) || ! is_numeric($x) || ! is_numeric($l) || ! is_numeric($m) || ! is_numeric($s)) continue;
                $rows[] = ['indicator' => $indicator, 'sex' => $sex, 'x_value' => (float) $x, 'l' => (float) $l, 'm' => (float) $m, 's' => (float) $s, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null];
            }
            fclose($handle);
            if ($rows === []) { CLI::error('No valid LMS rows found in ' . basename($file) . '. Include indicator and sex columns or use an explicitly named indicator/sex file.'); $invalidFiles++; continue; }
            $db->transBegin();
            foreach (array_chunk($rows, 500) as $chunk) {
                foreach ($chunk as $row) {
                    $db->table('who_growth_references')->where(['indicator' => $row['indicator'], 'sex' => $row['sex'], 'x_value' => $row['x_value']])->delete();
                    $db->table('who_growth_references')->insert($row);
                }
            }
            $db->transComplete();
            if ($db->transStatus() === false) { CLI::error('Import failed for ' . basename($file)); continue; }
            foreach(['wfa','lhfa','wfl','wfh','bfa'] as $indicator)foreach(['male','female'] as $sex)cache()->delete('who-lms-'.$indicator.'-'.$sex);
            $imported += count($rows); CLI::write('Imported ' . count($rows) . ' WHO rows from ' . basename($file));
        }
        CLI::write('Total imported LMS rows: ' . $imported);
        return $imported > 0 && $invalidFiles === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function metadataFromFilename(string $name): array
    {
        $text = strtolower($name);
        $indicator = str_contains($text, 'lhfa') || str_contains($text, 'height-for-age') || str_contains($text, 'length-for-age') ? 'lhfa'
            : (str_contains($text, 'wfl') || str_contains($text, 'weight-for-length') ? 'wfl'
                : (str_contains($text, 'wfh') || str_contains($text, 'weight-for-height') ? 'wfh'
                    : (str_contains($text, 'bfa') || str_contains($text, 'bmi') ? 'bfa'
                        : (str_contains($text, 'wfa') || str_contains($text, 'weight-for-age') ? 'wfa' : null))));
        $sex = str_contains($text, 'girl') || str_contains($text, 'female') ? 'female' : (str_contains($text, 'boy') || str_contains($text, 'male') ? 'male' : null);
        return compact('indicator', 'sex');
    }
}

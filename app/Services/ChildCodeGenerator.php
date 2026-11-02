<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ChildCodeGenerator
{
    public function __construct(private readonly ?BaseConnection $db = null) {}

    public function next(?int $year = null): string
    {
        $db = $this->db ?? db_connect();
        $year ??= (int) date('Y');
        $db->transBegin();
        $db->query('INSERT IGNORE INTO id_sequences (name, year, last_value) VALUES (?, ?, 0)', ['child', $year]);
        $row = $db->query('SELECT last_value FROM id_sequences WHERE name = ? AND year = ? FOR UPDATE', ['child', $year])->getRowArray();
        if ($row !== null) {
            $next = (int) $row['last_value'] + 1;
            $db->table('id_sequences')->where(['name' => 'child', 'year' => $year])->update(['last_value' => $next]);
            $row['last_value'] = $next;
        }
        if ($row === null || $db->transStatus() === false) {
            $db->transRollback();
            throw new \RuntimeException('Unable to allocate a unique Child ID.');
        }
        $db->transCommit();
        return sprintf('CH-%04d-%04d', $year, (int) $row['last_value']);
    }
}

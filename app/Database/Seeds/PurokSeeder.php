<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
final class PurokSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $number) {
            if ($this->db->table('puroks')->where('name', 'Purok ' . $number)->countAllResults() > 0) continue;
            $this->db->table('puroks')->insert(['name' => 'Purok ' . $number, 'is_active' => 1, 'sort_order' => $number, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
}

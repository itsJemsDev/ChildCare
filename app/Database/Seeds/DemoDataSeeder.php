<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\ImmunizationScheduler;
use CodeIgniter\Database\Seeder;

/**
 * Adds clearly labeled, synthetic records for local UI demonstrations.
 * Run explicitly with `php spark db:seed DemoDataSeeder` in development only.
 */
final class DemoDataSeeder extends Seeder
{
    private const DEMO_NOTE = 'Synthetic demo record — not a real patient and not for clinical use.';

    public function run(): void
    {
        if (ENVIRONMENT !== 'development') {
            throw new \RuntimeException('Demo data may only be seeded when CI_ENVIRONMENT is development.');
        }

        $this->call(PurokSeeder::class);
        $this->call(VaccineSeeder::class);

        $puroks = $this->db->table('puroks')->select('id, name')->where('is_active', 1)->get()->getResultArray();
        if ($puroks === []) {
            throw new \RuntimeException('Seed the Purok list before loading demo records.');
        }
        $purokIds = array_column($puroks, 'id', 'name');
        $doses = $this->db->table('vaccine_doses')->where('is_active', 1)->orderBy('recommended_age_days')->get()->getResultArray();
        if ($doses === []) {
            throw new \RuntimeException('Seed the vaccine catalog before loading demo records.');
        }

        $profiles = [
            ['age_days' => 90, 'sex' => 'female', 'purok' => 1, 'category' => 'A', 'z' => [0.1, 0.2, 0.1, 0.0], 'complete_through' => 42, 'missed' => []],
            ['age_days' => 180, 'sex' => 'male', 'purok' => 2, 'category' => 'B', 'z' => [-2.3, -1.4, -2.2, -2.3], 'complete_through' => 70, 'missed' => []],
            ['age_days' => 300, 'sex' => 'female', 'purok' => 3, 'category' => 'A', 'z' => [-1.4, -0.8, -0.9, -1.4], 'complete_through' => 98, 'missed' => []],
            ['age_days' => 540, 'sex' => 'male', 'purok' => 4, 'category' => 'C', 'z' => [0.4, 0.2, 1.3, 1.5], 'complete_through' => 270, 'missed' => []],
            ['age_days' => 900, 'sex' => 'female', 'purok' => 5, 'category' => 'A', 'z' => [-0.2, 0.1, -0.3, -0.2], 'complete_through' => 365, 'missed' => ['MMR2']],
            ['age_days' => 1500, 'sex' => 'male', 'purok' => 1, 'category' => 'A', 'z' => [0.2, 0.0, 0.1, 0.2], 'complete_through' => 9999, 'missed' => []],
        ];

        $now = date('Y-m-d H:i:s');
        $today = new \DateTimeImmutable('today');
        $scheduler = new ImmunizationScheduler($this->db);
        $created = 0;

        $this->db->transBegin();
        try {
            foreach ($profiles as $index => $profile) {
                $number = $index + 1;
                $childCode = sprintf('DEMO-%03d', $number);
                if ($this->db->table('children')->where('child_code', $childCode)->countAllResults() > 0) {
                    continue;
                }

                $purokId = $purokIds['Purok ' . $profile['purok']] ?? reset($purokIds);
                $birthdate = $today->modify('-' . $profile['age_days'] . ' days')->format('Y-m-d');
                $guardianId = $this->insert('guardians', [
                    'first_name' => sprintf('Demo Guardian %02d', $number),
                    'last_name' => 'Sample',
                    'mobile_no' => sprintf('09000000%03d', $number),
                    'email' => sprintf('guardian%02d@example.invalid', $number),
                    'address' => sprintf('Demo address, Purok %d', $profile['purok']),
                    'purok_id' => $purokId,
                    'sms_opt_in' => 0,
                    'created_at' => $now,
                ]);

                $ageYears = $profile['age_days'] / 365.25;
                $category = $profile['category'];
                $z = $profile['z'];
                $childId = $this->insert('children', [
                    'child_code' => $childCode,
                    'first_name' => sprintf('Demo Child %02d', $number),
                    'last_name' => 'Sample',
                    'nickname' => sprintf('Demo %02d', $number),
                    'sex' => $profile['sex'],
                    'birthdate' => $birthdate,
                    'birth_order' => 1,
                    'birth_weight_kg' => number_format(2.8 + (($number % 4) * 0.2), 2, '.', ''),
                    'purok_id' => $purokId,
                    'address' => sprintf('Demo address, Purok %d', $profile['purok']),
                    'remarks' => self::DEMO_NOTE,
                    'registration_status' => 'verified',
                    'verified_at' => $now,
                    'nutrition_category' => $category,
                    'nutrition_status' => $this->nutritionStatus($category, $z),
                    'immunization_status' => 'not_immunized',
                    'is_fic' => 0,
                    'registered_by' => null,
                    'created_at' => $now,
                ]);

                $this->db->table('child_guardian')->insert([
                    'child_id' => $childId,
                    'guardian_id' => $guardianId,
                    'relationship' => 'Mother',
                    'is_primary' => 1,
                ]);

                $this->seedImmunizations($childId, $birthdate, $profile, $doses, $now);
                $scheduler->recomputeChild($childId);
                $this->seedGrowth($childId, $profile, $today, $now);
                $this->seedCheckup($childId, $number, $today, $now);
                $created++;
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            throw new \RuntimeException('Could not seed the demo records.');
        }
        $this->db->transCommit();
        cache()->delete('staff-dashboard-v4');

        if ($created === 0) {
            log_message('info', 'Demo records already exist; no rows were added.');
            return;
        }

        log_message('info', 'Added {count} synthetic demo child profiles and related health records.', ['count' => $created]);
    }

    private function seedImmunizations(int $childId, string $birthdate, array $profile, array $doses, string $now): void
    {
        foreach ($doses as $dose) {
            $recommendedDays = (int) $dose['recommended_age_days'];
            $dueDate = (new \DateTimeImmutable($birthdate))->modify('+' . $recommendedDays . ' days');
            $status = 'pending';
            $dateAdministered = null;
            $missed = in_array($dose['code'], $profile['missed'], true);

            if (! $missed && $recommendedDays <= $profile['complete_through'] && $dueDate <= new \DateTimeImmutable('today')) {
                $status = 'completed';
                $dateAdministered = $dueDate->format('Y-m-d');
            }

            $this->db->table('immunizations')->insert([
                'child_id' => $childId,
                'vaccine_dose_id' => $dose['id'],
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $status,
                'date_administered' => $dateAdministered,
                'reminder_opt_in' => 0,
                'administered_by_name' => $status === 'completed' ? 'Demo Health Worker' : null,
                'facility' => $status === 'completed' ? 'Demo Health Center' : null,
                'lot_number' => $status === 'completed' ? 'DEMO-LOT' : null,
                'remarks' => self::DEMO_NOTE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedGrowth(int $childId, array $profile, \DateTimeImmutable $today, string $now): void
    {
        $ageDays = (int) $profile['age_days'];
        $monthsAgo = $ageDays >= 300 ? [6, 3, 0] : ($ageDays >= 90 ? [1, 0] : [0]);
        [$wfa, $lhfa, $wfh, $bfa] = $profile['z'];
        $nutritionStatus = $this->nutritionStatus($profile['category'], $profile['z']);

        foreach ($monthsAgo as $months) {
            $visitAgeDays = max(0, $ageDays - (int) round($months * 30.4375));
            $visitDate = $today->modify('-' . $months . ' months')->format('Y-m-d');
            $visitAgeYears = $visitAgeDays / 365.25;
            $height = 50 + (17 * min($visitAgeYears, 2)) + (6.5 * max(0, $visitAgeYears - 2));
            $weight = 3.2 + (2.25 * $visitAgeYears);
            $height = round($height, 1);
            $weight = round($weight, 2);
            $bmi = round($weight / (($height / 100) ** 2), 2);
            $percentiles = array_map(static fn (float $score): float => round(max(0.1, min(99.9, 50 + ($score * 19.1))), 2), [$wfa, $lhfa, $wfh, $bfa]);

            $this->db->table('growth_records')->insert([
                'child_id' => $childId,
                'visit_date' => $visitDate,
                'age_days' => $visitAgeDays,
                'weight_kg' => $weight,
                'height_cm' => $height,
                'measured_position' => $visitAgeDays < 730 ? 'recumbent' : 'standing',
                'muac_cm' => round(10.5 + ($visitAgeYears * 1.1), 1),
                'head_circ_cm' => round(34 + min(15, $visitAgeYears * 2.2), 1),
                'bmi' => $bmi,
                'wfa_z' => $wfa,
                'lhfa_z' => $lhfa,
                'wfh_z' => $wfh,
                'bfa_z' => $bfa,
                'wfa_pct' => $percentiles[0],
                'lhfa_pct' => $percentiles[1],
                'wfh_pct' => $percentiles[2],
                'bfa_pct' => $percentiles[3],
                'wfa_status' => 'Synthetic demo value',
                'lhfa_status' => 'Synthetic demo value',
                'wfh_status' => 'Synthetic demo value',
                'nutrition_status' => $nutritionStatus,
                'nutrition_category' => $profile['category'],
                'is_at_risk' => $nutritionStatus === 'At Risk' ? 1 : 0,
                'remarks' => self::DEMO_NOTE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedCheckup(int $childId, int $number, \DateTimeImmutable $today, string $now): void
    {
        $checkupDate = $today->modify('-' . ($number * 3) . ' days')->format('Y-m-d');
        $this->db->table('checkups')->insert([
            'child_id' => $childId,
            'checkup_date' => $checkupDate,
            'chief_complaint' => 'Routine demonstration entry',
            'diagnosis' => 'Synthetic demo record; no real diagnosis.',
            'findings' => self::DEMO_NOTE,
            'temperature_c' => '36.7',
            'treatment' => 'None — demo only',
            'medicines' => json_encode([], JSON_THROW_ON_ERROR),
            'outcome' => 'Stable',
            'remarks' => self::DEMO_NOTE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('children')->where('id', $childId)->update(['last_checkup_at' => $checkupDate]);
    }

    private function insert(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);
        return (int) $this->db->insertID();
    }

    private function nutritionStatus(string $category, array $z): string
    {
        if ($category === 'B') return 'Mildly Malnourished';
        if ($category === 'C') return 'Overweight';
        if (min($z) >= -2 && min($z) < -1) return 'At Risk';
        return 'Normal';
    }
}

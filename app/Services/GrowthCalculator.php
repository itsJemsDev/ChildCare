<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class GrowthCalculator
{
    public function __construct(private readonly ?BaseConnection $db = null) {}

    public function calculate(array $child, array $measurement): array
    {
        $visitDate=(string)($measurement['visit_date']??'');$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$visitDate);
        if($date===false||$date->format('Y-m-d')!==$visitDate||$visitDate<(string)$child['birthdate'])throw new \InvalidArgumentException('Visit date must be a valid date on or after the child’s birthdate.');
        if($visitDate>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->format('Y-m-d'))throw new \InvalidArgumentException('Visit date cannot be in the future.');
        $birthDate = new \DateTimeImmutable($child['birthdate']);
        $age = $this->ageRules($birthDate, $date);
        $ageDays = $age['age_days'];
        $ageMonths = $age['age_months'];
        $underTwo = $age['under_two'];
        $underFive = $age['under_five'];
        $withinTen = $age['within_ten'];
        $height = (float) $measurement['height_cm'];
        if ($underTwo && ($measurement['measured_position'] ?? 'recumbent') === 'standing') $height += 0.7;
        if (! $underTwo && ($measurement['measured_position'] ?? 'standing') === 'recumbent') $height -= 0.7;
        $weight = (float) $measurement['weight_kg'];
        $bmi = $weight / (($height / 100) ** 2);
        $sex = strtolower((string) $child['sex']);
        $sex = in_array($sex, ['male', 'm', 'boy'], true) ? 'male' : 'female';
        $ageX = $underFive ? $ageDays : $ageMonths;
        // WHO 2007 weight-for-age references stop at 10 years; older children use height/BMI indicators.
        $wfa = $withinTen ? $this->score('wfa', $sex, $ageX, $weight, true) : ['z' => null, 'percentile' => null];
        $lhfa = $this->score('lhfa', $sex, $ageX, $height, false);
        $weightIndicator = $underTwo ? 'wfl' : ($underFive ? 'wfh' : 'bfa');
        $weightX = $weightIndicator === 'bfa' ? $ageMonths : $height;
        $weightScore = $this->score($weightIndicator, $sex, $weightX, $weight, true);
        $nutrition = (new NutritionClassifier())->classify($wfa['z'], $lhfa['z'], in_array($weightIndicator, ['wfl', 'wfh'], true) ? $weightScore['z'] : null, $weightIndicator === 'bfa' ? $weightScore['z'] : null, (float)(setting('ChildCare.growth_watch_min')??-2), (float)(setting('ChildCare.growth_watch_max')??-1));
        return [
            'age_days' => $ageDays, 'height_cm' => round($height, 1), 'bmi' => round($bmi, 2),
            'wfa_z' => $wfa['z'], 'wfa_pct' => $wfa['percentile'], 'lhfa_z' => $lhfa['z'], 'lhfa_pct' => $lhfa['percentile'],
            'wfh_z' => in_array($weightIndicator, ['wfl', 'wfh'], true) ? $weightScore['z'] : null,
            'wfh_pct' => in_array($weightIndicator, ['wfl', 'wfh'], true) ? $weightScore['percentile'] : null,
            'bfa_z' => $weightIndicator === 'bfa' ? $weightScore['z'] : null,
            'bfa_pct' => $weightIndicator === 'bfa' ? $weightScore['percentile'] : null,
            'wfa_status' => $wfa['z'] === null ? null : $this->status('wfa', $wfa['z']), 'lhfa_status' => $this->status('lhfa', $lhfa['z']),
            'wfh_status' => $this->status($weightIndicator, $weightScore['z']), ...$nutrition,
        ];
    }

    /** @return array{age_days:int,age_months:float,under_two:bool,under_five:bool,within_ten:bool} */
    public function ageRules(\DateTimeImmutable $birthDate, \DateTimeImmutable $visitDate): array
    {
        $ageDays = (int) $birthDate->diff($visitDate)->days;
        // Calendar anniversaries keep children on their birthday in the new group;
        // average-month conversion can otherwise place them just below a boundary.
        return [
            'age_days' => $ageDays,
            'age_months' => $ageDays / 30.4375,
            'under_two' => $visitDate < $birthDate->modify('+24 months'),
            'under_five' => $visitDate < $birthDate->modify('+60 months'),
            'within_ten' => $visitDate <= $birthDate->modify('+120 months'),
        ];
    }

    public function lmsZ(float $value, array $lms, bool $restricted = false): float
    {
        $l = (float) $lms['l']; $m = (float) $lms['m']; $s = (float) $lms['s'];
        if ($m <= 0 || $s <= 0 || $value <= 0) throw new \InvalidArgumentException('LMS values and measurements must be positive.');
        $z = abs($l) < 1.0e-8 ? log($value / $m) / $s : ((($value / $m) ** $l) - 1) / ($l * $s);
        if (! $restricted || abs($z) <= 3) return $z;
        if (abs($l) < 1.0e-8) {
            $sd3 = $m * exp(3 * $s); $sd2 = $m * exp(2 * $s); $sd3neg = $m * exp(-3 * $s); $sd2neg = $m * exp(-2 * $s);
        } else {
            $sd3 = $m * ((1 + 3 * $l * $s) ** (1 / $l)); $sd2 = $m * ((1 + 2 * $l * $s) ** (1 / $l));
            $sd3neg = $m * ((1 - 3 * $l * $s) ** (1 / $l)); $sd2neg = $m * ((1 - 2 * $l * $s) ** (1 / $l));
        }
        if ($z > 3) return 3 + ($value - $sd3) / ($sd3 - $sd2);
        return -3 + ($value - $sd3neg) / ($sd2neg - $sd3neg);
    }

    private function score(string $indicator, string $sex, float $x, float $value, bool $restricted): array
    {
        $cacheKey='who-lms-'.$indicator.'-'.$sex;$rows=cache()->get($cacheKey);
        if(!is_array($rows)){$rows=($this->db ?? db_connect())->table('who_growth_references')->select('x_value, l, m, s')->where('indicator', $indicator)->where('sex', $sex)->orderBy('x_value')->get()->getResultArray();cache()->save($cacheKey,$rows,86400);}
        if ($rows === []) throw new \RuntimeException('WHO reference data is missing for ' . $indicator . ' (' . $sex . '). Import the official tables with php spark who:import.');
        if($x<(float)$rows[0]['x_value']||$x>(float)$rows[count($rows)-1]['x_value'])throw new \RuntimeException('The WHO reference table does not cover this age or measurement range.');
        $lower = $rows[0]; $upper = $rows[count($rows) - 1];
        foreach ($rows as $row) {
            if ((float) $row['x_value'] <= $x) $lower = $row;
            if ((float) $row['x_value'] >= $x) { $upper = $row; break; }
        }
        $ratio = (float) $upper['x_value'] === (float) $lower['x_value'] ? 0.0 : ($x - (float) $lower['x_value']) / ((float) $upper['x_value'] - (float) $lower['x_value']);
        $lms = [];
        foreach (['l', 'm', 's'] as $key) $lms[$key] = (float) $lower[$key] + (((float) $upper[$key] - (float) $lower[$key]) * $ratio);
        $z = $this->lmsZ($value, $lms, $restricted);
        return ['z' => round($z, 2), 'percentile' => round($this->normalCdf($z) * 100, 2)];
    }

    private function normalCdf(float $z): float
    {
        $sign = $z < 0 ? -1 : 1; $x = abs($z) / sqrt(2); $t = 1 / (1 + 0.3275911 * $x);
        $erf = 1 - (((((1.061405429 * $t - 1.453152027) * $t) + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);
        return 0.5 * (1 + $sign * $erf);
    }

    private function status(string $indicator, float $z): string
    {
        if ($indicator === 'lhfa') return $z < -3 ? 'Severely Stunted' : ($z < -2 ? 'Stunted' : ($z > 3 ? 'Tall' : 'Normal'));
        if ($indicator === 'wfa') return $z < -3 ? 'Severely Underweight' : ($z < -2 ? 'Underweight' : ($z > 2 ? 'Above normal (check WFH/BMI)' : 'Normal'));
        if ($indicator === 'bfa') return $z < -3 ? 'Severe Thinness' : ($z < -2 ? 'Thinness' : ($z > 2 ? 'Obese' : ($z > 1 ? 'Overweight' : 'Normal')));
        return $z < -3 ? 'Severely Wasted' : ($z < -2 ? 'Wasted' : ($z > 3 ? 'Obese' : ($z > 2 ? 'Overweight' : 'Normal')));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

final class NutritionClassifier
{
    public function classify(?float $wfa, ?float $lhfa, ?float $weightForHeight, ?float $bfa = null, float $watchMin = -2.0, float $watchMax = -1.0): array
    {
        $weightZ = $bfa ?? $weightForHeight;
        $malnutrition = ($wfa !== null && $wfa < -2) || ($lhfa !== null && $lhfa < -2) || ($weightZ !== null && $weightZ < -2);
        $obese = $bfa !== null ? $bfa > 1 : ($weightForHeight !== null && $weightForHeight > 2);
        $category = $malnutrition ? 'B' : ($obese ? 'C' : 'A');
        $zScores = array_filter([$wfa, $lhfa, $weightForHeight, $bfa], static fn ($z) => $z !== null);
        $atRisk = $category === 'A' && array_filter($zScores, static fn (float $z) => $z >= $watchMin && $z < $watchMax) !== [];
        $status = 'Normal';
        if ($malnutrition) {
            $worst = min($zScores ?: [0]);
            $status = $worst < -3 ? 'Severely Malnourished' : ($worst < -2 ? 'Mildly Malnourished' : 'Malnourished');
        } elseif ($obese) {
            $status = $bfa !== null ? ($bfa > 2 ? 'Obese' : 'Overweight') : ($weightForHeight > 3 ? 'Obese' : 'Overweight');
        } elseif ($atRisk) {
            $status = 'At Risk';
        }
        return ['category' => $category, 'status' => $status, 'at_risk' => $atRisk, 'malnourished' => $malnutrition, 'obese' => $obese];
    }
}

<?php
declare(strict_types=1);

use App\Services\GrowthCalculator;
use App\Services\NutritionClassifier;
use App\Services\ImmunizationScheduler;
use App\Services\Sms\TemplateRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HealthRulesTest extends CIUnitTestCase
{
    public function testLmsMedianHasZeroZScore(): void
    {
        $this->assertEqualsWithDelta(0.0, (new GrowthCalculator())->lmsZ(10.0, ['l' => 1.0, 'm' => 10.0, 's' => 0.1]), 0.00001);
    }

    public function testLmsTwoStandardDeviationsHasExpectedScore(): void
    {
        $x = 10.0 * (1 + (2 * 1.0 * 0.1)) ** (1 / 1.0);
        $this->assertEqualsWithDelta(2.0, (new GrowthCalculator())->lmsZ($x, ['l' => 1.0, 'm' => 10.0, 's' => 0.1]), 0.00001);
    }

    public function testGrowthAgeTransitionsUseCalendarAnniversaries(): void
    {
        $calculator = new GrowthCalculator();
        $birth = new \DateTimeImmutable('2024-01-01');

        $secondBirthday = $calculator->ageRules($birth, new \DateTimeImmutable('2026-01-01'));
        $fifthBirthday = $calculator->ageRules($birth, new \DateTimeImmutable('2029-01-01'));
        $tenthBirthday = $calculator->ageRules($birth, new \DateTimeImmutable('2034-01-01'));

        $this->assertFalse($secondBirthday['under_two']);
        $this->assertTrue($secondBirthday['under_five']);
        $this->assertFalse($fifthBirthday['under_five']);
        $this->assertTrue($tenthBirthday['within_ten']);
        $this->assertFalse($calculator->ageRules($birth, new \DateTimeImmutable('2034-01-02'))['within_ten']);
    }

    public function testRestrictedLmsAdjustmentIsContinuousAtThreeSd(): void
    {
        $x = 10.0 * (1 + (4 * 1.0 * 0.1));
        $z = (new GrowthCalculator())->lmsZ($x, ['l' => 1.0, 'm' => 10.0, 's' => 0.1], true);
        $this->assertEqualsWithDelta(4.0, $z, 0.00001);
    }

    public function testRestrictedLmsAdjustmentHandlesLowTail(): void
    {
        $x = 10.0 * (1 - (4 * 1.0 * 0.1));
        $z = (new GrowthCalculator())->lmsZ($x, ['l' => 1.0, 'm' => 10.0, 's' => 0.1], true);
        $this->assertEqualsWithDelta(-4.0, $z, 0.00001);
    }

    public function testMalnutritionTakesPrecedenceWhenObesityFlagAlsoApplies(): void
    {
        $result = (new NutritionClassifier())->classify(-2.5, -2.2, 3.4);
        $this->assertSame('B', $result['category']);
        $this->assertTrue($result['malnourished']);
        $this->assertTrue($result['obese']);
    }

    public function testBmiOverweightUsesBmiSpecificThreshold(): void
    {
        $result = (new NutritionClassifier())->classify(0.0, 0.0, null, 1.5);
        $this->assertSame('C', $result['category']);
        $this->assertSame('Overweight', $result['status']);
    }

    public function testGrowthClassificationHonorsWatchAndClinicalBoundaries(): void
    {
        $classifier = new NutritionClassifier();
        $this->assertSame('A', $classifier->classify(-2.0, 0.0, 0.0)['category']);
        $this->assertSame('B', $classifier->classify(-2.01, 0.0, 0.0)['category']);
        $this->assertTrue($classifier->classify(0.0, -1.5, 0.0)['at_risk']);
        $this->assertFalse($classifier->classify(0.0, -1.0, 0.0)['at_risk']);
        $this->assertSame('A', $classifier->classify(0.0, 0.0, null, 1.0)['category']);
        $this->assertSame('Overweight', $classifier->classify(0.0, 0.0, null, 2.0)['status']);
        $this->assertSame('Obese', $classifier->classify(0.0, 0.0, null, 2.01)['status']);
    }

    public function testImmunizationNextDueDateUsesRecommendedAgeOrMinimumInterval(): void
    {
        $scheduler=new ImmunizationScheduler();
        $this->assertSame('2026-04-01',$scheduler->nextDueDate('2026-01-01',90,'2026-02-01',28));
        $this->assertSame('2026-05-01',$scheduler->nextDueDate('2026-01-01',90,'2026-04-01',30));
    }

    public function testImmunizationDisplayStatusesUseDateBoundaries(): void
    {
        $scheduler=new ImmunizationScheduler();
        $this->assertSame('Completed',$scheduler->displayStatus('completed','2026-03-01','2026-03-10'));
        $this->assertSame('Overdue',$scheduler->displayStatus('pending','2026-03-09','2026-03-10'));
        $this->assertSame('Due Soon',$scheduler->displayStatus('pending','2026-03-13','2026-03-10'));
        $this->assertSame('Upcoming',$scheduler->displayStatus('pending','2026-03-14','2026-03-10'));
    }

    public function testImmunizationChildStatusBoundaries(): void
    {
        $scheduler=new ImmunizationScheduler();
        $this->assertSame('complete',$scheduler->childStatus(2,0,4));
        $this->assertSame('partial',$scheduler->childStatus(1,1,4));
        $this->assertSame('not_immunized',$scheduler->childStatus(0,2,4));
        $this->assertSame('not_immunized',$scheduler->childStatus(0,0,0));
    }

    public function testPhoneNormalizationAcceptsPhilippineFormats(): void
    {
        $this->assertSame('639171234567', format_ph_mobile('0917 123 4567'));
        $this->assertSame('639171234567', format_ph_mobile('+63 917 123 4567'));
        $this->assertSame('', format_ph_mobile('12345'));
    }

    public function testSmsSegmentsRespectUnicodeConcatenationLimits(): void
    {
        $renderer = new TemplateRenderer();
        $this->assertSame(1, $renderer->segments(str_repeat('A', 160)));
        $this->assertSame(2, $renderer->segments(str_repeat('A', 161)));
        $this->assertSame(2, $renderer->segments(str_repeat('你', 71)));
    }
}

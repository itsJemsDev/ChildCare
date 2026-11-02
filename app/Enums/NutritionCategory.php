<?php
declare(strict_types=1);
namespace App\Enums;
enum NutritionCategory: string { case Healthy = 'A'; case Malnourished = 'B'; case Obese = 'C'; }

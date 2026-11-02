<?php
declare(strict_types=1);
namespace App\Enums;
enum ImmunizationStatus: string { case Pending = 'pending'; case Completed = 'completed'; case Deferred = 'deferred'; case NotApplicable = 'not_applicable'; }

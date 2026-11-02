<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

if (! function_exists('age_label')) {
    function age_label(string $birthdate, ?string $asOf = null, bool $compact = false): string
    {
        $birth = Time::parse($birthdate, 'Asia/Manila');
        $date = Time::parse($asOf ?? date('Y-m-d'), 'Asia/Manila');
        if ($birth->isAfter($date)) return '0 days';
        $days = (int) $birth->difference($date)->getDays();
        if ($days < 30) return $days . ($compact ? 'd' : ' days');
        $months = (int) $birth->difference($date)->getMonths();
        if ($months < 24) return $months . ($compact ? 'm' : ' mos');
        $years = intdiv($months, 12);
        $remaining = $months % 12;
        return $compact ? $years . 'y ' . $remaining . 'm' : $years . ' yrs ' . $remaining . ' mos';
    }
}

if (! function_exists('format_ph_mobile')) {
    function format_ph_mobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (str_starts_with($digits, '09') && strlen($digits) === 11) return '63' . substr($digits, 1);
        if (str_starts_with($digits, '9') && strlen($digits) === 10) return '63' . $digits;
        if (str_starts_with($digits, '639') && strlen($digits) === 12) return $digits;
        return '';
    }
}

if (! function_exists('format_date')) {
    function format_date(?string $date): string
    {
        return $date ? Time::parse($date, 'Asia/Manila')->toLocalizedString('MMM d, yyyy') : '—';
    }
}

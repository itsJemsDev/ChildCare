<?php
declare(strict_types=1);
namespace App\Services\Sms;
final class TemplateRenderer
{
    public function render(string $template, array $values): string
    {
        $replace = [];
        foreach ($values as $key => $value) $replace['{' . $key . '}'] = (string) $value;
        return strtr($template, $replace);
    }
    public function segments(string $message): int
    {
        $gsmBasic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $gsmExtended = "^{}\\[~]|€";
        $characters = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $units = 0; $gsm = true;
        foreach ($characters as $character) { if (str_contains($gsmBasic, $character)) $units++; elseif (str_contains($gsmExtended, $character)) $units += 2; else { $gsm = false; break; } }
        if (! $gsm) { $units = mb_strlen($message); $limit = $units <= 70 ? 70 : 67; }
        else $limit = $units <= 160 ? 160 : 153;
        return max(1, (int) ceil($units / $limit));
    }
}

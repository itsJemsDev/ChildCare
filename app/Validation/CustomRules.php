<?php
declare(strict_types=1);
namespace App\Validation;

final class CustomRules
{
    public function ph_mobile(string $value): bool
    {
        return format_ph_mobile($value) !== '';
    }

    public function not_future_date(string $value): bool
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('Asia/Manila'));
        return $date!==false&&$date->format('Y-m-d')===$value&&$value<=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }

    public function after_birthdate(string $value,string $params,array $data): bool
    {
        $birthdate=$data[$params]??null;
        return is_string($birthdate)&&$value>=$birthdate;
    }
}

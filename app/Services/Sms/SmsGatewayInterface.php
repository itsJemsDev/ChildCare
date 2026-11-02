<?php
declare(strict_types=1);
namespace App\Services\Sms;
interface SmsGatewayInterface
{
    public function send(string $number, string $message): SmsResult;
    public function sendOtp(string $number, string $code): SmsResult;
    public function balance(): ?int;
    public function status(string $providerId): ?string;
}

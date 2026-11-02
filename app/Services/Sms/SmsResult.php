<?php
declare(strict_types=1);
namespace App\Services\Sms;
final class SmsResult
{
    public function __construct(public readonly bool $success, public readonly ?string $providerId = null, public readonly ?string $error = null, public readonly ?string $status = null) {}
}

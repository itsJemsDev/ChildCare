<?php
declare(strict_types=1);
namespace App\Services\Sms;
final class SmsGatewayFactory
{
    public static function make(): SmsGatewayInterface
    {
        $driver = setting('ChildCare.sms_driver') ?? env('sms.driver', ENVIRONMENT === 'production' ? 'semaphore' : 'log');
        return $driver === 'semaphore' ? new SemaphoreGateway() : new LogGateway();
    }
}

<?php
declare(strict_types=1);
namespace App\Services\Sms;
use CodeIgniter\Log\Logger;
final class LogGateway implements SmsGatewayInterface
{
    public function send(string $number, string $message): SmsResult
    {
        log_message('info', '[SMS LOG DRIVER] To {number}: {message}', ['number' => $number, 'message' => $message]);
        return new SmsResult(true, 'log-' . bin2hex(random_bytes(6)), null, 'Sent');
    }
    public function sendOtp(string $number, string $code): SmsResult { return $this->send($number, 'Your Barangay ChildCare verification code is ' . $code . '. It expires in 5 minutes.'); }
    public function balance(): ?int { return null; }
    public function status(string $providerId): ?string { return 'Sent'; }
}

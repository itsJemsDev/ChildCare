<?php
declare(strict_types=1);
namespace App\Services;
use App\Services\Sms\SmsGatewayFactory;
final class OtpService
{
    public function send(string $mobile, string $purpose, ?int $userId = null): bool
    {
        $number = format_ph_mobile($mobile); if ($number === '') return false;
        $request = service('request'); $throttler = service('throttler');
        if (! $throttler->check('otp-number-' . hash('sha256', $number), 5, HOUR) || ! $throttler->check('otp-ip-' . hash('sha256', $request->getIPAddress()), 5, HOUR)) return false;
        $db = db_connect();
        $recent = $db->table('otp_codes')->where('mobile_no', $number)->where('purpose', $purpose)->where('created_at >=', date('Y-m-d H:i:s', time() - 60))->countAllResults();
        if ($recent > 0) return false;
        $code = (string) random_int(100000, 999999);
        $result = SmsGatewayFactory::make()->sendOtp($number, $code);
        if (! $result->success) { log_message('error', 'OTP delivery failed: {error}', ['error' => $result->error]); return false; }
        $db->table('otp_codes')->where(['mobile_no' => $number, 'purpose' => $purpose])->where('consumed_at', null)->update(['consumed_at' => date('Y-m-d H:i:s')]);
        $db->table('otp_codes')->insert([
            'user_id' => $userId, 'mobile_no' => $number, 'purpose' => $purpose, 'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + 300), 'attempts' => 0, 'ip_address' => $request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogger())->log('otp_sent', 'otp', null, null, ['purpose' => $purpose, 'mobile' => substr($number, -4)], $userId);
        return true;
    }
    public function verify(string $mobile, string $purpose, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) return false;
        $number = format_ph_mobile($mobile); if ($number === '') return false;
        $db = db_connect(); $db->transBegin();
        $row = $db->table('otp_codes')->where(['mobile_no' => $number, 'purpose' => $purpose])->where('consumed_at', null)->where('expires_at >=', date('Y-m-d H:i:s'))->orderBy('id', 'DESC')->get()->getRowArray();
        if (! $row || (int) $row['attempts'] >= 5) { $db->transRollback(); return false; }
        $valid = password_verify($code, $row['code_hash']);
        $db->table('otp_codes')->where('id', $row['id'])->update(['attempts' => (int) $row['attempts'] + 1, 'consumed_at' => $valid ? date('Y-m-d H:i:s') : null]);
        $db->transComplete();
        return $valid && $db->transStatus() !== false;
    }
}

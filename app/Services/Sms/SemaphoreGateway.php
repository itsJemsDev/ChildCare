<?php
declare(strict_types=1);
namespace App\Services\Sms;
use CodeIgniter\HTTP\CURLRequest;
final class SemaphoreGateway implements SmsGatewayInterface
{
    private CURLRequest $http;
    public function __construct(?CURLRequest $http = null, private readonly ?string $apiKey = null, private readonly ?string $senderName = null)
    {
        $this->http = $http ?? service('curlrequest', ['baseURI' => 'https://api.semaphore.co/api/v4/', 'timeout' => 15]);
    }
    public function send(string $number, string $message): SmsResult
    {
        try {
            $response = $this->http->post('messages', ['form_params' => array_filter(['apikey' => $this->key(), 'number' => $number, 'message' => $message, 'sendername' => $this->senderName ?? env('sms.semaphore.senderName', '')])]);
            $data = json_decode($response->getBody(), true);
            $item = is_array($data) && array_is_list($data) ? ($data[0] ?? []) : (is_array($data) ? $data : []);
            if ($response->getStatusCode() >= 400 || (empty($item['message_id']) && empty($item['id']))) return new SmsResult(false, null, (string) ($item['message'] ?? 'Semaphore rejected the SMS.'));
            return new SmsResult(true, (string) ($item['message_id'] ?? $item['id'] ?? ''), null, (string) ($item['status'] ?? 'Queued'));
        } catch (\Throwable $e) { return new SmsResult(false, null, $e->getMessage()); }
    }
    public function sendOtp(string $number, string $code): SmsResult
    {
        try {
            $response = $this->http->post('otp', ['form_params' => ['apikey' => $this->key(), 'number' => $number, 'message' => 'Your Barangay ChildCare verification code is {code}.', 'code' => $code, 'sendername' => $this->senderName ?? env('sms.semaphore.senderName', '')]]);
            $data = json_decode($response->getBody(), true);
            if ($response->getStatusCode() >= 400 || ! is_array($data)) return new SmsResult(false, null, 'Semaphore OTP request failed.');
            return new SmsResult(true, (string) ($data['message_id'] ?? $data['id'] ?? ''), null, (string) ($data['status'] ?? 'Queued'));
        } catch (\Throwable $e) { return new SmsResult(false, null, $e->getMessage()); }
    }
    public function balance(): ?int
    {
        try { $response = $this->http->get('account', ['query' => ['apikey' => $this->key()]]); $data = json_decode($response->getBody(), true); return isset($data['credit_balance']) ? (int) $data['credit_balance'] : null; }
        catch (\Throwable) { return null; }
    }
    public function status(string $providerId): ?string
    {
        try { $response = $this->http->get('messages/' . rawurlencode($providerId), ['query' => ['apikey' => $this->key()]]); $data = json_decode($response->getBody(), true); return is_array($data) ? (string) ($data['status'] ?? '') : null; }
        catch (\Throwable) { return null; }
    }
    private function key(): string { return $this->apiKey ?? (string) env('sms.semaphore.apiKey', ''); }
}

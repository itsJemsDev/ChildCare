<?php

declare(strict_types=1);

namespace App\Authentication\Actions;

use App\Services\OtpService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;

final class SmsOtp2FA implements ActionInterface, ConditionalActionInterface
{
    private string $type = 'sms_2fa';

    public function appliesTo(User $user): bool
    {
        if ($user->inGroup('admin')) return setting('ChildCare.admin_sms_2fa') !== false;
        if ($user->inGroup('bhw')) return setting('ChildCare.bhw_sms_2fa') === true;
        return false;
    }

    public function show(): string
    {
        $user = auth('session')->getAuthenticator()->getPendingUser();
        return view('auth/sms_verify', ['title' => 'Verify sign in', 'action' => site_url('auth/a/verify'), 'resendAction' => site_url('auth/a/resend'), 'note' => $user ? 'Enter the code sent to the mobile number on your account.' : 'Verification is required.']);
    }

    public function handle(IncomingRequest $request)
    {
        return $this->verify($request);
    }

    public function verify(IncomingRequest $request)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        $user = $authenticator->getPendingUser();
        $code = (string) $request->getPost('token');
        if (! $user || ! (new OtpService())->verify((string) $user->mobile_no, '2fa', $code)) {
            return view('auth/sms_verify', ['title' => 'Verify sign in', 'action' => site_url('auth/a/verify'), 'resendAction' => site_url('auth/a/resend'), 'note' => 'That code is invalid or expired. Try again.']);
        }
        $identity = new UserIdentity(['secret' => 'otp-verified', 'type' => $this->type]);
        $authenticator->checkAction($identity, 'otp-verified');
        (new \App\Services\AuditLogger())->log('login_2fa', 'user', (int) $user->id, null, null, (int) $user->id);
        return redirect()->to(config('Auth')->loginRedirect());
    }

    public function getType(): string { return $this->type; }

    public function createIdentity(User $user): string
    {
        (new OtpService())->send((string) $user->mobile_no, '2fa', (int) $user->id);
        return bin2hex(random_bytes(20));
    }
}

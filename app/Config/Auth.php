<?php

declare(strict_types=1);

namespace Config;

use App\Authentication\Actions\SmsOtp2FA;
use App\Models\UserModel;
use CodeIgniter\Shield\Config\Auth as ShieldAuth;

final class Auth extends ShieldAuth
{
    public array $redirects = [
        'register' => '/', 'login' => '/dashboard', 'logout' => '/login',
        'force_reset' => '/account/set-password', 'permission_denied' => '/login', 'group_denied' => '/login',
    ];
    public array $actions = ['register' => null, 'login' => SmsOtp2FA::class];
    public array $validFields = ['username'];
    public bool $allowRegistration = false;
    public bool $allowMagicLinkLogins = false;
    public bool $recordActiveDate = true;
    public string $userProvider = UserModel::class;
    public array $personalFields = ['first_name', 'middle_name', 'last_name'];
    public array $sessionConfig = ['field' => 'user', 'allowRemembering' => false, 'rememberCookieName' => 'remember', 'rememberLength' => 0];
}

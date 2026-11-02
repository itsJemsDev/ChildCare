<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\OtpService;
use CodeIgniter\Shield\Entities\User;

final class AuthController extends BaseController
{
    public function login(): string
    {
        return view('auth/login', ['title' => 'Log in', 'error' => session('error'), 'centerName' => setting('ChildCare.center_name') ?? 'Barangay ChildCare']);
    }

    public function attempt()
    {
        $throttler = service('throttler');
        if (! $throttler->check('login-ip-' . hash('sha256', $this->request->getIPAddress()), 10, 300)) return redirect()->back()->withInput()->with('error', 'Too many sign-in attempts. Please try again shortly.');
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $selected = (string) $this->request->getPost('user_type');
        if (! in_array($selected, ['admin', 'bhw', 'parent'], true)) return $this->invalidCredentials();
        $check = auth('session')->getAuthenticator()->check(['username' => $username, 'password' => $password]);
        if (! $check->isOK()) return $this->invalidCredentials();
        $user = $check->extraInfo();
        if (! $user->inGroup($selected) || ! (bool) $user->active) return $this->invalidCredentials();
        $result = auth('session')->getAuthenticator()->attempt(['username' => $username, 'password' => $password]);
        if (! $result->isOK()) return $this->invalidCredentials();
        (new \App\Services\AuditLogger())->log('login', 'user', (int) $user->id, null, ['group' => $selected], (int) $user->id);
        $authenticator = auth('session')->getAuthenticator();
        if ($authenticator->hasAction()) return redirect()->route('auth-action-show');
        return redirect()->to($user->inGroup('parent') ? site_url('parent/dashboard') : site_url('dashboard'));
    }

    public function smsForm(): string { return view('auth/sms_login', ['title' => 'Log in with SMS']); }

    public function requestSmsCode()
    {
        $mobile = format_ph_mobile((string) $this->request->getPost('mobile_no'));
        if ($mobile !== '') {
            $user = (new UserModel())->where('mobile_no', $mobile)->first();
            if ($user && $user->active) {
                (new OtpService())->send($mobile, 'login', (int) $user->id);
                session()->set('otp_login_mobile', $mobile);
            }
        }
        return redirect()->to(site_url('login/sms/verify'))->with('message', 'If this number is registered, we sent a code.');
    }

    public function verifySmsForm(): string { return view('auth/sms_verify', ['title' => 'Verify your mobile', 'action' => site_url('login/sms/verify'), 'resendAction' => site_url('login/sms')]); }

    public function resendStaff2fa()
    {
        $user=auth('session')->getAuthenticator()->getPendingUser();
        if(!$user)return redirect()->to(site_url('login'))->with('error','Sign in again to request a verification code.');
        $sent=(new OtpService())->send((string)$user->mobile_no,'2fa',(int)$user->id);
        return redirect()->route('auth-action-show')->with($sent?'message':'error',$sent?'A new verification code was sent.':'Wait one minute before requesting another code.');
    }

    public function verifySms()
    {
        $mobile = (string) session('otp_login_mobile');
        $code = (string) $this->request->getPost('token');
        if ($mobile === '' || ! (new OtpService())->verify($mobile, 'login', $code)) return redirect()->back()->withInput()->with('error', 'Invalid or expired code.');
        $user = (new UserModel())->where('mobile_no', $mobile)->first();
        if (! $user || ! $user->active) return redirect()->to(site_url('login'))->with('error', 'Invalid or expired code.');
        auth('session')->getAuthenticator()->login($user);
        session()->remove('otp_login_mobile');
        return redirect()->to((bool) ($user->must_set_password ?? false) ? site_url('account/set-password') : ($user->inGroup('parent') ? site_url('parent/dashboard') : site_url('dashboard')));
    }

    public function logout()
    {
        if (auth()->loggedIn()) (new \App\Services\AuditLogger())->log('logout', 'user', (int) auth()->id());
        auth()->logout();
        return redirect()->to(site_url('login'))->with('message', 'You have been signed out.');
    }

    public function setPassword(): string
    {
        return view('auth/set_password', ['title' => 'Set your password']);
    }

    public function savePassword()
    {
        $rules = ['password' => 'required|min_length[12]', 'password_confirm' => 'required|matches[password]'];
        if (! $this->validateData($this->request->getPost(), $rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $users = auth()->getProvider(); $user = $users->findById(auth()->id());
        $user->fill(['password' => (string) $this->request->getPost('password'), 'must_set_password' => 0]); $users->save($user);
        return redirect()->to($user->inGroup('parent') ? site_url('parent/dashboard') : site_url('dashboard'))->with('message', 'Password updated.');
    }

    public function forgot(): string { return view('auth/forgot', ['title' => 'Reset password']); }

    public function sendResetCode()
    {
        $method=(string)$this->request->getPost('method');
        if($method==='email'){
            $email=mb_strtolower(trim((string)$this->request->getPost('email')));
            if(filter_var($email,FILTER_VALIDATE_EMAIL)){$user=(new UserModel())->where('email',$email)->first();if($user&&$user->active)$this->sendEmailResetLink($user);}
            return redirect()->to(site_url('reset-password'))->with('message','If that account can receive email, a password reset link was sent.');
        }
        $mobile=format_ph_mobile((string)$this->request->getPost('mobile_no'));
        if($mobile!==''){$user=(new UserModel())->where('mobile_no',$mobile)->first();if($user&&$user->active){(new OtpService())->send($mobile,'password_reset',(int)$user->id);session()->set('password_reset_mobile',$mobile);}}
        return redirect()->to(site_url('reset-password'))->with('message','If this number is registered, we sent a code.');
    }

    private function sendEmailResetLink(\CodeIgniter\Shield\Entities\User $user): bool
    {
        if(empty($user->email))return false;$token=bin2hex(random_bytes(32));$db=db_connect();$db->table('auth_identities')->where(['user_id'=>$user->id,'type'=>'email_password_reset'])->delete();$now=date('Y-m-d H:i:s');
        $db->table('auth_identities')->insert(['user_id'=>$user->id,'type'=>'email_password_reset','secret'=>hash('sha256',$token),'expires'=>date('Y-m-d H:i:s',time()+1800),'created_at'=>$now,'updated_at'=>$now]);
        $email=service('email');$email->setTo($user->email);$email->setSubject('Barangay ChildCare password reset');$email->setMessage("A password reset was requested for your Barangay ChildCare account. Open this one-time link within 30 minutes: ".site_url('reset-password?token='.$token)."\n\nIf you did not request this, ignore this email.");
        if(!$email->send(false)){$db->table('auth_identities')->where(['user_id'=>$user->id,'type'=>'email_password_reset','secret'=>hash('sha256',$token)])->delete();return false;}return true;
    }

    public function resetForm(): string
    {
        $token=(string)$this->request->getGet('token');$identity=null;if(preg_match('/^[a-f0-9]{64}$/',$token))$identity=db_connect()->table('auth_identities')->where(['type'=>'email_password_reset','secret'=>hash('sha256',$token)])->where('expires >=',date('Y-m-d H:i:s'))->get()->getRowArray();
        return view('auth/reset_password',['title'=>'Reset password','emailToken'=>$identity?$token:'','invalidEmailToken'=>$token!==''&&!$identity]);
    }

    public function resetPassword()
    {
        $rules=['password'=>'required|min_length[12]','password_confirm'=>'required|matches[password]'];if(!$this->validateData($this->request->getPost(),$rules)){$emailToken=(string)$this->request->getPost('email_token');$target=$emailToken!==''?site_url('reset-password?token='.rawurlencode($emailToken)):site_url('reset-password');return redirect()->to($target)->withInput()->with('errors',$this->validator->getErrors());}
        $emailToken=(string)$this->request->getPost('email_token');$provider=auth()->getProvider();
        if($emailToken!==''){$identity=null;if(preg_match('/^[a-f0-9]{64}$/',$emailToken))$identity=db_connect()->table('auth_identities')->where(['type'=>'email_password_reset','secret'=>hash('sha256',$emailToken)])->where('expires >=',date('Y-m-d H:i:s'))->get()->getRowArray();if(!$identity)return redirect()->to(site_url('forgot-password'))->with('error','That password reset link is invalid or expired.');$user=$provider->findById((int)$identity['user_id']);if(!$user||!$user->active)return redirect()->to(site_url('forgot-password'))->with('error','That password reset link is invalid or expired.');$user->fill(['password'=>(string)$this->request->getPost('password'),'must_set_password'=>0]);$provider->save($user);db_connect()->table('auth_identities')->where(['user_id'=>$user->id,'type'=>'email_password_reset'])->delete();(new \App\Services\AuditLogger())->log('password_reset_email','user',(int)$user->id);return redirect()->to(site_url('login'))->with('message','Password reset. You can now sign in.');}
        $mobile=(string)session('password_reset_mobile');if(!$mobile||!(new OtpService())->verify($mobile,'password_reset',(string)$this->request->getPost('token')))return redirect()->back()->with('error','Invalid or expired code.');$user=(new UserModel())->where('mobile_no',$mobile)->first();if(!$user)return redirect()->to(site_url('login'))->with('error','Unable to reset password.');$user->fill(['password'=>(string)$this->request->getPost('password'),'must_set_password'=>0]);$provider->save($user);session()->remove('password_reset_mobile');(new \App\Services\AuditLogger())->log('password_reset_sms','user',(int)$user->id);return redirect()->to(site_url('login'))->with('message','Password reset. You can now sign in.');
    }

    public function contact(): string { return view('auth/contact', ['title' => 'Contact the health center', 'centerName' => setting('ChildCare.center_name'), 'contactNumber' => setting('ChildCare.contact_number')]); }

    private function invalidCredentials()
    {
        return redirect()->back()->withInput()->with('error', 'Invalid credentials for the selected user type.');
    }
}

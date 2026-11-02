<?php
declare(strict_types=1);
namespace App\Filters;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
final class ForceSetPassword implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();
        if ($user && (bool) ($user->must_set_password ?? false) && service('request')->getUri()->getPath() !== 'account/set-password') return redirect()->to(site_url('account/set-password'))->with('warning', 'Please set a password to continue.');
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}

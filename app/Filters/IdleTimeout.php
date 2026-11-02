<?php
declare(strict_types=1);
namespace App\Filters;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
final class IdleTimeout implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user(); if (! $user) return null;
        $timeout = (int) (setting('ChildCare.staff_idle_timeout') ?? 1800);
        $last = (int) session()->get('last_activity_at');
        if ($last && time() - $last > max(300, $timeout)) { auth()->logout(); return redirect()->to(site_url('login'))->with('warning', 'Your session expired. Please sign in again.'); }
        session()->set('last_activity_at', time());
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}

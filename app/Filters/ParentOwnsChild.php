<?php
declare(strict_types=1);
namespace App\Filters;
use App\Policies\ChildAccessPolicy;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
final class ParentOwnsChild implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $id = (int) ($arguments[0] ?? 0);
        if ($id < 1 || ! (new ChildAccessPolicy())->canView(auth()->user(), $id)) return service('response')->setStatusCode(404)->setBody('Not Found');
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}

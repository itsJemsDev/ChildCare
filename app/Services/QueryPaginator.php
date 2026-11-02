<?php
declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Pager\Pager;

/** Paginate Query Builder queries while retaining their joins, filters, and sort order. */
final class QueryPaginator
{
    /** @return array{rows: array, pager: Pager} */
    public static function paginate(BaseBuilder $query, int $perPage, string $group = 'default'): array
    {
        if ($perPage < 1) {
            throw new \InvalidArgumentException('The page size must be positive.');
        }

        $pager = service('pager');
        $total = (int) (clone $query)->countAllResults(false);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $pager->getCurrentPage($group)), $lastPage);
        $pager->store($group, $page, $perPage, $total);
        $offset = ($page - 1) * $perPage;
        $rows = $query->limit($perPage, $offset)->get()->getResultArray();

        return ['rows' => $rows, 'pager' => $pager];
    }
}

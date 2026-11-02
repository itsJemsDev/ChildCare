<?php

declare(strict_types=1);

namespace App\Policies;

final class ChildAccessPolicy
{
    public function canView(object|int $user, int $childId): bool
    {
        $userId = is_int($user) ? $user : (int) ($user->id ?? 0);
        if ($userId < 1) return false;
        return db_connect()->table('child_guardian cg')->join('guardians g', 'g.id = cg.guardian_id')->join('children c', 'c.id = cg.child_id')
            ->where('c.deleted_at', null)->where('g.deleted_at', null)->where('cg.child_id', $childId)->where('g.user_id', $userId)->countAllResults() > 0;
    }

    public function childrenFor(object|int $user): array
    {
        $userId = is_int($user) ? $user : (int) ($user->id ?? 0);
        return db_connect()->table('children c')->select('c.*,p.name AS purok_name')->join('puroks p','p.id=c.purok_id','left')->join('child_guardian cg', 'cg.child_id = c.id')
            ->join('guardians g', 'g.id = cg.guardian_id')->where('g.user_id', $userId)->where('g.deleted_at', null)->where('c.deleted_at', null)->get()->getResultArray();
    }
}

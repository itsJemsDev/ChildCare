<?php
declare(strict_types=1);
namespace App\Services;
final class NotificationService
{
    public function create(int $userId,string $type,string $title,string $body,?string $url=null): int
    {
        $db=db_connect();$db->table('notifications')->insert(['user_id'=>$userId,'type'=>$type,'title'=>$title,'body'=>$body,'url'=>$url,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);return (int)$db->insertID();
    }
    public function notifyChildGuardians(int $childId,string $type,string $title,string $body): int
    {
        $db=db_connect();$users=$db->table('child_guardian cg')->select('DISTINCT g.user_id AS user_id',false)->join('guardians g','g.id=cg.guardian_id')->where('cg.child_id',$childId)->where('g.user_id IS NOT NULL',null,false)->where('g.deleted_at',null)->get()->getResultArray();
        foreach($users as $row)$this->create((int)$row['user_id'],$type,$title,$body,site_url('parent/children/'.$childId));
        return count($users);
    }
    public function notifyStaff(string $type,string $title,string $body,?string $url=null): int
    {
        $users=db_connect()->table('auth_groups_users agu')->select('DISTINCT agu.user_id AS user_id',false)->join('users u','u.id=agu.user_id')->whereIn('agu.group',['admin','bhw'])->where('u.active',1)->where('u.deleted_at',null)->get()->getResultArray();
        foreach($users as $row)$this->create((int)$row['user_id'],$type,$title,$body,$url);
        return count($users);
    }
    public function unreadCount(int $userId): int { return (int)db_connect()->table('notifications')->where(['user_id'=>$userId,'read_at'=>null,'deleted_at'=>null])->countAllResults(); }
}

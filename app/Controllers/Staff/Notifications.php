<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
final class Notifications extends BaseController
{
    public function index(): string
    {
        $query=db_connect()->table('notifications')->where('user_id',auth()->id())->where('deleted_at',null)->orderBy('created_at','DESC');
        $page=\App\Services\QueryPaginator::paginate($query,20);
        return view('staff/notifications/index',['title'=>'Notifications','rows'=>$page['rows'],'pager'=>$page['pager']]);
    }
    public function read(int $id)
    {
        db_connect()->table('notifications')->where(['id'=>$id,'user_id'=>auth()->id()])->update(['read_at'=>date('Y-m-d H:i:s')]);return redirect()->back();
    }
}

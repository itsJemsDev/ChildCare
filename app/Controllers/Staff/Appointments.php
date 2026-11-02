<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\Sms\SmsService;
final class Appointments extends BaseController
{
    public function index(): string
    {
        $query=db_connect()->table('appointments a')->select('a.*,c.first_name,c.last_name,c.child_code,u.first_name AS parent_first,u.last_name AS parent_last')
            ->join('children c','c.id=a.child_id')->join('users u','u.id=a.requested_by')->where('a.deleted_at',null)->orderBy('a.preferred_date');
        $page=\App\Services\QueryPaginator::paginate($query,20);
        return view('staff/appointments/index',['title'=>'Appointments','rows'=>$page['rows'],'pager'=>$page['pager']]);
    }
    public function update(int $id,string $action)
    {
        if(!in_array($action,['approve','decline','complete'],true))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $reason=trim((string)$this->request->getPost('reason'));if($action==='decline'&&$reason==='')return redirect()->back()->with('error','A reason is required when declining an appointment.');
        $db=db_connect();$row=$db->table('appointments')->where('id',$id)->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        if(($row['status']==='requested'&&!in_array($action,['approve','decline'],true))||($row['status']==='approved'&&$action!=='complete')||!in_array($row['status'],['requested','approved'],true))return redirect()->back()->with('error','That appointment can no longer be changed with this action.');$status=['approve'=>'approved','decline'=>'declined','complete'=>'completed'][$action];$db->table('appointments')->where('id',$id)->update(['status'=>$status,'decline_reason'=>$action==='decline'?$reason:null,'handled_by'=>auth()->id(),'handled_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $body='Your appointment request for '.format_date($row['preferred_date']).' was '.$status.($reason!==''?' Reason: '.$reason:'').'.';(new NotificationService())->create((int)$row['requested_by'],'appointment_update','Appointment '.$status,$body,site_url('parent/appointments'));
        $guardian=$db->table('child_guardian cg')->select('g.id,g.mobile_no,g.sms_opt_in')->join('guardians g','g.id=cg.guardian_id')->where('cg.child_id',$row['child_id'])->where('g.user_id',$row['requested_by'])->get()->getRowArray();
        if($guardian&&(int)$guardian['sms_opt_in']===1)(new SmsService())->queue('Appointment update','appointment',[['guardian_id'=>$guardian['id'],'child_id'=>$row['child_id'],'number'=>$guardian['mobile_no'],'body'=>$body]],null,(int)auth()->id());
        (new AuditLogger())->log('appointment_'.$action,'appointment',$id,$row,['status'=>$status,'reason'=>$reason]);
        return redirect()->back()->with('message','Appointment updated.');
    }
}

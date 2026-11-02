<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\ChildService;
final class Checkups extends BaseController
{
    public function index(int $childId): string
    {
        $child=(new ChildService())->find($childId);if(!$child)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $query=db_connect()->table('checkups')->where('child_id',$childId)->where('deleted_at',null)->orderBy('checkup_date','DESC');
        $page=\App\Services\QueryPaginator::paginate($query,10);
        return view('staff/checkups/index',['title'=>'Medical History','child'=>$child,'records'=>$page['rows'],'pager'=>$page['pager']]);
    }
    public function create(int $childId)
    {
        $rules=['checkup_date'=>'required|valid_date[Y-m-d]','temperature_c'=>'permit_empty|decimal','weight_kg'=>'permit_empty|decimal','outcome'=>'permit_empty|in_list[Recovered,Resolved,Stable,Ongoing,Referred]','follow_up_date'=>'permit_empty|valid_date[Y-m-d]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $date=(string)$this->request->getPost('checkup_date');if($date>date('Y-m-d'))return redirect()->back()->withInput()->with('error','Check-up date cannot be in the future.');$followUp=(string)$this->request->getPost('follow_up_date');if($followUp!==''&&$followUp<$date)return redirect()->back()->withInput()->with('error','Follow-up date cannot be before the check-up.');
        $data=$this->request->getPost();$now=date('Y-m-d H:i:s');$record=['child_id'=>$childId,'checkup_date'=>$date,'chief_complaint'=>$data['chief_complaint']??null,'diagnosis'=>$data['diagnosis']??null,'findings'=>$data['findings']??null,'temperature_c'=>($data['temperature_c']??'')===''?null:$data['temperature_c'],'weight_kg'=>($data['weight_kg']??'')===''?null:$data['weight_kg'],'treatment'=>$data['treatment']??null,'medicines'=>json_encode(array_values(array_filter([$data['treatment']??null])),JSON_THROW_ON_ERROR),'outcome'=>$data['outcome']??null,'referred_to'=>$data['referred_to']??null,'follow_up_date'=>$followUp?:null,'attended_by'=>auth()->id(),'remarks'=>$data['remarks']??null,'created_at'=>$now,'updated_at'=>$now];
        db_connect()->table('checkups')->insert($record);$id=(int)db_connect()->insertID();db_connect()->table('children')->where('id',$childId)->update(['last_checkup_at'=>$date]);(new AuditLogger())->log('create','checkup',$id,null,$record);(new \App\Services\NotificationService())->notifyChildGuardians($childId,'new_health_record','Check-up record added','A new check-up note was added to your child’s health record.');cache()->delete('staff-dashboard-v4');
        return redirect()->back()->with('message','Medical history record saved.');
    }
    public function update(int $childId,int $recordId)
    {
        $rules=['checkup_date'=>'required|valid_date[Y-m-d]','temperature_c'=>'permit_empty|decimal','weight_kg'=>'permit_empty|decimal','outcome'=>'permit_empty|in_list[Recovered,Resolved,Stable,Ongoing,Referred]','follow_up_date'=>'permit_empty|valid_date[Y-m-d]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $db=db_connect();$before=$db->table('checkups')->where(['id'=>$recordId,'child_id'=>$childId])->where('deleted_at',null)->get()->getRowArray();$data=$this->request->getPost();$date=(string)$data['checkup_date'];
        if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();if($date>date('Y-m-d'))return redirect()->back()->withInput()->with('error','Check-up date cannot be in the future.');if(!empty($data['follow_up_date'])&&$data['follow_up_date']<$date)return redirect()->back()->withInput()->with('error','Follow-up date cannot be before the check-up.');
        $record=['checkup_date'=>$date,'chief_complaint'=>$data['chief_complaint']??null,'diagnosis'=>$data['diagnosis']??null,'findings'=>$data['findings']??null,'temperature_c'=>($data['temperature_c']??'')===''?null:$data['temperature_c'],'weight_kg'=>($data['weight_kg']??'')===''?null:$data['weight_kg'],'treatment'=>$data['treatment']??null,'medicines'=>json_encode(array_values(array_filter([$data['treatment']??null])),JSON_THROW_ON_ERROR),'outcome'=>$data['outcome']??null,'referred_to'=>$data['referred_to']??null,'follow_up_date'=>$data['follow_up_date']?:null,'remarks'=>$data['remarks']??null,'updated_at'=>date('Y-m-d H:i:s')];
        $db->table('checkups')->where('id',$recordId)->update($record);$latest=$db->table('checkups')->select('checkup_date')->where('child_id',$childId)->where('deleted_at',null)->orderBy('checkup_date','DESC')->orderBy('id','DESC')->get()->getRow('checkup_date');$db->table('children')->where('id',$childId)->update(['last_checkup_at'=>$latest]);(new AuditLogger())->log('update','checkup',$recordId,$before,$record);cache()->delete('staff-dashboard-v4');return redirect()->back()->with('message','Medical history record updated.');
    }

    public function delete(int $childId,int $recordId)
    {
        $reason=trim((string)$this->request->getPost('reason'));if($reason==='')return redirect()->back()->with('error','A deletion reason is required.');
        $db=db_connect();$before=$db->table('checkups')->where(['id'=>$recordId,'child_id'=>$childId])->where('deleted_at',null)->get()->getRowArray();if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$remarks=trim((string)($before['remarks']??''));$db->table('checkups')->where('id',$recordId)->update(['deleted_at'=>date('Y-m-d H:i:s'),'remarks'=>trim($remarks.($remarks!==''?"\n":'').'Deleted: '.$reason),'updated_at'=>date('Y-m-d H:i:s')]);$latest=$db->table('checkups')->select('checkup_date')->where('child_id',$childId)->where('deleted_at',null)->orderBy('checkup_date','DESC')->orderBy('id','DESC')->get()->getRow('checkup_date');$db->table('children')->where('id',$childId)->update(['last_checkup_at'=>$latest]);(new AuditLogger())->log('soft_delete','checkup',$recordId,$before,['reason'=>$reason]);cache()->delete('staff-dashboard-v4');return redirect()->back()->with('message','Medical history record deleted.');
    }
}

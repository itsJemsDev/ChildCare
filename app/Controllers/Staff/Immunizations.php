<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\ChildService;
use App\Services\ImmunizationScheduler;
final class Immunizations extends BaseController
{
    public function index(int $childId): string
    {
        $child=(new ChildService())->find($childId);if(!$child)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $db=db_connect();$query=$db->table('immunizations i')->select('i.*,vd.code AS dose_code,vd.label,v.name AS vaccine_name,u.first_name AS staff_first,u.last_name AS staff_last')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->join('users u','u.id=i.administered_by','left')->where('i.child_id',$childId)->where('i.deleted_at',null)->orderBy('i.due_date');
        $page=\App\Services\QueryPaginator::paginate($query,20);
        $outstanding=$db->table('immunizations i')->select('i.*,vd.code AS dose_code,vd.label,v.name AS vaccine_name')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.child_id',$childId)->where('i.deleted_at',null)->whereIn('i.status',['pending','deferred'])->orderBy('i.due_date')->get()->getResultArray();
        return view('staff/immunizations/index',['title'=>'Immunization Records','child'=>$child,'records'=>$page['rows'],'pager'=>$page['pager'],'outstanding'=>$outstanding]);
    }
    public function create(int $childId)
    {
        $rules=['vaccine_dose_id'=>'required|integer','date_administered'=>'required|valid_date[Y-m-d]','facility'=>'permit_empty|max_length[150]','lot_number'=>'permit_empty|max_length[80]','site'=>'permit_empty|max_length[40]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $db=db_connect();$child=$db->table('children')->where('id',$childId)->get()->getRowArray();$dose=$db->table('vaccine_doses')->where('id',(int)$this->request->getPost('vaccine_dose_id'))->get()->getRowArray();
        $date=(string)$this->request->getPost('date_administered');$override=trim((string)$this->request->getPost('override_reason'));
        if(!$child||!$dose||$date<$child['birthdate']||$date>date('Y-m-d'))return redirect()->back()->withInput()->with('error','Choose an outstanding dose and a date between the child’s birthdate and today.');
        $warning=null;$age=(new \DateTimeImmutable($child['birthdate']))->diff(new \DateTimeImmutable($date))->days;
        if($age<(int)$dose['min_age_days'])$warning='Dose administered before the minimum age.';
        $priorDoseIds=array_column($db->table('vaccine_doses')->select('id')->where('vaccine_id',$dose['vaccine_id'])->where('dose_number <',(int)$dose['dose_number'])->get()->getResultArray(),'id');
        $previous=$priorDoseIds?$db->table('immunizations')->where('child_id',$childId)->where('status','completed')->whereIn('vaccine_dose_id',$priorDoseIds)->orderBy('date_administered','DESC')->get()->getRowArray():null;
        if($previous&&$date<date('Y-m-d',strtotime($previous['date_administered'].' +'.(int)$dose['min_interval_days'].' days')))$warning='Dose administered before the minimum interval.';
        if($warning&&$override==='')return redirect()->back()->withInput()->with('error',$warning.' Enter an override reason to continue.');
        try{(new ImmunizationScheduler())->record($childId,(int)$dose['id'],$date,['administered_by'=>trim((string)$this->request->getPost('administered_by_name'))===''?auth()->id():null,'administered_by_name'=>$this->request->getPost('administered_by_name'),'facility'=>$this->request->getPost('facility'),'lot_number'=>$this->request->getPost('lot_number'),'site'=>$this->request->getPost('site'),'override_reason'=>$override?:null,'remarks'=>$this->request->getPost('remarks')]);(new AuditLogger())->log('record_immunization','immunization',null,null,['child_id'=>$childId,'dose_id'=>$dose['id'],'date'=>$date]);(new \App\Services\NotificationService())->notifyChildGuardians($childId,'new_health_record','Immunization record added','A new immunization record was added to your child’s health record.');cache()->delete('staff-dashboard-v4');return redirect()->back()->with('message','Immunization recorded.');}
        catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }
    public function update(int $childId, int $recordId)
    {
        $rules=['date_administered'=>'required|valid_date[Y-m-d]','facility'=>'permit_empty|max_length[150]','lot_number'=>'permit_empty|max_length[80]','site'=>'permit_empty|max_length[40]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $db=db_connect();$child=$db->table('children')->where('id',$childId)->where('deleted_at',null)->get()->getRowArray();
        $before=$db->table('immunizations')->where(['id'=>$recordId,'child_id'=>$childId,'status'=>'completed'])->where('deleted_at',null)->get()->getRowArray();
        $date=(string)$this->request->getPost('date_administered');$override=trim((string)$this->request->getPost('override_reason'));
        if(!$child||!$before||$date<$child['birthdate']||$date>date('Y-m-d'))return redirect()->back()->withInput()->with('error','Choose a completed dose and a date between the child’s birthdate and today.');
        $dose=$db->table('vaccine_doses')->where('id',$before['vaccine_dose_id'])->get()->getRowArray();$warning=null;
        if($dose){$age=(new \DateTimeImmutable($child['birthdate']))->diff(new \DateTimeImmutable($date))->days;if($age<(int)$dose['min_age_days'])$warning='Dose administered before the minimum age.';$priorIds=array_column($db->table('vaccine_doses')->select('id')->where('vaccine_id',$dose['vaccine_id'])->where('dose_number <',(int)$dose['dose_number'])->get()->getResultArray(),'id');$previous=$priorIds?$db->table('immunizations')->where('child_id',$childId)->where('status','completed')->where('id !=',$recordId)->whereIn('vaccine_dose_id',$priorIds)->orderBy('date_administered','DESC')->get()->getRowArray():null;if($previous&&$date<date('Y-m-d',strtotime($previous['date_administered'].' +'.(int)$dose['min_interval_days'].' days')))$warning='Dose administered before the minimum interval.';}
        if($warning&&$override==='')return redirect()->back()->withInput()->with('error',$warning.' Enter an override reason to continue.');
        $data=$this->request->getPost();$administeredByName=trim((string)($data['administered_by_name']??''));$changes=['administered_by'=>$administeredByName===''?$before['administered_by']:null,'administered_by_name'=>$administeredByName?:null,'facility'=>$data['facility']??null,'lot_number'=>$data['lot_number']??null,'site'=>$data['site']??null,'override_reason'=>$override?:null,'remarks'=>$data['remarks']??null];
        try{(new ImmunizationScheduler())->updateRecord($childId,$recordId,$date,$changes);(new AuditLogger())->log('update','immunization',$recordId,$before,['date_administered'=>$date,...$changes]);cache()->delete('staff-dashboard-v4');return redirect()->back()->with('message','Immunization updated.');}catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }

    public function delete(int $childId,int $recordId)
    {
        $reason=trim((string)$this->request->getPost('reason'));if($reason==='')return redirect()->back()->with('error','A reason is required to undo a dose record.');
        try{$old=(new ImmunizationScheduler())->undoRecord($childId,$recordId,$reason);(new AuditLogger())->log('undo_immunization','immunization',$recordId,$old,['reason'=>$reason]);cache()->delete('staff-dashboard-v4');return redirect()->back()->with('message','Dose record returned to pending with the reason recorded.');}catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
    }
}

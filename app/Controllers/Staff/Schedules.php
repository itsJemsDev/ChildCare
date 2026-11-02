<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\ExportService;
final class Schedules extends BaseController
{
    public function index(): string
    {
        $filters=$this->filters();$view=in_array(($filters['view']??'list'),['list','calendar','pending','completed'],true)?$filters['view']:'list';$db=db_connect();
        $base=$db->table('immunizations i')->select('i.id,i.due_date,i.status,i.date_administered,c.id AS child_id,c.child_code,c.first_name,c.last_name,c.birthdate,vd.code AS dose_code,v.name AS vaccine_name,vd.min_age_days')
            ->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('c.registration_status','verified')->where('c.deleted_at',null)->where('i.deleted_at',null);
        if(!empty($filters['child']))$base->groupStart()->like('c.first_name',$filters['child'])->orLike('c.last_name',$filters['child'])->orLike('c.child_code',$filters['child'])->groupEnd();
        if(!empty($filters['vaccine']))$base->where('v.id',(int)$filters['vaccine']);
        if(!empty($filters['from']))$base->where('i.due_date >=',$filters['from']);if(!empty($filters['to']))$base->where('i.due_date <=',$filters['to']);
        $status=(string)($filters['status']??'');
        if($view==='completed')$base->where('i.status','completed');elseif($view==='pending'){$base->where('i.status','pending');if($status==='overdue')$base->where('i.due_date <',date('Y-m-d'));}elseif($status==='overdue')$base->where('i.status','pending')->where('i.due_date <',date('Y-m-d'));elseif(in_array($status,['pending','completed','deferred'],true))$base->where('i.status',$status);else $base->whereIn('i.status',['pending','completed','deferred']);
        if($view==='calendar'){$rows=[];$pager=service('pager');}
        else{$page=\App\Services\QueryPaginator::paginate($base->orderBy('i.due_date'),50);$rows=$page['rows'];$pager=$page['pager'];}
        $childIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['child_id'],$rows)));$nextByChild=[];if($childIds!==[]){$nextRows=$db->table('immunizations i')->select('i.child_id,i.due_date,v.name AS vaccine_name,vd.code AS dose_code')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->whereIn('i.child_id',$childIds)->where('i.status','pending')->where('i.deleted_at',null)->orderBy('i.due_date')->get()->getResultArray();foreach($nextRows as $next)$nextByChild[(int)$next['child_id']][]=$next;}
        foreach($rows as &$row){$row['display_status']=$row['status']==='completed'?'Completed':($row['due_date']<date('Y-m-d')?'Overdue':($row['due_date']<=date('Y-m-d',strtotime('+3 days'))?'Due Soon':'Pending'));$row['next_schedule']='—';foreach($nextByChild[(int)$row['child_id']]??[] as $next)if($next['due_date']>$row['due_date']){$row['next_schedule']=format_date($next['due_date']).' · '.$next['vaccine_name'].' '.$next['dose_code'];break;}}
        unset($row);
        $candidate=(string)($filters['month']??'');$calendarDate=preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/',$candidate)?$candidate:date('Y-m');$filters['month']=$calendarDate;$start=new \DateTimeImmutable($calendarDate.'-01');$days=(int)$start->format('t');$offset=(int)$start->format('N')-1;
        $calendarQuery=$db->table('immunizations i')->select('i.due_date,COUNT(*) AS total')->join('children c','c.id=i.child_id')->where('i.status','pending')->where('i.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->where('i.due_date >=',$start->format('Y-m-01'))->where('i.due_date <=',$start->format('Y-m-t'));
        if(!empty($filters['child']))$calendarQuery->groupStart()->like('c.first_name',$filters['child'])->orLike('c.last_name',$filters['child'])->orLike('c.child_code',$filters['child'])->groupEnd();
        if(!empty($filters['vaccine']))$calendarQuery->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->where('vd.vaccine_id',(int)$filters['vaccine']);
        if(!empty($filters['from']))$calendarQuery->where('i.due_date >=',$filters['from']);if(!empty($filters['to']))$calendarQuery->where('i.due_date <=',$filters['to']);
        $calendarRows=$calendarQuery->groupBy('i.due_date')->get()->getResultArray();
        $counts=[];foreach($calendarRows as $row)$counts[(int)date('j',strtotime($row['due_date']))]=(int)$row['total'];
        $totalChildren=(int)$db->table('children')->where('registration_status','verified')->where('deleted_at',null)->countAllResults();
        $upToDate=(int)$db->table('children')->where('registration_status','verified')->where('immunization_status','complete')->where('deleted_at',null)->countAllResults();
        $doseBase=$db->table('immunizations i')->join('children c','c.id=i.child_id')->where('i.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified');
        $totalDoses=(int)(clone $doseBase)->countAllResults();
        $pendingDoses=(int)(clone $doseBase)->where('i.status','pending')->where('i.due_date >=',date('Y-m-d'))->countAllResults();
        $overdueDoses=(int)(clone $doseBase)->where('i.status','pending')->where('i.due_date <',date('Y-m-d'))->countAllResults();
        $kpis=['total'=>$totalChildren,'complete'=>$upToDate,'completePct'=>$totalChildren?round($upToDate/$totalChildren*100):0,'pending'=>$pendingDoses,'pendingPct'=>$totalDoses?round($pendingDoses/$totalDoses*100):0,'overdue'=>$overdueDoses,'overduePct'=>$totalDoses?round($overdueDoses/$totalDoses*100):0];
        return view('staff/schedules/index', [
            'title' => 'Schedule Monitoring', 'rows' => $rows, 'pager' => $pager, 'filters' => $filters,
            'view' => $view, 'days' => $days, 'offset' => $offset, 'counts' => $counts,
            'vaccines' => $db->table('vaccines')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'kpis' => $kpis,
        ]);
    }
    public function export(string $format)
    {
        $filters=$this->filters();$builder=db_connect()->table('immunizations i')->select('c.child_code AS Child_ID,c.first_name AS First_name,c.last_name AS Last_name,vd.code AS Dose,v.name AS Vaccine,i.due_date AS Schedule_date,i.status AS Status')
            ->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('c.registration_status','verified')->where('c.deleted_at',null)->where('i.deleted_at',null);
        if(!empty($filters['child']))$builder->groupStart()->like('c.first_name',$filters['child'])->orLike('c.last_name',$filters['child'])->orLike('c.child_code',$filters['child'])->groupEnd();
        if(!empty($filters['vaccine']))$builder->where('v.id',(int)$filters['vaccine']);if(!empty($filters['from']))$builder->where('i.due_date >=',$filters['from']);if(!empty($filters['to']))$builder->where('i.due_date <=',$filters['to']);
        $view=(string)($filters['view']??'list');$status=(string)($filters['status']??'');
        if($view==='completed')$builder->where('i.status','completed');elseif($view==='pending'){$builder->where('i.status','pending');if($status==='overdue')$builder->where('i.due_date <',date('Y-m-d'));}elseif($status==='overdue')$builder->where('i.status','pending')->where('i.due_date <',date('Y-m-d'));elseif(in_array($status,['pending','completed','deferred'],true))$builder->where('i.status',$status);else $builder->whereIn('i.status',['pending','completed','deferred']);
        $rows=$builder->orderBy('i.due_date')->get()->getResultArray();
        if($format==='xlsx')return (new ExportService())->xlsx('immunization-schedule.xlsx','Schedule',['Child ID','First name','Last name','Dose','Vaccine','Schedule date','Status'],array_map('array_values',$rows));
        return (new ExportService())->pdf('immunization-schedule.pdf','Immunization Schedule',$rows,$this->request->getGet());
    }

    public function remindSelected()
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)$this->request->getPost('child_ids')),static fn($id)=>$id>0)));
        if($ids===[])return redirect()->back()->with('error','Select at least one child with a pending dose.');
        $rows=db_connect()->table('immunizations i')->select('i.child_id,i.due_date,v.name AS vaccine,vd.code,g.id AS guardian_id,g.mobile_no,g.first_name AS guardian_name,c.first_name AS child_first,c.last_name AS child_last')
            ->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')
            ->join('child_guardian cg','cg.child_id=c.id AND cg.is_primary = 1')->join('guardians g','g.id=cg.guardian_id')
            ->whereIn('i.child_id',$ids)->where('i.status','pending')->where('g.sms_opt_in',1)->where('c.registration_status','verified')->where('c.deleted_at',null)
            ->orderBy('i.due_date')->get()->getResultArray();
        $perChild=[];foreach($rows as $row)if(!isset($perChild[$row['child_id']]))$perChild[$row['child_id']]=$row;
        if($perChild===[])return redirect()->back()->with('error','Selected children have no pending doses or no opted-in primary guardian.');
        $recipients=[];foreach($perChild as $row)$recipients[]=['guardian_id'=>(int)$row['guardian_id'],'child_id'=>(int)$row['child_id'],'number'=>$row['mobile_no'],'body'=>'Hello {guardian_name}, {child_name} has a pending {vaccine} {dose} scheduled for {date}. Please visit {center_name}.','values'=>['guardian_name'=>$row['guardian_name'],'child_name'=>$row['child_first'].' '.$row['child_last'],'vaccine'=>$row['vaccine'],'dose'=>$row['code'],'date'=>$row['due_date'],'center_name'=>setting('ChildCare.center_name')??'Barangay Health Center']];
        $batch=(new \App\Services\Sms\SmsService())->queue('Selected immunization reminders','immunization',$recipients,null,(int)auth()->id());
        return redirect()->back()->with('message','SMS batch #'.$batch.' queued for '.count($recipients).' selected guardians.');
    }

    private function filters(): array
    {
        $get=$this->request->getGet();
        foreach(['from','to'] as $key)if(!empty($get[$key])){$value=(string)$get[$key];$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$value)||$date===false||$date->format('Y-m-d')!==$value)$get[$key]='';}
        if(!in_array(($get['status']??''),['','pending','overdue','completed','deferred'],true))$get['status']='';
        return $get;
    }
}

<?php
declare(strict_types=1);
namespace App\Commands;
use App\Services\Sms\SmsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
final class RemindersGenerate extends BaseCommand
{
    protected $group='ChildCare';protected $name='reminders:generate';protected $description='Queue idempotent immunization and appointment reminders.';
    public function run(array $params): void
    {
        $db=db_connect();$today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->format('Y-m-d');$leadDays=array_filter(array_map('intval',explode(',',(string)(setting('ChildCare.reminder_lead_days')??'3,1'))));$interval=max(1,(int)(setting('ChildCare.overdue_interval_days')??7));$max=max(0,(int)(setting('ChildCare.overdue_max_reminders')??3));$count=0;
        $dueRows=$db->table('immunizations i')->select('i.id,i.child_id,i.due_date,i.reminder_opt_in,c.first_name,c.last_name,v.name AS vaccine,g.id AS guardian_id,g.first_name AS guardian_first,g.mobile_no')->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->join('child_guardian cg','cg.child_id=c.id AND cg.is_primary = 1')->join('guardians g','g.id=cg.guardian_id')->where('g.sms_opt_in',1)->where('i.status','pending')->where('c.registration_status','verified')->where('c.deleted_at',null)->get()->getResultArray();
        foreach($dueRows as $row){
            $key=null;foreach($leadDays as $lead)if($row['due_date']===date('Y-m-d',strtotime($today.' +'.$lead.' days'))){$key='imm:'.$row['id'].':d-'.$lead;break;}
            if($key===null&&$row['due_date']<$today){$overdue=$db->table('sms_messages')->like('reminder_key','imm:'.$row['id'].':overdue-','after')->orderBy('created_at','DESC')->get()->getResultArray();$n=count($overdue);$lastSent=$overdue[0]['created_at']??null;$intervalElapsed=$lastSent===null||$lastSent<=date('Y-m-d H:i:s',strtotime($today.' -'.$interval.' days'));if($n<$max&&$intervalElapsed)$key='imm:'.$row['id'].':overdue-'.($n+1);}
            if($key!==null&&!$db->table('sms_messages')->where('reminder_key',$key)->countAllResults()){$this->queueDoseReminder($row,$key,'Immunization reminder','Hello {guardian_name}, {child_name} is due for {vaccine} on {date}. Please visit {center_name}.',$today.' 07:00:00');$count++;}
            // Parent opt-in creates a separate reminder, with its own stable key so reruns never duplicate it.
            $extraKey='imm:'.$row['id'].':extra-d-1';
            if((int)$row['reminder_opt_in']===1&&$row['due_date']===date('Y-m-d',strtotime($today.' +1 day'))&&!$db->table('sms_messages')->where('reminder_key',$extraKey)->countAllResults()){$this->queueDoseReminder($row,$extraKey,'Extra immunization reminder','Reminder: {child_name} is due for {vaccine} tomorrow, {date}. Please visit {center_name}.',$today.' 07:00:00');$count++;}
        }
        $followups=$db->table('checkups ch')->select('ch.id,ch.child_id,ch.follow_up_date,c.first_name,c.last_name,g.id AS guardian_id,g.first_name AS guardian_first,g.mobile_no')->join('children c','c.id=ch.child_id')->join('child_guardian cg','cg.child_id=c.id AND cg.is_primary = 1')->join('guardians g','g.id=cg.guardian_id')->where('g.sms_opt_in',1)->where('ch.follow_up_date',date('Y-m-d',strtotime($today.' +1 day')))->where('ch.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->get()->getResultArray();
        foreach($followups as $row){$key='checkup:'.$row['id'].':d-1';if($db->table('sms_messages')->where('reminder_key',$key)->countAllResults())continue;(new SmsService())->queue('Check-up follow-up','checkup',[['guardian_id'=>$row['guardian_id'],'child_id'=>$row['child_id'],'number'=>$row['mobile_no'],'body'=>'Hello {guardian_name}, please bring {child_name} for a follow-up on {date} at {center_name}.','values'=>['guardian_name'=>$row['guardian_first'],'child_name'=>$row['first_name'].' '.$row['last_name'],'date'=>$row['follow_up_date'],'center_name'=>setting('ChildCare.center_name')??'Barangay Health Center'],'reminder_key'=>$key]],$today.' 07:00:00',null);$count++;}
        $appointments=$db->table('appointments a')->select('a.id,a.child_id,a.preferred_date,c.first_name,c.last_name,g.id AS guardian_id,g.first_name AS guardian_first,g.mobile_no')->join('children c','c.id=a.child_id')->join('child_guardian cg','cg.child_id=c.id AND cg.is_primary = 1')->join('guardians g','g.id=cg.guardian_id')->where('g.sms_opt_in',1)->where('a.status','approved')->where('a.preferred_date',date('Y-m-d',strtotime($today.' +1 day')))->where('a.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->get()->getResultArray();
        foreach($appointments as $row){$key='appt:'.$row['id'].':d-1';if($db->table('sms_messages')->where('reminder_key',$key)->countAllResults())continue;(new SmsService())->queue('Appointment reminder','appointment',[['guardian_id'=>$row['guardian_id'],'child_id'=>$row['child_id'],'number'=>$row['mobile_no'],'body'=>'Hello {guardian_name}, {child_name} has an appointment on {date} at {center_name}.','values'=>['guardian_name'=>$row['guardian_first'],'child_name'=>$row['first_name'].' '.$row['last_name'],'date'=>$row['preferred_date'],'center_name'=>setting('ChildCare.center_name')??'Barangay Health Center'],'reminder_key'=>$key]],$today.' 07:00:00',null);$count++;}
        CLI::write('Queued '.$count.' new reminders.');
    }

    private function queueDoseReminder(array $row,string $key,string $title,string $body,string $scheduled): void
    {
        (new SmsService())->queue($title,'immunization',[['guardian_id'=>$row['guardian_id'],'child_id'=>$row['child_id'],'number'=>$row['mobile_no'],'body'=>$body,'values'=>['guardian_name'=>$row['guardian_first'],'child_name'=>$row['first_name'].' '.$row['last_name'],'vaccine'=>$row['vaccine'],'date'=>$row['due_date'],'center_name'=>setting('ChildCare.center_name')??'Barangay Health Center'],'reminder_key'=>$key]],$scheduled,null);
    }
}

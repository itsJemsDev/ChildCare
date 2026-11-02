<?php
declare(strict_types=1);
namespace App\Commands;
use App\Services\AuditLogger;
use App\Services\Sms\SmsGatewayFactory;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
final class SmsDispatch extends BaseCommand
{
    protected $group='ChildCare';protected $name='sms:dispatch';protected $description='Dispatch up to 100 queued SMS messages while respecting quiet hours.';
    public function run(array $params): void
    {
        $now=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila'));$start=(string)(setting('ChildCare.quiet_start')??'21:00');$end=(string)(setting('ChildCare.quiet_end')??'07:00');$time=$now->format('H:i');
        if($start>$end?($time>=$start||$time<$end):($time>=$start&&$time<$end)){CLI::write('Quiet hours active; no messages dispatched.');return;}
        $db=db_connect();$rows=$db->table('sms_messages')->whereIn('status',['queued','scheduled'])->groupStart()->where('scheduled_at',null)->orWhere('scheduled_at <=',$now->format('Y-m-d H:i:s'))->groupEnd()->orderBy('id')->limit(100)->get()->getResultArray();$gateway=SmsGatewayFactory::make();$sent=0;
        foreach($rows as $row){$builder=$db->table('sms_messages')->set('attempts','attempts + 1',false)->set('status','sending')->set('updated_at',date('Y-m-d H:i:s'))->where('id',$row['id'])->whereIn('status',['queued','scheduled']);$builder->update();if($db->affectedRows()===0)continue;$attempt=(int)$row['attempts']+1;$result=$gateway->send($row['recipient_number'],$row['body']);
            if($result->success){$db->table('sms_messages')->where('id',$row['id'])->update(['status'=>'sent','provider_message_id'=>$result->providerId,'provider_status'=>$result->status,'sent_at'=>date('Y-m-d H:i:s'),'error_message'=>null,'updated_at'=>date('Y-m-d H:i:s')]);$sent++;(new AuditLogger())->log('sms_sent','sms_message',(int)$row['id'],null,['provider_id'=>$result->providerId]);}
            else{$failed=$attempt>=3;$retryAt=date('Y-m-d H:i:s',time()+(60*(2**($attempt-1))));$db->table('sms_messages')->where('id',$row['id'])->update(['status'=>$failed?'failed':'queued','scheduled_at'=>$failed?null:$retryAt,'error_message'=>substr((string)$result->error,0,1000),'updated_at'=>date('Y-m-d H:i:s')]);if($failed)(new \App\Services\NotificationService())->notifyStaff('sms_failure','SMS delivery failed','An SMS message failed after three delivery attempts. Review SMS Logs.',site_url('sms/logs'));}
        }
        CLI::write('Dispatched: '.$sent.' / '.count($rows));
    }
}

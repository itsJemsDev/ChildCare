<?php
declare(strict_types=1);
namespace App\Commands;
use App\Services\Sms\SmsGatewayFactory;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
final class SmsSyncStatus extends BaseCommand
{
    protected $group='ChildCare';protected $name='sms:sync-status';protected $description='Synchronize provider delivery states for sent SMS messages.';
    public function run(array $params): void
    {
        $db=db_connect();$rows=$db->table('sms_messages')->where('status','sent')->where('provider_message_id !=',null)->orderBy('id')->limit(100)->get()->getResultArray();$gateway=SmsGatewayFactory::make();$updated=0;
        foreach($rows as $row){$status=$gateway->status((string)$row['provider_message_id']);if(!$status)continue;$status=ucfirst(strtolower($status));$mapped=match($status){'Delivered'=>'delivered','Failed'=>'failed','Refunded'=>'refunded',default=>'sent'};$db->table('sms_messages')->where('id',$row['id'])->update(['status'=>$mapped,'provider_status'=>$status,'updated_at'=>date('Y-m-d H:i:s')]);$updated++;}
        CLI::write('Synchronized '.$updated.' provider statuses.');
    }
}

<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
final class Dashboard extends BaseController
{
    public function index(): string
    {
        $db = db_connect(); $cache = cache();
        $data = $cache->get('staff-dashboard-v4');
        if (! is_array($data)) {
            $total = (int) $db->table('children')->where(['deleted_at'=>null,'registration_status'=>'verified'])->countAllResults();
            $dueStats=$db->table('immunizations i')->select('COUNT(*) AS doses,COUNT(DISTINCT i.child_id) AS children',false)->join('children c','c.id=i.child_id')->where('i.status','pending')->where('i.deleted_at',null)->where('i.due_date <=',date('Y-m-d',strtotime('+7 days')))->where('c.registration_status','verified')->where('c.deleted_at',null)->get()->getRowArray();$due=(int)($dueStats['doses']??0);$dueChildren=(int)($dueStats['children']??0);
            $checkups = (int) $db->table('checkups ch')->join('children c','c.id=ch.child_id')->where('ch.checkup_date >=',date('Y-m-01'))->where('ch.checkup_date <=',date('Y-m-t'))->where('ch.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->countAllResults();
            $completed = (int) $db->table('children')->where(['deleted_at'=>null,'registration_status'=>'verified','immunization_status'=>'complete'])->countAllResults();
            $data = [
                'total'=>$total,'due'=>$dueChildren,'dueDoses'=>$due,'dueChildren'=>$dueChildren,'duePct'=>$total?round($dueChildren/$total*100):0,'checkups'=>$checkups,'completed'=>$completed,'nowManila'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->format('F j, Y g:i A'),
                'category'=>$db->table('children')->select('nutrition_category AS category, COUNT(*) AS total')->where('registration_status','verified')->where('deleted_at',null)->groupBy('nutrition_category')->get()->getResultArray(),
                'immunizationBreakdown'=>$db->table('children')->select('immunization_status AS status, COUNT(*) AS total')->where('registration_status','verified')->where('deleted_at',null)->groupBy('immunization_status')->get()->getResultArray(),
                'puroks'=>$db->table('children')->select('puroks.name, COUNT(children.id) AS total')->join('puroks','puroks.id=children.purok_id','left')->where('children.registration_status','verified')->where('children.deleted_at',null)->groupBy('puroks.id')->orderBy('puroks.sort_order')->get()->getResultArray(),
                'upcoming'=>$db->table('immunizations i')->select('i.due_date, c.id AS child_id, c.child_code, c.first_name, c.last_name, vd.code, v.name AS vaccine')->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.status','pending')->where('i.deleted_at',null)->where('c.registration_status','verified')->where('c.deleted_at',null)->orderBy('i.due_date')->limit(5)->get()->getResultArray(),
                'recent'=>$db->table('children c')->select('c.id,c.child_code,c.first_name,c.last_name,c.birthdate,c.created_at,p.name AS purok_name')->join('puroks p','p.id=c.purok_id','left')->where('c.registration_status','verified')->where('c.deleted_at',null)->orderBy('c.id','DESC')->limit(5)->get()->getResultArray(),
                'purokNames'=>[], 'monthlyLabels'=>[], 'monthlyCounts'=>[],
            ];
            $data['purokNames'] = array_column($data['puroks'],'name');
            $data['monthlyLabels'] = []; $data['monthlyCounts'] = [];
            for ($i=5;$i>=0;$i--) { $month = date('Y-m',strtotime('-' . $i . ' months')); $data['monthlyLabels'][] = date('M',strtotime($month . '-01')); $row=$db->table('immunizations i')->select('COUNT(DISTINCT i.child_id) AS total')->join('children c','c.id=i.child_id')->where('i.status','completed')->where('i.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->where('i.date_administered >=',$month . '-01')->where('i.date_administered <=',date('Y-m-t',strtotime($month . '-01')))->get()->getRowArray();$data['monthlyCounts'][]=(int)($row['total']??0); }
            $cache->save('staff-dashboard-v4',$data,300);
        }
        $data['title'] = 'Dashboard'; $data['user'] = auth()->user();
        $data['categoryValues'] = ['A'=>0,'B'=>0,'C'=>0]; foreach($data['category'] as $entry) if(isset($data['categoryValues'][$entry['category']??''])) $data['categoryValues'][$entry['category']]=(int)$entry['total'];
        $creditBalance=cache()->get('sms-credit-balance');$checkedAt=(int)(cache()->get('sms-credit-checked-at')??0);
        if(time()-$checkedAt>=600){$creditBalance=\App\Services\Sms\SmsGatewayFactory::make()->balance();cache()->save('sms-credit-balance',$creditBalance??'unavailable',600);cache()->save('sms-credit-checked-at',time(),600);}
        $bhwCount=(int)$db->table('auth_groups_users agu')->join('users u','u.id=agu.user_id')->where('agu.group','bhw')->where('u.active',1)->countAllResults();
        if(is_numeric($creditBalance)&&(int)$creditBalance<=(int)(setting('ChildCare.low_sms_credit_threshold')??100)){$alertKey='sms-low-credit-alert-'.date('Y-m-d');if(!cache()->get($alertKey)){(new \App\Services\NotificationService())->notifyStaff('sms_low_credits','Low SMS credits','Semaphore balance is '.(int)$creditBalance.' credits, at or below the configured threshold.',site_url('settings?tab=sms'));cache()->save($alertKey,true,86400);}}
        $data['system'] = ['users'=>(int)$db->table('users')->where('deleted_at',null)->countAllResults(),'bhw'=>$bhwCount,'parents'=>(int)$db->table('guardians')->where('deleted_at',null)->countAllResults(),'credits'=>$creditBalance==='unavailable'?null:$creditBalance];
        $data['recentEvents']=$db->table('notifications')->where('user_id',auth()->id())->where('deleted_at',null)->orderBy('created_at','DESC')->limit(5)->get()->getResultArray();
        return view('staff/dashboard',$data);
    }
}

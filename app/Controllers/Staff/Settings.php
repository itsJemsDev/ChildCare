<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
final class Settings extends BaseController
{
    public function index(): string
    {
        $keys=['center_name','center_logo','barangay','municipality','province','contact_number','office_hours','sms_driver','reminder_lead_days','overdue_interval_days','overdue_max_reminders','quiet_start','quiet_end','low_sms_credit_threshold','growth_watch_min','growth_watch_max','age_coverage_months','admin_sms_2fa','bhw_sms_2fa','staff_idle_timeout','parent_self_registration'];
        $values=[];foreach($keys as $key)$values[$key]=setting('ChildCare.'.$key);
        $db=db_connect();$tab=$this->request->getGet('tab')??'general';if(!in_array($tab,['general','puroks','vaccines','sms','growth','security','audit','backups'],true))$tab='general';
        $auditFilters=['user'=>trim((string)$this->request->getGet('user')),'entity'=>trim((string)$this->request->getGet('entity')),'action'=>trim((string)$this->request->getGet('action')),'from'=>(string)$this->request->getGet('from'),'to'=>(string)$this->request->getGet('to')];
        foreach(['from','to'] as $key){$value=$auditFilters[$key];$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if($value!==''&&(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$value)||$date===false||$date->format('Y-m-d')!==$value))$auditFilters[$key]='';}
        $auditPage=['rows'=>[],'pager'=>service('pager')];
        if($tab==='audit'){$auditQuery=$db->table('audit_logs a')->select('a.*,u.username,u.first_name,u.last_name')->join('users u','u.id=a.user_id','left')->orderBy('a.id','DESC');
            if(ctype_digit($auditFilters['user'])&&$auditFilters['user']!=='')$auditQuery->where('a.user_id',(int)$auditFilters['user']);
            if($auditFilters['entity']!=='')$auditQuery->like('a.entity_type',$auditFilters['entity']);if($auditFilters['action']!=='')$auditQuery->like('a.action',$auditFilters['action']);
            if($auditFilters['from']!=='')$auditQuery->where('a.created_at >=',$auditFilters['from'].' 00:00:00');if($auditFilters['to']!=='')$auditQuery->where('a.created_at <=',$auditFilters['to'].' 23:59:59');
            $auditPage=\App\Services\QueryPaginator::paginate($auditQuery,25);
        }
        return view('staff/settings/index',['title'=>'System Settings','tab'=>$tab,'values'=>$values,'puroks'=>$db->table('puroks')->where('deleted_at',null)->orderBy('sort_order')->get()->getResultArray(),'vaccines'=>$db->table('vaccines')->where('deleted_at',null)->orderBy('sort_order')->get()->getResultArray(),'doses'=>$db->table('vaccine_doses vd')->select('vd.*,v.name AS vaccine_name')->join('vaccines v','v.id=vd.vaccine_id')->where('vd.deleted_at',null)->orderBy('v.sort_order')->orderBy('vd.dose_number')->get()->getResultArray(),'audit'=>$auditPage['rows'],'auditPager'=>$auditPage['pager'],'auditFilters'=>$auditFilters,'auditUsers'=>$tab==='audit'?$db->table('users')->select('id,username,first_name,last_name')->where('deleted_at',null)->orderBy('last_name')->get()->getResultArray():[],'backups'=>glob(WRITEPATH.'backups/*.{gz,sql}',GLOB_BRACE)?:[]]);
    }
    public function save()
    {
        $tab=(string)$this->request->getPost('tab');if(!in_array($tab,['general','sms','growth','security'],true))return redirect()->back()->with('error','Choose a valid settings section.');
        $data=$this->request->getPost();$errors=[];
        if($tab==='general'){
            foreach(['center_name'=>160,'barangay'=>100,'municipality'=>100,'province'=>100,'office_hours'=>160] as $key=>$max){$value=trim((string)($data[$key]??''));if($key==='center_name'&&$value==='')$errors[$key]='Center name is required.';elseif(mb_strlen($value)>$max)$errors[$key]='This value is too long.';else $data[$key]=$value;}
            $phone=trim((string)($data['contact_number']??''));if(mb_strlen($phone)>30)$errors['contact_number']='Contact number is too long.';else $data['contact_number']=$phone;
        }elseif($tab==='sms'){
            if(!in_array($data['sms_driver']??'', ['log','semaphore'],true))$errors['sms_driver']='Choose a valid SMS driver.';
            $lead=trim((string)($data['reminder_lead_days']??''));$days=explode(',',$lead);if($lead===''||count($days)>5||array_filter($days,static fn($v)=>!ctype_digit(trim($v))||(int)$v<1||(int)$v>30))$errors['reminder_lead_days']='Use comma-separated lead days from 1 to 30.';
            foreach(['overdue_interval_days'=>[1,365],'overdue_max_reminders'=>[0,20],'low_sms_credit_threshold'=>[0,1000000]] as $key=>[$min,$max]){$value=filter_var($data[$key]??null,FILTER_VALIDATE_INT);if($value===false||$value<$min||$value>$max)$errors[$key]='Enter a whole number from '.$min.' to '.$max.'.';}
            foreach(['quiet_start','quiet_end'] as $key)if(!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/',(string)($data[$key]??'')))$errors[$key]='Use 24-hour time in HH:MM format.';
            if(empty($errors['quiet_start'])&&empty($errors['quiet_end'])&&($data['quiet_start']??'')===($data['quiet_end']??''))$errors['quiet_end']='Quiet hours must start and end at different times.';
        }elseif($tab==='growth'){
            foreach(['growth_watch_min','growth_watch_max'] as $key)if(!is_numeric($data[$key]??null)||(float)$data[$key]<-5||(float)$data[$key]>5)$errors[$key]='Enter a z-score threshold from -5 to 5.';
            if(empty($errors['growth_watch_min'])&&empty($errors['growth_watch_max'])&&(float)$data['growth_watch_min']>=(float)$data['growth_watch_max'])$errors['growth_watch_max']='The upper watch threshold must exceed the lower threshold.';
            $coverage=filter_var($data['age_coverage_months']??null,FILTER_VALIDATE_INT);if($coverage===false||$coverage<1||$coverage>240)$errors['age_coverage_months']='Enter an age coverage from 1 to 240 months.';
        }else{
            $timeout=filter_var($data['staff_idle_timeout']??null,FILTER_VALIDATE_INT);if($timeout===false||$timeout<60||$timeout>86400)$errors['staff_idle_timeout']='Use a session timeout from 60 to 86,400 seconds.';
        }
        $logo=null;if($tab==='general'){$logo=$this->request->getFile('center_logo');if($logo&&$logo->getError()!==UPLOAD_ERR_NO_FILE){if(!$logo->isValid()||$logo->getSize()>2*1024*1024)$errors['center_logo']='Choose a valid logo no larger than 2 MB.';else{$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($logo->getTempName())?:'';if(!in_array($mime,['image/png','image/jpeg'],true))$errors['center_logo']='Center logo must be PNG or JPG.';}}}
        if($errors)return redirect()->back()->withInput()->with('errors',$errors)->with('error',reset($errors));
        $editable=match($tab){'general'=>['center_name','barangay','municipality','province','contact_number','office_hours'],'sms'=>['sms_driver','reminder_lead_days','overdue_interval_days','overdue_max_reminders','quiet_start','quiet_end','low_sms_credit_threshold'],'growth'=>['growth_watch_min','growth_watch_max','age_coverage_months'],'security'=>['staff_idle_timeout']};
        foreach($editable as $key)setting('ChildCare.'.$key,$data[$key]);
        if($tab==='general'&&$logo&&$logo->getError()!==UPLOAD_ERR_NO_FILE){$folder=FCPATH.'assets/img';if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder))return redirect()->back()->with('error','Unable to prepare the center logo folder.');$name='center-'.bin2hex(random_bytes(12)).($mime==='image/png'?'.png':'.jpg');if(!$logo->move($folder,$name))return redirect()->back()->with('error','Unable to save the center logo.');$old=(string)setting('ChildCare.center_logo');setting('ChildCare.center_logo','assets/img/'.$name);if(preg_match('#^assets/img/center-[a-f0-9]{24}\\.(png|jpg)$#',$old)){$oldPath=FCPATH.$old;if(is_file($oldPath))@unlink($oldPath);}}
        if($tab==='security')foreach(['admin_sms_2fa','bhw_sms_2fa','parent_self_registration'] as $key)setting('ChildCare.'.$key,(bool)$this->request->getPost($key));
        (new AuditLogger())->log('settings_update','settings',null,null,['tab'=>$tab,...array_intersect_key($data,array_flip($editable))]);cache()->delete('staff-dashboard-v4');return redirect()->to(site_url('settings?tab='.urlencode($tab)))->with('message','Settings saved.');
    }
    public function savePurok()
    {
        $name=trim((string)$this->request->getPost('name'));$description=trim((string)$this->request->getPost('description'));$sort=filter_var($this->request->getPost('sort_order')??0,FILTER_VALIDATE_INT);
        if($name===''||mb_strlen($name)>80||mb_strlen($description)>255||$sort===false||$sort<0)return redirect()->back()->withInput()->with('error','Enter a unique name (up to 80 characters), description (up to 255), and non-negative sort order.');
        $db=db_connect();if($db->table('puroks')->where('name',$name)->countAllResults())return redirect()->back()->withInput()->with('error','That purok name already exists.');
        $data=['name'=>$name,'description'=>$description?:null,'is_active'=>1,'sort_order'=>$sort,'created_at'=>date('Y-m-d H:i:s')];$db->table('puroks')->insert($data);(new AuditLogger())->log('create','purok',(int)$db->insertID(),null,$data);cache()->delete('staff-dashboard-v4');return redirect()->to(site_url('settings?tab=puroks'))->with('message','Purok added.');
    }
    public function updatePurok(int $id)
    {
        $db=db_connect();$before=$db->table('puroks')->where('id',$id)->get()->getRowArray();if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $name=trim((string)$this->request->getPost('name'));$description=trim((string)$this->request->getPost('description'));$sort=filter_var($this->request->getPost('sort_order')??0,FILTER_VALIDATE_INT);
        if($name===''||mb_strlen($name)>80||mb_strlen($description)>255||$sort===false||$sort<0)return redirect()->back()->withInput()->with('error','Enter a valid name, description, and non-negative sort order.');
        if($db->table('puroks')->where('name',$name)->where('id !=',$id)->countAllResults())return redirect()->back()->withInput()->with('error','That purok name already exists.');
        $data=['name'=>$name,'description'=>$description?:null,'sort_order'=>$sort,'updated_at'=>date('Y-m-d H:i:s')];$db->table('puroks')->where('id',$id)->update($data);(new AuditLogger())->log('update','purok',$id,$before,$data);cache()->delete('staff-dashboard-v4');return redirect()->to(site_url('settings?tab=puroks'))->with('message','Purok updated.');
    }
    public function togglePurok(int $id)
    {
        $row=db_connect()->table('puroks')->where('id',$id)->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$active=(int)!$row['is_active'];db_connect()->table('puroks')->where('id',$id)->update(['is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s')]);(new AuditLogger())->log($active?'activate':'deactivate','purok',$id,$row,['is_active'=>$active]);return redirect()->to(site_url('settings?tab=puroks'))->with('message','Purok status updated.');
    }
    public function saveVaccine()
    {
        $data=$this->vaccineInput();if(is_string($data))return redirect()->back()->withInput()->with('error',$data);$db=db_connect();
        if($db->table('vaccines')->where('code',$data['code'])->countAllResults())return redirect()->back()->withInput()->with('error','That vaccine code already exists.');
        $data+=['is_active'=>1,'created_at'=>date('Y-m-d H:i:s')];$db->table('vaccines')->insert($data);(new AuditLogger())->log('create','vaccine',(int)$db->insertID(),null,$data);return redirect()->to(site_url('settings?tab=vaccines'))->with('message','Vaccine added.');
    }
    public function updateVaccine(int $id)
    {
        $db=db_connect();$before=$db->table('vaccines')->where('id',$id)->get()->getRowArray();if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$data=$this->vaccineInput();if(is_string($data))return redirect()->back()->withInput()->with('error',$data);
        if($db->table('vaccines')->where('code',$data['code'])->where('id !=',$id)->countAllResults())return redirect()->back()->withInput()->with('error','That vaccine code already exists.');$data['is_active']=(int)(bool)$this->request->getPost('is_active');$data['updated_at']=date('Y-m-d H:i:s');$db->table('vaccines')->where('id',$id)->update($data);(new AuditLogger())->log('update','vaccine',$id,$before,$data);return redirect()->to(site_url('settings?tab=vaccines'))->with('message','Vaccine updated.');
    }
    public function saveDose()
    {
        $data=$this->doseInput();if(is_string($data))return redirect()->back()->withInput()->with('error',$data);$db=db_connect();if($db->table('vaccine_doses')->where('code',$data['code'])->countAllResults())return redirect()->back()->withInput()->with('error','That dose code already exists.');
        $data+=['is_active'=>1,'created_at'=>date('Y-m-d H:i:s')];$db->table('vaccine_doses')->insert($data);(new AuditLogger())->log('create','vaccine_dose',(int)$db->insertID(),null,$data);return redirect()->to(site_url('settings?tab=vaccines'))->with('message','Vaccine dose added.');
    }
    public function updateDose(int $id)
    {
        $db=db_connect();$before=$db->table('vaccine_doses')->where('id',$id)->get()->getRowArray();if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$data=$this->doseInput();if(is_string($data))return redirect()->back()->withInput()->with('error',$data);
        if($db->table('vaccine_doses')->where('code',$data['code'])->where('id !=',$id)->countAllResults())return redirect()->back()->withInput()->with('error','That dose code already exists.');$data['is_active']=(int)(bool)$this->request->getPost('is_active');$data['updated_at']=date('Y-m-d H:i:s');$db->table('vaccine_doses')->where('id',$id)->update($data);(new AuditLogger())->log('update','vaccine_dose',$id,$before,$data);return redirect()->to(site_url('settings?tab=vaccines'))->with('message','Vaccine dose updated.');
    }
    private function vaccineInput(): array|string
    {
        $code=strtoupper(trim((string)$this->request->getPost('code')));$name=trim((string)$this->request->getPost('name'));$route=trim((string)$this->request->getPost('route'));$description=trim((string)$this->request->getPost('description'));$sort=filter_var($this->request->getPost('sort_order')??0,FILTER_VALIDATE_INT);
        if(!preg_match('/^[A-Z0-9_-]{1,16}$/',$code)||$name===''||mb_strlen($name)>120||mb_strlen($route)>40||$sort===false||$sort<0)return 'Enter a valid vaccine code, name, route, and sort order.';
        return ['code'=>$code,'name'=>$name,'route'=>$route?:null,'description'=>$description?:null,'sort_order'=>$sort];
    }
    private function doseInput(): array|string
    {
        $integer=fn($key)=>filter_var($this->request->getPost($key),FILTER_VALIDATE_INT);$vaccineId=(int)$this->request->getPost('vaccine_id');$number=$integer('dose_number');$recommended=$integer('recommended_age_days');$minimum=$integer('min_age_days');$interval=$integer('min_interval_days');$code=strtoupper(trim((string)$this->request->getPost('code')));$label=trim((string)$this->request->getPost('label'));
        if(!db_connect()->table('vaccines')->where('id',$vaccineId)->countAllResults()||$number===false||$number<1||$recommended===false||$recommended<0||$minimum===false||$minimum<0||$minimum>$recommended||$interval===false||$interval<0||!preg_match('/^[A-Z0-9_-]{1,20}$/',$code)||$label===''||mb_strlen($label)>80)return 'Enter a valid vaccine dose, code, and non-negative schedule ages (minimum age cannot exceed recommended age).';
        return ['vaccine_id'=>$vaccineId,'dose_number'=>$number,'code'=>$code,'label'=>$label,'recommended_age_days'=>$recommended,'min_age_days'=>$minimum,'min_interval_days'=>$interval,'is_booster'=>(int)(bool)$this->request->getPost('is_booster')];
    }
    public function backup()
    {
        $command=[PHP_BINARY,ROOTPATH.'spark','db:backup'];$pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOTPATH);
        if(!is_resource($process))return redirect()->back()->with('error','Unable to start backup command.');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
        return redirect()->to(site_url('settings?tab=backups'))->with($status===0?'message':'error',$status===0?trim($out):trim($err));
    }
    public function downloadBackup(string $filename)
    {
        $filename=basename($filename);$path=WRITEPATH.'backups/'.$filename;
        if(!is_file($path))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        (new AuditLogger())->log('download_backup','backup',null,null,['filename'=>$filename]);return $this->response->download($path,null)->setFileName($filename);
    }
}

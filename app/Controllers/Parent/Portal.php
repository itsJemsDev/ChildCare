<?php
declare(strict_types=1);
namespace App\Controllers\Parent;
use App\Controllers\BaseController;
use App\Policies\ChildAccessPolicy;
use App\Services\ChildService;
use App\Services\DocumentStorage;
use App\Services\NotificationService;

final class Portal extends BaseController
{
    private function linkedChildren(): array {return (new ChildAccessPolicy())->childrenFor(auth()->user());}
    private function owned(int $id): array {if(!(new ChildAccessPolicy())->canView(auth()->user(),$id))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$row=(new ChildService())->find($id);if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();return $row;}
    private function page(string $title,array $data=[]): string {return view('parent/page',['title'=>$title,...$data]);}
    public function dashboard(): string
    {
        $children=$this->linkedChildren();$db=db_connect();$ids=array_map('intval',array_column($children,'id'));$selectedId=(int)$this->request->getGet('child_id');
        if(!in_array($selectedId,$ids,true))$selectedId=$ids[0]??0;
        $selected=null;foreach($children as $child)if((int)$child['id']===$selectedId){$selected=$child;break;}
        $next=null;$growth=[];$upcoming=0;
        if($selectedId){$next=$db->table('immunizations i')->select('i.*,vd.code,vd.min_age_days,v.name AS vaccine')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.child_id',$selectedId)->where('i.status','pending')->where('i.deleted_at',null)->orderBy('i.due_date')->get()->getRowArray();$upcoming=(int)$db->table('immunizations')->where('child_id',$selectedId)->where('status','pending')->where('due_date >=',date('Y-m-d'))->where('deleted_at',null)->countAllResults();$growth=$db->table('growth_records')->where('child_id',$selectedId)->where('deleted_at',null)->orderBy('visit_date','DESC')->limit(12)->get()->getResultArray();}
        $unread=(new NotificationService())->unreadCount((int)auth()->id());$notifications=$db->table('notifications')->where('user_id',auth()->id())->where('deleted_at',null)->orderBy('created_at','DESC')->limit(5)->get()->getResultArray();
        return view('parent/dashboard',['title'=>'Parent Dashboard','children'=>$children,'selected'=>$selected,'next'=>$next,'growth'=>array_reverse($growth),'latestGrowth'=>$growth[0]??null,'upcomingCount'=>$upcoming,'unread'=>$unread,'notifications'=>$notifications,'today'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->format('F j, Y')]);
    }
    public function children(): string {return $this->page('My Children',['children'=>$this->linkedChildren()]);}
    public function newChild(): string {return $this->page('Submit Child Registration',['registrationForm'=>true]);}
    public function submitChild()
    {
        $rules=['first_name'=>'required|max_length[100]','last_name'=>'required|max_length[100]','sex'=>'required|in_list[male,female]','birthdate'=>'required|valid_date[Y-m-d]|not_future_date','consent_given'=>'required|in_list[1]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $guardian=db_connect()->table('guardians')->where('user_id',auth()->id())->where('deleted_at',null)->get()->getRowArray();
        if(!$guardian)return redirect()->back()->with('error','Your portal account is not linked to a guardian record. Contact the health center.');
        $files=$this->request->getFiles();$uploads=[];foreach(['birth_certificate','residency_certificate','immunization_card'] as $type)if(isset($files[$type])&&$files[$type]->isValid())$uploads[$type]=$files[$type];
        $data=$this->request->getPost();$guardianData=['first_name'=>$guardian['first_name'],'last_name'=>$guardian['last_name'],'mobile_no'=>$guardian['mobile_no'],'email'=>$guardian['email'],'relationship'=>$this->request->getPost('relationship')?:'guardian'];
        try{$id=(new ChildService())->create($data,$guardianData,$uploads,(int)auth()->id(),true);}
        catch(\Throwable $e){log_message('error','Parent child registration failed: {error}',['error'=>$e->getMessage()]);return redirect()->back()->withInput()->with('error','The registration could not be saved. Check the child details and uploaded files, then try again.');}
        $staff=db_connect()->table('auth_groups_users')->select('user_id')->whereIn('group',['admin','bhw'])->groupBy('user_id')->get()->getResultArray();foreach($staff as $member)(new NotificationService())->create((int)$member['user_id'],'registration_pending','New child registration','A parent submitted a child registration for verification.',site_url('registrations/pending'));
        return redirect()->to(site_url('parent/children/'.$id))->with('message','Registration submitted. Health center staff will verify it.');
    }
    public function child(int $id): string
    {
        $child=$this->owned($id);$db=db_connect();$child['doses']=$db->table('immunizations i')->select('i.*,vd.code,v.name AS vaccine')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.child_id',$id)->orderBy('i.due_date')->get()->getResultArray();$child['growth_records']=$db->table('growth_records')->where('child_id',$id)->where('deleted_at',null)->orderBy('visit_date','DESC')->get()->getResultArray();$child['checkups']=$db->table('checkups')->where('child_id',$id)->where('deleted_at',null)->orderBy('checkup_date','DESC')->get()->getResultArray();$child['documents']=$db->table('child_documents')->where('child_id',$id)->where('deleted_at',null)->get()->getResultArray();
        return $this->page('Child Health Record',['child'=>$child]);
    }
    public function immunizations(): string {$ids=array_column($this->linkedChildren(),'id');$rows=$ids?db_connect()->table('immunizations i')->select('i.*,c.first_name,c.last_name,vd.code,v.name AS vaccine')->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->whereIn('i.child_id',$ids)->orderBy('i.due_date')->get()->getResultArray():[];return $this->page('Immunization Schedule',['rows'=>$rows]);}
    public function setDoseReminder(int $immunizationId)
    {
        $db=db_connect();$row=$db->table('immunizations')->where('id',$immunizationId)->where('deleted_at',null)->get()->getRowArray();if(!$row||!(new ChildAccessPolicy())->canView(auth()->user(),(int)$row['child_id']))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        if($row['status']!=='pending')return redirect()->back()->with('error','Reminders can only be set for pending doses.');
        $enabled=(int)(bool)$this->request->getPost('reminder_opt_in');$db->table('immunizations')->where('id',$immunizationId)->update(['reminder_opt_in'=>$enabled,'updated_at'=>date('Y-m-d H:i:s')]);(new \App\Services\AuditLogger())->log('dose_reminder_preference','immunization',$immunizationId,['reminder_opt_in'=>(int)$row['reminder_opt_in']],['reminder_opt_in'=>$enabled]);return redirect()->back()->with('message',$enabled?'An extra SMS reminder is enabled for this dose.':'The extra SMS reminder is turned off.');
    }
    public function growth(): string {$ids=array_column($this->linkedChildren(),'id');$rows=$ids?db_connect()->table('growth_records g')->select('g.*,c.first_name,c.last_name')->join('children c','c.id=g.child_id')->whereIn('g.child_id',$ids)->where('g.deleted_at',null)->orderBy('g.visit_date','DESC')->get()->getResultArray():[];return $this->page('Growth Monitoring',['rows'=>$rows]);}
    public function medicalHistory(): string {$ids=array_column($this->linkedChildren(),'id');$rows=$ids?db_connect()->table('checkups ch')->select('ch.*,c.first_name,c.last_name')->join('children c','c.id=ch.child_id')->whereIn('ch.child_id',$ids)->where('ch.deleted_at',null)->orderBy('ch.checkup_date','DESC')->get()->getResultArray():[];return $this->page('Medical History',['rows'=>$rows]);}
    public function notifications(): string {$query=db_connect()->table('notifications')->where('user_id',auth()->id())->where('deleted_at',null)->orderBy('created_at','DESC');$page=\App\Services\QueryPaginator::paginate($query,20);return $this->page('Notifications',['rows'=>$page['rows'],'pager'=>$page['pager']]);}
    public function readNotification(int $id) {db_connect()->table('notifications')->where(['id'=>$id,'user_id'=>auth()->id()])->update(['read_at'=>date('Y-m-d H:i:s')]);return redirect()->back();}
    public function appointments(): string {$rows=db_connect()->table('appointments a')->select('a.*,c.first_name,c.last_name')->join('children c','c.id=a.child_id')->where('a.requested_by',auth()->id())->where('a.deleted_at',null)->orderBy('a.preferred_date','DESC')->get()->getResultArray();return $this->page('Appointments',['appointments'=>$rows,'children'=>$this->linkedChildren()]);}
    public function requestAppointment()
    {
        $child=(int)$this->request->getPost('child_id');$this->owned($child);$date=(string)$this->request->getPost('preferred_date');$dateValue=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);$type=(string)$this->request->getPost('type');if(!$dateValue||$dateValue->format('Y-m-d')!==$date||$date<=date('Y-m-d')||!in_array($type,['immunization','check-up','growth monitoring','consultation'],true)||mb_strlen((string)$this->request->getPost('time_slot'))>30||mb_strlen((string)$this->request->getPost('notes'))>2000)return redirect()->back()->withInput()->with('error','Choose a valid appointment type and future date.');
        $now=date('Y-m-d H:i:s');db_connect()->table('appointments')->insert(['child_id'=>$child,'requested_by'=>auth()->id(),'type'=>$type,'preferred_date'=>$date,'time_slot'=>$this->request->getPost('time_slot'),'notes'=>$this->request->getPost('notes'),'status'=>'requested','created_at'=>$now,'updated_at'=>$now]);
        $staff=db_connect()->table('auth_groups_users')->select('user_id')->whereIn('group',['admin','bhw'])->groupBy('user_id')->get()->getResultArray();foreach($staff as $u)(new NotificationService())->create((int)$u['user_id'],'appointment_request','New appointment request','A parent requested '.$type.' on '.format_date($date),site_url('appointments'));
        return redirect()->to(site_url('parent/appointments'))->with('message','Appointment request sent to the health center.');
    }
    public function documents(): string {$children=$this->linkedChildren();$ids=array_map('intval',array_column($children,'id'));$rows=$ids?db_connect()->table('child_documents d')->select('d.id,d.child_id,d.document_type,d.original_name,d.mime_type,d.size_bytes,d.status,d.created_at')->join('children c','c.id=d.child_id')->whereIn('d.child_id',$ids)->where('d.deleted_at',null)->orderBy('d.created_at','DESC')->get()->getResultArray():[];return $this->page('Documents',['rows'=>$rows,'children'=>$children,'documentsPage'=>true]);}
    public function uploadDocument(int $id)
    {
        $this->owned($id);$file=$this->request->getFile('document');$type=(string)$this->request->getPost('document_type');if(!in_array($type,['birth_certificate','residency_certificate','immunization_card','other'],true)||!$file||!$file->isValid())return redirect()->back()->with('error','Select a valid document and type.');
        $storage=new DocumentStorage();$stored=null;$db=db_connect();
        try{$stored=$storage->store($file,$id);$now=date('Y-m-d H:i:s');$db->table('child_documents')->insert(['child_id'=>$id,'document_type'=>$type,'original_name'=>$stored['original_name'],'stored_name'=>$stored['stored_name'],'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes'],'status'=>'pending','uploaded_by'=>auth()->id(),'created_at'=>$now,'updated_at'=>$now]);$documentId=(int)$db->insertID();(new \App\Services\AuditLogger())->log('upload','child_document',$documentId,null,['child_id'=>$id,'document_type'=>$type,'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes']]);}
        catch(\Throwable $e){if(is_array($stored)&&is_file($stored['path']))@unlink($stored['path']);log_message('error','Parent document upload failed: {error}',['error'=>$e->getMessage()]);return redirect()->back()->with('error','The document could not be uploaded. Use a PDF, JPG, or PNG file no larger than 5 MB.');}
        return redirect()->to(site_url('parent/children/'.$id))->with('message','Document uploaded for staff verification.');
    }
    public function document(int $id,int $documentId)
    {
        $this->owned($id);$row=db_connect()->table('child_documents')->where(['id'=>$documentId,'child_id'=>$id,'deleted_at'=>null])->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $path=(new DocumentStorage())->path($id,$row['stored_name']);
        if($this->request->getGet('download')==='1'){(new \App\Services\AuditLogger())->log('download_document','child_document',$documentId);return $this->response->download($path,null)->setFileName(basename($row['original_name']));}
        (new \App\Services\AuditLogger())->log('view_document','child_document',$documentId);return $this->response->setHeader('Content-Type',$row['mime_type'])->setHeader('Content-Disposition','inline; filename="'.str_replace(['"',"\\",'\r','\n'],'_',basename($row['original_name'])).'"')->setHeader('X-Content-Type-Options','nosniff')->setBody(file_get_contents($path));
    }
    public function photo(int $id)
    {
        $child=$this->owned($id);if(empty($child['photo_path']))throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$path=(new DocumentStorage())->path($id,$child['photo_path']);$extension=pathinfo($path,PATHINFO_EXTENSION);return $this->response->setHeader('Content-Type',$extension==='png'?'image/png':'image/jpeg')->setHeader('Content-Disposition','inline')->setHeader('X-Content-Type-Options','nosniff')->setBody(file_get_contents($path));
    }
    public function profile(): string {$guardian=db_connect()->table('guardians')->where('user_id',auth()->id())->where('deleted_at',null)->get()->getRowArray();return $this->page('Profile Settings',['user'=>auth()->user(),'guardian'=>$guardian]);}
    public function saveProfile()
    {
        $user=auth()->user();$name=trim((string)$this->request->getPost('first_name'));if($name==='')return redirect()->back()->withInput()->with('error','First name is required.');
        $data=['first_name'=>$name,'middle_name'=>$this->request->getPost('middle_name'),'last_name'=>$this->request->getPost('last_name'),'email'=>$this->request->getPost('email')];$mobile=trim((string)$this->request->getPost('mobile_no'));
        if($mobile!==''&&format_ph_mobile($mobile)!==($user->mobile_no??''))return redirect()->back()->withInput()->with('error','Changing your mobile number requires verification by the health center. Please contact staff.');
        $provider=auth()->getProvider();$user->fill($data);$provider->save($user);return redirect()->back()->with('message','Profile updated.');
    }
    public function savePreferences()
    {
        $db=db_connect();$guardian=$db->table('guardians')->where('user_id',auth()->id())->where('deleted_at',null)->get()->getRowArray();
        if(!$guardian)return redirect()->back()->with('error','No guardian record is linked to this account.');
        $enabled=(int)(bool)$this->request->getPost('sms_opt_in');$db->table('guardians')->where('id',$guardian['id'])->update(['sms_opt_in'=>$enabled,'updated_at'=>date('Y-m-d H:i:s')]);
        (new \App\Services\AuditLogger())->log('notification_preferences','guardian',(int)$guardian['id'],['sms_opt_in'=>(int)$guardian['sms_opt_in']],['sms_opt_in'=>$enabled]);
        return redirect()->to(site_url('parent/profile'))->with('message','Notification preferences saved.');
    }

    public function requestMobileVerification()
    {
        $mobile=format_ph_mobile((string)$this->request->getPost('mobile_no'));if($mobile==='')return redirect()->back()->with('error','Enter a valid Philippine mobile number.');
        $used=db_connect()->table('users')->where('mobile_no',$mobile)->where('id !=',(int)auth()->id())->where('deleted_at',null)->countAllResults();if($used)return redirect()->back()->with('error','That mobile number is already linked to an account.');
        if(!(new \App\Services\OtpService())->send($mobile,'verify_mobile',(int)auth()->id()))return redirect()->back()->with('error','Unable to send a verification code right now. Wait one minute and try again.');
        session()->set('pending_mobile_verification',$mobile);return redirect()->back()->with('message','If the number can receive messages, a verification code was sent.');
    }
    public function verifyMobile()
    {
        $mobile=(string)session('pending_mobile_verification');$code=(string)$this->request->getPost('token');
        if($mobile===''||!(new \App\Services\OtpService())->verify($mobile,'verify_mobile',$code))return redirect()->back()->with('error','Invalid or expired mobile verification code.');
        $db=db_connect();if($db->table('users')->where('mobile_no',$mobile)->where('id !=',(int)auth()->id())->where('deleted_at',null)->countAllResults())return redirect()->back()->with('error','That mobile number is already linked to an account.');
        $user=auth()->user();$old=(string)$user->mobile_no;$provider=auth()->getProvider();$user->fill(['mobile_no'=>$mobile]);$provider->save($user);$db->table('guardians')->where('user_id',$user->id)->update(['mobile_no'=>$mobile,'updated_at'=>date('Y-m-d H:i:s')]);session()->remove('pending_mobile_verification');
        (new \App\Services\AuditLogger())->log('mobile_verified','user',(int)$user->id,['mobile_last4'=>substr($old,-4)],['mobile_last4'=>substr($mobile,-4)]);return redirect()->to(site_url('parent/profile'))->with('message','Mobile number verified and updated.');
    }
    public function changePassword()
    {
        $rules=['current_password'=>'required','password'=>'required|min_length[12]','password_confirm'=>'required|matches[password]'];if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $user=auth()->user();$valid=auth('session')->getAuthenticator()->check(['username'=>$user->username,'password'=>(string)$this->request->getPost('current_password')]);if(!$valid->isOK())return redirect()->back()->with('error','Current password is incorrect.');
        $provider=auth()->getProvider();$user->fill(['password'=>(string)$this->request->getPost('password')]);$provider->save($user);(new \App\Services\AuditLogger())->log('password_changed','user',(int)$user->id);return redirect()->to(site_url('parent/profile'))->with('message','Password changed.');
    }
    public function pdf(int $id)
    {
        $child=$this->owned($id);$db=db_connect();$child['growth_records']=$db->table('growth_records')->where('child_id',$id)->where('deleted_at',null)->orderBy('visit_date','DESC')->get()->getResultArray();$child['doses']=$db->table('immunizations i')->select('i.*,vd.code,v.name AS vaccine')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.child_id',$id)->orderBy('i.due_date')->get()->getResultArray();$child['checkups']=$db->table('checkups')->where('child_id',$id)->where('deleted_at',null)->orderBy('checkup_date','DESC')->get()->getResultArray();$html=view('pdf/child_record',['child'=>$child,'centerName'=>setting('ChildCare.center_name')]);
        $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false]);$pdf->loadHtml($html);$pdf->setPaper('letter');$pdf->render();return $this->response->setHeader('Content-Type','application/pdf')->setHeader('Content-Disposition','attachment; filename="child-record-'.$child['child_code'].'.pdf"')->setBody($pdf->output());
    }
}

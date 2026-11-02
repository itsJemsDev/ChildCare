<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Services\Sms\TemplateRenderer;
final class Sms extends BaseController
{
    public function compose(): string
    {
        $db=db_connect();return view('staff/sms/compose',['title'=>'SMS Notifications','templates'=>$db->table('sms_templates')->where('is_active',1)->orderBy('name')->get()->getResultArray(),'puroks'=>$db->table('puroks')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray(),'categories'=>['A'=>'Healthy','B'=>'Malnourished','C'=>'Obese'],'batches'=>$db->table('sms_batches')->orderBy('id','DESC')->limit(10)->get()->getResultArray(),'estimate'=>0]);
    }
    public function countRecipients()
    {
        $data=$this->request->getGet();$recipients=$this->recipients($data);$body=(string)($data['body']??'');$renderer=new TemplateRenderer();$segments=0;$preview='';
        foreach($recipients as $recipient){$rendered=$renderer->render($body,$recipient['values']);$segments+=$renderer->segments($rendered);if($preview==='')$preview=$rendered;}
        return $this->response->setJSON(['count'=>count($recipients),'segments'=>$segments,'preview'=>$preview]);
    }
    public function queue()
    {
        $data=$this->request->getPost();$body=trim((string)($data['body']??''));if($body===''||mb_strlen($body)>1000)return redirect()->back()->withInput()->with('error','Enter a message of 1 to 1,000 characters.');
        if(!in_array((string)($data['recipient_type']??'all'),['all','purok','category','due','overdue','selected'],true))return redirect()->back()->withInput()->with('error','Choose a valid recipient group.');
        $templateId=empty($data['template_id'])?null:(int)$data['template_id'];if($templateId&&!db_connect()->table('sms_templates')->where(['id'=>$templateId,'is_active'=>1])->where('deleted_at',null)->countAllResults())return redirect()->back()->withInput()->with('error','Choose an active message template.');
        if(($data['recipient_type']??'')==='due'){$from=(string)($data['due_from']??'');$to=(string)($data['due_to']??'');$a=\DateTimeImmutable::createFromFormat('!Y-m-d',$from);$b=\DateTimeImmutable::createFromFormat('!Y-m-d',$to);if(!$a||!$b||$a->format('Y-m-d')!==$from||$b->format('Y-m-d')!==$to||$from>$to)return redirect()->back()->withInput()->with('error','Choose a valid due-date range.');}
        $recipients=$this->recipients($data);if($recipients===[])return redirect()->back()->withInput()->with('error','No eligible recipients match your selection.');
        $renderer=new TemplateRenderer();$credits=0;foreach($recipients as &$r){$r['body']=$body;$r['values']['message']=$data['message']??'';$credits+=$renderer->segments($renderer->render($body,$r['values']));}unset($r);
        $scheduled=null;if(($data['send_mode']??'now')==='schedule'){$date=(string)($data['scheduled_date']??'');$time=(string)($data['scheduled_time']??'07:00');$scheduledAt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,new \DateTimeZone('Asia/Manila'));if(!$scheduledAt||$scheduledAt->format('Y-m-d H:i')!==$date.' '.$time||$scheduledAt<new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))return redirect()->back()->withInput()->with('error','Choose a valid future schedule date and time.');$scheduled=$scheduledAt->format('Y-m-d H:i:s');}elseif(($data['send_mode']??'now')!=='now')return redirect()->back()->withInput()->with('error','Choose a valid send time.');
        try{$batch=(new SmsService())->queue((string)($data['title']??'Custom reminder'),(string)($data['message_type']??'general'),$recipients,$scheduled,(int)auth()->id(),$templateId);(new AuditLogger())->log('sms_queued','sms_batch',$batch,null,['recipients'=>count($recipients),'segments'=>$credits]);return redirect()->to(site_url('sms'))->with('message','SMS batch queued for '.count($recipients).' personalized messages. Estimated credits: '.$credits.'.');}
        catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }
    public function logs(): string
    {
        $query=db_connect()->table('sms_messages sm')->select('sm.*,c.first_name,c.last_name,b.title AS batch_title')->join('children c','c.id=sm.child_id','left')->join('sms_batches b','b.id=sm.batch_id','left');
        if($status=$this->request->getGet('status'))$query->where('sm.status',$status);if($type=$this->request->getGet('type'))$query->where('b.message_type',$type);if($from=$this->request->getGet('from'))$query->where('sm.created_at >=',$from.' 00:00:00');if($to=$this->request->getGet('to'))$query->where('sm.created_at <=',$to.' 23:59:59');
        $page=\App\Services\QueryPaginator::paginate($query->orderBy('sm.id','DESC'),30);return view('staff/sms/logs',['title'=>'SMS Logs','rows'=>$page['rows'],'pager'=>$page['pager']]);
    }
    public function retry(int $id)
    {
        $db=db_connect();$row=$db->table('sms_messages')->where('id',$id)->where('status','failed')->get()->getRowArray();if(!$row)return redirect()->back()->with('error','Only failed messages can be retried.');
        $db->table('sms_messages')->where('id',$id)->where('status','failed')->update(['status'=>'queued','scheduled_at'=>null,'attempts'=>0,'error_message'=>null,'provider_message_id'=>null,'provider_status'=>null,'updated_at'=>date('Y-m-d H:i:s')]);
        (new AuditLogger())->log('sms_retry','sms_message',$id,$row,['status'=>'queued']);return redirect()->back()->with('message','Message requeued for background delivery.');
    }
    public function importContacts(): string {return view('staff/sms/import',['title'=>'Import Guardian Contacts']);}
    public function processContacts()
    {
        $file=$this->request->getFile('contacts');if(!$file||!$file->isValid()||$file->getSize()>2*1024*1024)return redirect()->back()->with('error','Choose a valid CSV file no larger than 2 MB.');
        $handle=fopen($file->getTempName(),'rb');if(!$handle)return redirect()->back()->with('error','Unable to read the uploaded CSV.');
        $headers=fgetcsv($handle);$headers=array_map(static fn($v)=>strtolower(trim((string)$v)),is_array($headers)?$headers:[]);$required=['first_name','last_name','mobile_no'];if(array_diff($required,$headers)){fclose($handle);return redirect()->back()->with('error','CSV headers must include first_name,last_name,mobile_no. Optional headers: email,sms_opt_in.');}
        $db=db_connect();$accepted=0;$updated=0;$issues=[];$line=1;
        while(($values=fgetcsv($handle))!==false){$line++;if($line>5001){$issues[]=['line'=>$line,'issue'=>'Import limit is 5,000 rows. Remaining rows were not processed.'];break;}if(count($values)!==count($headers)){$issues[]=['line'=>$line,'issue'=>'Column count does not match the header.'];continue;}$row=array_combine($headers,$values);$first=trim((string)$row['first_name']);$last=trim((string)$row['last_name']);$mobile=format_ph_mobile((string)$row['mobile_no']);if($first===''||$last===''||$mobile===''){$issues[]=['line'=>$line,'issue'=>'Name or Philippine mobile number is invalid.'];continue;}
            $existing=$db->table('guardians')->where('mobile_no',$mobile)->where('deleted_at',null)->get()->getRowArray();$payload=['first_name'=>$first,'last_name'=>$last,'mobile_no'=>$mobile,'email'=>trim((string)($row['email']??''))?:null,'updated_at'=>date('Y-m-d H:i:s')];if(array_key_exists('sms_opt_in',$row))$payload['sms_opt_in']=filter_var($row['sms_opt_in'],FILTER_VALIDATE_BOOLEAN)?1:0;
            if($existing){$db->table('guardians')->where('id',$existing['id'])->update($payload);$updated++;}else{$payload['created_at']=date('Y-m-d H:i:s');$db->table('guardians')->insert($payload);$accepted++;}
        }
        fclose($handle);(new AuditLogger())->log('import','guardians',null,null,['created'=>$accepted,'updated'=>$updated,'rejected'=>count($issues)]);return redirect()->to(site_url('sms/import'))->with('import_report',['created'=>$accepted,'updated'=>$updated,'issues'=>$issues])->with('message','Contact import finished.');
    }
    public function templates(): string
    {
        return view('staff/sms/templates',['title'=>'SMS Templates','rows'=>db_connect()->table('sms_templates')->where('deleted_at',null)->orderBy('name')->get()->getResultArray()]);
    }
    public function saveTemplate()
    {
        $data=['name'=>trim((string)$this->request->getPost('name')),'type'=>trim((string)$this->request->getPost('type')),'body'=>trim((string)$this->request->getPost('body')),'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
        if($data['name']===''||$data['body']==='')return redirect()->back()->with('error','Template name and message are required.');db_connect()->table('sms_templates')->insert($data);(new AuditLogger())->log('create','sms_template',(int)db_connect()->insertID(),null,$data);return redirect()->to(site_url('sms/templates'))->with('message','SMS template added.');
    }
    public function updateTemplate(int $id)
    {
        $db=db_connect();$before=$db->table('sms_templates')->where('id',$id)->where('deleted_at',null)->get()->getRowArray();if(!$before)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$data=['name'=>trim((string)$this->request->getPost('name')),'type'=>trim((string)$this->request->getPost('type')),'body'=>trim((string)$this->request->getPost('body')),'is_active'=>(int)(bool)$this->request->getPost('is_active'),'updated_at'=>date('Y-m-d H:i:s')];if(!$data['name']||!$data['type']||!$data['body'])return redirect()->back()->with('error','Template name, type, and message are required.');$db->table('sms_templates')->where('id',$id)->update($data);(new AuditLogger())->log('update','sms_template',$id,$before,$data);return redirect()->to(site_url('sms/templates'))->with('message','SMS template updated.');
    }
    public function deleteTemplate(int $id)
    {
        $db=db_connect();$row=$db->table('sms_templates')->where('id',$id)->where('deleted_at',null)->get()->getRowArray();if(!$row)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$db->table('sms_templates')->where('id',$id)->update(['deleted_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);(new AuditLogger())->log('soft_delete','sms_template',$id,$row,['deleted_at'=>date('Y-m-d H:i:s')]);return redirect()->to(site_url('sms/templates'))->with('message','SMS template archived.');
    }
    private function recipients(array $data): array
    {
        $db=db_connect();$type=(string)($data['recipient_type']??'all');$builder=$db->table('guardians g')->select('g.id AS guardian_id,g.first_name AS guardian_name,g.mobile_no,c.id AS child_id,c.first_name AS child_name,c.last_name AS child_last,c.purok_id,c.nutrition_category')
            ->select('COALESCE(c.purok_id,g.purok_id) AS recipient_purok',false)->join('child_guardian cg','cg.guardian_id=g.id AND cg.is_primary = 1','left')->join('children c','c.id=cg.child_id','left')->where('g.sms_opt_in',1);
        $builder->groupStart()->groupStart()->where('c.registration_status','verified')->where('c.deleted_at',null)->groupEnd()->orGroupStart()->where('cg.child_id',null)->groupEnd()->groupEnd();
        switch($type){
            case 'purok': $purokId=(int)($data['purok_id']??0);$builder->groupStart()->where('c.purok_id',$purokId)->orGroupStart()->where('c.purok_id',null)->where('g.purok_id',$purokId)->groupEnd()->groupEnd();break;
            case 'category': $builder->where('c.nutrition_category',$data['category']??'');break;
            case 'due': $builder->where('c.id IS NOT NULL',null,false)->join('immunizations i','i.child_id=c.id')->where('i.status','pending')->where('i.due_date >=',$data['due_from']??date('Y-m-d'))->where('i.due_date <=',$data['due_to']??date('Y-m-d',strtotime('+30 days')));break;
            case 'overdue': $builder->where('c.id IS NOT NULL',null,false)->join('immunizations i','i.child_id=c.id')->where('i.status','pending')->where('i.due_date <',date('Y-m-d'));break;
            case 'selected': $raw=$data['child_ids']??explode(',',(string)($data['child_ids_text']??''));$ids=array_map('intval',array_filter((array)$raw,static fn($v)=>trim((string)$v)!==''));if(!$ids)return []; $builder->whereIn('c.id',$ids);break;
        }
        $rows=$builder->distinct()->get()->getResultArray();$out=[];
        foreach($rows as $row)$out[]=['guardian_id'=>(int)$row['guardian_id'],'child_id'=>$row['child_id']?(int)$row['child_id']:null,'number'=>$row['mobile_no'],'body'=>'','values'=>['guardian_name'=>$row['guardian_name'],'child_name'=>trim((string)($row['child_name']??'').' '.(string)($row['child_last']??'')),'center_name'=>setting('ChildCare.center_name')??'Barangay Health Center','date'=>$data['date']??'','time'=>$data['time']??'','vaccine'=>$data['vaccine']??'']];
        return $out;
    }
}

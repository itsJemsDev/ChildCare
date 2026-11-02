<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\ChildService;
use App\Services\ExportService;

final class Children extends BaseController
{
    public function index(): string
    {
        $filters=$this->request->getGet();$action=(string)($filters['action']??'');if(!in_array($action,['immunization','growth','checkup'],true))$action='';if($action!=='')$filters['registration_status']='verified';
        $model = new ChildService(); $rows = $model->list($filters, 15);
        $pager=service('pager');$details=$pager->getDetails();$total=(int)$details['total'];$from=$total?(((int)$details['currentPage']-1)*(int)$details['perPage'])+1:0;$to=min((int)$details['currentPage']*(int)$details['perPage'],$total);
        return view('staff/children/index', ['title'=>'Child Profiles','children'=>$rows,'pager'=>$pager,'pageInfo'=>['from'=>$from,'to'=>$to,'total'=>$total],'puroks'=>db_connect()->table('puroks')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray(),'filters'=>$filters,'recordAction'=>$action]);
    }
    public function export()
    {
        $filters = $this->request->getGet();
        $rows = (new ChildService())->exportList($filters);
        (new AuditLogger())->log('export', 'children', null, null, ['filters' => $filters, 'count' => count($rows)]);
        $values = array_map(static fn (array $row): array => [
            $row['child_code'] ?? '', $row['first_name'], $row['middle_name'] ?? '', $row['last_name'],
            $row['birthdate'], $row['sex'], $row['purok_name'] ?? '', $row['nutrition_category'] ?? '',
            $row['nutrition_status'] ?? '', $row['immunization_status'], $row['registration_status'],
        ], $rows);
        return (new ExportService())->xlsx('children-' . date('Ymd') . '.xlsx', 'Children',
            ['Child ID', 'First name', 'Middle name', 'Last name', 'Birthdate', 'Sex', 'Purok', 'Category', 'Nutrition status', 'Immunization status', 'Registration status'], $values);
    }

    public function new(): string { return $this->form(); }
    public function edit(int $id): string
    {
        $child = (new ChildService())->find($id); if (! $child) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return $this->form($child);
    }
    public function create()
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $childData = $this->request->getPost(); $guardian = $this->request->getPost('guardian') ?? [];
        $duplicates = (new ChildService())->duplicates($childData, (string) ($guardian['mobile_no'] ?? ''));
        if ($duplicates && ! $this->request->getPost('confirm_duplicate')) return redirect()->back()->withInput()->with('duplicates',$duplicates)->with('error','A similar child record exists. Review the possible duplicate before continuing.');
        try {
            if (!empty($childData['first_weight']) && !empty($childData['first_height'])) {
                (new \App\Services\GrowthCalculator())->calculate($childData, ['visit_date'=>date('Y-m-d'),'weight_kg'=>$childData['first_weight'],'height_cm'=>$childData['first_height'],'measured_position'=>$childData['first_position']??'recumbent']);
            }
            $files = $this->request->getFiles();
            $docs = [];
            foreach (['birth_certificate','residency_certificate','immunization_card'] as $type) if (isset($files[$type]) && $files[$type]->isValid()) $docs[$type] = $files[$type];
            $photo = isset($files['photo']) && $files['photo']->getError() !== UPLOAD_ERR_NO_FILE ? $files['photo'] : null;
            $service = new ChildService(); $id = $service->create($childData, $guardian, $docs, (int) auth()->id(), false, $photo);
            cache()->delete('staff-dashboard-v4');
            return redirect()->to(site_url('children/'.$id))->with('message','Child profile registered.');
        } catch (\Throwable $e) { log_message('error','Child create failed: {error}',['error'=>$e->getMessage()]); return redirect()->back()->withInput()->with('error',$e->getMessage()); }
    }
    public function update(int $id)
    {
        if (! $this->validateData($this->request->getPost(), $this->rules(false))) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $data=$this->request->getPost();$guardian=$data['guardian']??[];$files=$this->request->getFiles();$docs=[];
        foreach(['birth_certificate','residency_certificate','immunization_card'] as $type)if(isset($files[$type])&&$files[$type]->getError()!==UPLOAD_ERR_NO_FILE)$docs[$type]=$files[$type];
        try{(new ChildService())->updateProfile($id,$data,$guardian,$docs,$files['photo']??null,(int)auth()->id());cache()->delete('staff-dashboard-v4');return redirect()->to(site_url('children/'.$id))->with('message','Child profile updated.');}
        catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }
    public function show(int $id): string
    {
        $child=(new ChildService())->find($id); if(!$child) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $db=db_connect();
        $child['documents']=$db->table('child_documents')->where('child_id',$id)->where('deleted_at',null)->orderBy('id')->get()->getResultArray();
        $child['next_dose']=$db->table('immunizations i')->select('i.due_date,v.name AS vaccine,vd.code')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('vaccines v','v.id=vd.vaccine_id')->where('i.child_id',$id)->where('i.status','pending')->orderBy('i.due_date')->get()->getRowArray();
        $child['growth']=$db->table('growth_records')->where('child_id',$id)->where('deleted_at',null)->orderBy('visit_date','DESC')->get()->getRowArray();
        $child['last_checkup']=$db->table('checkups')->where('child_id',$id)->where('deleted_at',null)->orderBy('checkup_date','DESC')->get()->getRowArray();
        return view('staff/children/show',['title'=>'Child Profile','child'=>$child]);
    }
    public function delete(int $id)
    {
        $reason=trim((string)$this->request->getPost('reason')); if($reason==='') return redirect()->back()->with('error','A reason is required to archive a child profile.');
        db_connect()->table('children')->where('id',$id)->update(['deleted_at'=>date('Y-m-d H:i:s'),'remarks'=>trim((string)db_connect()->table('children')->select('remarks')->where('id',$id)->get()->getRow('remarks').'\nArchived: '.$reason)]);
        (new AuditLogger())->log('soft_delete','child',$id,null,['reason'=>$reason]); cache()->delete('staff-dashboard-v4');
        return redirect()->to(site_url('children'))->with('message','Child profile archived.');
    }
    public function pending(): string
    {
        $rows=db_connect()->table('children c')->select('c.*,g.first_name AS guardian_first,g.last_name AS guardian_last,g.mobile_no')->join('child_guardian cg','cg.child_id=c.id')->join('guardians g','g.id=cg.guardian_id')->where('c.registration_status','pending')->where('c.deleted_at',null)->orderBy('c.created_at')->get()->getResultArray();
        return view('staff/children/pending',['title'=>'Pending Registrations','rows'=>$rows]);
    }
    public function verify(int $id)
    {
        try { (new ChildService())->verify($id,(int)auth()->id()); cache()->delete('staff-dashboard-v4'); return redirect()->to(site_url('registrations/pending'))->with('message','Registration verified and schedule created.'); }
        catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
    }
    public function reject(int $id)
    {
        try {(new ChildService())->reject($id,(string)$this->request->getPost('reason'),(int)auth()->id());return redirect()->to(site_url('registrations/pending'))->with('message','Registration rejected and parent notified.');}
        catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
    }
    private function form(?array $child=null): string
    {
        return view('staff/children/form',['title'=>$child?'Edit Child Profile':'Register a Child','child'=>$child,'puroks'=>db_connect()->table('puroks')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray(),'guardian'=>array_values(array_filter($child['guardians']??[],static fn($g)=>(int)$g['is_primary']===1))[0]??($child['guardians'][0]??[]),'doses'=>db_connect()->table('vaccine_doses')->select('vaccine_doses.*,vaccines.name AS vaccine_name')->join('vaccines','vaccines.id=vaccine_doses.vaccine_id')->where('vaccine_doses.is_active',1)->orderBy('vaccine_doses.id')->get()->getResultArray()]);
    }
    private function rules(bool $consent=true): array
    {
        $rules=['first_name'=>'required|max_length[100]','last_name'=>'required|max_length[100]','sex'=>'required|in_list[male,female]','birthdate'=>'required|valid_date[Y-m-d]|not_future_date','purok_id'=>'permit_empty|integer','guardian.first_name'=>'required|max_length[100]','guardian.last_name'=>'required|max_length[100]','guardian.mobile_no'=>'required|ph_mobile','guardian.relationship'=>'permit_empty|in_list[mother,father,guardian,grandparent,other]'];
        $rules['first_weight']='permit_empty|decimal|greater_than_equal_to[1]|less_than_equal_to[40]';
        $rules['first_height']='permit_empty|decimal|greater_than_equal_to[40]|less_than_equal_to[130]';
        $rules['first_position']='permit_empty|in_list[recumbent,standing]';
        if($consent) $rules['consent_given']='required|in_list[1]';
        return $rules;
    }
}

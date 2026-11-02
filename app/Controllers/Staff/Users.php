<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\OtpService;
use CodeIgniter\Shield\Entities\User;
final class Users extends BaseController
{
    public function index(): string
    {
        $q=mb_strtolower(trim((string)$this->request->getGet('q')));$role=(string)$this->request->getGet('role');$status=(string)$this->request->getGet('status');$isAdmin=auth()->user()->inGroup('admin');
        if(!in_array($role,['','admin','bhw','parent'],true))$role='';if(!$isAdmin)$role='parent';if(!in_array($status,['','active','inactive'],true))$status='';
        $provider=auth()->getProvider()->withGroups();$db=db_connect();
        if(!$isAdmin||$role!==''){$roles=$isAdmin?($role!==''?[$role]:['admin','bhw','parent']):['parent'];$subquery=$db->table('auth_groups_users')->select('user_id')->whereIn('group',$roles);$provider->whereIn('id',$subquery);}
        if($q!=='')$provider->groupStart()->like('first_name',$q)->orLike('middle_name',$q)->orLike('last_name',$q)->orLike('username',$q)->orLike('mobile_no',$q)->groupEnd();
        if($status==='active')$provider->where('active',1);elseif($status==='inactive')$provider->where('active',0);
        $users=$provider->orderBy('id','DESC')->paginate(25);$pager=$provider->pager;
        return view('staff/users/index',['title'=>'Users Management','users'=>$users,'pager'=>$pager,'isAdmin'=>$isAdmin,'filters'=>compact('q','role','status')]);
    }
    public function new(): string {return $this->form();}
    public function edit(int $id): string {$user=auth()->getProvider()->findById($id);if(!$user)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();if(!auth()->user()->inGroup('admin')&&!$user->inGroup('parent'))return redirect()->to(site_url('users'))->with('error','BHW accounts may manage parent accounts only.');return $this->form($user);}
    private function form(?User $user=null): string {return view('staff/users/form',['title'=>$user?'Edit User':'Create User','user'=>$user,'puroks'=>db_connect()->table('puroks')->where('is_active',1)->get()->getResultArray(),'isAdmin'=>auth()->user()->inGroup('admin')]);}
    public function create()
    {
        $role=(string)$this->request->getPost('role');$isAdmin=auth()->user()->inGroup('admin');
        if(!$isAdmin&&$role!=='parent')return redirect()->back()->with('error','BHW accounts may create parent accounts only.');
        if(!in_array($role,['admin','bhw','parent'],true))return redirect()->back()->with('error','Choose a valid role.');
        $rules=['first_name'=>'required|max_length[100]','last_name'=>'required|max_length[100]','mobile_no'=>'required|max_length[20]','username'=>'required|min_length[3]|max_length[30]'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $mobile=format_ph_mobile((string)$this->request->getPost('mobile_no'));if($mobile==='')return redirect()->back()->withInput()->with('error','Enter a valid Philippine mobile number.');
        $db=db_connect();$existing=$db->table('users')->where('mobile_no',$mobile)->where('deleted_at',null)->get()->getRowArray();if($existing)return redirect()->back()->withInput()->with('error','A user already uses this mobile number.');
        $temporary=$role==='parent'?null:bin2hex(random_bytes(12));
        $user=new User(['username'=>$this->request->getPost('username'),'first_name'=>$this->request->getPost('first_name'),'middle_name'=>$this->request->getPost('middle_name'),'last_name'=>$this->request->getPost('last_name'),'sex'=>$this->request->getPost('sex'),'birthdate'=>$this->request->getPost('birthdate')?:null,'mobile_no'=>$mobile,'address'=>$this->request->getPost('address'),'purok_id'=>$this->request->getPost('purok_id')?:null,'email'=>$this->request->getPost('email')?:null,'password'=>$temporary,'must_set_password'=>1,'active'=>1]);
        $provider=auth()->getProvider();try{$provider->save($user);$created=$provider->findById($provider->getInsertID());$created->addGroup($role);}catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
        if($role==='parent'){
            $g=$db->table('guardians')->where('mobile_no',$mobile)->get()->getRowArray();
            if($g)$db->table('guardians')->where('id',$g['id'])->update(['user_id'=>$created->id]);
            else $db->table('guardians')->insert(['user_id'=>$created->id,'first_name'=>$created->first_name,'middle_name'=>$created->middle_name,'last_name'=>$created->last_name,'mobile_no'=>$mobile,'email'=>$created->email,'address'=>$created->address,'purok_id'=>$created->purok_id,'sms_opt_in'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        }
        (new AuditLogger())->log('create','user',(int)$created->id,null,['username'=>$created->username,'role'=>$role]);
        return redirect()->to(site_url('users'))->with('message','User created.')->with('temporary_password',$temporary);
    }
    public function toggle(int $id)
    {
        $provider=auth()->getProvider();$user=$provider->findById($id);if(!$user)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $isAdmin=auth()->user()->inGroup('admin');if(!$isAdmin&&!$user->inGroup('parent'))return redirect()->back()->with('error','BHW accounts may manage parent accounts only.');
        if((int)$user->id===(int)auth()->id())return redirect()->back()->with('error','You cannot deactivate your current account.');
        $user->active=!$user->active;$provider->save($user);(new AuditLogger())->log($user->active?'activate':'deactivate','user',$id,null,['active'=>$user->active]);return redirect()->back()->with('message','User status updated.');
    }
    public function update(int $id)
    {
        $provider=auth()->getProvider();$user=$provider->findById($id);if(!$user)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();$isAdmin=auth()->user()->inGroup('admin');
        if(!$isAdmin&&!$user->inGroup('parent'))return redirect()->to(site_url('users'))->with('error','BHW accounts may manage parent accounts only.');
        if(!$this->validateData($this->request->getPost(),['first_name'=>'required|max_length[100]','last_name'=>'required|max_length[100]','mobile_no'=>'required|ph_mobile','email'=>'permit_empty|valid_email','birthdate'=>'permit_empty|valid_date[Y-m-d]']))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $role=(string)$this->request->getPost('role');if(!$isAdmin)$role='parent';if(!in_array($role,['admin','bhw','parent'],true))return redirect()->back()->withInput()->with('error','Choose a valid role.');
        $mobile=format_ph_mobile((string)$this->request->getPost('mobile_no'));if($mobile==='')return redirect()->back()->withInput()->with('error','Enter a valid Philippine mobile number.');
        if(db_connect()->table('users')->where('mobile_no',$mobile)->where('id !=',$id)->where('deleted_at',null)->countAllResults())return redirect()->back()->withInput()->with('error','That mobile number is already assigned.');
        $before=['mobile_no'=>$user->mobile_no,'first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'role'=>implode(',',$user->getGroups())];
        $user->fill(['first_name'=>$this->request->getPost('first_name'),'middle_name'=>$this->request->getPost('middle_name'),'last_name'=>$this->request->getPost('last_name'),'sex'=>$this->request->getPost('sex'),'birthdate'=>$this->request->getPost('birthdate')?:null,'mobile_no'=>$mobile,'email'=>$this->request->getPost('email')?:null,'address'=>$this->request->getPost('address'),'purok_id'=>$this->request->getPost('purok_id')?:null]);$provider->save($user);
        foreach(['admin','bhw','parent'] as $group)if($user->inGroup($group)&&$group!==$role)$user->removeGroup($group);$user->addGroup($role);
        if($role==='parent')db_connect()->table('guardians')->where('user_id',$id)->update(['mobile_no'=>$mobile,'email'=>$user->email,'first_name'=>$user->first_name,'last_name'=>$user->last_name,'updated_at'=>date('Y-m-d H:i:s')]);
        (new AuditLogger())->log('update','user',$id,$before,['mobile_no'=>$mobile,'first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'role'=>$role]);return redirect()->to(site_url('users'))->with('message','User updated.');
    }
    public function resetAccess(int $id)
    {
        $user=auth()->getProvider()->findById($id);if(!$user)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        if(!auth()->user()->inGroup('admin')&&!$user->inGroup('parent'))return redirect()->back()->with('error','Not authorized.');
        (new OtpService())->send((string)$user->mobile_no,'password_reset',(int)$user->id);return redirect()->back()->with('message','If the mobile number is reachable, an access reset code was sent.');
    }
}

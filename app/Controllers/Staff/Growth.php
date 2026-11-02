<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\GrowthService;
final class Growth extends BaseController
{
    public function index(int $childId): string
    {
        $child=(new \App\Services\ChildService())->find($childId);if(!$child)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $db=db_connect();$chartRows=$db->table('growth_records')->select('visit_date,weight_kg,height_cm')->where('child_id',$childId)->where('deleted_at',null)->orderBy('visit_date')->get()->getResultArray();
        $page=\App\Services\QueryPaginator::paginate($db->table('growth_records')->where('child_id',$childId)->where('deleted_at',null)->orderBy('visit_date','DESC')->orderBy('id','DESC'),15);
        return view('staff/growth/index',['title'=>'Growth Monitoring','child'=>$child,'records'=>$page['rows'],'pager'=>$page['pager'],'chart'=>['labels'=>array_column($chartRows,'visit_date'),'weights'=>array_map('floatval',array_column($chartRows,'weight_kg')),'heights'=>array_map('floatval',array_column($chartRows,'height_cm'))] ]);
    }
    public function create(int $childId)
    {
        $rules=['visit_date'=>'required|valid_date[Y-m-d]','weight_kg'=>'required|decimal|greater_than_equal_to[1]|less_than_equal_to[40]','height_cm'=>'required|decimal|greater_than_equal_to[40]|less_than_equal_to[130]','measured_position'=>'required|in_list[recumbent,standing]','muac_cm'=>'permit_empty|decimal','head_circ_cm'=>'permit_empty|decimal'];
        if(!$this->validateData($this->request->getPost(),$rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        try{$id=(new GrowthService())->save($childId,$this->request->getPost(),(int)auth()->id());(new \App\Services\NotificationService())->notifyChildGuardians($childId,'new_health_record','Growth record added','A new growth measurement was added to your child’s health record.');return redirect()->back()->with('message','Growth record saved and nutritional status updated.');}
        catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }
    public function preview(int $childId)
    {
        $child=db_connect()->table('children')->where('id',$childId)->get()->getRowArray();if(!$child)return $this->response->setStatusCode(404)->setJSON(['error'=>'Child not found.']);
        try{return $this->response->setJSON((new \App\Services\GrowthCalculator())->calculate($child,$this->request->getGet()));}
        catch(\Throwable $e){return $this->response->setStatusCode(422)->setJSON(['error'=>$e->getMessage()]);}
    }
    public function delete(int $childId,int $recordId)
    {
        try{(new GrowthService())->delete($childId,$recordId,(string)$this->request->getPost('reason'),(int)auth()->id());return redirect()->back()->with('message','Growth record deleted.');}
        catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
    }
    public function chart(int $childId)
    {
        $rows=db_connect()->table('growth_records')->select('visit_date,weight_kg,height_cm,wfa_z')->where('child_id',$childId)->where('deleted_at',null)->orderBy('visit_date')->get()->getResultArray();
        return $this->response->setJSON(['labels'=>array_column($rows,'visit_date'),'datasets'=>[['label'=>'Weight (kg)','data'=>array_map('floatval',array_column($rows,'weight_kg'))],['label'=>'Height (cm)','data'=>array_map('floatval',array_column($rows,'height_cm'))]],'total'=>count($rows),'meta'=>[]]);
    }
}

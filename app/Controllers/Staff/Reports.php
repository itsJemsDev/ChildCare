<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\ExportService;

final class Reports extends BaseController
{
    private const TYPES = ['health','purok','beneficiaries','immunization','age','growth','checkups','masterlist','overdue'];
    public function index(): string
    {
        $filters=$this->filters();
        $kpis=db_connect()->table('children')->where('deleted_at',null)->where('registration_status','verified')->countAllResults();
        $db=db_connect();$children=$db->table('children')->where('deleted_at',null)->where('registration_status','verified');if($filters['purok']!=='')$children->where('purok_id',(int)$filters['purok']);$total=(int)$children->countAllResults();
        $statusCounts=[];foreach(['complete','partial','not_immunized'] as $status){$q=$db->table('children')->where('deleted_at',null)->where('registration_status','verified')->where('immunization_status',$status);if($filters['purok']!=='')$q->where('purok_id',(int)$filters['purok']);$statusCounts[$status]=(int)$q->countAllResults();}
        $checkups=$db->table('checkups ch')->join('children c','c.id=ch.child_id')->where('ch.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->where('ch.checkup_date >=',$filters['from'])->where('ch.checkup_date <=',$filters['to']);if($filters['purok']!=='')$checkups->where('c.purok_id',(int)$filters['purok']);
        return view('staff/reports/index',['title'=>'Graphical Reports','filters'=>$filters,'total'=>$total,'kpis'=>['complete'=>$statusCounts['complete'],'partial'=>$statusCounts['partial'],'not_immunized'=>$statusCounts['not_immunized'],'checkups'=>(int)$checkups->countAllResults()],'puroks'=>$db->table('puroks')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray()]);
    }
    public function data(string $type)
    {
        if(!in_array($type,self::TYPES,true))return $this->response->setStatusCode(404);
        $f=$this->filters();$db=db_connect();$labels=[];$values=[];$name='Children';
        $base=$db->table('children c')->where('c.deleted_at',null)->where('c.registration_status','verified');
        if($f['purok']!=='')$base->where('c.purok_id',(int)$f['purok']);
        switch($type){
            case 'health': $name='Children';$rows=(clone $base)->select('c.nutrition_category AS label,COUNT(*) AS total')->groupBy('c.nutrition_category')->get()->getResultArray();break;
            case 'purok':$name='Children';$rows=(clone $base)->select('p.name AS label,COUNT(c.id) AS total')->join('puroks p','p.id=c.purok_id','left')->groupBy('p.id,p.name')->orderBy('p.sort_order')->get()->getResultArray();break;
            case 'immunization':$name='Children';$rows=(clone $base)->select('c.immunization_status AS label,COUNT(*) AS total')->groupBy('c.immunization_status')->get()->getResultArray();break;
            case 'age':$name='Children';$rows=(clone $base)->select("CASE WHEN TIMESTAMPDIFF(MONTH,c.birthdate,CURDATE())<12 THEN '0–11 months' WHEN TIMESTAMPDIFF(YEAR,c.birthdate,CURDATE())<3 THEN '1–2 years' WHEN TIMESTAMPDIFF(YEAR,c.birthdate,CURDATE())<5 THEN '3–4 years' ELSE '5 years and above' END AS label, COUNT(*) AS total",false)->groupBy('label')->get()->getResultArray();break;
            case 'growth':$name='Growth records';$q=$db->table('growth_records g')->select('g.nutrition_status AS label,COUNT(*) AS total')->join('children c','c.id=g.child_id')->where('g.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->where('g.visit_date >=',$f['from'])->where('g.visit_date <=',$f['to']);if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->groupBy('g.nutrition_status')->get()->getResultArray();break;
            case 'beneficiaries':$name='Children vaccinated';$q=$db->table('immunizations i')->select("DATE_FORMAT(i.date_administered,'%Y-%m') AS label,COUNT(DISTINCT i.child_id) AS total",false)->join('children c','c.id=i.child_id')->where('i.status','completed')->where('i.date_administered >=',$f['from'])->where('i.date_administered <=',$f['to'])->where('c.deleted_at',null)->where('c.registration_status','verified');if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->groupBy('label')->orderBy('label')->get()->getResultArray();break;
            case 'checkups':$name='Check-ups';$q=$db->table('checkups ch')->select("DATE_FORMAT(ch.checkup_date,'%Y-%m') AS label,COUNT(*) AS total",false)->join('children c','c.id=ch.child_id')->where('ch.deleted_at',null)->where('c.deleted_at',null)->where('c.registration_status','verified')->where('ch.checkup_date >=',$f['from'])->where('ch.checkup_date <=',$f['to']);if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->groupBy('label')->orderBy('label')->get()->getResultArray();break;
            case 'overdue':$name='Overdue doses';$q=$db->table('immunizations i')->select('vd.code AS label,COUNT(*) AS total')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->join('children c','c.id=i.child_id')->where('i.status','pending')->where('i.due_date <',date('Y-m-d'))->where('i.due_date >=',$f['from'])->where('i.due_date <=',$f['to'])->where('c.deleted_at',null)->where('c.registration_status','verified');if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->groupBy('vd.code')->orderBy('total','DESC')->get()->getResultArray();break;
            case 'masterlist':$name='Children';$rows=(clone $base)->select("CONCAT_WS(' ',c.first_name,c.middle_name,c.last_name,c.suffix) AS label,COUNT(*) AS total",false)->groupBy('c.id')->orderBy('c.last_name')->get()->getResultArray();break;
        }
        foreach($rows as $row){$labels[]=$row['label']??'Unclassified';$values[]=(int)$row['total'];}
        return $this->response->setJSON(['labels'=>$labels,'datasets'=>[['label'=>$name,'data'=>$values]],'total'=>array_sum($values),'meta'=>['from'=>$f['from'],'to'=>$f['to']]]);
    }
    public function export(string $type,string $format)
    {
        if(!in_array($format,['pdf','xlsx'],true)||(!in_array($type,self::TYPES,true)&&!($type==='all'&&$format==='xlsx')))return $this->response->setStatusCode(404);
        if($type==='all'){$sheets=[];foreach(self::TYPES as $reportType){$data=$this->dataRows($reportType);$sheets[]=['title'=>ucfirst($reportType),'headings'=>$data['headers'],'rows'=>$data['rows']];}(new AuditLogger())->log('export','report',null,null,['type'=>'all','format'=>'xlsx','filters'=>$this->filters()]);return (new ExportService())->xlsxSheets('reports-'.date('Ymd').'.xlsx',$sheets);}
        $data=$this->dataRows($type);
        (new AuditLogger())->log('export','report',null,null,['type'=>$type,'format'=>$format,'filters'=>$this->filters()]);
        $service=new ExportService();$filename=$type.'-'.date('Ymd');
        if($format==='xlsx')return $service->xlsx($filename.'.xlsx',ucfirst($type),$data['headers'],$data['rows']);
        $charts=[];$image=(string)$this->request->getPost('chart_image');
        if($image!==''&&strlen($image)<=2800000&&preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/D',$image,$match)){$decoded=base64_decode($match[1],true);if($decoded!==false&&strlen($decoded)<=2097152&&str_starts_with($decoded,"\x89PNG\r\n\x1a\n"))$charts[]=$image;}
        return $service->pdf($filename.'.pdf',ucfirst($type).' Report',[$data['headers'],...$data['rows']],$this->filters(),$charts);
    }
    private function dataRows(string $type): array
    {
        $db=db_connect();$f=$this->filters();
        if($type==='masterlist'){$q=$db->table('children c')->select('c.child_code,c.first_name,c.middle_name,c.last_name,c.birthdate,c.sex,c.nutrition_category,c.nutrition_status,c.immunization_status,p.name AS purok')->join('puroks p','p.id=c.purok_id','left')->where('c.deleted_at',null)->where('c.registration_status','verified');if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->orderBy('c.last_name')->orderBy('c.first_name')->get()->getResultArray();return ['headers'=>['Child ID','First name','Middle name','Last name','Birthdate','Sex','Category','Nutrition status','Immunization status','Purok'],'rows'=>array_map('array_values',$rows)];}
        if($type==='overdue'){$q=$db->table('immunizations i')->select('c.child_code,c.first_name,c.last_name,vd.code,i.due_date')->join('children c','c.id=i.child_id')->join('vaccine_doses vd','vd.id=i.vaccine_dose_id')->where('i.status','pending')->where('i.due_date <',date('Y-m-d'))->where('i.due_date >=',$f['from'])->where('i.due_date <=',$f['to'])->where('c.deleted_at',null)->where('c.registration_status','verified');if($f['purok']!=='')$q->where('c.purok_id',(int)$f['purok']);$rows=$q->orderBy('i.due_date')->get()->getResultArray();return ['headers'=>['Child ID','First name','Last name','Dose','Due date'],'rows'=>array_map('array_values',$rows)];}
        $payload=json_decode($this->data($type)->getBody(),true,512,JSON_THROW_ON_ERROR);return ['headers'=>['Label','Total'],'rows'=>array_map(null,$payload['labels'],$payload['datasets'][0]['data'])];
    }
    private function filters(): array { $valid=static function($value):bool{if(!is_string($value)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$value))return false;$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date!==false&&$date->format('Y-m-d')===$value;};$input=$this->request->getGet()?:$this->request->getPost();$from=(string)($input['from']??'');$to=(string)($input['to']??'');$purok=(string)($input['purok']??'');return ['from'=>$valid($from)?$from:date('Y-m-01'),'to'=>$valid($to)?$to:date('Y-m-d'),'purok'=>ctype_digit($purok)?$purok:'']; }
}

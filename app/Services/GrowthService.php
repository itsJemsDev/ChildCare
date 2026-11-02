<?php
declare(strict_types=1);
namespace App\Services;
final class GrowthService
{
    public function save(int $childId,array $input,int $userId): int
    {
        $db=db_connect();$child=$db->table('children')->where('id',$childId)->where('deleted_at',null)->get()->getRowArray();
        if(!$child)throw new \RuntimeException('Child profile not found.');
        if((float)($input['weight_kg']??0)<1||(float)$input['weight_kg']>40||(float)($input['height_cm']??0)<40||(float)$input['height_cm']>130)throw new \InvalidArgumentException('Weight must be 1–40 kg and height must be 40–130 cm.');
        if(!in_array($input['measured_position']??'', ['recumbent','standing'], true))throw new \InvalidArgumentException('Choose recumbent or standing measurement position.');
        $assessment=(new GrowthCalculator($db))->calculate($child,$input);$now=date('Y-m-d H:i:s');
        $record=['child_id'=>$childId,'visit_date'=>$input['visit_date'],'age_days'=>$assessment['age_days'],'weight_kg'=>$input['weight_kg'],'height_cm'=>$assessment['height_cm'],'measured_position'=>$input['measured_position'],'muac_cm'=>($input['muac_cm']??'')===''?null:$input['muac_cm'],'head_circ_cm'=>($input['head_circ_cm']??'')===''?null:$input['head_circ_cm'],'bmi'=>$assessment['bmi'],'wfa_z'=>$assessment['wfa_z'],'lhfa_z'=>$assessment['lhfa_z'],'wfh_z'=>$assessment['wfh_z'],'bfa_z'=>$assessment['bfa_z'],'wfa_pct'=>$assessment['wfa_pct'],'lhfa_pct'=>$assessment['lhfa_pct'],'wfh_pct'=>$assessment['wfh_pct'],'bfa_pct'=>$assessment['bfa_pct'],'wfa_status'=>$assessment['wfa_status'],'lhfa_status'=>$assessment['lhfa_status'],'wfh_status'=>$assessment['wfh_status'],'nutrition_status'=>$assessment['status'],'nutrition_category'=>$assessment['category'],'is_at_risk'=>$assessment['at_risk']?1:0,'recorded_by'=>$userId,'remarks'=>$input['remarks']??null,'created_at'=>$now,'updated_at'=>$now];
        $db->transBegin();$db->table('growth_records')->insert($record);$id=(int)$db->insertID();
        $latest=$db->table('growth_records')->where('child_id',$childId)->where('deleted_at',null)->orderBy('visit_date','DESC')->orderBy('id','DESC')->get()->getRowArray();
        $db->table('children')->where('id',$childId)->update(['nutrition_category'=>$latest['nutrition_category']??null,'nutrition_status'=>$latest['nutrition_status']??null,'updated_at'=>$now]);
        (new AuditLogger())->log('create','growth_record',$id,null,$record,$userId);$db->transComplete();
        if($db->transStatus()===false)throw new \RuntimeException('Unable to save growth record.');
        cache()->delete('staff-dashboard-v4');return $id;
    }
    public function delete(int $childId,int $recordId,string $reason,int $userId): void
    {
        if(trim($reason)==='')throw new \InvalidArgumentException('A deletion reason is required.');
        $db=db_connect();$db->table('growth_records')->where(['id'=>$recordId,'child_id'=>$childId])->update(['deleted_at'=>date('Y-m-d H:i:s'),'remarks'=>trim((string)$db->table('growth_records')->select('remarks')->where('id',$recordId)->get()->getRow('remarks').'\nDeleted: '.$reason)]);
        $latest=$db->table('growth_records')->where('child_id',$childId)->where('deleted_at',null)->orderBy('visit_date','DESC')->orderBy('id','DESC')->get()->getRowArray();
        $db->table('children')->where('id',$childId)->update(['nutrition_category'=>$latest['nutrition_category']??null,'nutrition_status'=>$latest['nutrition_status']??null]);
        (new AuditLogger())->log('soft_delete','growth_record',$recordId,null,['reason'=>$reason],$userId);cache()->delete('staff-dashboard-v4');
    }
}

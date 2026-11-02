<?php
declare(strict_types=1);
namespace App\Models;
use CodeIgniter\Model;
final class GrowthRecordModel extends Model
{
    protected $table = 'growth_records'; protected $primaryKey = 'id'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $useTimestamps = true;
    protected $allowedFields = ['child_id','visit_date','age_days','weight_kg','height_cm','measured_position','muac_cm','head_circ_cm','bmi','wfa_z','lhfa_z','wfh_z','bfa_z','wfa_pct','lhfa_pct','wfh_pct','bfa_pct','wfa_status','lhfa_status','wfh_status','nutrition_status','nutrition_category','is_at_risk','recorded_by','remarks'];
}

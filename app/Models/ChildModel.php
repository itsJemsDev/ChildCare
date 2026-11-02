<?php
declare(strict_types=1);
namespace App\Models;
use CodeIgniter\Model;
final class ChildModel extends Model
{
    protected $table = 'children'; protected $primaryKey = 'id'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $useTimestamps = true;
    protected $allowedFields = ['child_code','first_name','middle_name','last_name','suffix','nickname','sex','birthdate','birth_order','birth_weight_kg','blood_type','religion','purok_id','address','allergies','remarks','photo_path','registration_status','rejection_reason','verified_by','verified_at','consent_given_at','consent_recorded_by','nutrition_category','nutrition_status','immunization_status','is_fic','last_checkup_at','registered_by'];
}

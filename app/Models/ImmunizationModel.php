<?php
declare(strict_types=1);
namespace App\Models;
use CodeIgniter\Model;
final class ImmunizationModel extends Model
{
    protected $table = 'immunizations'; protected $primaryKey = 'id'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $useTimestamps = true;
    protected $allowedFields = ['child_id','vaccine_dose_id','due_date','status','date_administered','administered_by','administered_by_name','facility','lot_number','site','override_reason','remarks','created_by','updated_by'];
}

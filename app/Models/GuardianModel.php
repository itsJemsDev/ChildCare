<?php
declare(strict_types=1);
namespace App\Models;
use CodeIgniter\Model;
final class GuardianModel extends Model
{
    protected $table = 'guardians'; protected $primaryKey = 'id'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $useTimestamps = true;
    protected $allowedFields = ['user_id','first_name','middle_name','last_name','mobile_no','email','address','purok_id','sms_opt_in'];
}

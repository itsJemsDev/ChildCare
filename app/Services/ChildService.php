<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Sms\SmsService;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

final class ChildService
{
    public function __construct(private readonly ?BaseConnection $db = null) {}

    public function list(array $filters = [], int $perPage = 15): array
    {
        $db = $this->db ?? db_connect(); $builder = $db->table('children c')->select('c.*, p.name AS purok_name')
            ->join('puroks p', 'p.id = c.purok_id', 'left')->where('c.deleted_at', null);
        if (($filters['q'] ?? '') !== '') {
            $term = trim((string) $filters['q']);
            $builder->groupStart()->like('c.first_name', $term)->orLike('c.last_name', $term)->orLike('c.child_code', $term)->groupEnd();
        }
        foreach (['purok_id', 'sex', 'nutrition_category', 'immunization_status', 'registration_status'] as $key) if (! empty($filters[$key])) $builder->where('c.' . $key, $filters[$key]);
        if (($filters['age_group'] ?? '') === '0-11') $builder->where('c.birthdate >=', date('Y-m-d', strtotime('-12 months')));
        if (($filters['age_group'] ?? '') === '12-35') $builder->where('c.birthdate <', date('Y-m-d', strtotime('-12 months')))->where('c.birthdate >=', date('Y-m-d', strtotime('-36 months')));
        if (($filters['age_group'] ?? '') === '36-59') $builder->where('c.birthdate <', date('Y-m-d', strtotime('-36 months')))->where('c.birthdate >=', date('Y-m-d', strtotime('-60 months')));
        return QueryPaginator::paginate($builder->orderBy('c.last_name'), $perPage)['rows'];
    }

    public function exportList(array $filters = []): array
    {
        $db = $this->db ?? db_connect();
        $builder = $db->table('children c')->select('c.*, p.name AS purok_name')
            ->join('puroks p', 'p.id = c.purok_id', 'left')->where('c.deleted_at', null);
        if (($filters['q'] ?? '') !== '') {
            $term = trim((string) $filters['q']);
            $builder->groupStart()->like('c.first_name', $term)->orLike('c.last_name', $term)->orLike('c.child_code', $term)->groupEnd();
        }
        foreach (['purok_id', 'sex', 'nutrition_category', 'immunization_status', 'registration_status'] as $key) {
            if (! empty($filters[$key])) $builder->where('c.' . $key, $filters[$key]);
        }
        if (($filters['age_group'] ?? '') === '0-11') $builder->where('c.birthdate >=', date('Y-m-d', strtotime('-12 months')));
        if (($filters['age_group'] ?? '') === '12-35') $builder->where('c.birthdate <', date('Y-m-d', strtotime('-12 months')))->where('c.birthdate >=', date('Y-m-d', strtotime('-36 months')));
        if (($filters['age_group'] ?? '') === '36-59') $builder->where('c.birthdate <', date('Y-m-d', strtotime('-36 months')))->where('c.birthdate >=', date('Y-m-d', strtotime('-60 months')));
        return $builder->orderBy('c.last_name')->get()->getResultArray();
    }

    public function find(int $id): ?array
    {
        $db = $this->db ?? db_connect();
        $child = $db->table('children c')->select('c.*, p.name AS purok_name')->join('puroks p', 'p.id = c.purok_id', 'left')->where('c.id', $id)->where('c.deleted_at', null)->get()->getRowArray();
        if ($child) $child['guardians'] = $db->table('child_guardian cg')->select('g.*, cg.relationship, cg.is_primary')->join('guardians g', 'g.id = cg.guardian_id')->where('cg.child_id', $id)->get()->getResultArray();
        return $child ?: null;
    }

    public function duplicates(array $data, string $mobile = ''): array
    {
        $db = $this->db ?? db_connect();
        $first=strtolower(trim((string)$data['first_name']));$last=strtolower(trim((string)$data['last_name']));
        $matches=$db->table('children c')->select('c.id,c.child_code,c.first_name,c.last_name,c.birthdate')->where('c.deleted_at',null)->where('LOWER(c.first_name)',$first)->where('LOWER(c.last_name)',$last)->where('c.birthdate',$data['birthdate'])->get()->getResultArray();
        if($mobile!==''){
            $byGuardian=$db->table('children c')->select('c.id,c.child_code,c.first_name,c.last_name,c.birthdate')->join('child_guardian cg','cg.child_id=c.id')->join('guardians g','g.id=cg.guardian_id')->where('c.deleted_at',null)->where('LOWER(c.first_name)',$first)->where('LOWER(c.last_name)',$last)->where('g.mobile_no',format_ph_mobile($mobile))->get()->getResultArray();
            $matches=array_merge($matches,$byGuardian);
        }
        $unique=[];foreach($matches as $row)$unique[(int)$row['id']]=$row;return array_values($unique);
    }

    public function create(array $data, array $guardian, array $uploads, int $actorId, bool $pending = false, ?UploadedFile $photo = null): int
    {
        $db = $this->db ?? db_connect(); $db->transBegin(); $now = date('Y-m-d H:i:s');
        try {
            $guardianId = $this->saveGuardian($guardian, $actorId, $now, ! $pending && ! empty($data['create_parent_account']));
            $child = array_intersect_key($data, array_flip(['first_name','middle_name','last_name','suffix','nickname','sex','birthdate','birth_order','birth_weight_kg','blood_type','religion','purok_id','address','allergies','remarks']));
            if (($child['purok_id'] ?? '') === '') $child['purok_id'] = null;
            $child += ['registration_status' => $pending ? 'pending' : 'verified', 'registered_by' => $actorId, 'consent_given_at' => $now, 'consent_recorded_by' => $actorId, 'created_at' => $now, 'updated_at' => $now];
            if (! $pending) { $child['child_code'] = (new ChildCodeGenerator($db))->next(); $child['verified_by'] = $actorId; $child['verified_at'] = $now; }
            $db->table('children')->insert($child); $childId = (int) $db->insertID();
            $db->table('child_guardian')->insert(['child_id' => $childId, 'guardian_id' => $guardianId, 'relationship' => $guardian['relationship'] ?? 'guardian', 'is_primary' => 1]);
            $this->storeUploads($db, $childId, $uploads, $actorId, $now);
            if ($photo && $photo->getError() !== UPLOAD_ERR_NO_FILE) {
                $storedPhoto = (new DocumentStorage())->store($photo, $childId, true);
                $db->table('children')->where('id', $childId)->update(['photo_path' => $storedPhoto['stored_name'], 'updated_at' => $now]);
            }
            if (! $pending) {
                $scheduler = new ImmunizationScheduler($db);
                $scheduler->createForChild($childId, (string) $child['birthdate']);
                if (!empty($data['first_weight']) || !empty($data['first_height'])) {
                    if (empty($data['first_weight']) || empty($data['first_height'])) throw new \InvalidArgumentException('Enter both weight and height for the first measurement.');
                    $measurement = ['visit_date' => date('Y-m-d'), 'weight_kg' => $data['first_weight'], 'height_cm' => $data['first_height'], 'measured_position' => $data['first_position'] ?? 'recumbent', 'remarks' => 'Initial measurement at registration'];
                    $assessment = (new GrowthCalculator())->calculate($child, $measurement);
                    $growthRecord = [
                        'child_id' => $childId, 'visit_date' => $measurement['visit_date'], 'age_days' => $assessment['age_days'],
                        'weight_kg' => $measurement['weight_kg'], 'height_cm' => $assessment['height_cm'], 'measured_position' => $measurement['measured_position'],
                        'bmi' => $assessment['bmi'], 'wfa_z' => $assessment['wfa_z'], 'lhfa_z' => $assessment['lhfa_z'], 'wfh_z' => $assessment['wfh_z'], 'bfa_z' => $assessment['bfa_z'],
                        'wfa_pct' => $assessment['wfa_pct'], 'lhfa_pct' => $assessment['lhfa_pct'], 'wfh_pct' => $assessment['wfh_pct'], 'bfa_pct' => $assessment['bfa_pct'],
                        'wfa_status' => $assessment['wfa_status'], 'lhfa_status' => $assessment['lhfa_status'], 'wfh_status' => $assessment['wfh_status'],
                        'nutrition_status' => $assessment['status'], 'nutrition_category' => $assessment['category'], 'is_at_risk' => $assessment['at_risk'] ? 1 : 0,
                        'recorded_by' => $actorId, 'remarks' => $measurement['remarks'], 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $db->table('growth_records')->insert($growthRecord);
                    (new AuditLogger())->log('create', 'growth_record', (int)$db->insertID(), null, $growthRecord, $actorId);
                    $db->table('children')->where('id', $childId)->update(['nutrition_category' => $assessment['category'], 'nutrition_status' => $assessment['status'], 'updated_at' => $now]);
                }
                foreach (($data['previous_dose'] ?? []) as $doseId => $date) {
                    if ($date === '') continue;
                    $scheduler->record($childId, (int) $doseId, (string) $date, ['administered_by' => $actorId, 'remarks' => 'Back-encoded from immunization card']);
                }
            }
            (new AuditLogger())->log('create', 'child', $childId, null, $child, $actorId);
            $db->transComplete();
            if ($db->transStatus() === false) throw new \RuntimeException('Child registration failed.');
            return $childId;
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }

    /** Update a profile, its primary guardian link, and any replacement private files atomically. */
    public function updateProfile(int $childId, array $data, array $guardian, array $uploads, ?\CodeIgniter\HTTP\Files\UploadedFile $photo, int $actorId): void
    {
        $db=$this->db??db_connect();$before=$db->table('children')->where('id',$childId)->where('deleted_at',null)->get()->getRowArray();
        if(!$before)throw new \RuntimeException('Child profile not found.');
        $db->transBegin();$now=date('Y-m-d H:i:s');
        try {
            $allowed=array_intersect_key($data,array_flip(['first_name','middle_name','last_name','suffix','nickname','sex','birthdate','birth_order','birth_weight_kg','blood_type','religion','purok_id','address','allergies','remarks']));
            if(($allowed['purok_id']??null)==='')$allowed['purok_id']=null;
            $allowed['updated_at']=$now;$db->table('children')->where('id',$childId)->update($allowed);
            $guardianId=$this->saveGuardian($guardian,$actorId,$now,false);
            $linked=$db->table('child_guardian')->where(['child_id'=>$childId,'guardian_id'=>$guardianId])->get()->getRowArray();
            $db->table('child_guardian')->where('child_id',$childId)->update(['is_primary'=>0]);
            if($linked){$db->table('child_guardian')->where(['child_id'=>$childId,'guardian_id'=>$guardianId])->update(['relationship'=>$guardian['relationship']??'guardian','is_primary'=>1]);}
            else $db->table('child_guardian')->insert(['child_id'=>$childId,'guardian_id'=>$guardianId,'relationship'=>$guardian['relationship']??'guardian','is_primary'=>1]);
            $this->storeUploads($db,$childId,$uploads,$actorId,$now);
            if($photo&&$photo->getError()!==UPLOAD_ERR_NO_FILE){$stored=(new DocumentStorage())->store($photo,$childId,true);$db->table('children')->where('id',$childId)->update(['photo_path'=>$stored['stored_name'],'updated_at'=>$now]);}
            (new AuditLogger())->log('update','child',$childId,$before,$allowed,$actorId);
            $db->transComplete();if($db->transStatus()===false)throw new \RuntimeException('Unable to update child profile.');
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }

    public function verify(int $childId, int $actorId): void
    {
        $db = $this->db ?? db_connect(); $child = $db->table('children')->where('id', $childId)->get()->getRowArray();
        if (! $child || $child['registration_status'] !== 'pending') throw new \RuntimeException('Registration is no longer pending.');
        $db->transBegin();
        $code = (new ChildCodeGenerator($db))->next(); $now = date('Y-m-d H:i:s');
        $db->table('children')->where('id', $childId)->update(['child_code' => $code, 'registration_status' => 'verified', 'verified_by' => $actorId, 'verified_at' => $now, 'updated_at' => $now]);
        (new ImmunizationScheduler($db))->createForChild($childId, $child['birthdate']);
        (new AuditLogger())->log('verify', 'child', $childId, $child, ['child_code' => $code], $actorId);
        $db->transComplete();
        if ($db->transStatus() === false) throw new \RuntimeException('Unable to verify registration.');
        $this->notifyGuardian($childId, 'Registration verified', 'Your child registration is verified. Child ID: ' . $code, 'registration_verified');
    }

    public function reject(int $childId, string $reason, int $actorId): void
    {
        if (trim($reason) === '') throw new \InvalidArgumentException('A rejection reason is required.');
        $db = $this->db ?? db_connect(); $db->table('children')->where('id', $childId)->update(['registration_status' => 'rejected', 'rejection_reason' => $reason, 'verified_by' => $actorId, 'verified_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditLogger())->log('reject', 'child', $childId, null, ['reason' => $reason], $actorId);
        $this->notifyGuardian($childId, 'Registration update', 'Your child registration requires correction: ' . $reason, 'registration_rejected');
    }

    private function saveGuardian(array $data, int $actorId, string $now, bool $createPortal = false): int
    {
        $mobile = format_ph_mobile((string) ($data['mobile_no'] ?? ''));
        if ($mobile === '') throw new \InvalidArgumentException('Enter a valid Philippine mobile number.');
        $existing = ($this->db ?? db_connect())->table('guardians')->where('mobile_no', $mobile)->where('deleted_at', null)->get()->getRowArray();
        if ($existing) {
            $updates=array_intersect_key($data,array_flip(['first_name','middle_name','last_name','email','address','purok_id','sms_opt_in']));
            if(($updates['purok_id']??null)==='')$updates['purok_id']=null;
            $updates['updated_at']=$now;
            ($this->db??db_connect())->table('guardians')->where('id',$existing['id'])->update($updates);
            if ($createPortal && empty($existing['user_id'])) {
                $userId = $this->createParentUser($data, $mobile);
                ($this->db ?? db_connect())->table('guardians')->where('id', $existing['id'])->update(['user_id'=>$userId,'updated_at'=>$now]);
            }
            return (int) $existing['id'];
        }
        $values = array_intersect_key($data, array_flip(['user_id','first_name','middle_name','last_name','email','address','purok_id','sms_opt_in']));
        if (($values['purok_id'] ?? '') === '') $values['purok_id'] = null;
        if ($createPortal && empty($values['user_id'])) $values['user_id'] = $this->createParentUser($data, $mobile);
        $values += ['mobile_no' => $mobile, 'sms_opt_in' => 0, 'created_at' => $now, 'updated_at' => $now];
        $db = $this->db ?? db_connect(); $db->table('guardians')->insert($values);
        return (int) $db->insertID();
    }

    private function createParentUser(array $guardian, string $mobile): int
    {
        $users = auth()->getProvider();
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim(($guardian['first_name'] ?? 'parent') . '.' . ($guardian['last_name'] ?? 'account'))) ?: 'parent.account');
        $username = substr($base, 0, 22) . '.' . substr($mobile, -4);
        while ($users->where('username', $username)->countAllResults() > 0) $username = substr($base, 0, 16) . '.' . random_int(10000, 99999);
        $user = new \CodeIgniter\Shield\Entities\User([
            'username'=>$username,'first_name'=>$guardian['first_name']??null,'middle_name'=>$guardian['middle_name']??null,'last_name'=>$guardian['last_name']??null,
            'email'=>$guardian['email']??null,'mobile_no'=>$mobile,'address'=>$guardian['address']??null,'must_set_password'=>1,'active'=>1,
        ]);
        $users->save($user);$created=$users->findById($users->getInsertID());$created->addGroup('parent');
        return (int)$created->id;
    }

    private function storeUploads(BaseConnection $db, int $childId, array $uploads, int $actorId, string $now): void
    {
        $storage = new DocumentStorage();
        foreach ($uploads as $type => $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) continue;
            $stored = $storage->store($file, $childId);
            $db->table('child_documents')->insert(['child_id' => $childId, 'document_type' => $type, 'original_name' => $stored['original_name'], 'stored_name' => $stored['stored_name'], 'mime_type' => $stored['mime_type'], 'size_bytes' => $stored['size_bytes'], 'status' => 'pending', 'uploaded_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function notifyGuardian(int $childId, string $title, string $body, string $type): void
    {
        $db = $this->db ?? db_connect();
        $items = $db->table('child_guardian cg')->select('g.user_id, g.mobile_no, g.sms_opt_in, g.id AS guardian_id')->join('guardians g', 'g.id = cg.guardian_id')->where('cg.child_id', $childId)->where('cg.is_primary', 1)->get()->getResultArray();
        foreach ($items as $item) {
            if ($item['user_id']) $db->table('notifications')->insert(['user_id' => $item['user_id'], 'type' => $type, 'title' => $title, 'body' => $body, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            if ((int) $item['sms_opt_in'] === 1) (new SmsService())->queue($title, $type, [['guardian_id' => $item['guardian_id'], 'child_id' => $childId, 'number' => $item['mobile_no'], 'body' => $body]], null, null);
        }
    }
}

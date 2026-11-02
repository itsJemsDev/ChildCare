<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class ImmunizationScheduler
{
    public function __construct(private readonly ?BaseConnection $db = null) {}

    public function nextDueDate(string $birthdate,int $recommendedAgeDays,string $givenDate,int $minimumIntervalDays): string
    {
        $birth=\DateTimeImmutable::createFromFormat('!Y-m-d',$birthdate);$given=\DateTimeImmutable::createFromFormat('!Y-m-d',$givenDate);
        if(!$birth||$birth->format('Y-m-d')!==$birthdate||!$given||$given->format('Y-m-d')!==$givenDate||$recommendedAgeDays<0||$minimumIntervalDays<0)throw new \InvalidArgumentException('Valid birthdate, administration date, and non-negative schedule intervals are required.');
        $recommended=$birth->modify('+'.$recommendedAgeDays.' days');$interval=$given->modify('+'.$minimumIntervalDays.' days');
        return max($recommended->format('Y-m-d'),$interval->format('Y-m-d'));
    }

    public function displayStatus(string $status,string $dueDate,?string $today=null,int $dueSoonDays=3): string
    {
        if($status==='completed')return 'Completed';if($status==='deferred')return 'Deferred';
        $today??=date('Y-m-d');if($dueDate<$today)return 'Overdue';if($dueDate<=date('Y-m-d',strtotime($today.' +'.max(0,$dueSoonDays).' days')))return 'Due Soon';return 'Upcoming';
    }

    public function childStatus(int $completed,int $overdue,int $scheduled): string
    {
        if($scheduled<1)return 'not_immunized';if($overdue<1)return 'complete';return $completed>0?'partial':'not_immunized';
    }

    public function createForChild(int $childId, string $birthdate): int
    {
        $db = $this->db ?? db_connect();
        $doses = $db->table('vaccine_doses')->select('vaccine_doses.id, vaccine_doses.recommended_age_days')
            ->join('vaccines', 'vaccines.id = vaccine_doses.vaccine_id')->where('vaccines.is_active', 1)->where('vaccine_doses.is_active', 1)->get()->getResultArray();
        $now = date('Y-m-d H:i:s');
        foreach ($doses as $dose) {
            $exists = $db->table('immunizations')->where(['child_id' => $childId, 'vaccine_dose_id' => $dose['id']])->countAllResults();
            if ($exists) continue;
            $db->table('immunizations')->insert([
                'child_id' => $childId, 'vaccine_dose_id' => $dose['id'], 'due_date' => date('Y-m-d', strtotime($birthdate . ' +' . (int) $dose['recommended_age_days'] . ' days')),
                'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->recomputeChild($childId);
        return count($doses);
    }

    public function record(int $childId, int $doseId, string $givenDate, array $data = []): void
    {
        $db = $this->db ?? db_connect();
        $doseRow = $db->table('immunizations')->where(['child_id' => $childId, 'vaccine_dose_id' => $doseId])->whereIn('status', ['pending','deferred'])->where('deleted_at', null)->get()->getRowArray();
        if ($doseRow === null) throw new \RuntimeException('That vaccine dose is not outstanding for this child.');
        $dose = $db->table('vaccine_doses')->where('id', $doseId)->get()->getRowArray();
        if ($dose === null) throw new \RuntimeException('Vaccine dose was not found.');
        $this->assertDoseTiming($childId, $dose, $givenDate, (string) ($data['override_reason'] ?? ''));
        $db->transBegin();
        $db->table('immunizations')->where(['child_id' => $childId, 'vaccine_dose_id' => $doseId])->update([
            'status' => 'completed', 'date_administered' => $givenDate, 'deleted_at' => null, 'administered_by' => $data['administered_by'] ?? null,
            'administered_by_name' => $data['administered_by_name'] ?? null, 'facility' => $data['facility'] ?? null, 'lot_number' => $data['lot_number'] ?? null,
            'site' => $data['site'] ?? null, 'override_reason' => $data['override_reason'] ?? null, 'remarks' => $data['remarks'] ?? null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $child = $db->table('children')->select('birthdate')->where('id', $childId)->get()->getRowArray();
        $dose = $db->table('vaccine_doses')->where('id', $doseId)->get()->getRowArray();
        if ($child && $dose) {
            $next = $db->table('vaccine_doses')->where('vaccine_id', $dose['vaccine_id'])->where('dose_number >', $dose['dose_number'])->where('is_active', 1)->orderBy('dose_number')->get()->getRowArray();
            if ($next) {
                $due=$this->nextDueDate($child['birthdate'],(int)$next['recommended_age_days'],$givenDate,(int)$next['min_interval_days']);
                $db->table('immunizations')->where(['child_id' => $childId, 'vaccine_dose_id' => $next['id']])->update(['due_date' => $due, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->recomputeChild($childId);
        $db->transComplete();
        if ($db->transStatus() === false) throw new \RuntimeException('The immunization could not be saved.');
    }

    public function updateRecord(int $childId, int $recordId, string $givenDate, array $data = []): void
    {
        $db = $this->db ?? db_connect();
        $record = $db->table('immunizations')->where(['id' => $recordId, 'child_id' => $childId, 'status' => 'completed'])->where('deleted_at', null)->get()->getRowArray();
        if ($record === null) throw new \RuntimeException('Only an active completed dose can be edited.');
        $doseForValidation = $db->table('vaccine_doses')->where('id', $record['vaccine_dose_id'])->get()->getRowArray();
        if ($doseForValidation === null) throw new \RuntimeException('Vaccine dose was not found.');
        $this->assertDoseTiming($childId, $doseForValidation, $givenDate, (string) ($data['override_reason'] ?? ''), $recordId);
        $db->transBegin();
        $db->table('immunizations')->where('id', $recordId)->update([
            'date_administered' => $givenDate, 'administered_by' => $data['administered_by'] ?? null,
            'administered_by_name' => $data['administered_by_name'] ?? null, 'facility' => $data['facility'] ?? null,
            'lot_number' => $data['lot_number'] ?? null, 'site' => $data['site'] ?? null,
            'override_reason' => $data['override_reason'] ?? null, 'remarks' => $data['remarks'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $dose = $db->table('vaccine_doses')->where('id', $record['vaccine_dose_id'])->get()->getRowArray();
        $child = $db->table('children')->select('birthdate')->where('id', $childId)->get()->getRowArray();
        if ($dose && $child) {
            $next = $db->table('vaccine_doses')->where('vaccine_id', $dose['vaccine_id'])->where('dose_number >', $dose['dose_number'])->where('is_active', 1)->orderBy('dose_number')->get()->getRowArray();
            if ($next) {
                $due=$this->nextDueDate($child['birthdate'],(int)$next['recommended_age_days'],$givenDate,(int)$next['min_interval_days']);
                $db->table('immunizations')->where(['child_id' => $childId, 'vaccine_dose_id' => $next['id']])->update(['due_date' => $due, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->recomputeChild($childId);
        $db->transComplete();
        if ($db->transStatus() === false) throw new \RuntimeException('The immunization could not be updated.');
    }

    public function undoRecord(int $childId,int $recordId,string $reason): array
    {
        $db=$this->db??db_connect();$record=$db->table('immunizations')->where(['id'=>$recordId,'child_id'=>$childId,'status'=>'completed'])->where('deleted_at',null)->get()->getRowArray();
        if(!$record)throw new \RuntimeException('Only an active completed dose can be undone.');
        $dose=$db->table('vaccine_doses')->where('id',$record['vaccine_dose_id'])->get()->getRowArray();if(!$dose)throw new \RuntimeException('Vaccine dose was not found.');
        $laterIds=array_column($db->table('vaccine_doses')->select('id')->where('vaccine_id',$dose['vaccine_id'])->where('dose_number >',(int)$dose['dose_number'])->get()->getResultArray(),'id');
        if($laterIds&&$db->table('immunizations')->where('child_id',$childId)->whereIn('vaccine_dose_id',$laterIds)->where('status','completed')->where('deleted_at',null)->countAllResults())throw new \RuntimeException('Undo later completed doses first so the vaccine history stays in order.');
        $db->transBegin();$oldRemarks=trim((string)($record['remarks']??''));$remarks=trim($oldRemarks.($oldRemarks!==''?"\n":'').'Record undone: '.trim($reason));
        $db->table('immunizations')->where('id',$recordId)->update(['status'=>'pending','date_administered'=>null,'administered_by'=>null,'administered_by_name'=>null,'facility'=>null,'deleted_at'=>null,'remarks'=>$remarks,'updated_at'=>date('Y-m-d H:i:s')]);
        $child=$db->table('children')->select('birthdate')->where('id',$childId)->get()->getRowArray();$doses=$db->table('vaccine_doses')->where('vaccine_id',$dose['vaccine_id'])->where('is_active',1)->orderBy('dose_number')->get()->getResultArray();$previousDate=null;
        foreach($doses as $item){$entry=$db->table('immunizations')->where(['child_id'=>$childId,'vaccine_dose_id'=>$item['id']])->where('deleted_at',null)->get()->getRowArray();if(!$entry)continue;if($entry['status']==='completed'){$previousDate=$entry['date_administered'];continue;}if(in_array($entry['status'],['pending','deferred'],true)&&$child){$due=strtotime($child['birthdate'].' +'.(int)$item['recommended_age_days'].' days');if($previousDate)$due=max($due,strtotime($previousDate.' +'.(int)$item['min_interval_days'].' days'));$db->table('immunizations')->where('id',$entry['id'])->update(['due_date'=>date('Y-m-d',$due),'updated_at'=>date('Y-m-d H:i:s')]);}}
        $this->recomputeChild($childId);$db->transComplete();if($db->transStatus()===false)throw new \RuntimeException('The dose could not be undone.');return $record;
    }

    public function recomputeChild(int $childId): string
    {
        $db = $this->db ?? db_connect();
        $row = $db->table('immunizations')->select("SUM(status = 'completed') AS given, SUM(status = 'pending' AND due_date < CURDATE()) AS overdue, SUM(status = 'pending') AS pending")
            ->where('child_id', $childId)->where('deleted_at', null)->get()->getRowArray() ?? ['given' => 0, 'overdue' => 0, 'pending' => 0];
        $total = (int) $row['pending'] + (int) $row['given'];
        $state = $this->childStatus((int)$row['given'],(int)$row['overdue'],$total);
        $db->table('children')->where('id', $childId)->update(['immunization_status' => $state, 'is_fic' => $this->fic($childId)]);
        return $state;
    }

    private function fic(int $childId): int
    {
        $db = $this->db ?? db_connect();
        $child = $db->table('children')->select('birthdate')->where('id', $childId)->get()->getRowArray();
        $doses = $db->table('immunizations')->select('immunizations.status, immunizations.date_administered')->join('vaccine_doses', 'vaccine_doses.id = immunizations.vaccine_dose_id')
            ->where('immunizations.child_id', $childId)->where('vaccine_doses.is_booster', 0)->where('vaccine_doses.recommended_age_days <', 365)->where('immunizations.deleted_at', null)->get()->getResultArray();
        if (! $child || $doses === []) return 0;
        $deadline = date('Y-m-d', strtotime($child['birthdate'] . ' +1 year'));
        foreach ($doses as $dose) if ($dose['status'] !== 'completed' || $dose['date_administered'] === null || $dose['date_administered'] >= $deadline) return 0;
        return 1;
    }

    /** Keep clinical timing rules enforced for imports and every non-controller caller too. */
    private function assertDoseTiming(int $childId, array $dose, string $givenDate, string $overrideReason, ?int $excludeRecordId = null): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $givenDate);
        if ($date === false || $date->format('Y-m-d') !== $givenDate || $givenDate > date('Y-m-d')) {
            throw new \InvalidArgumentException('Administration date must be a valid date no later than today.');
        }
        $db = $this->db ?? db_connect();
        $child = $db->table('children')->select('birthdate')->where('id', $childId)->where('deleted_at', null)->get()->getRowArray();
        if ($child === null || $givenDate < $child['birthdate']) throw new \InvalidArgumentException('Administration date cannot be before the child’s birthdate.');

        $warning = null;
        $ageDays = (new \DateTimeImmutable($child['birthdate']))->diff($date)->days;
        if ($ageDays < (int) $dose['min_age_days']) $warning = 'Dose administered before the minimum age.';
        $previousQuery = $db->table('immunizations i')->select('i.date_administered,vd.dose_number')
            ->join('vaccine_doses vd', 'vd.id=i.vaccine_dose_id')->where('i.child_id', $childId)
            ->where('vd.vaccine_id', $dose['vaccine_id'])->where('vd.dose_number <', (int) $dose['dose_number'])
            ->where('i.status', 'completed')->where('i.deleted_at', null);
        if ($excludeRecordId !== null) $previousQuery->where('i.id !=', $excludeRecordId);
        $previous = $previousQuery->orderBy('vd.dose_number', 'DESC')->get()->getRowArray();
        if ($previous && $givenDate < date('Y-m-d', strtotime($previous['date_administered'] . ' +' . (int) $dose['min_interval_days'] . ' days'))) {
            $warning = 'Dose administered before the minimum interval.';
        }
        if ($warning !== null && trim($overrideReason) === '') throw new \InvalidArgumentException($warning . ' Enter an override reason to continue.');

        if ($excludeRecordId !== null) {
            $later = $db->table('immunizations i')->select('i.date_administered,vd.min_interval_days,vd.dose_number')
                ->join('vaccine_doses vd', 'vd.id=i.vaccine_dose_id')->where('i.child_id', $childId)
                ->where('vd.vaccine_id', $dose['vaccine_id'])->where('vd.dose_number >', (int) $dose['dose_number'])
                ->where('i.status', 'completed')->where('i.deleted_at', null)->orderBy('vd.dose_number')->get()->getResultArray();
            $previousDate = $givenDate;
            foreach ($later as $entry) {
                $earliest = date('Y-m-d', strtotime($previousDate . ' +' . (int) $entry['min_interval_days'] . ' days'));
                if ($entry['date_administered'] < $earliest) throw new \InvalidArgumentException('This change would make a later completed dose violate its minimum interval.');
                $previousDate = $entry['date_administered'];
            }
        }
    }
}

<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
final class VaccineSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['BCG','Bacille Calmette–Guérin','BCG',[['BCG',1,0,0]]],
            ['HEPB','Hepatitis B birth dose','HepB',[['HEPB0',1,0,0]]],
            ['PENTA','Pentavalent DPT-HepB-Hib','IM',[['PENTA1',1,42,0],['PENTA2',2,70,28],['PENTA3',3,98,28]]],
            ['OPV','Oral Polio Vaccine','Oral',[['OPV1',1,42,0],['OPV2',2,70,28],['OPV3',3,98,28]]],
            ['IPV','Inactivated Polio Vaccine','IM',[['IPV1',1,98,0],['IPV2',2,270,0]]],
            ['PCV','Pneumococcal Conjugate Vaccine','IM',[['PCV1',1,42,0],['PCV2',2,70,28],['PCV3',3,98,28]]],
            ['MMR','Measles-Mumps-Rubella','SC',[['MMR1',1,270,0],['MMR2',2,365,0]]],
        ];
        foreach ($catalog as [$code, $name, $route, $doses]) {
            $vaccine = $this->db->table('vaccines')->where('code', $code)->get()->getRowArray();
            if (! $vaccine) { $this->db->table('vaccines')->insert(['code'=>$code,'name'=>$name,'route'=>$route,'is_active'=>1,'sort_order'=>count($catalog),'created_at'=>date('Y-m-d H:i:s')]); $vaccineId = (int) $this->db->insertID(); }
            else $vaccineId = (int) $vaccine['id'];
            foreach ($doses as [$doseCode,$number,$age,$interval]) {
                if ($this->db->table('vaccine_doses')->where('code', $doseCode)->countAllResults()) continue;
                $this->db->table('vaccine_doses')->insert(['vaccine_id'=>$vaccineId,'dose_number'=>$number,'code'=>$doseCode,'label'=>'Dose ' . $number,'recommended_age_days'=>$age,'min_age_days'=>0,'min_interval_days'=>$interval,'is_booster'=>0,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s')]);
            }
        }
        log_message('warning', 'Seeded routine schedule must be checked against the current DOH NIP schedule before pilot use.');
    }
}

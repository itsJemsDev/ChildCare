<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
final class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['Immunization Reminder','immunization','Hello {guardian_name}, {child_name} is due for {vaccine} on {date}. Please visit {center_name}.'],
            ['Health Check-up Reminder','checkup','Hello {guardian_name}, {child_name} has a health check-up on {date} at {time}. {center_name}'],
            ['Vitamin Distribution Reminder','vitamin','Hello {guardian_name}, vitamin distribution for {child_name} is on {date} at {center_name}.'],
            ['Growth Monitoring Reminder','growth','Hello {guardian_name}, please bring {child_name} for growth monitoring on {date} at {center_name}.'],
            ['Feeding Program','feeding','Hello {guardian_name}, {child_name} may be eligible for our feeding program. Please contact {center_name}.'],
            ['General Announcement','general','Hello {guardian_name}, an update from {center_name}: {message}'],
        ];
        foreach ($templates as [$name,$type,$body]) if (! $this->db->table('sms_templates')->where('name', $name)->countAllResults()) $this->db->table('sms_templates')->insert(['name'=>$name,'type'=>$type,'body'=>$body,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s')]);
    }
}

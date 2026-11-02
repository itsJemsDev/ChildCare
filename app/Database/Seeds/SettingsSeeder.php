<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'center_name' => 'Barangay Kalawag 1 Health Center-B', 'barangay' => 'Kalawag 1', 'municipality' => 'Isulan', 'province' => 'Sultan Kudarat',
            'contact_number' => '', 'office_hours' => 'Monday–Friday, 8:00 AM–5:00 PM', 'sms_driver' => env('sms.driver','log'), 'reminder_lead_days' => '3,1', 'overdue_interval_days' => 7,
            'overdue_max_reminders' => 3, 'quiet_start' => '21:00', 'quiet_end' => '07:00', 'low_sms_credit_threshold' => 100,
            'growth_watch_min' => -2, 'growth_watch_max' => -1, 'age_coverage_months' => 59,
            'admin_sms_2fa' => true, 'bhw_sms_2fa' => false, 'staff_idle_timeout' => 1800, 'parent_self_registration' => false,
        ];
        foreach ($defaults as $key => $value) if (setting('ChildCare.' . $key) === null) setting('ChildCare.' . $key, $value);
    }
}

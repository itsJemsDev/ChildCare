<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([RoleSeeder::class,PurokSeeder::class,VaccineSeeder::class,SmsTemplateSeeder::class,SettingsSeeder::class,AdminSeeder::class] as $seeder) $this->call($seeder);
    }
}

<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class AddImmunizationReminderPreference extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('immunizations',['reminder_opt_in'=>['type'=>'TINYINT','constraint'=>1,'default'=>0,'after'=>'status']]);
    }
    public function down(): void
    {
        $this->forge->dropColumn('immunizations','reminder_opt_in');
    }
}

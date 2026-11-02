<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ExtendShieldUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'sex' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'birthdate' => ['type' => 'DATE', 'null' => true],
            'mobile_no' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'purok_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'avatar_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'must_set_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_active_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('mobile_no', false, true);
        $this->forge->addKey('purok_id');
        $this->forge->addForeignKey('purok_id', 'puroks', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->processIndexes('users');
        $this->forge->reset();
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('users', 'users_purok_id_foreign');
        $this->forge->dropColumn('users', ['first_name', 'middle_name', 'last_name', 'sex', 'birthdate', 'mobile_no', 'address', 'purok_id', 'avatar_path', 'must_set_password', 'last_active_at']);
    }
}

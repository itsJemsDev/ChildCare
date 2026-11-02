<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateChildcareSchema extends Migration
{
    public function up(): void
    {
        $this->create('puroks', [
            'name' => ['type' => 'VARCHAR', 'constraint' => 80], 'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['name', true]]);
        $this->create('guardians', [
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'mobile_no' => ['type' => 'VARCHAR', 'constraint' => 12], 'email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'purok_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sms_opt_in' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['mobile_no'], ['user_id'], ['purok_id']]);
        $this->create('children', [
            'child_code' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true], 'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'suffix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'nickname' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'sex' => ['type' => 'VARCHAR', 'constraint' => 16], 'birthdate' => ['type' => 'DATE'], 'birth_order' => ['type' => 'TINYINT', 'null' => true],
            'birth_weight_kg' => ['type' => 'DECIMAL', 'constraint' => '4,2', 'null' => true], 'blood_type' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'religion' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true], 'purok_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'allergies' => ['type' => 'TEXT', 'null' => true],
            'remarks' => ['type' => 'TEXT', 'null' => true], 'photo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'registration_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'verified'], 'rejection_reason' => ['type' => 'TEXT', 'null' => true],
            'verified_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'verified_at' => $this->dt(true), 'consent_given_at' => $this->dt(true),
            'consent_recorded_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'nutrition_category' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => true],
            'nutrition_status' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true], 'immunization_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'not_immunized'],
            'is_fic' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], 'last_checkup_at' => $this->dt(true),
            'registered_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_code', true], ['last_name', 'first_name'], ['birthdate'], ['purok_id'], ['nutrition_category'], ['registration_status']]);
        $this->create('child_guardian', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'guardian_id' => ['type' => 'INT', 'unsigned' => true],
            'relationship' => ['type' => 'VARCHAR', 'constraint' => 20], 'is_primary' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ], [['child_id', 'guardian_id', true], ['guardian_id']]);
        $this->create('child_documents', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'document_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'stored_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100], 'size_bytes' => ['type' => 'INT', 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], 'verified_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'verified_at' => $this->dt(true), 'uploaded_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_id'], ['status']]);
        $this->create('vaccines', [
            'code' => ['type' => 'VARCHAR', 'constraint' => 16], 'name' => ['type' => 'VARCHAR', 'constraint' => 120], 'description' => ['type' => 'TEXT', 'null' => true],
            'route' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'default' => 0], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['code', true]]);
        $this->create('vaccine_doses', [
            'vaccine_id' => ['type' => 'INT', 'unsigned' => true], 'dose_number' => ['type' => 'TINYINT'], 'code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'label' => ['type' => 'VARCHAR', 'constraint' => 80], 'recommended_age_days' => ['type' => 'INT', 'unsigned' => true],
            'min_age_days' => ['type' => 'INT', 'unsigned' => true, 'default' => 0], 'min_interval_days' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_booster' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], 'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['code', true], ['vaccine_id', 'dose_number']]);
        $this->create('immunizations', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'vaccine_dose_id' => ['type' => 'INT', 'unsigned' => true], 'due_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], 'date_administered' => ['type' => 'DATE', 'null' => true],
            'administered_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'administered_by_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'facility' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], 'lot_number' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'override_reason' => ['type' => 'TEXT', 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_id', 'vaccine_dose_id', true], ['status', 'due_date'], ['vaccine_dose_id']]);
        $this->create('growth_records', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'visit_date' => ['type' => 'DATE'], 'age_days' => ['type' => 'INT', 'unsigned' => true],
            'weight_kg' => ['type' => 'DECIMAL', 'constraint' => '5,2'], 'height_cm' => ['type' => 'DECIMAL', 'constraint' => '5,1'],
            'measured_position' => ['type' => 'VARCHAR', 'constraint' => 12], 'muac_cm' => ['type' => 'DECIMAL', 'constraint' => '5,1', 'null' => true],
            'head_circ_cm' => ['type' => 'DECIMAL', 'constraint' => '5,1', 'null' => true], 'bmi' => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'wfa_z' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true], 'lhfa_z' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'wfh_z' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true], 'bfa_z' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'wfa_pct' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true], 'lhfa_pct' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'wfh_pct' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true], 'bfa_pct' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true],
            'wfa_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'lhfa_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'wfh_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'nutrition_status' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nutrition_category' => ['type' => 'VARCHAR', 'constraint' => 1], 'is_at_risk' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'recorded_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_id', 'visit_date'], ['recorded_by']]);
        $this->create('who_growth_references', [
            'indicator' => ['type' => 'VARCHAR', 'constraint' => 8], 'sex' => ['type' => 'VARCHAR', 'constraint' => 10], 'x_value' => ['type' => 'DECIMAL', 'constraint' => '7,2'],
            'l' => ['type' => 'DECIMAL', 'constraint' => '12,8'], 'm' => ['type' => 'DECIMAL', 'constraint' => '12,6'], 's' => ['type' => 'DECIMAL', 'constraint' => '12,8'],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['indicator', 'sex', 'x_value', true]]);
        $this->create('checkups', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'checkup_date' => ['type' => 'DATE'], 'chief_complaint' => ['type' => 'TEXT', 'null' => true],
            'diagnosis' => ['type' => 'TEXT', 'null' => true], 'findings' => ['type' => 'TEXT', 'null' => true], 'temperature_c' => ['type' => 'DECIMAL', 'constraint' => '4,1', 'null' => true],
            'weight_kg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true], 'treatment' => ['type' => 'TEXT', 'null' => true],
            'medicines' => ['type' => 'JSON', 'null' => true], 'outcome' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'referred_to' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], 'follow_up_date' => ['type' => 'DATE', 'null' => true],
            'attended_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_id', 'checkup_date'], ['follow_up_date']]);
        $this->create('appointments', [
            'child_id' => ['type' => 'INT', 'unsigned' => true], 'requested_by' => ['type' => 'INT', 'unsigned' => true], 'type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'preferred_date' => ['type' => 'DATE'], 'time_slot' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'requested'], 'decline_reason' => ['type' => 'TEXT', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true], 'handled_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'handled_at' => $this->dt(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['child_id', 'preferred_date'], ['requested_by'], ['status']]);
        $this->create('sms_templates', [
            'name' => ['type' => 'VARCHAR', 'constraint' => 100], 'type' => ['type' => 'VARCHAR', 'constraint' => 30], 'body' => ['type' => 'TEXT'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['type'], ['is_active']]);
        $this->create('sms_batches', [
            'title' => ['type' => 'VARCHAR', 'constraint' => 160], 'message_type' => ['type' => 'VARCHAR', 'constraint' => 30], 'template_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'recipient_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0], 'scheduled_at' => $this->dt(true),
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true),
        ], [['scheduled_at']]);
        $this->create('sms_messages', [
            'batch_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'guardian_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'child_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'recipient_number' => ['type' => 'VARCHAR', 'constraint' => 12],
            'body' => ['type' => 'TEXT'], 'segments' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'queued'], 'reminder_key' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'scheduled_at' => $this->dt(true), 'sent_at' => $this->dt(true), 'provider_message_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'provider_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'error_message' => ['type' => 'TEXT', 'null' => true],
            'attempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0], 'created_at' => $this->dt(), 'updated_at' => $this->dt(true),
        ], [['status', 'scheduled_at'], ['reminder_key', true], ['batch_id']]);
        $this->create('otp_codes', [
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'mobile_no' => ['type' => 'VARCHAR', 'constraint' => 12],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 24], 'code_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at' => ['type' => 'DATETIME'], 'attempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'consumed_at' => $this->dt(true), 'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true], 'created_at' => $this->dt(),
        ], [['mobile_no', 'purpose'], ['expires_at']]);
        $this->create('notifications', [
            'user_id' => ['type' => 'INT', 'unsigned' => true], 'type' => ['type' => 'VARCHAR', 'constraint' => 30], 'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'body' => ['type' => 'TEXT'], 'url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'read_at' => $this->dt(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(true), 'deleted_at' => $this->dt(true),
        ], [['user_id', 'read_at']]);
        $this->create('id_sequences', [
            'name' => ['type' => 'VARCHAR', 'constraint' => 30], 'year' => ['type' => 'SMALLINT', 'unsigned' => true], 'last_value' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ], [['name', 'year', true]]);
        $this->create('audit_logs', [
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], 'action' => ['type' => 'VARCHAR', 'constraint' => 40],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 80], 'entity_id' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'old_values' => ['type' => 'JSON', 'null' => true], 'new_values' => ['type' => 'JSON', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true], 'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => $this->dt(),
        ], [['entity_type', 'entity_id'], ['user_id', 'created_at']]);

        $this->foreign('guardians', 'user_id', 'users', 'id');
        $this->foreign('guardians', 'purok_id', 'puroks', 'id');
        $this->foreign('children', 'purok_id', 'puroks', 'id');
        $this->foreign('children', 'registered_by', 'users', 'id');
        $this->foreign('children', 'verified_by', 'users', 'id');
        $this->foreign('children', 'consent_recorded_by', 'users', 'id');
        $this->foreign('child_guardian', 'child_id', 'children', 'id');
        $this->foreign('child_guardian', 'guardian_id', 'guardians', 'id');
        $this->foreign('child_documents', 'child_id', 'children', 'id');
        $this->foreign('child_documents', 'verified_by', 'users', 'id');
        $this->foreign('child_documents', 'uploaded_by', 'users', 'id');
        $this->foreign('vaccine_doses', 'vaccine_id', 'vaccines', 'id');
        $this->foreign('immunizations', 'child_id', 'children', 'id');
        $this->foreign('immunizations', 'vaccine_dose_id', 'vaccine_doses', 'id');
        $this->foreign('immunizations', 'administered_by', 'users', 'id');
        $this->foreign('immunizations', 'created_by', 'users', 'id');
        $this->foreign('immunizations', 'updated_by', 'users', 'id');
        $this->foreign('growth_records', 'child_id', 'children', 'id');
        $this->foreign('growth_records', 'recorded_by', 'users', 'id');
        $this->foreign('checkups', 'child_id', 'children', 'id');
        $this->foreign('checkups', 'attended_by', 'users', 'id');
        $this->foreign('appointments', 'child_id', 'children', 'id');
        $this->foreign('appointments', 'requested_by', 'users', 'id');
        $this->foreign('appointments', 'handled_by', 'users', 'id');
        $this->foreign('sms_batches', 'template_id', 'sms_templates', 'id');
        $this->foreign('sms_batches', 'created_by', 'users', 'id');
        $this->foreign('sms_messages', 'batch_id', 'sms_batches', 'id');
        $this->foreign('sms_messages', 'guardian_id', 'guardians', 'id');
        $this->foreign('sms_messages', 'child_id', 'children', 'id');
        $this->foreign('otp_codes', 'user_id', 'users', 'id');
        $this->foreign('notifications', 'user_id', 'users', 'id');
        $this->foreign('audit_logs', 'user_id', 'users', 'id');
        foreach ($this->pendingForeignKeys as $table => $keys) {
            foreach ($keys as $key) $this->forge->addForeignKey($key[0], $key[1], $key[2], 'RESTRICT', 'RESTRICT');
            $this->forge->processIndexes($table);
            $this->forge->reset();
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'id_sequences', 'notifications', 'otp_codes', 'sms_messages', 'sms_batches', 'sms_templates', 'appointments', 'checkups', 'who_growth_references', 'growth_records', 'immunizations', 'vaccine_doses', 'vaccines', 'child_documents', 'child_guardian', 'children', 'guardians', 'puroks'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private array $pendingForeignKeys = [];

    private function create(string $table, array $fields, array $keys = []): void
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], ...$fields]);
        $this->forge->addKey('id', true);
        foreach ($keys as $key) {
            $unique = end($key) === true;
            if ($unique) {
                array_pop($key);
            }
            $this->forge->addKey($key, false, $unique);
        }
        $this->forge->createTable($table, true, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    private function foreign(string $table, string $column, string $refTable, string $refColumn): void
    {
        $this->pendingForeignKeys[$table][] = [$column, $refTable, $refColumn];
    }

    private function dt(bool $nullable = false): array
    {
        return ['type' => 'DATETIME', 'null' => $nullable];
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL production hardening.
 *
 * This migration is intentionally additive: it adds constraints, indexes and
 * database-level immutability protections without dropping application data.
 * SQLite remains available for isolated automated tests; production schema
 * guarantees must be verified against PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // The production database is PostgreSQL. SQLite is used only when
            // explicitly selected by the test environment (phpunit.xml).
            return;
        }

        $this->assertNoCaseInsensitiveEmailDuplicates();
        $this->assertNoDuplicateSessionTokenHashes();
        $this->assertValidWorkScheduleScopes();

        // Complete the relational links that were previously represented only
        // as unsigned integer columns. Existing invalid references stop the
        // migration with an actionable message instead of being discarded.
        $foreignKeys = [
            ['departments', 'head_id', 'employees', 'department_head_employee_fk'],
            ['divisions', 'department_id', 'departments', 'division_department_fk'],
            ['divisions', 'head_id', 'employees', 'division_head_employee_fk'],
            ['employees', 'department_id', 'departments', 'employee_department_fk'],
            ['employees', 'division_id', 'divisions', 'employee_division_fk'],
            ['employees', 'position_id', 'positions', 'employee_position_fk'],
            ['employees', 'manager_id', 'employees', 'employee_manager_fk'],
            ['users', 'employee_id', 'employees', 'user_employee_fk'],
            ['schedules', 'employee_id', 'employees', 'schedule_employee_fk'],
            ['schedules', 'shift_id', 'shifts', 'schedule_shift_fk'],
            ['schedules', 'created_by', 'users', 'schedule_created_by_fk'],
            ['work_schedule_configs', 'shift_id', 'shifts', 'wsc_shift_fk'],
            ['work_schedule_configs', 'created_by', 'users', 'wsc_created_by_fk'],
            ['schedule_change_histories', 'work_schedule_config_id', 'work_schedule_configs', 'schedule_history_config_fk'],
            ['schedule_change_histories', 'changed_by', 'users', 'schedule_history_actor_fk'],
            ['integration_logs', 'device_id', 'devices', 'integration_log_device_fk'],
            ['attendance_logs', 'device_id', 'devices', 'attendance_log_device_fk'],
            ['attendance_logs', 'employee_id', 'employees', 'attendance_log_employee_fk'],
            ['attendance_logs', 'primary_log_id', 'attendance_logs', 'attendance_log_primary_fk'],
            ['attendance_records', 'employee_id', 'employees', 'attendance_record_employee_fk'],
            ['attendance_records', 'shift_id', 'shifts', 'attendance_record_shift_fk'],
            ['attendance_records', 'verified_by', 'users', 'attendance_record_verifier_fk'],
            ['attendance_corrections', 'attendance_record_id', 'attendance_records', 'attendance_correction_record_fk'],
            ['attendance_corrections', 'employee_id', 'employees', 'attendance_correction_employee_fk'],
            ['attendance_corrections', 'approved_by_kadiv', 'users', 'attendance_correction_kadiv_fk'],
            ['attendance_corrections', 'approved_by_hrd', 'users', 'attendance_correction_hrd_fk'],
            ['leave_requests', 'employee_id', 'employees', 'leave_request_employee_fk'],
            ['leave_requests', 'leave_type_id', 'leave_types', 'leave_request_type_fk'],
            ['leave_requests', 'kadiv_approved_by', 'users', 'leave_request_kadiv_fk'],
            ['leave_requests', 'hrd_approved_by', 'users', 'leave_request_hrd_fk'],
            ['leave_balances', 'employee_id', 'employees', 'leave_balance_employee_fk'],
            ['leave_balances', 'leave_type_id', 'leave_types', 'leave_balance_type_fk'],
            ['permission_requests', 'employee_id', 'employees', 'permission_request_employee_fk'],
            ['permission_requests', 'kadiv_approved_by', 'users', 'permission_request_kadiv_fk'],
            ['permission_requests', 'hrd_approved_by', 'users', 'permission_request_hrd_fk'],
            ['overtime_requests', 'employee_id', 'employees', 'overtime_request_employee_fk'],
            ['overtime_requests', 'kadiv_approved_by', 'users', 'overtime_request_kadiv_fk'],
            ['overtime_requests', 'hrd_approved_by', 'users', 'overtime_request_hrd_fk'],
            ['deduction_records', 'employee_id', 'employees', 'deduction_record_employee_fk'],
            ['deduction_records', 'confirmed_by', 'users', 'deduction_record_confirmer_fk'],
            ['deduction_events', 'deduction_record_id', 'deduction_records', 'deduction_event_record_fk'],
            ['deduction_events', 'attendance_record_id', 'attendance_records', 'deduction_event_attendance_fk'],
            ['deduction_calculation_histories', 'deduction_record_id', 'deduction_records', 'deduction_history_record_fk'],
            ['deduction_calculation_histories', 'employee_id', 'employees', 'deduction_history_employee_fk'],
            ['deduction_calculation_histories', 'actor_user_id', 'users', 'deduction_history_actor_fk'],
            ['settings', 'updated_by', 'users', 'setting_updated_by_fk'],
            ['notifications', 'user_id', 'users', 'notification_user_fk'],
            ['reports', 'created_by', 'users', 'report_created_by_fk'],
            ['audit_logs', 'user_id', 'users', 'audit_log_actor_fk'],
            ['device_checkpoints', 'device_id', 'devices', 'device_checkpoint_device_fk'],
            ['device_checkpoints', 'last_inserted_log_id', 'attendance_logs', 'device_checkpoint_last_log_fk'],
            ['device_sync_histories', 'device_id', 'devices', 'device_sync_history_device_fk'],
            ['device_employee_pushes', 'device_id', 'devices', 'device_employee_push_device_fk'],
            ['device_employee_pushes', 'employee_id', 'employees', 'device_employee_push_employee_fk'],
            ['attendance_warning_events', 'attendance_record_id', 'attendance_records', 'attendance_warning_record_fk'],
            ['attendance_warning_events', 'employee_id', 'employees', 'attendance_warning_employee_fk'],
            ['attendance_log_mapping_histories', 'attendance_log_id', 'attendance_logs', 'attendance_mapping_log_fk'],
            ['attendance_log_mapping_histories', 'employee_id', 'employees', 'attendance_mapping_employee_fk'],
            ['attendance_log_mapping_histories', 'actor_user_id', 'users', 'attendance_mapping_actor_fk'],
            ['password_histories', 'user_id', 'users', 'password_history_user_fk'],
            ['ai_conversations', 'user_id', 'users', 'ai_conversation_user_fk'],
        ];

        foreach ($foreignKeys as [$table, $column, $parent, $constraint]) {
            $this->assertNoOrphans($table, $column, $parent);
            $this->replaceWithRestrictiveForeignKey($table, $column, $parent, $constraint);
        }

        $this->addIndexes();
        $this->addCheckConstraints();
        $this->installAppendOnlyTriggers();
    }

    private function assertNoCaseInsensitiveEmailDuplicates(): void
    {
        $duplicate = DB::selectOne(<<<'SQL'
            SELECT lower(btrim(email)) AS normalized_email, COUNT(*) AS total
            FROM users
            GROUP BY lower(btrim(email))
            HAVING COUNT(*) > 1
            LIMIT 1
        SQL);

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Database hardening dihentikan: terdapat email pengguna duplikat jika dibandingkan tanpa memperhatikan huruf besar/kecil: '
                .$duplicate->normalized_email.'. Normalisasi data terlebih dahulu.'
            );
        }
    }

    private function assertNoDuplicateSessionTokenHashes(): void
    {
        $duplicate = DB::selectOne(<<<'SQL'
            SELECT token_hash, COUNT(*) AS total
            FROM sessions
            GROUP BY token_hash
            HAVING COUNT(*) > 1
            LIMIT 1
        SQL);

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Database hardening dihentikan: ditemukan token_hash sesi yang duplikat. '
                .'Cabut sesi bermasalah dan pastikan token_hash unik sebelum menjalankan migration ulang.'
            );
        }
    }

    private function assertValidWorkScheduleScopes(): void
    {
        $invalid = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS total
            FROM work_schedule_configs config
            WHERE (config.scope_type = 'COMPANY' AND config.scope_id IS NOT NULL)
               OR (config.scope_type = 'DIVISION' AND NOT EXISTS (
                    SELECT 1 FROM divisions WHERE divisions.id = config.scope_id
               ))
               OR (config.scope_type = 'EMPLOYEE' AND NOT EXISTS (
                    SELECT 1 FROM employees WHERE employees.id = config.scope_id
               ))
               OR config.scope_type NOT IN ('COMPANY', 'DIVISION', 'EMPLOYEE')
        SQL);

        $total = (int) ($invalid->total ?? 0);

        if ($total > 0) {
            throw new RuntimeException(
                "Database hardening dihentikan: ditemukan {$total} konfigurasi jadwal dengan scope_type/scope_id yang tidak valid. "
                .'Perbaiki referensi perusahaan/divisi/karyawan sebelum menjalankan migration ulang.'
            );
        }
    }

    private function assertNoOrphans(string $table, string $column, string $parent): void
    {
        $row = DB::selectOne(sprintf(
            'SELECT COUNT(*) AS total FROM "%s" child LEFT JOIN "%s" parent ON parent.id = child."%s" WHERE child."%s" IS NOT NULL AND parent.id IS NULL',
            $table,
            $parent,
            $column,
            $column
        ));

        $total = (int) ($row->total ?? 0);

        if ($total > 0) {
            throw new RuntimeException(
                "Database hardening dihentikan: {$table}.{$column} memiliki {$total} referensi yatim. "
                .'Perbaiki referensi tersebut sebelum menjalankan migration ulang.'
            );
        }
    }

    private function replaceWithRestrictiveForeignKey(
        string $table,
        string $column,
        string $parent,
        string $constraint
    ): void {
        $legacyName = $table.'_'.$column.'_foreign';

        DB::statement(sprintf(
            'ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s"',
            $table,
            $legacyName
        ));

        DB::statement(sprintf(
            'ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s"',
            $table,
            $constraint
        ));

        DB::statement(sprintf(
            'ALTER TABLE "%s" ADD CONSTRAINT "%s" FOREIGN KEY ("%s") REFERENCES "%s" (id) ON DELETE RESTRICT NOT VALID',
            $table,
            $constraint,
            $column,
            $parent
        ));

        DB::statement(sprintf(
            'ALTER TABLE "%s" VALIDATE CONSTRAINT "%s"',
            $table,
            $constraint
        ));
    }

    private function addIndexes(): void
    {
        $indexes = [
            'CREATE UNIQUE INDEX IF NOT EXISTS users_email_normalized_unique ON users (lower(btrim(email)))',
            'CREATE UNIQUE INDEX IF NOT EXISTS sessions_token_hash_unique ON sessions (token_hash)',
            'CREATE INDEX IF NOT EXISTS employees_division_status_idx ON employees (division_id, employment_status)',
            'CREATE INDEX IF NOT EXISTS employees_department_status_idx ON employees (department_id, employment_status)',
            'CREATE INDEX IF NOT EXISTS schedules_work_date_shift_idx ON schedules (work_date, shift_id)',
            'CREATE INDEX IF NOT EXISTS devices_active_status_sync_idx ON devices (is_active, status, last_sync_at)',
            'CREATE INDEX IF NOT EXISTS attendance_logs_device_scanned_id_idx ON attendance_logs (device_id, scanned_at, id)',
            'CREATE INDEX IF NOT EXISTS attendance_logs_status_scanned_id_idx ON attendance_logs (processing_status, scanned_at, id)',
            'CREATE INDEX IF NOT EXISTS leave_requests_status_period_idx ON leave_requests (status, start_date, end_date)',
            'CREATE INDEX IF NOT EXISTS permission_requests_date_status_idx ON permission_requests (date, status)',
            'CREATE INDEX IF NOT EXISTS overtime_requests_date_status_idx ON overtime_requests (date, status)',
            'CREATE INDEX IF NOT EXISTS deduction_events_date_type_idx ON deduction_events (event_date, event_type)',
            'CREATE INDEX IF NOT EXISTS deduction_events_attendance_type_idx ON deduction_events (attendance_record_id, event_type)',
            'CREATE INDEX IF NOT EXISTS deduction_history_employee_period_idx ON deduction_calculation_histories (employee_id, period)',
            'CREATE INDEX IF NOT EXISTS notifications_user_read_created_idx ON notifications (user_id, is_read, created_at DESC)',
            'CREATE INDEX IF NOT EXISTS audit_logs_created_at_idx ON audit_logs (created_at DESC)',
            'CREATE INDEX IF NOT EXISTS attendance_mapping_log_created_idx ON attendance_log_mapping_histories (attendance_log_id, created_at DESC)',
            'CREATE INDEX IF NOT EXISTS reports_creator_created_idx ON reports (created_by, created_at DESC)',
            'CREATE INDEX IF NOT EXISTS attendance_warning_event_date_idx ON attendance_warning_events (event_date, event_type)',
        ];

        foreach ($indexes as $sql) {
            DB::statement($sql);
        }
    }

    private function addCheckConstraints(): void
    {
        $checks = [
            'devices_port_range_chk' => "ALTER TABLE devices ADD CONSTRAINT devices_port_range_chk CHECK (port BETWEEN 1 AND 65535) NOT VALID",
            'shifts_min_work_hours_chk' => "ALTER TABLE shifts ADD CONSTRAINT shifts_min_work_hours_chk CHECK (min_work_hours >= 0 AND min_work_hours <= 24) NOT VALID",
            'shifts_late_tolerance_range_chk' => "ALTER TABLE shifts ADD CONSTRAINT shifts_late_tolerance_range_chk CHECK (late_tolerance_minutes BETWEEN 0 AND 1440) NOT VALID",
            'attendance_record_minutes_nonnegative_chk' => "ALTER TABLE attendance_records ADD CONSTRAINT attendance_record_minutes_nonnegative_chk CHECK ((work_duration_minutes IS NULL OR work_duration_minutes >= 0) AND late_minutes >= 0 AND early_departure_minutes >= 0) NOT VALID",
            'integration_log_duration_nonnegative_chk' => "ALTER TABLE integration_logs ADD CONSTRAINT integration_log_duration_nonnegative_chk CHECK (duration_ms IS NULL OR duration_ms >= 0) NOT VALID",
            'device_sync_counters_nonnegative_chk' => "ALTER TABLE device_sync_histories ADD CONSTRAINT device_sync_counters_nonnegative_chk CHECK (duration_ms >= 0 AND total_fetched >= 0 AND total_inserted >= 0 AND total_duplicate >= 0 AND total_unmatched >= 0 AND total_failed >= 0 AND attempt_count >= 1) NOT VALID",
            'leave_type_limits_chk' => "ALTER TABLE leave_types ADD CONSTRAINT leave_type_limits_chk CHECK (default_quota >= 0 AND max_days_per_request >= 1) NOT VALID",
            'leave_balance_values_chk' => "ALTER TABLE leave_balances ADD CONSTRAINT leave_balance_values_chk CHECK (quota >= 0 AND used >= 0 AND remaining >= 0) NOT VALID",
            'permission_request_duration_chk' => "ALTER TABLE permission_requests ADD CONSTRAINT permission_request_duration_chk CHECK (duration_minutes IS NULL OR duration_minutes >= 0) NOT VALID",
            'overtime_request_minutes_chk' => "ALTER TABLE overtime_requests ADD CONSTRAINT overtime_request_minutes_chk CHECK (claimed_minutes >= 0 AND (approved_minutes IS NULL OR approved_minutes >= 0)) NOT VALID",
            'deduction_record_counts_nonnegative_chk' => "ALTER TABLE deduction_records ADD CONSTRAINT deduction_record_counts_nonnegative_chk CHECK (total_events >= 0 AND tolerated_events >= 0 AND charged_events >= 0 AND alpha_events >= 0 AND early_departure_events >= 0) NOT VALID",
            'deduction_history_counts_nonnegative_chk' => "ALTER TABLE deduction_calculation_histories ADD CONSTRAINT deduction_history_counts_nonnegative_chk CHECK (total_events >= 0 AND tolerated_events >= 0 AND charged_events >= 0 AND alpha_events >= 0 AND early_departure_events >= 0) NOT VALID",
            'attendance_warning_minutes_nonnegative_chk' => "ALTER TABLE attendance_warning_events ADD CONSTRAINT attendance_warning_minutes_nonnegative_chk CHECK (minutes_early >= 0) NOT VALID",
            'notification_attempts_nonnegative_chk' => "ALTER TABLE notifications ADD CONSTRAINT notification_attempts_nonnegative_chk CHECK (attempts >= 0) NOT VALID",
            'leave_requests_date_range_chk' => "ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_date_range_chk CHECK (end_date >= start_date) NOT VALID",
            'leave_requests_total_days_chk' => "ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_total_days_chk CHECK (total_days > 0) NOT VALID",
            'work_schedule_date_range_chk' => "ALTER TABLE work_schedule_configs ADD CONSTRAINT work_schedule_date_range_chk CHECK (effective_to IS NULL OR effective_to >= effective_from) NOT VALID",
            'work_schedule_scope_chk' => "ALTER TABLE work_schedule_configs ADD CONSTRAINT work_schedule_scope_chk CHECK ((scope_type = 'COMPANY' AND scope_id IS NULL) OR (scope_type IN ('DIVISION', 'EMPLOYEE') AND scope_id IS NOT NULL)) NOT VALID",
            'attendance_record_time_order_chk' => "ALTER TABLE attendance_records ADD CONSTRAINT attendance_record_time_order_chk CHECK (check_out_at IS NULL OR check_in_at IS NULL OR check_out_at >= check_in_at) NOT VALID",
            'deduction_period_format_chk' => "ALTER TABLE deduction_records ADD CONSTRAINT deduction_period_format_chk CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$') NOT VALID",
            'deduction_base_amount_nonnegative_chk' => "ALTER TABLE deduction_records ADD CONSTRAINT deduction_base_amount_nonnegative_chk CHECK (base_amount IS NULL OR base_amount > 0) NOT VALID",
            'deduction_amount_nonnegative_chk' => "ALTER TABLE deduction_records ADD CONSTRAINT deduction_amount_nonnegative_chk CHECK (deduction_amount >= 0) NOT VALID",
            'deduction_percentage_nonnegative_chk' => "ALTER TABLE deduction_records ADD CONSTRAINT deduction_percentage_nonnegative_chk CHECK (total_percentage >= 0 AND alpha_percentage >= 0) NOT VALID",
            'deduction_event_percentage_range_chk' => "ALTER TABLE deduction_events ADD CONSTRAINT deduction_event_percentage_range_chk CHECK (percentage >= 0 AND percentage <= 100) NOT VALID",
            'deduction_event_type_allowed_chk' => "ALTER TABLE deduction_events ADD CONSTRAINT deduction_event_type_allowed_chk CHECK (event_type IN ('LATE', 'ABSENT')) NOT VALID",
            'deduction_history_period_format_chk' => "ALTER TABLE deduction_calculation_histories ADD CONSTRAINT deduction_history_period_format_chk CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$') NOT VALID",
        ];

        foreach ($checks as $name => $sql) {
            $exists = DB::selectOne(
                'SELECT 1 FROM pg_constraint WHERE conname = ? AND conrelid = ?::regclass LIMIT 1',
                [$name, $this->tableForConstraint($name)]
            );

            if ($exists === null) {
                DB::statement($sql);
            }

            DB::statement(sprintf(
                'ALTER TABLE "%s" VALIDATE CONSTRAINT "%s"',
                $this->tableForConstraint($name),
                $name
            ));
        }
    }

    private function tableForConstraint(string $name): string
    {
        return match ($name) {
            'devices_port_range_chk' => 'devices',
            'shifts_min_work_hours_chk', 'shifts_late_tolerance_range_chk' => 'shifts',
            'attendance_record_minutes_nonnegative_chk' => 'attendance_records',
            'integration_log_duration_nonnegative_chk' => 'integration_logs',
            'device_sync_counters_nonnegative_chk' => 'device_sync_histories',
            'leave_type_limits_chk' => 'leave_types',
            'leave_balance_values_chk' => 'leave_balances',
            'permission_request_duration_chk' => 'permission_requests',
            'overtime_request_minutes_chk' => 'overtime_requests',
            'deduction_record_counts_nonnegative_chk' => 'deduction_records',
            'deduction_history_counts_nonnegative_chk' => 'deduction_calculation_histories',
            'attendance_warning_minutes_nonnegative_chk' => 'attendance_warning_events',
            'notification_attempts_nonnegative_chk' => 'notifications',
            'leave_requests_date_range_chk', 'leave_requests_total_days_chk' => 'leave_requests',
            'work_schedule_date_range_chk', 'work_schedule_scope_chk' => 'work_schedule_configs',
            'attendance_record_time_order_chk' => 'attendance_records',
            'deduction_period_format_chk', 'deduction_base_amount_nonnegative_chk',
            'deduction_amount_nonnegative_chk', 'deduction_percentage_nonnegative_chk' => 'deduction_records',
            'deduction_event_percentage_range_chk', 'deduction_event_type_allowed_chk' => 'deduction_events',
            'deduction_history_period_format_chk' => 'deduction_calculation_histories',
            default => throw new InvalidArgumentException("Unknown constraint {$name}"),
        };
    }

    private function installAppendOnlyTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION pride_prevent_audit_log_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs bersifat append-only; UPDATE dan DELETE ditolak.'
                    USING ERRCODE = '55000';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS audit_logs_append_only_trigger ON audit_logs;
            CREATE TRIGGER audit_logs_append_only_trigger
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION pride_prevent_audit_log_mutation();

            CREATE OR REPLACE FUNCTION pride_protect_attendance_log_source()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'attendance_logs tidak boleh dihapus; raw log bersifat append-only.'
                        USING ERRCODE = '55000';
                END IF;

                IF NEW.device_id IS DISTINCT FROM OLD.device_id
                    OR NEW.device_user_identifier IS DISTINCT FROM OLD.device_user_identifier
                    OR NEW.device_log_id IS DISTINCT FROM OLD.device_log_id
                    OR NEW.scanned_at IS DISTINCT FROM OLD.scanned_at
                    OR NEW.raw_payload::text IS DISTINCT FROM OLD.raw_payload::text
                    OR NEW.fingerprint IS DISTINCT FROM OLD.fingerprint
                    OR NEW.sync_batch_id IS DISTINCT FROM OLD.sync_batch_id THEN
                    RAISE EXCEPTION 'Identitas dan payload sumber attendance_logs tidak boleh diubah.'
                        USING ERRCODE = '55000';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS attendance_logs_source_immutable_trigger ON attendance_logs;
            CREATE TRIGGER attendance_logs_source_immutable_trigger
            BEFORE UPDATE OR DELETE ON attendance_logs
            FOR EACH ROW EXECUTE FUNCTION pride_protect_attendance_log_source();

            CREATE OR REPLACE FUNCTION pride_prevent_employee_physical_delete()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Karyawan tidak boleh dihapus secara fisik; gunakan employment_status.'
                    USING ERRCODE = '55000';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS employees_no_physical_delete_trigger ON employees;
            CREATE TRIGGER employees_no_physical_delete_trigger
            BEFORE DELETE ON employees
            FOR EACH ROW EXECUTE FUNCTION pride_prevent_employee_physical_delete();

            CREATE OR REPLACE FUNCTION pride_validate_work_schedule_scope()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.scope_type = 'DIVISION' AND NOT EXISTS (
                    SELECT 1 FROM divisions WHERE id = NEW.scope_id
                ) THEN
                    RAISE EXCEPTION 'work_schedule_configs.scope_id tidak menunjuk ke divisions yang valid.'
                        USING ERRCODE = '23503';
                ELSIF NEW.scope_type = 'EMPLOYEE' AND NOT EXISTS (
                    SELECT 1 FROM employees WHERE id = NEW.scope_id
                ) THEN
                    RAISE EXCEPTION 'work_schedule_configs.scope_id tidak menunjuk ke employees yang valid.'
                        USING ERRCODE = '23503';
                ELSIF NEW.scope_type NOT IN ('COMPANY', 'DIVISION', 'EMPLOYEE') THEN
                    RAISE EXCEPTION 'work_schedule_configs.scope_type tidak valid.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS work_schedule_scope_reference_trigger ON work_schedule_configs;
            CREATE TRIGGER work_schedule_scope_reference_trigger
            BEFORE INSERT OR UPDATE ON work_schedule_configs
            FOR EACH ROW EXECUTE FUNCTION pride_validate_work_schedule_scope();

            CREATE OR REPLACE FUNCTION pride_prevent_referenced_division_delete()
            RETURNS trigger AS $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM work_schedule_configs
                    WHERE scope_type = 'DIVISION' AND scope_id = OLD.id
                ) THEN
                    RAISE EXCEPTION 'Divisi masih digunakan oleh konfigurasi jadwal; nonaktifkan divisi, jangan hapus.'
                        USING ERRCODE = '55000';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS divisions_referenced_by_schedule_trigger ON divisions;
            CREATE TRIGGER divisions_referenced_by_schedule_trigger
            BEFORE DELETE ON divisions
            FOR EACH ROW EXECUTE FUNCTION pride_prevent_referenced_division_delete();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // This migration deliberately hardens production data integrity. A
        // rollback that restores cascading deletes or mutable audit records is
        // unsafe. Reversal must be a separately reviewed migration.
        throw new RuntimeException(
            'Migration database hardening tidak dapat di-rollback otomatis karena akan melemahkan integritas dan retensi data. '
            .'Buat migration reversal khusus setelah review dan backup.'
        );
    }
};

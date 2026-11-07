<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Hardening absensi & deduction — MIGRASI ADDITIVE (tidak menghapus data).
 
  Menambahkan:
   - identitas log perangkat + fingerprint idempotency pada attendance_logs
   - data pendukung EARLY_DEPARTURE pada attendance_records
   - field audit pembulatan nominal akhir pada deduction_records/histories
   - flag sinkronisasi & kesehatan pada devices */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('device_user_identifier', 100)->nullable()->after('employee_id');
            $table->string('device_log_id', 100)->nullable()->after('device_user_identifier');
            // Fingerprint deterministik untuk idempotency bila perangkat tidak
            // menyediakan device_log_id. NULL diperbolehkan (baris lama).
            $table->string('fingerprint', 64)->nullable()->after('device_log_id');
            $table->string('sync_batch_id', 64)->nullable()->after('fingerprint');

            $table->unique('fingerprint', 'att_logs_fingerprint_unique');
            $table->unique(['device_id', 'device_log_id'], 'att_logs_dev_log_unique');
            $table->index(['sync_batch_id'], 'att_logs_sync_batch_idx');
            $table->index(['device_user_identifier'], 'att_logs_dev_user_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Pemetaan identitas karyawan pada mesin ZKTeco (PRD 6.E / 7.3).
            $table->string('device_user_identifier', 100)->nullable()->after('nip');
            $table->unique('device_user_identifier', 'employees_device_user_unique');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('first_scan_at')->nullable()->after('check_out_at');
            $table->timestamp('scheduled_end_at')->nullable()->after('first_scan_at');
            $table->unsignedInteger('early_departure_minutes')->default(0)->after('late_minutes');
            $table->unsignedInteger('additional_scan_count')->default(0)->after('early_departure_minutes');
        });

        Schema::table('deduction_records', function (Blueprint $table) {
            $table->decimal('raw_final_amount', 15, 2)->default(0)->after('deduction_amount');
            $table->decimal('rounding_adjustment', 15, 2)->default(0)->after('raw_final_amount');
            // Jumlah warning Pulang Cepat: DILAPORKAN, tidak pernah masuk percentage
            $table->unsignedSmallInteger('early_departure_events')->default(0)->after('alpha_events');
        });

        Schema::table('deduction_calculation_histories', function (Blueprint $table) {
            $table->decimal('raw_final_amount', 15, 2)->default(0)->after('deduction_amount');
            $table->decimal('rounding_adjustment', 15, 2)->default(0)->after('raw_final_amount');
            $table->unsignedSmallInteger('early_departure_events')->default(0)->after('alpha_events');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('last_successful_connection_at')->nullable()->after('last_sync_at');
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_successful_connection_at');
            $table->string('last_error_code', 50)->nullable()->after('consecutive_failures');
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->string('firmware', 100)->nullable()->after('model');
            $table->unsignedBigInteger('last_log_count')->default(0)->after('firmware');
            // Offset staggered scheduling (detik) agar perangkat tidak sinkron bareng
            $table->unsignedSmallInteger('sync_offset_seconds')->default(0)->after('last_log_count');
            $table->unsignedSmallInteger('sync_interval_minutes')->default(15)->after('sync_offset_seconds');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->string('activation_token_hash', 64)->nullable()->after('must_change_password');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_token_hash');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('link');
            $table->string('channel', 20)->default('DATABASE')->after('idempotency_key');
            $table->string('delivery_status', 20)->default('SENT')->after('channel');
            $table->unsignedSmallInteger('attempts')->default(0)->after('delivery_status');

            $table->unique('idempotency_key', 'notif_idem_unique');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        // Sengaja tidak drop kolom secara otomatis (menghindari destructive migration)
        // Rollback manual harus melalui migration yang disetujui
    }
};

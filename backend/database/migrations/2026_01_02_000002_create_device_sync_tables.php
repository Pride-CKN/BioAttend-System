<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*Arsitektur sinkronisasi perangkat Semua tabel baru,
 tidak ada penghapusan atau perubahan data existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Checkpoint per perangkat — hanya boleh maju setelah batch tersimpan.
        Schema::create('device_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('cursor', 191)->nullable();       // device log ID / cursor resmi SDK
            $table->timestamp('last_scan_at')->nullable();   // fallback aman (bukan satu-satunya penanda)
            $table->string('strategy', 30)->default('NONE'); // SDK_CURSOR / TIMESTAMP_FALLBACK / FULL_SCAN
            $table->unsignedBigInteger('last_inserted_log_id')->nullable();
            $table->timestamps();

            $table->unique('device_id', 'device_checkpoints_dev_unique');
        });

        // Riwayat setiap proses sinkronisasi
        Schema::create('device_sync_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('device_name', 191);
            $table->string('sync_type', 30)->default('PULL_LOGS'); // PULL_LOGS / PUSH_EMPLOYEES / HEALTH / RECONCILE
            $table->string('trigger_source', 20)->default('scheduled'); // scheduled / manual / retry
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedBigInteger('total_fetched')->default(0);
            $table->unsignedBigInteger('total_inserted')->default(0);
            $table->unsignedBigInteger('total_duplicate')->default(0);
            $table->unsignedBigInteger('total_unmatched')->default(0);
            $table->unsignedBigInteger('total_failed')->default(0);
            $table->string('checkpoint_before', 191)->nullable();
            $table->string('checkpoint_after', 191)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(1);
            // RUNNING / SUCCESS / PARTIAL_SUCCESS / FAILED / TIMEOUT / SKIPPED
            $table->string('status', 20)->default('RUNNING');
            $table->string('error_code', 50)->nullable();
            $table->text('error_summary')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'started_at'], 'dev_sync_hist_dev_time_idx');
            $table->index(['status', 'started_at'], 'dev_sync_hist_status_idx');
            $table->index('correlation_id', 'dev_sync_hist_corr_idx');
        });

        // Status push karyawan per perangkat (event-driven, bukan full push 15 menit).
        Schema::create('device_employee_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('device_user_identifier', 100)->nullable();
            $table->string('operation', 20)->default('UPSERT'); // UPSERT / DISABLE / DELETE
            $table->string('payload_hash', 64);
            $table->string('status', 20)->default('PENDING'); // PENDING / SUCCESS / FAILED
            $table->text('error_summary')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'employee_id'], 'dev_emp_push_unique');
            $table->index(['status'], 'dev_emp_push_status_idx');
        });

        // Warning EARLY_DEPARTURE — idempotency key mencegah notifikasi ganda.
        Schema::create('attendance_warning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('event_type', 30)->default('EARLY_DEPARTURE');
            $table->string('idempotency_key', 64);
            $table->date('event_date');
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('actual_check_out_at')->nullable();
            $table->unsignedInteger('minutes_early')->default(0);
            $table->json('payload')->nullable();
            $table->string('delivery_status', 20)->default('PENDING');
            $table->timestamps();

            $table->unique('idempotency_key', 'att_warn_event_idem_unique');
            $table->index(['employee_id', 'event_date'], 'att_warn_event_emp_date_idx');
        });

        // Mapping manual log unmatched
        Schema::create('attendance_log_mapping_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_log_id')->constrained('attendance_logs')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('previous_employee_id', 30)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_log_mapping_histories');
        Schema::dropIfExists('attendance_warning_events');
        Schema::dropIfExists('device_employee_pushes');
        Schema::dropIfExists('device_sync_histories');
        Schema::dropIfExists('device_checkpoints');
    }
};

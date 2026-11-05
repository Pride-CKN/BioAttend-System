<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inti absensi: attendance_logs (append-only), attendance_records,
     * attendance_corrections (PRD 19.2, BR-03, BR-04, BR-16).
     */
    public function up(): void
    {
        // Raw log pemindaian — APPEND-ONLY (BR-04)
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('scanned_at');
            $table->string('scan_type', 20)->nullable();          // CHECK_IN / CHECK_OUT / UNKNOWN
            $table->json('raw_payload')->nullable();
            $table->string('processing_status', 20)->default('PENDING'); // PENDING / PROCESSED / UNMATCHED / FAILED
            $table->boolean('is_duplicate')->default(false);
            $table->unsignedBigInteger('primary_log_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'scanned_at'], 'att_logs_emp_scan_idx');
            $table->index(['device_id', 'processing_status'], 'att_logs_dev_status_idx');
        });

        // Satu record per karyawan per hari kerja (BR-03)
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->unsignedInteger('work_duration_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            // PRESENT / LATE / EARLY_DEPARTURE / INCOMPLETE / ABSENT / LEAVE / PERMISSION / BUSINESS_TRIP / HOLIDAY
            $table->string('status', 20)->default('ABSENT');
            $table->string('violation_types')->nullable(); // CSV: LATE,EARLY_DEPARTURE,ABSENT
            $table->boolean('is_incomplete')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('source', 20)->default('AUTO'); // AUTO / MANUAL / CORRECTION
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'att_rec_emp_date_unique');
            $table->index(['work_date', 'status'], 'att_rec_date_status_idx');
            $table->index(['work_date', 'is_incomplete'], 'att_rec_date_incomplete_idx');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('field', 30);                       // check_in_at / check_out_at / status
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            // PENDING_KADIV / PENDING_HRD / APPROVED / REJECTED
            $table->string('status', 20)->default('PENDING_KADIV');
            $table->unsignedBigInteger('approved_by_kadiv')->nullable();
            $table->timestamp('kadiv_approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_hrd')->nullable();
            $table->timestamp('hrd_approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_logs');
    }
};

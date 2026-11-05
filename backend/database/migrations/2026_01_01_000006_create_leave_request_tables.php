<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
      Pengajuan: leave_types, leave_requests, leave_balances,
      permission_requests, overtime_requests
      Seluruh pengajuan mengikuti persetujuan berjenjang KADIV -> HRD
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('default_quota')->default(12);
            $table->unsignedSmallInteger('max_days_per_request')->default(12);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('PENDING_KADIV'); // PENDING_KADIV/PENDING_HRD/APPROVED/REJECTED/CANCELLED
            $table->unsignedBigInteger('kadiv_approved_by')->nullable();
            $table->timestamp('kadiv_approved_at')->nullable();
            $table->unsignedBigInteger('hrd_approved_by')->nullable();
            $table->timestamp('hrd_approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'start_date'], 'leave_req_idx');
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('quota')->default(12);
            $table->unsignedSmallInteger('used')->default(0);
            $table->unsignedSmallInteger('remaining')->default(12);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_bal_unique');
        });

        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 20)->default('PERSONAL'); // PERSONAL / SICK / OTHER
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('PENDING_KADIV');
            $table->unsignedBigInteger('kadiv_approved_by')->nullable();
            $table->timestamp('kadiv_approved_at')->nullable();
            $table->unsignedBigInteger('hrd_approved_by')->nullable();
            $table->timestamp('hrd_approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('claimed_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->decimal('multiplier', 4, 2)->default(1.00);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('PENDING_KADIV');
            $table->unsignedBigInteger('kadiv_approved_by')->nullable();
            $table->timestamp('kadiv_approved_at')->nullable();
            $table->unsignedBigInteger('hrd_approved_by')->nullable();
            $table->timestamp('hrd_approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('permission_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};

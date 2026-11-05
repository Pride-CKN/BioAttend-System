<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*Shift, penugasan jadwal, hari libur, dan konfigurasi jam kerja 3 tingkat
      (shifts, schedules, holidays, work_schedule_configs, schedule_change_histories)*/
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->time('start_time');              // contoh 08:00
            $table->time('end_time');                // contoh 17:00
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->decimal('min_work_hours', 5, 2)->default(8);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('source', 20)->default('MANUAL'); // MANUAL / AUTO / IMPORT
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->string('type', 30)->default('NATIONAL'); // NATIONAL / COMPANY / RELIGIOUS
            $table->boolean('is_paid')->default(true);
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();
        });

        // Konfigurasi jam kerja berjenjang: COMPANY / DIVISION / EMPLOYEE (BR-13)
        Schema::create('work_schedule_configs', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20);       // COMPANY / DIVISION / EMPLOYEE
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->json('work_days')->nullable();   // [1,2,3,4,5] = Senin..Jumat
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'effective_from', 'effective_to'], 'wsc_scope_range_idx');
            $table->index(['scope_type', 'is_active'], 'wsc_scope_active_idx');
        });

        // Riwayat perubahan konfigurasi jam kerja
        Schema::create('schedule_change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_config_id')->nullable()->constrained('work_schedule_configs')->nullOnDelete();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['work_schedule_config_id', 'created_at'], 'sch_hist_config_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_histories');
        Schema::dropIfExists('work_schedule_configs');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('shifts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
      Deduction & PPh

      ATURAN ALPHA (permintaan eksplisit pengguna):
        - alpha (tanpa keterangan) dikenakan potongan 4% LANGSUNG sejak kejadian pertama
        - alpha TIDAK mendapat jatah toleransi
        Kolom alpha_events / alpha_rate / alpha_percentage menyimpan jejaknya
     */
    public function up(): void
    {
        Schema::create('deduction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period', 7);                    // YYYY-MM
            $table->unsignedSmallInteger('total_events')->default(0);      // E
            $table->unsignedSmallInteger('tolerated_events')->default(2);  // T
            $table->unsignedSmallInteger('charged_events')->default(0);    // Ec (non-alpha)
            $table->decimal('rate_per_event', 5, 2)->default(2.00);        // R
            $table->decimal('total_percentage', 6, 2)->default(0);         // P
            // --- Jejak alpha ---
            $table->unsignedSmallInteger('alpha_events')->default(0);
            $table->decimal('alpha_rate', 5, 2)->default(4.00);
            $table->decimal('alpha_percentage', 6, 2)->default(0);
            // --- Nominal ---
            $table->decimal('base_amount', 15, 2)->nullable();             // N (input HRD)
            $table->decimal('deduction_amount', 15, 2)->default(0);        // D
            $table->decimal('pph_amount', 15, 2)->default(0);
            $table->json('pph_details')->nullable();
            $table->decimal('final_amount', 15, 2)->default(0);            // A
            // --- Status: DRAFT / CALCULATED / CONFIRMED / FINAL ---
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirmation_note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period'], 'deduction_emp_period_unique');
            $table->index(['period', 'status']);
        });

        Schema::create('deduction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_record_id')->constrained('deduction_records')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->date('event_date');
            $table->string('event_type', 20);   // LATE / EARLY_DEPARTURE / ABSENT
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_tolerated')->default(false);
            $table->decimal('percentage', 6, 2)->default(0);
            $table->timestamps();

            $table->index(['deduction_record_id', 'sequence'], 'deduction_events_idx');
        });

        Schema::create('deduction_calculation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_record_id')->constrained('deduction_records')->cascadeOnDelete();
            $table->string('period', 7);
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('total_events')->default(0);
            $table->unsignedSmallInteger('tolerated_events')->default(0);
            $table->unsignedSmallInteger('charged_events')->default(0);
            $table->unsignedSmallInteger('alpha_events')->default(0);
            $table->decimal('alpha_rate', 5, 2)->default(4.00);
            $table->decimal('rate_per_event', 5, 2)->default(2.00);
            $table->decimal('total_percentage', 6, 2)->default(0);
            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('deduction_amount', 15, 2)->default(0);
            $table->decimal('pph_amount', 15, 2)->default(0);
            $table->json('pph_details')->nullable();
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->string('status', 20);
            // CALCULATE / RECALCULATE / INPUT_NOMINAL / CONFIRM / FINALIZE / UPDATE_PPH
            $table->string('action', 30)->default('CALCULATE');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['deduction_record_id', 'created_at'], 'deduction_hist_idx');
        });

        // Modul PPh — ekstensi yang dapat dimatikan lewat setting PPH_ENABLED
        Schema::create('tax_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 20)->default('DEDUCTION');      // DEDUCTION / ALLOWANCE
            $table->string('calc_type', 20)->default('PERCENTAGE'); // PERCENTAGE / FIXED
            $table->decimal('value', 15, 2)->default(0);
            $table->string('base_on', 20)->default('BASE');         // BASE / AFTER_ATTENDANCE
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_components');
        Schema::dropIfExists('deduction_calculation_histories');
        Schema::dropIfExists('deduction_events');
        Schema::dropIfExists('deduction_records');
    }
};

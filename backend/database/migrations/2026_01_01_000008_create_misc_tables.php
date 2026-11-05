<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
      Pengaturan, notifikasi, laporan, dan audit log
      audit_logs bersifat append-only — hanya ada created_at */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('STRING'); // STRING / NUMBER / BOOLEAN / JSON
            $table->string('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('category', 30)->default('SYSTEM'); // SYSTEM / LEAVE / DEDUCTION / ATTENDANCE
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);              // ATTENDANCE / DEDUCTION / OVERTIME / AUDIT
            $table->json('parameters')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('format', 10)->default('CSV'); // CSV / PDF / XLSX
            $table->string('status', 20)->default('PENDING'); // PENDING/PROCESSING/DONE/FAILED
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        // Append-only, tidak ada updated_at
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 30)->nullable();
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('object_type', 50)->nullable();
            $table->string('object_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'audit_user_idx');
            $table->index(['module', 'action'], 'audit_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
    }
};

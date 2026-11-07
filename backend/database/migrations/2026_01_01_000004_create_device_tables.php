<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* Perangkat absensi & log integrasicommkey disimpan terenkripsi */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('ip_address', 45);
            $table->unsignedInteger('port')->default(4370);
            $table->text('commkey_encrypted')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('OFFLINE'); // ONLINE / OFFLINE / ERROR
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50);        // DEVICE / GOOGLE
            $table->string('operation', 50);      // PULL_LOGS / PUSH_EMPLOYEES / SYNC
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('status', 20);         // SUCCESS / FAILED
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['service', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('devices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_warning_events') || Schema::hasColumn('attendance_warning_events', 'event_status')) {
            return;
        }

        Schema::table('attendance_warning_events', function (Blueprint $table) {
            $table->string('event_status', 20)->default('ACTIVE')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_warning_events') && Schema::hasColumn('attendance_warning_events', 'event_status')) {
            Schema::table('attendance_warning_events', function (Blueprint $table) {
                $table->dropColumn('event_status');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive scheduler cursor; existing sync history and data are preserved. */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (! Schema::hasColumn('devices', 'next_sync_at')) {
                $table->timestamp('next_sync_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Intentionally retain operational scheduling data on rollback.
    }
};

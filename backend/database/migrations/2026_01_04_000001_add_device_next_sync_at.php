<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* Kursor penjadwal aditif; riwayat sinkronisasi dan data yang sudah ada tetap dipertahankan */
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
        // Sengaja menyimpan data penjadwalan operasional saat rollback.
    }
};

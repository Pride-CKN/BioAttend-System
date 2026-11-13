<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('devices')->whereNull('ip_address')->exists()) {
            throw new RuntimeException(
                'Cannot restore a required device IP address while ADMS-only devices exist.'
            );
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable(false)->change();
        });
    }
};

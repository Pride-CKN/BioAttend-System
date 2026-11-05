
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memastikan satu user hanya memiliki satu role.
     */
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->unique('user_id', 'role_user_user_id_unique');
        });
    }

    /**
     * Menghapus pembatas satu role per user.
     */
    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropUnique('role_user_user_id_unique');
        });
    }
};

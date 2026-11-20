<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->unsignedSmallInteger('employee_code_digit')->nullable();
        });

        // Isi digit berdasarkan kode divisi
        DB::statement(<<<'SQL'
            UPDATE divisions
            SET employee_code_digit = CASE code
                WHEN 'DIR' THEN 0
                WHEN 'SDM' THEN 1
                WHEN 'TKG' THEN 2
                WHEN 'KEU' THEN 3
                WHEN 'PNG' THEN 4
                WHEN 'IND' THEN 5
                WHEN 'OPR' THEN 6
                WHEN 'KOM' THEN 7
                ELSE NULL
            END
        SQL);

        Schema::table('divisions', function (Blueprint $table) {
            $table->unique(
                'employee_code_digit',
                'divisions_employee_code_digit_unique'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE divisions
            ADD CONSTRAINT divisions_employee_code_digit_chk
            CHECK (
                employee_code_digit IS NULL
                OR employee_code_digit BETWEEN 0 AND 9
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE divisions DROP CONSTRAINT IF EXISTS divisions_employee_code_digit_chk'
        );

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropUnique('divisions_employee_code_digit_unique');
            $table->dropColumn('employee_code_digit');
        });
    }
};

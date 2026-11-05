
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Migration restrukturisasi organisasi ini harus dijalankan menggunakan PostgreSQL.'
            );
        }

        /* Restrukturisasi tabel organisasi
        departments -> divisions
        divisions   -> units
        Tabel divisions lama dipindahin dulu buat ngehindarin konflik nama tabel */

        Schema::rename('divisions', 'units');
        Schema::rename('departments', 'divisions');

        /* Perubahan foreign key organisasi */

        Schema::table('units', function (Blueprint $table) {
            $table->renameColumn('department_id', 'division_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Relasi lama ke divisions (sekarang units).
            $table->renameColumn('division_id', 'unit_id');

            // Relasi lama ke departments (sekarang divisions)
            $table->renameColumn('department_id', 'division_id');
        });

        /* Perubahan nama foreign key PostgreSQL */

        DB::unprepared(<<<'SQL'
            ALTER TABLE divisions
                RENAME CONSTRAINT department_head_employee_fk
                TO division_head_employee_fk;

            ALTER TABLE units
                RENAME CONSTRAINT division_department_fk
                TO unit_division_fk;

            ALTER TABLE units
                RENAME CONSTRAINT division_head_employee_fk
                TO unit_head_employee_fk;

            ALTER TABLE employees
                RENAME CONSTRAINT employee_division_fk
                TO employee_unit_fk;

            ALTER TABLE employees
                RENAME CONSTRAINT employee_department_fk
                TO employee_division_fk;
        SQL);

        /* Perubahan nama index PostgreSQL */

        DB::unprepared(<<<'SQL'
            ALTER INDEX employees_division_status_idx
                RENAME TO employees_unit_status_idx;

            ALTER INDEX employees_department_status_idx
                RENAME TO employees_division_status_idx;

            ALTER INDEX employees_division_id_index
                RENAME TO employees_unit_id_index;

            ALTER INDEX employees_department_id_index
                RENAME TO employees_division_id_index;

            ALTER INDEX divisions_department_id_index
                RENAME TO units_division_id_index;
        SQL);

        /* Tambahkan digit Employee ID ke unit


            employee_code_digit:
            Nomor unit dalam Employee ID
            Berlaku per divisi
            Rentang 1 sampai 9
            units.code tetap menjadi kode unik global */

        Schema::table('units', function (Blueprint $table) {
            $table->unsignedSmallInteger('employee_code_digit')
                ->nullable();

            $table->unique(
                ['division_id', 'employee_code_digit'],
                'units_division_employee_digit_unique'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE units
            ADD CONSTRAINT units_employee_code_digit_chk
            CHECK (
                employee_code_digit IS NULL
                OR employee_code_digit BETWEEN 1 AND 9
            )
        SQL);

        /* Master kantor */

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('offices')->insert([
            [
                'code' => '1',
                'name' => 'Kantor Singgasana',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2',
                'name' => 'Produksi Soekarno-Hatta',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '3',
                'name' => 'Stokis Kembar',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '4',
                'name' => 'Stokis Cibolerang',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '5',
                'name' => 'Stokis Cileunyi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '6',
                'name' => 'Storage M. Toha',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*  Kolom kantor dan Employee ID
            Employee ID disimpan sebagai VARCHAR(6), bukan integer, biar format enam digit tetap dijaga
            Contoh:
            110001 -> 1.1.0.001 */

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices')
                ->restrictOnDelete();

            $table->string('employee_code', 6)
                ->nullable()
                ->unique();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE employees
            ADD CONSTRAINT employees_employee_code_format_chk
            CHECK (
                employee_code IS NULL
                OR employee_code ~ '^[0-9]{6}$'
            )
        SQL);

        /* Histori Employee ID
         Employee ID lama gk boleh dipake lagi, histori
         tetap terhubung ke employees.id yang bersifat permanen */

        Schema::create('employee_code_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->string('employee_code', 6)->unique();

            $table->foreignId('office_id')
                ->constrained('offices')
                ->restrictOnDelete();

            $table->foreignId('division_id')
                ->constrained('divisions')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->restrictOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['employee_id', 'effective_from'],
                'employee_code_history_period_idx'
            );
        });

        /* Perbaiki validasi scope jadwal
        Scope DIVISION sekarang mengarah ke tabel divisions baru,
        trigger proteksi penghapusan juga harus ada didivisions */

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS divisions_referenced_by_schedule_trigger
                ON units;

            DROP TRIGGER IF EXISTS divisions_referenced_by_schedule_trigger
                ON divisions;

            CREATE OR REPLACE FUNCTION pride_validate_work_schedule_scope()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.scope_type = 'DIVISION' AND NOT EXISTS (
                    SELECT 1
                    FROM divisions
                    WHERE id = NEW.scope_id
                ) THEN
                    RAISE EXCEPTION
                        'work_schedule_configs.scope_id tidak menunjuk ke divisions yang valid.'
                        USING ERRCODE = '23503';

                ELSIF NEW.scope_type = 'EMPLOYEE' AND NOT EXISTS (
                    SELECT 1
                    FROM employees
                    WHERE id = NEW.scope_id
                ) THEN
                    RAISE EXCEPTION
                        'work_schedule_configs.scope_id tidak menunjuk ke employees yang valid.'
                        USING ERRCODE = '23503';

                ELSIF NEW.scope_type NOT IN (
                    'COMPANY',
                    'DIVISION',
                    'EMPLOYEE'
                ) THEN
                    RAISE EXCEPTION
                        'work_schedule_configs.scope_type tidak valid.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION pride_prevent_referenced_division_delete()
            RETURNS trigger AS $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM work_schedule_configs
                    WHERE scope_type = 'DIVISION'
                      AND scope_id = OLD.id
                ) THEN
                    RAISE EXCEPTION
                        'Divisi masih digunakan oleh konfigurasi jadwal; nonaktifkan divisi, jangan hapus.'
                        USING ERRCODE = '55000';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER divisions_referenced_by_schedule_trigger
            BEFORE DELETE ON divisions
            FOR EACH ROW
            EXECUTE FUNCTION pride_prevent_referenced_division_delete();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /* Validasi sebelum rollback
            Rollback gk diizinkan kalo data employee ID atau kantor udah dipake */

        if (
            DB::table('employee_code_histories')->exists()
            || DB::table('employees')->whereNotNull('employee_code')->exists()
            || DB::table('employees')->whereNotNull('office_id')->exists()
        ) {
            throw new RuntimeException(
                'Rollback dibatalkan: Employee ID, histori, atau data kantor sudah digunakan.'
            );
        }

        /* Hapus trigger ke tabel divisions baru */

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS divisions_referenced_by_schedule_trigger
            ON divisions;
        SQL);

        /* Kembalikan nama foreign key */

        DB::unprepared(<<<'SQL'
            ALTER TABLE employees
                RENAME CONSTRAINT employee_division_fk
                TO employee_department_fk;

            ALTER TABLE employees
                RENAME CONSTRAINT employee_unit_fk
                TO employee_division_fk;

            ALTER TABLE units
                RENAME CONSTRAINT unit_head_employee_fk
                TO division_head_employee_fk;

            ALTER TABLE units
                RENAME CONSTRAINT unit_division_fk
                TO division_department_fk;

            ALTER TABLE divisions
                RENAME CONSTRAINT division_head_employee_fk
                TO department_head_employee_fk;
        SQL);

        /* Hapus histori Employee ID */

        Schema::dropIfExists('employee_code_histories');

        /* Hapus constraint Employee ID */

        DB::statement(
            'ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_employee_code_format_chk'
        );

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_code_unique');
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn('employee_code');
        });

        Schema::dropIfExists('offices');

        /* Hapus digit Employee ID dari units */

        DB::statement(
            'ALTER TABLE units DROP CONSTRAINT IF EXISTS units_employee_code_digit_chk'
        );

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_division_employee_digit_unique');
            $table->dropColumn('employee_code_digit');
        });

        /* Kembalikan nama index */

        DB::unprepared(<<<'SQL'
            ALTER INDEX employees_division_status_idx
                RENAME TO employees_department_status_idx;

            ALTER INDEX employees_unit_status_idx
                RENAME TO employees_division_status_idx;

            ALTER INDEX employees_division_id_index
                RENAME TO employees_department_id_index;

            ALTER INDEX employees_unit_id_index
                RENAME TO employees_division_id_index;

            ALTER INDEX units_division_id_index
                RENAME TO divisions_department_id_index;
        SQL);

        /* Kembalikan nama kolom employees */

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
            $table->renameColumn('unit_id', 'division_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->renameColumn('division_id', 'department_id');
        });

        /* Kembalikan nama tabel organisasi */

        Schema::rename('divisions', 'departments');
        Schema::rename('units', 'divisions');

        /* Pasang kembali trigger pada tabel divisions lama */

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER divisions_referenced_by_schedule_trigger
            BEFORE DELETE ON divisions
            FOR EACH ROW
            EXECUTE FUNCTION pride_prevent_referenced_division_delete();
        SQL);
    }
};

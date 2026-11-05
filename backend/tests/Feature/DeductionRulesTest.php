<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\DeductionCalculationHistory;
use App\Models\DeductionRecord;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeductionService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriks aturan deduction resmi (PRD 11.6) + pembulatan akhir Rp1.000 (12.2).
 */
class DeductionRulesTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('DEDUCTION_TOLERANCE_EVENTS', 2, Setting::TYPE_NUMBER);
        Setting::set('DEDUCTION_RATE_PER_EVENT', 2, Setting::TYPE_NUMBER);
        Setting::set('MAX_DEDUCTION_PERCENTAGE', 100, Setting::TYPE_NUMBER);
        Setting::set('ALPHA_DEDUCTION_RATE_PERCENT', 4, Setting::TYPE_NUMBER);
        Setting::set('PPH_ENABLED', 0, Setting::TYPE_BOOLEAN);
        Setting::set('DEDUCTION_CUTOFF_DAY', 0, Setting::TYPE_NUMBER);

        $this->employee = Employee::create([
            'nip' => 'DED-001', 'full_name' => 'Uji Deduction',
            'email' => 'ded@example.test', 'join_date' => '2020-01-01',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
    }

    /** Buat N record dengan satu jenis pelanggaran. */
    protected function violations(string $type, int $count, int $startDay = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $day = str_pad((string) ($startDay + $i), 2, '0', STR_PAD_LEFT);
            AttendanceRecord::create([
                'employee_id' => $this->employee->id,
                'work_date' => "2026-10-{$day}",
                'status' => $type === AttendanceRecord::EVENT_ABSENT
                    ? AttendanceRecord::STATUS_ABSENT
                    : AttendanceRecord::STATUS_LATE,
                'violation_types' => $type,
            ]);
        }
    }

    protected function percentage(): float
    {
        return (float) app(DeductionService::class)
            ->calculateForEmployee($this->employee->id, '2026-10')
            ->total_percentage;
    }

    public function test_dua_keterlambatan_nol_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 2);
        $this->assertSame(0.0, $this->percentage());
    }

    public function test_tiga_keterlambatan_dua_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 3);
        $this->assertSame(2.0, $this->percentage());
    }

    public function test_lima_keterlambatan_enam_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 5);
        $this->assertSame(6.0, $this->percentage());
    }

    public function test_satu_alpha_empat_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_ABSENT, 1);
        $this->assertSame(4.0, $this->percentage());
    }

    public function test_tiga_alpha_duabelas_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_ABSENT, 3);
        $this->assertSame(12.0, $this->percentage());
    }

    public function test_lima_alpha_duapuluh_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_ABSENT, 5);
        $this->assertSame(20.0, $this->percentage());
    }

    public function test_lima_telat_dan_satu_alpha_sepuluh_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 5);
        $this->violations(AttendanceRecord::EVENT_ABSENT, 1, 10);
        $this->assertSame(10.0, $this->percentage());
    }

    public function test_dua_telat_dan_dua_alpha_delapan_persen(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 2);
        $this->violations(AttendanceRecord::EVENT_ABSENT, 2, 10);
        $this->assertSame(8.0, $this->percentage());
    }

    public function test_pulang_cepat_tidak_menambah_persentase_dan_nominal(): void
    {
        $this->violations(AttendanceRecord::EVENT_EARLY_DEPARTURE, 3);

        $service = app(DeductionService::class);
        $record = $service->calculateForEmployee($this->employee->id, '2026-10');

        $this->assertSame(0.0, (float) $record->total_percentage);
        $this->assertSame(3, (int) $record->early_departure_events);
        $this->assertSame(0, $record->events()->count());
        $this->assertSame('0.00', (string) $record->deduction_amount);

        $record = $service->inputBaseAmount($record->id, 5_000_000);
        $this->assertSame('0.00', (string) $record->deduction_amount);
        $this->assertSame('5000000.00', (string) $record->final_amount);
    }

    public function test_pulang_cepat_tidak_memakan_toleransi_keterlambatan(): void
    {
        $this->violations(AttendanceRecord::EVENT_EARLY_DEPARTURE, 3, 1);
        $this->violations(AttendanceRecord::EVENT_LATE, 2, 10);

        $this->assertSame(0.0, $this->percentage()); // 2 late masih ditoleransi
    }

    public function test_belum_lengkap_tidak_otomatis_menjadi_pelanggaran(): void
    {
        AttendanceRecord::create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-05',
            'status' => AttendanceRecord::STATUS_INCOMPLETE,
            'is_incomplete' => true,
            'violation_types' => null,
        ]);

        $this->assertSame(0.0, $this->percentage());
    }

    public function test_perhitungan_ulang_idempoten(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 5);
        $service = app(DeductionService::class);

        $a = $service->calculateForEmployee($this->employee->id, '2026-10');
        $b = $service->calculateForEmployee($this->employee->id, '2026-10');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, DeductionRecord::count());
        $this->assertSame(5, $b->events()->count());
    }


public function test_final_tidak_bisa_diedit_langsung(): void
{
    $this->violations(AttendanceRecord::EVENT_ABSENT, 1);

    $service = app(DeductionService::class);

    $record = $service->calculateForEmployee(
        $this->employee->id,
        '2026-10'
    );

    $service->inputBaseAmount($record->id, 5_000_000);

    // Buat user HRD sungguhan agar foreign key valid.
    $hrd = $this->hrd();

    $service->confirm($record->id, $hrd->id);

    $this->expectExceptionCode(409);

    $service->inputBaseAmount($record->id, 6_000_000);
}


    // ---------------------------------------------------------------
    // Pembulatan nominal akhir (keputusan bisnis terbaru)
    // ---------------------------------------------------------------

    #[DataProvider('roundingProvider')]
    public function test_pembulatan_nominal_akhir(string $raw, string $expected): void
    {
        $this->assertSame($expected, Money::applyFinalRounding($raw)['final_amount']);
    }

    public static function roundingProvider(): array
    {
        return [
            ['4700000', '4700000.00'],
            ['4700001', '4701000.00'],
            ['4700100', '4701000.00'],
            ['4700500', '4701000.00'],
            ['4700999', '4701000.00'],
            ['4701000', '4701000.00'],
            ['0', '0.00'],
            ['-500', '-500.00'],
        ];
    }

    public function test_nominal_akhir_nol_atau_negatif_tidak_dibulatkan_ke_atas(): void
    {
        $this->assertSame('0.00', Money::applyFinalRounding('0')['final_amount']);
        $this->assertSame('0.00', Money::applyFinalRounding('0')['rounding_adjustment']);
        $this->assertSame('-2500.00', Money::applyFinalRounding('-2500')['final_amount']);
    }

    public function test_komponen_nominal_dapat_diaudit(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 5); // 6%
        $service = app(DeductionService::class);
        $record = $service->calculateForEmployee($this->employee->id, '2026-10');
        $record = $service->inputBaseAmount($record->id, 5_000_001);

        // D = 300.000 (rupiah terdekat), raw = 4.700.001 -> final 4.701.000
        $this->assertSame('6.00', (string) $record->total_percentage);
        $this->assertSame('300000.00', (string) $record->deduction_amount);
        $this->assertSame('4700001.00', (string) $record->raw_final_amount);
        $this->assertSame('4701000.00', (string) $record->final_amount);
        $this->assertSame('999.00', (string) $record->rounding_adjustment);

        $this->assertGreaterThan(0, DeductionCalculationHistory::count());
    }

    public function test_contoh_prd_lengkap(): void
    {
        $this->violations(AttendanceRecord::EVENT_LATE, 5);
        $this->violations(AttendanceRecord::EVENT_ABSENT, 1, 10);
        $this->violations(AttendanceRecord::EVENT_EARLY_DEPARTURE, 1, 15);

        $service = app(DeductionService::class);
        $record = $service->calculateForEmployee($this->employee->id, '2026-10');
        $record = $service->inputBaseAmount($record->id, 5_000_000);

        $this->assertSame('10.00', (string) $record->total_percentage);
        $this->assertSame('500000.00', (string) $record->deduction_amount);
        $this->assertSame('4500000.00', (string) $record->raw_final_amount);
        $this->assertSame('4500000.00', (string) $record->final_amount);
        $this->assertSame(0, $record->events()->where('event_type', 'EARLY_DEPARTURE')->count());
    }

    // ---------------------------------------------------------------
    // Kontrak endpoint (status code)
    // ---------------------------------------------------------------

    protected function hrd(): User
    {
        $role = Role::create(['name' => Role::HRD, 'scope' => 'GLOBAL', 'is_system' => true]);
        $user = User::create([
            'name' => 'HRD', 'email' => 'hrd@example.test',
            'password' => Hash::make('hrdpass123'), 'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    protected function kadiv(): User
    {
        $role = Role::create(['name' => Role::KADIV, 'scope' => 'DIVISION', 'is_system' => true]);
        $user = User::create([
            'name' => 'KADIV', 'email' => 'kadiv@example.test',
            'password' => Hash::make('kadivpass123'), 'is_active' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_konfirmasi_sebelum_cutoff_409_dan_nominal_kosong_422(): void
    {
        $hrd = $this->hrd();
        $record = DeductionRecord::create([
            'employee_id' => $this->employee->id,
            'period' => '2099-12',
            'status' => DeductionRecord::STATUS_CALCULATED,
        ]);

        $this->actingAs($hrd, 'sanctum')
            ->postJson("/api/v1/deductions/{$record->id}/confirm", [])
            ->assertStatus(422);

        $record->update(['base_amount' => 5_000_000]);

        $this->actingAs($hrd, 'sanctum')
            ->postJson("/api/v1/deductions/{$record->id}/confirm", [])
            ->assertStatus(409);
    }


    public function test_hrd_confirmation_sets_status_final_without_client_finalize_flag(): void
    {
        $hrd = $this->hrd();
        $this->violations(AttendanceRecord::EVENT_LATE, 3);
        $record = app(DeductionService::class)->calculateForEmployee($this->employee->id, '2026-09');
        app(DeductionService::class)->inputBaseAmount($record->id, '5000000.00');

        $this->actingAs($hrd, 'sanctum')
            ->postJson("/api/v1/deductions/{$record->id}/confirm", ['note' => 'Sudah diverifikasi'])
            ->assertOk()
            ->assertJsonPath('data.status', DeductionRecord::STATUS_FINAL);

        $this->assertSame(DeductionRecord::STATUS_FINAL, $record->fresh()->status);
    }

    public function test_kadiv_tidak_bisa_konfirmasi_403_dan_data_tidak_ditemukan_404(): void
    {
        $kadiv = $this->kadiv();

        $this->actingAs($kadiv, 'sanctum')
            ->postJson('/api/v1/deductions/999/confirm', [])
            ->assertStatus(403);

        $this->actingAs($this->hrd(), 'sanctum')
            ->postJson('/api/v1/deductions/999/confirm', [])
            ->assertStatus(404);
    }

    public function test_endpoint_post_nominal_tersedia(): void
    {
        $this->violations(AttendanceRecord::EVENT_ABSENT, 1);
        $record = app(DeductionService::class)->calculateForEmployee($this->employee->id, '2026-10');
        $hrd = $this->hrd();

        $this->actingAs($hrd, 'sanctum')
            ->postJson("/api/v1/deductions/{$record->id}/nominal", ['base_amount' => 5_000_000])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('5000000.00', (string) $record->fresh()->base_amount);
    }
}

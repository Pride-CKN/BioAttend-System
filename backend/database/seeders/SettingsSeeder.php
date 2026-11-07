<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/*
  Parameter bisnis global
 
  Yang paling penting — ATURAN ALPHA (permintaan eksplisit):
    ALPHA_DEDUCTION_RATE_PERCENT = 4   -> alpha dipotong 4% per kejadian
    ALPHA_IGNORE_TOLERANCE       = 1   -> langsung kena sejak kejadian pertama,
                                         TIDAK memakai jatah toleransi
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ---------------- Potongan (PRD Bagian 11) ----------------
            'DEDUCTION_TOLERANCE_EVENTS' => [
                2,
                Setting::TYPE_NUMBER,
                'Jumlah kejadian pelanggaran yang masih ditoleransi (tidak dipotong).',
            ],
            'DEDUCTION_RATE_PER_EVENT' => [
                2,
                Setting::TYPE_NUMBER,
                'Persentase potongan per kejadian pelanggaran yang melewati toleransi (%).',
            ],
            'MAX_DEDUCTION_PERCENTAGE' => [
                100,
                Setting::TYPE_NUMBER,
                'Batas maksimum total persentase potongan (%).',
            ],
            'DEDUCTION_CUTOFF_DAY' => [
                1,
                Setting::TYPE_NUMBER,
                'Tanggal mulai finalisasi potongan periode sebelumnya pada bulan berikutnya.',
            ],

            // ---------------- ATURAN ALPHA ---------------------------
            'ALPHA_DEDUCTION_RATE_PERCENT' => [
                4,
                Setting::TYPE_NUMBER,
                'Potongan alpha (tidak masuk tanpa keterangan) per kejadian (%). Berlaku LANGSUNG sejak kejadian pertama.',
            ],
            'ALPHA_IGNORE_TOLERANCE' => [
                1,
                Setting::TYPE_BOOLEAN,
                '1 = alpha tidak mendapat jatah toleransi, langsung dipotong sejak kejadian pertama.',
            ],

            // ---------------- Absensi --------------------------------
            'DUPLICATE_WINDOW_MINUTES' => [
                60,
                Setting::TYPE_NUMBER,
                'Jendela waktu (menit) untuk menganggap dua pemindaian sebagai duplikat (PRD 9.2).',
            ],

            // ---------------- Modul PPh ---------------------------
            'PPH_ENABLED' => [
                0,
                Setting::TYPE_BOOLEAN,
                'Aktifkan perhitungan PPh. Jika 0, potongan PPh selalu 0.',
            ],

            // ---------------- Perusahaan -----------------------------
            'COMPANY_NAME' => ['PT Pride Chicken', Setting::TYPE_STRING, 'Nama perusahaan.'],
            'COMPANY_TIMEZONE' => ['Asia/Jakarta', Setting::TYPE_STRING, 'Zona waktu operasional (WIB).'],
        ];

        foreach ($settings as $key => [$value, $type, $description]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'description' => $description]
            );
        }

        $this->command?->info('Pengaturan siap (termasuk aturan alpha 4% tanpa toleransi).');
    }
}

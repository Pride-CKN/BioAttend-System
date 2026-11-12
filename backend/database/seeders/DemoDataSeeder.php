<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Office;
use App\Models\Position;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleConfig;
use App\Services\AttendanceProcessor;
use App\Services\EmployeeCodeGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/* Data contoh agar API bisa langsung dicoba.

  Berisi: struktur organisasi, shift, hari libur, jenis cuti, perangkat,
  12 karyawan + akun (HRD / KADIV / EMPLOYEE), dan 60 hari log absensi
  yang sudah diproses menjadi attendance_records.

  Jalankan: php artisan db:seed --class=DemoDataSeeder */
class DemoDataSeeder extends Seeder
{
    protected const PASSWORD = 'password123';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException(
                'DemoDataSeeder hanya boleh dijalankan pada environment local/testing karena menggunakan kredensial demo. Gunakan proses provisioning akun HRD yang aman.'
            );
        }

        mt_srand(20260101);   // data demo bisa direproduksi

        $this->seedOrganization();
        $shifts = $this->seedShifts();
        $this->seedWorkScheduleConfig($shifts['PAGI']);
        $this->seedHolidays();
        $leaveTypes = $this->seedLeaveTypes();
        $device = $this->seedDevice();
        $employees = $this->seedEmployees();
        $this->seedUsers($employees);

        // Penyetuju untuk data demo (akun HRD / KADIV pertama)
        $approverId = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::HRD))
            ->value('id') ?? User::query()->value('id');
        $this->seedLeaveBalances($employees, $leaveTypes);
        $this->seedAttendance($employees, $shifts, $device, $leaveTypes, $approverId);
    }

    // Struktur organisasi

    protected function seedOrganization(): void
    {
        // Gunakan master organisasi baru:
        // Office -> Division -> Unit
        $this->call(OrganizationSeeder::class);

        // Posisi/jabatan demo.
        foreach ([
            ['STF', 'Staff', 'STAFF'],
            ['MGR', 'Manager', 'STAFF'],
            ['KDV', 'Kepala Divisi', 'MANAGER'],
            ['DIR', 'Direktur', 'DIREKTUR'],
        ] as [$code, $name, $level]) {
            Position::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'level' => $level,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Master organisasi dan posisi demo siap.');
    }

    // Shift
    protected function seedShifts(): array
    {
        $definitions = [
            'PAGI' => ['Shift Pagi', '08:00:00', '17:00:00', false],
            'SIANG' => ['Shift Siang', '13:00:00', '21:00:00', false],
            'MALAM' => ['Shift Malam', '22:00:00', '06:00:00', true],
        ];

        $shifts = [];

        foreach ($definitions as $code => [$name, $start, $end, $night]) {
            $shifts[$code] = Shift::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'late_tolerance_minutes' => 15,
                    'min_work_hours' => 8,
                    'is_night_shift' => $night,
                    'is_active' => true,
                ]
            );
        }

        return $shifts;
    }

    protected function seedWorkScheduleConfig(Shift $defaultShift): void
    {
        WorkScheduleConfig::query()->firstOrCreate(
            ['scope_type' => WorkScheduleConfig::SCOPE_COMPANY, 'scope_id' => null],
            [
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
                'shift_id' => $defaultShift->id,
                'work_days' => [1, 2, 3, 4, 5],
                'late_tolerance_minutes' => 15,
                'is_active' => true,
            ]
        );
    }

    protected function seedHolidays(): void
    {
        $year = (int) now()->format('Y');

        $holidays = [
            ['01-01', 'Tahun Baru Masehi', 'NATIONAL'],
            ['03-31', 'Hari Raya Nyepi', 'NATIONAL'],
            ['05-01', 'Hari Buruh Internasional', 'NATIONAL'],
            ['06-01', 'Hari Lahir Pancasila', 'NATIONAL'],
            ['08-17', 'Hari Kemerdekaan RI', 'NATIONAL'],
            ['12-25', 'Hari Raya Natal', 'NATIONAL'],
        ];

        foreach ($holidays as [$md, $name, $type]) {
            Holiday::query()->firstOrCreate(
                ['date' => $year.'-'.$md],
                ['name' => $name, 'type' => $type, 'is_paid' => true, 'year' => $year]
            );
        }
    }

    protected function seedLeaveTypes(): array
    {
        $definitions = [
            ['ANNUAL', 'Cuti Tahunan', 12, 12, false],
            ['SICK', 'Cuti Sakit', 12, 3, true],
            ['IMPORTANT', 'Cuti Alasan Penting', 5, 5, true],
            ['MATERNITY', 'Cuti Melahirkan', 90, 90, true],
        ];

        $types = [];

        foreach ($definitions as [$code, $name, $quota, $max, $attachment]) {
            $types[$code] = LeaveType::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'default_quota' => $quota,
                    'max_days_per_request' => $max,
                    'requires_attachment' => $attachment,
                    'is_active' => true,
                ]
            );
        }

        return $types;
    }

    protected function seedDevice(): Device
    {
        return Device::query()->firstOrCreate(
            ['ip_address' => '192.168.110.137'],
            [
                'name' => 'Fingerprint Utama',
                'model' => 'MB10-VL',
                'port' => 4370,
                'commkey_encrypted' => env('ZKTECO_DEMO_COMMKEY'),
                'location' => 'Office',
                'status' => DeviceStatus::Online,
                'is_active' => true,
            ]
        );
    }

    // Karyawan

    protected function seedEmployees(): array
    {
        // Seluruh data demo ditempatkan pada Kantor Singgasana.
        $office = Office::query()
            ->where('code', '1')
            ->firstOrFail();

        $generator = app(EmployeeCodeGeneratorService::class);

        /*
         * Format:
         * [NIP, nama, email, kode divisi, kode unit, kode posisi, role]
         *
         * Divisi:
         * IND = Industrial
         * KOM = Komersial
         * PNG = Pengadaan
         * KEU = Keuangan
         * OPR = Operasional
         * TKG = Tata Kelola
         * SDM = SDM
         */
        $people = [
            ['10000000', 'Muhammad Irfan Naufaldi', 'muhammad.naufaldi@pride.co.id', 'DIR', null, 'DIR', Role::HRD],
            ['11000000', 'Rivaldo Gosman', 'rivaldo.gosman@pride.co.id', 'SDM', null, 'KDV', Role::HRD],
            ['12000000', 'Salma Annisa Luthfiyyah', 'salma.luthfiyyah@pride.co.id', 'TKG', null, 'KDV', Role::KADIV],
            ['12000001', 'Dony Ramdhani', 'dony.ramdhani@pride.co.id', 'TKG', null, 'STF', Role::EMPLOYEE],
            ['13000000', 'Muhammad Rizal Fadhilla', 'muhammad.fadhilla@pride.co.id', 'KEU', null, 'KDV', Role::KADIV],
            ['13200001', 'Nurmayanti Tresna Dewi', 'nurmayanti.dewi@pride.co.id', 'KEU', 'AKT', 'STF', Role::EMPLOYEE],
            ['13200002', 'Desty Dwi Fitrian', 'desty.fitrian@pride.co.id', 'KEU', 'AKT', 'STF', Role::EMPLOYEE],
            ['13200003', 'Dinda Lestari', 'dinda.lestari@pride.co.id', 'KEU', 'AKT', 'STF', Role::EMPLOYEE],
            ['14000000', 'Hadin Hadaina', 'hadin.hadaina@pride.co.id', 'PNG', null, 'KDV', Role::KADIV],
            ['14100001', 'Novi Aryanti', 'novi.aryanti@pride.co.id', 'PNG', 'AST', 'MGR', Role::EMPLOYEE],
            ['14200002', 'Putri Azmi', 'putri.azmi@pride.co.id', 'PNG', 'PEM', 'MGR', Role::EMPLOYEE],
            ['15000000', 'Muhammad Ghani Permana', 'muhammad.permana@pride.co.id', 'IND', null, 'KDV', Role::KADIV],
            ['15100001', 'Nashrul Haq', 'nashrul.haq@pride.co.id', 'IND', 'PROD', 'MGR', Role::EMPLOYEE],
            ['15200002', 'Muhamad Rais Kurniawan', 'muhamad.kurniawan@pride.co.id', 'IND', 'GUD', 'STF', Role::EMPLOYEE],
            ['16000000', 'Rio Ferdiansyah Putra', 'rio.putra@pride.co.id', 'OPR', null, 'KDV', Role::KADIV],
            ['16100001', 'Nadya Ulfa Rahmani Setiawan', 'nadya.setiawan@pride.co.id', 'OPR', 'REG', 'MGR', Role::EMPLOYEE],
            ['16200002', 'Vieri Ramdani', 'vieri.ramdani@pride.co.id', 'OPR', 'GER', 'MGR', Role::EMPLOYEE],
            ['16200003', 'Abdi Sanjaya', 'abdi.sanjaya@pride.co.id', 'OPR', 'GER', 'STF', Role::EMPLOYEE],
            ['17000000', 'Rafli Naufal Malik', 'rafli.malik@pride.co.id', 'KOM', null, 'KDV', Role::KADIV],
            ['17200001', 'Gusti Akbar Maulana', 'gusti.maulana@pride.co.id', 'KOM', 'BRD', 'MGR', Role::EMPLOYEE],
            ['17300002', 'Michael Juliano', 'michael.juliano@pride.co.id', 'KOM', 'KEM', 'MGR', Role::EMPLOYEE],
            ['17100003', 'Sahal Surya Winata', 'sahal.winata@pride.co.id', 'KOM', 'MKT', 'STF', Role::EMPLOYEE],
            ['17100004', 'Suhel Surya Winata', 'suhel.winata@pride.co.id', 'KOM', 'MKT', 'STF', Role::EMPLOYEE],
            ['17300005', 'Dani Apriyandi', 'dani.apriyandi@pride.co.id', 'KOM', 'KEM', 'STF', Role::EMPLOYEE],
            ['17300006', 'Boby Ramdani', 'boby.ramdani@pride.co.id', 'KOM', 'KEM', 'STF', Role::EMPLOYEE],

            ['25100001', 'Deri Mulyana', 'deri.mulyana@pride.co.id', 'IND', 'PROD', 'MGR', Role::EMPLOYEE],
            ['25100002', 'Erlangga Pringgajaya', 'erlangga.pringgajaya@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100003', 'Riki Ruswandi', 'riki.ruswandi@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100004', 'Reedho Muhammad Hijjaryato Muttaqien', 'reedho.muttaqien@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100005', 'Faiz Rabbani', 'faiz.rabbani@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100006', 'Fauzan Fadhlurrohman Santosa', 'fauzan.santosa@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100007', 'Rafi Faturrohman', 'rafi.faturrohman@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100008', 'Ruben Septian', 'ruben.septian@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100009', 'Andri Maulana Iskandar', 'andri.iskandar@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100010', 'Adi Mudrikah', 'adi.mudrikah@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100011', 'Anggi Lesmana', 'anggi.lesmana@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100012', 'Samsudin', 'samsudin@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100013', 'Faisal Hasbi Ashidiqqi', 'faisal.ashidiqqi@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100014', 'Rangga Muharram', 'rangga.muharram@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],
            ['25100015', 'Yoga Aryo Pratama', 'yoga.pratama@pride.co.id', 'IND', 'PROD', 'STF', Role::EMPLOYEE],

            ['35200001', 'Akhmad Nursyhan Sholeh', 'akhmad.sholeh@pride.co.id', 'IND', 'GUD', 'MGR', Role::EMPLOYEE],
            ['35200005', 'Aldy Permadi', 'aldy.permadi@pride.co.id', 'IND', 'GUD', 'STF', Role::EMPLOYEE],
            ['35200006', 'Rasya', 'rasya@pride.co.id', 'IND', 'GUD', 'STF', Role::EMPLOYEE],

            ['45200003', 'Alif Muhamad Gifari', 'alif.gifari@pride.co.id', 'IND', 'GUD', 'STF', Role::EMPLOYEE],
            ['45200004', 'Teguh Akil Maliki', 'teguh.maliki@pride.co.id', 'IND', 'GUD', 'STF', Role::EMPLOYEE],
        ];

        $employees = [];

        foreach ($people as [
            $nip,
            $name,
            $email,
            $divisionCode,
            $unitCode,
            $positionCode,
            $role,
        ]) {
            $division = Division::query()
                ->where('code', $divisionCode)
                ->firstOrFail();

            $unit = $unitCode !== null
                ? Unit::query()
                    ->where('division_id', $division->id)
                    ->where('code', $unitCode)
                    ->firstOrFail()
                : null;

            $position = Position::query()
                ->where('code', $positionCode)
                ->firstOrFail();

            $employee = Employee::query()->firstOrCreate(
                ['nip' => $nip],
                [
                    'full_name' => $name,
                    'email' => $email,
                    'device_user_identifier' => $nip,
                    'phone' => '0812'.mt_rand(10000000, 99999999),
                    'gender' => mt_rand(0, 1) ? 'MALE' : 'FEMALE',
                    'join_date' => now()
                        ->subYears(mt_rand(1, 6))
                        ->subDays(mt_rand(0, 300))
                        ->toDateString(),
                    'employment_status' => Employee::STATUS_ACTIVE,
                    'position_id' => $position->id,
                ]
            );

            /* Employee ID tidak boleh diam-diam diganti saat seeding ulang.
              Jika sudah punya kode, penempatan harus tetap sama */
            if ($employee->employee_code !== null) {
                $samePlacement =
                    (int) $employee->office_id === (int) $office->id
                    && (int) $employee->division_id === (int) $division->id
                    && (int) $employee->unit_id === (int) ($unit?->id ?? 0);

                if (! $samePlacement) {
                    throw new \RuntimeException(
                        "Penempatan karyawan {$employee->nip} berbeda dari konfigurasi demo. "
                        .'Perubahan Employee ID harus dilakukan melalui proses perubahan penempatan resmi.'
                    );
                }
            } else {
                /* Pembuatan Employee ID awal beserta histori
                  Tanggal efektif menggunakan tanggal seeding */
                $employee = $generator->assignOrChange(
                    employee: $employee,
                    officeId: (int) $office->id,
                    divisionId: (int) $division->id,
                    unitId: $unit?->id,
                    reason: 'Pembuatan Employee ID awal melalui DemoDataSeeder',
                    createdBy: null,
                    effectiveFrom: now()->toDateString(),
                );
            }

            $employees[] = [
                'employee' => $employee,
                'role' => $role,
            ];
        }

        $this->command?->info(
            'Semua data karyawan Sekarang(NOV 2026) demo berhasil diperiksa/dibuat.'
        );

        return $employees;
    }

    protected function seedUsers(array $employees): void
    {
        foreach ($employees as $entry) {
            /** @var Employee $employee */
            $employee = $entry['employee'];

            $role = Role::query()
                ->where('name', $entry['role'])
                ->firstOrFail();

            DB::transaction(function () use ($employee, $role): void {
                $user = User::query()->firstOrCreate(
                    ['email' => $employee->email],
                    [
                        'employee_id' => $employee->id,
                        'name' => $employee->full_name,
                        'password' => Hash::make(self::PASSWORD),
                        'is_active' => true,
                        'must_change_password' => true,
                    ]
                );

                // Pastikan akun tidak terhubung ke karyawan yang berbeda
                if ((int) $user->employee_id !== (int) $employee->id) {
                    throw new \RuntimeException(
                        "Akun {$user->email} sudah terhubung dengan karyawan lain."
                    );
                }

                // Pastikan tepat satu role terpasang
                // Role lama diganti, bukan ditambahkan
                $user->roles()->sync([$role->id]);
            }, 3);
        }

        $this->command?->info(
            'Semua akun demo berhasil diperiksa/dibuat dengan tepat satu role.'
        );
    }

    protected function seedLeaveBalances(array $employees, array $leaveTypes): void
    {
        $year = (int) now()->format('Y');

        foreach ($employees as $entry) {
            foreach ($leaveTypes as $type) {
                $quota = (int) $type->default_quota;

                LeaveBalance::query()->firstOrCreate(
                    [
                        'employee_id' => $entry['employee']->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    [
                        'quota' => $quota,
                        'used' => 0,
                        'remaining' => $quota,
                    ]
                );
            }
        }
    }

    // 60 hari log absensi
    protected function seedAttendance(
        array $employees,
        array $shifts,
        Device $device,
        array $leaveTypes,
        int $approverId
    ): void {
        if (AttendanceLog::query()->exists()) {
            $this->command?->warn('Log absensi sudah ada — seeding absensi dilewati.');

            return;
        }

        $processor = app(AttendanceProcessor::class);
        $start = now()->subDays(60)->startOfDay();

        foreach ($employees as $entry) {
            /** @var Employee $employee */
            $employee = $entry['employee'];

            for ($i = 0; $i <= 60; $i++) {
                $date = $start->copy()->addDays($i);

                // Lewati akhir pekan & hari libur
                if ($date->isWeekend() || Holiday::query()
                    ->whereDate('date', $date->toDateString())
                    ->where('is_active', true)
                    ->exists()) {
                    continue;
                }

                $roll = mt_rand(1, 100);

                // 8% alpha (tidak masuk tanpa keterangan) -> langsung 4%
                if ($roll <= 8) {
                    continue;
                }

                // 6% cuti yang sudah disetujui -> tidak jadi alpha
                if ($roll <= 14) {
                    LeaveRequest::query()->firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'leave_type_id' => $leaveTypes['ANNUAL']->id,
                            'start_date' => $date->toDateString(),
                        ],
                        [
                            'end_date' => $date->toDateString(),
                            'total_days' => 1,
                            'reason' => 'Keperluan pribadi',
                            'status' => LeaveRequest::STATUS_APPROVED,
                            'kadiv_approved_by' => $approverId,
                            'kadiv_approved_at' => $date->copy()->subDay(),
                            'hrd_approved_by' => $approverId,
                            'hrd_approved_at' => $date->copy()->subDay(),
                        ]
                    );

                    continue;
                }

                $shift = $shifts['PAGI'];

                // 12% terlambat
                $lateMinutes = $roll <= 26 ? mt_rand(20, 90) : mt_rand(0, 8);

                $checkIn = $date->copy()->setTime(8, 0)->addMinutes($lateMinutes)->addSeconds(mt_rand(0, 59));

                // 10% pulang cepat
                $earlyMinutes = $roll >= 90 ? mt_rand(30, 120) : 0;
                $checkOut = $date->copy()->setTime(17, 0)->subMinutes($earlyMinutes)->addSeconds(mt_rand(0, 59));

                // 3% tidak lengkap (lupa check-out)
                $skipCheckOut = $roll >= 97;

                // CHECK-IN
                $checkInPayload = [
                    'source' => 'seed',
                    'employee_code' => $employee->employee_code,
                ];

                $checkInDeviceLogId = sprintf(
                    'seed-%d-%s-in',
                    $employee->id,
                    $date->format('Ymd')
                );

                $checkInDeviceUserIdentifier = $employee->device_user_identifier;

                AttendanceLog::query()->create([
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'device_user_identifier' => $checkInDeviceUserIdentifier,
                    'device_log_id' => $checkInDeviceLogId,
                    'fingerprint' => AttendanceLog::fingerprintFor(
                        $device->id,
                        $checkInDeviceLogId,
                        $checkInDeviceUserIdentifier,
                        $checkIn->toIso8601String(),
                        $checkInPayload
                    ),
                    'scanned_at' => $checkIn,
                    'scan_type' => null,
                    'raw_payload' => $checkInPayload,
                    'processing_status' => AttendanceLog::STATUS_PENDING,
                ]);

                // CHECK-OUT
                if (! $skipCheckOut) {
                    $checkOutPayload = [
                        'source' => 'seed',
                        'employee_code' => $employee->employee_code,
                    ];

                    $checkOutDeviceLogId = sprintf(
                        'seed-%d-%s-out',
                        $employee->id,
                        $date->format('Ymd')
                    );

                    $checkOutDeviceUserIdentifier = $employee->device_user_identifier;

                    AttendanceLog::query()->create([
                        'device_id' => $device->id,
                        'employee_id' => $employee->id,
                        'device_user_identifier' => $checkOutDeviceUserIdentifier,
                        'device_log_id' => $checkOutDeviceLogId,
                        'fingerprint' => AttendanceLog::fingerprintFor(
                            $device->id,
                            $checkOutDeviceLogId,
                            $checkOutDeviceUserIdentifier,
                            $checkOut->toIso8601String(),
                            $checkOutPayload
                        ),
                        'scanned_at' => $checkOut,
                        'scan_type' => null,
                        'raw_payload' => $checkOutPayload,
                        'processing_status' => AttendanceLog::STATUS_PENDING,
                    ]);
                }
            }
        }

        // Proses hanya hari kerja menjadi attendance_records
        $processed = 0;

        for ($i = 0; $i <= 60; $i++) {
            $day = $start->copy()->addDays($i);

            if ($day->isWeekend() || Holiday::query()
                ->whereDate('date', $day->toDateString())
                ->where('is_active', true)
                ->exists()) {
                continue;
            }

            $processor->processDateForAll($day->toDateString());
            $processed++;
        }

        $this->command?->info("Log absensi 60 hari dibuat dan diproses ({$processed} tanggal).");
    }
}

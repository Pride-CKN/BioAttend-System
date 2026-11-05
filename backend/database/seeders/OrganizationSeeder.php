<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $definitions = [
                [
                    'code' => 'IND',
                    'name' => 'Industrial',
                    'units' => [
                        ['code' => 'PROD', 'name' => 'Produksi', 'digit' => 1],
                        ['code' => 'GUD', 'name' => 'Pergudangan', 'digit' => 2],
                    ],
                ],
                [
                    'code' => 'KOM',
                    'name' => 'Komersial',
                    'units' => [
                        ['code' => 'MKT', 'name' => 'Marketing', 'digit' => 1],
                        ['code' => 'BRD', 'name' => 'Branding', 'digit' => 2],
                    ],
                ],
                [
                    'code' => 'PNG',
                    'name' => 'Pengadaan',
                    'units' => [
                        ['code' => 'AST', 'name' => 'Aset', 'digit' => 1],
                        ['code' => 'PEM', 'name' => 'Pembelian', 'digit' => 2],
                    ],
                ],
                [
                    'code' => 'KEU',
                    'name' => 'Keuangan',
                    'units' => [
                        ['code' => 'PAJ', 'name' => 'Perpajakan', 'digit' => 1],
                        ['code' => 'AKT', 'name' => 'Akuntan', 'digit' => 2],
                    ],
                ],
                [
                    'code' => 'OPR',
                    'name' => 'Operasional',
                    'units' => [
                        ['code' => 'REG', 'name' => 'Regional', 'digit' => 1],
                        ['code' => 'GER', 'name' => 'Gerai', 'digit' => 2],
                        ['code' => 'KEM', 'name' => 'Kemitraan', 'digit' => 3],
                    ],
                ],
                [
                    'code' => 'TKG',
                    'name' => 'Tata Kelola',
                    'units' => [],
                ],
                [
                    'code' => 'SDM',
                    'name' => 'SDM',
                    'units' => [],
                ],
            ];

            foreach ($definitions as $definition) {
                $division = Division::query()->firstOrCreate(
                    ['code' => $definition['code']],
                    [
                        'name' => $definition['name'],
                        'is_active' => true,
                    ]
                );

                if ($division->id < 1 || $division->id > 9) {
                    throw new RuntimeException(
                        "ID Divisi {$division->name} harus berada pada rentang 1 sampai 9. ID saat ini: {$division->id}."
                    );
                }

                foreach ($definition['units'] as $unitDefinition) {
                    $unit = Unit::query()->firstOrCreate(
                        [
                            'division_id' => $division->id,
                            'code' => $unitDefinition['code'],
                        ],
                        [
                            'name' => $unitDefinition['name'],
                            'employee_code_digit' => $unitDefinition['digit'],
                            'is_active' => true,
                        ]
                    );

                    if ((int) $unit->employee_code_digit !== $unitDefinition['digit']) {
                        throw new RuntimeException(
                            "Digit Employee ID Unit {$unit->name} tidak sesuai konfigurasi."
                        );
                    }
                }
            }
        });

        $this->command?->info('Master organisasi berhasil diperiksa/dibuat.');
    }
}
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/* Role & permission dasar 
 
  Tiga role sistem:
    HRD      -> akses global (semua divisi)
    KADIV    -> akses terbatas divisinya
    EMPLOYEE -> akses hanya data dirinya */
class RolePermissionSeeder extends Seeder
{
    /* Daftar modul beserta aksi yang dikenali sistem */
    protected function modules(): array
    {
        return [
            'dashboard'   => ['view'],
            'employee'    => ['view', 'create', 'update', 'delete', 'import', 'export'],
            'attendance'  => ['view', 'verify', 'correct', 'process', 'import', 'export'],
            'schedule'    => ['view', 'create', 'update', 'delete', 'assign'],
            'leave'       => ['view', 'create', 'approve', 'reject', 'delete'],
            'permission'  => ['view', 'create', 'approve', 'reject', 'delete'],
            'overtime'    => ['view', 'create', 'approve', 'reject', 'delete'],
            'deduction'   => ['view', 'calculate', 'confirm', 'export'],
            'report'      => ['view', 'generate', 'export'],
            'device'      => ['view', 'create', 'update', 'delete', 'sync'],
            'master'      => ['view', 'create', 'update', 'delete'],
            'setting'     => ['view', 'update'],
            'user'        => ['view', 'create', 'update', 'delete', 'reset-password'],
            'audit'       => ['view', 'export'],
            'tax'         => ['view', 'update'],
            'ai'          => ['use'],
        ];
    }

    /* Hak akses bawaan tiap role: modul => aksi yang diizinkan */
    protected function grants(): array
    {
        return [
            Role::HRD => [
                'dashboard'  => ['view'],
                'employee'   => ['view', 'create', 'update', 'delete', 'import', 'export'],
                'attendance' => ['view', 'verify', 'correct', 'process', 'import', 'export'],
                'schedule'   => ['view', 'create', 'update', 'delete', 'assign'],
                'leave'      => ['view', 'create', 'approve', 'reject', 'delete'],
                'permission' => ['view', 'create', 'approve', 'reject', 'delete'],
                'overtime'   => ['view', 'create', 'approve', 'reject', 'delete'],
                'deduction'  => ['view', 'calculate', 'confirm', 'export'],
                'report'     => ['view', 'generate', 'export'],
                'device'     => ['view', 'create', 'update', 'delete', 'sync'],
                'master'     => ['view', 'create', 'update', 'delete'],
                'setting'    => ['view', 'update'],
                'user'       => ['view', 'create', 'update', 'delete', 'reset-password'],
                'audit'      => ['view', 'export'],
                'tax'        => ['view', 'update'],
                'ai'         => ['use'],
            ],
            Role::KADIV => [
                'dashboard'  => ['view'],
                'employee'   => ['view', 'export'],
                'attendance' => ['view', 'verify', 'export'],
                'schedule'   => ['view', 'assign'],
                'leave'      => ['view', 'create', 'approve', 'reject'],
                'permission' => ['view', 'approve', 'reject'],
                'overtime'   => ['view', 'approve', 'reject'],
                'deduction'  => ['view', 'export'],
                'report'     => ['view', 'generate', 'export'],
                'ai'         => ['use'],
            ],
            Role::EMPLOYEE => [
                'dashboard'  => ['view'],
                'attendance' => ['view'],        // hanya miliknya sendiri
                'schedule'   => ['view'],
                'leave'      => ['view', 'create'],
                'permission' => ['view', 'create'],
                'overtime'   => ['view', 'create'],
                'deduction'  => ['view'],        // hanya miliknya sendiri
                'ai'         => ['use'],
            ],
        ];
    }

    public function run(): void
    {
        // ---- Permissions -------------------------------------------------
        $permissionIds = [];   // "module.action" => id

        foreach ($this->modules() as $module => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::query()->firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['description' => ucfirst($action) . ' ' . $module]
                );

                $permissionIds[$module . '.' . $action] = $permission->id;
            }
        }

        // ---- Roles -------------------------------------------------------
        $roleDefinitions = [
            Role::HRD => [
                'description' => 'HRD — akses penuh ke seluruh data perusahaan.',
                'scope'       => 'GLOBAL',
            ],
            Role::KADIV => [
                'description' => 'Kepala Divisi — akses terbatas pada divisinya.',
                'scope'       => 'DIVISION',
            ],
            Role::EMPLOYEE => [
                'description' => 'Karyawan — akses hanya untuk data dirinya sendiri.',
                'scope'       => 'SELF',
            ],
        ];

        foreach ($roleDefinitions as $name => $definition) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => $definition['description'],
                    'scope'       => $definition['scope'],
                    'is_system'   => true,
                ]
            );

            $ids = [];

            foreach ($this->grants()[$name] as $module => $actions) {
                foreach ($actions as $action) {
                    if (isset($permissionIds[$module . '.' . $action])) {
                        $ids[] = $permissionIds[$module . '.' . $action];
                    }
                }
            }

            $role->permissions()->sync($ids);
        }

        $this->command?->info('Role & permission siap: HRD, KADIV, EMPLOYEE.');
    }
}

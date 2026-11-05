<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'employee_id',
    'name',
    'email',
    'password',
    'is_active',
    'must_change_password',
    'activation_token_hash',
    'activation_expires_at',
])]
#[Hidden([
    'password',
    'remember_token',
    'mfa_secret',
    'activation_token_hash',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /*
      Relasi akun dengan data karyawan.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /* Relasi akun dengan satu role melalui tabel role_user */
    public function roles(): BelongsToMany
    {
    return $this->belongsToMany(Role::class, 'role_user')
        ->withTimestamps();
    }

    /*
      Satu akun hanya boleh memiliki satu role
      Pembatas utamanya ditegakkan oleh UNIQUE(role_user.user_id)
      di PostgreSQL
     */
/*
  Mengambil nama satu-satunya role yang dimiliki akun.
 */
public function primaryRole(): ?string
{
    if ($this->relationLoaded('roles')) {
        return $this->roles->first()?->name;
    }

    return $this->roles()->value('roles.name');
}

    /*
      Memeriksa apakah akun memiliki salah satu role yang diminta
     */
    public function hasRole(string ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return $this->roles()
            ->whereIn('roles.name', $roles)
            ->exists();
    }

    public function isHrd(): bool
    {
        return $this->hasRole(Role::HRD);
    }

    public function isKadiv(): bool
    {
        return $this->hasRole(Role::KADIV);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::EMPLOYEE);
    }

    /*
      Mengambil ID divisi dari data karyawan pemilik akun
      Null berarti akun tidak memiliki divisi
     */
    public function divisionId(): ?int
    {
        $divisionId = $this->employee?->division_id;

        return $divisionId === null ? null : (int) $divisionId;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'activation_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->BelongsTo(Employee::class);
    }
}

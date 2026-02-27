<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Check if user has a specific permission scope.
     * Super admins bypass all checks.
     * Supports wildcards: '*' matches everything, 'contacts.*' matches all contact scopes.
     */
    public function hasPermission(string $scope): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($this->getAllPermissions() as $perm) {
            if ($perm === '*' || $perm === $scope) {
                return true;
            }

            if (str_ends_with($perm, '.*')) {
                $prefix = substr($perm, 0, -2);
                if (str_starts_with($scope, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get flat array of all permission strings from all assigned roles.
     *
     * @return string[]
     */
    public function getAllPermissions(): array
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        $permissions = [];
        foreach ($this->roles as $role) {
            $rolePerms = $role->permissions ?? [];
            $permissions = array_merge($permissions, $rolePerms);
        }

        return array_unique($permissions);
    }
}

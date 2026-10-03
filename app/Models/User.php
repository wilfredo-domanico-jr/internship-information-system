<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'email', 'password',
        'role', 'member_no', 'status', 'phone', 'avatar_path', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'status' => AccountStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /* Accessors */

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(
            mb_substr((string) $this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1)
        ));
    }

    /* Role and status helpers */

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isAdviser(): bool
    {
        return $this->role === Role::Adviser;
    }

    public function isCompany(): bool
    {
        return $this->role === Role::Company;
    }

    public function isIntern(): bool
    {
        return $this->role === Role::Intern;
    }

    public function isDisabled(): bool
    {
        return $this->status === AccountStatus::Disabled;
    }

    /* Scopes */

    public function scopeOfRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Active);
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Disabled);
    }

    /* Relationships */

    public function internProfile(): HasOne
    {
        return $this->hasOne(InternProfile::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function advisedClasses(): HasMany
    {
        return $this->hasMany(ClassSection::class, 'adviser_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'intern_id')->latest('started_at');
    }

    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class, 'intern_id')->whereNull('ended_at');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'intern_id')->latest();
    }
}

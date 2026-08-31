<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'username',
        'email',
        'email_verified_at',
        'portal_last_login_at',
        'password',
        'person_id',
        'lang',
        'theme',
        'current_team_id',
        'profile_photo_url',
        'default_projekt_id',
        'unterweisung_unterschrift',
        'unterweisung_unterschrift_updated_at',
    ];

    protected $date = [
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'unterweisung_unterschrift',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'portal_last_login_at' => 'datetime',
        'unterweisung_unterschrift' => 'encrypted:array',
        'unterweisung_unterschrift_updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'first_name',
        'last_name',
        'name',
        'profile_photo_url',
        'has_unterweisung_unterschrift',
    ];

    public function getFirstNameAttribute(): ?string
    {
        return $this->person?->vorname;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->person?->nachname;
    }

    public function getNameAttribute(): string
    {
        $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : ($this->username ?? $this->email ?? '');
    }

    public function getHasUnterweisungUnterschriftAttribute(): bool
    {
        return ! empty($this->unterweisung_unterschrift);
    }

    /**
     * Use a host-relative URL so profile photos keep working when the
     * application URL or filesystem URL is cached for another environment.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->profile_photo_path
            ? '/storage/'.ltrim($this->profile_photo_path, '/')
            : $this->defaultProfilePhotoUrl());
    }

    /**
     * Check an effective permission directly in the assignment tables.
     *
     * This intentionally bypasses Spatie's permission cache so navigation and
     * access checks reflect role changes immediately.
     */
    public function hasStoredPermission(string $permission): bool
    {
        $directlyAssigned = $this->permissions()
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'web')
            ->exists();

        if ($directlyAssigned) {
            return true;
        }

        return $this->roles()
            ->where('roles.guard_name', 'web')
            ->whereHas('permissions', fn ($permissions) => $permissions
                ->where('permissions.name', $permission)
                ->where('permissions.guard_name', 'web'))
            ->exists();
    }

    /**
     * Freigaben, die dieser Benutzer erhalten hat
     */
    public function person()
    {
        return $this->belongsTo(Personen::class);
    }

    public function receivedFreigaben2()
    {
        return $this->morphMany(Freigabe::class, 'shareable_to')
            ->where('shareable_to_id', Auth::user())
            ->where('shareable_to_id', '!=', 'shared_by');
    }

    public function receivedFreigaben()
    {
        return Freigabe::where('shareable_to_type', self::class)
            ->where('shareable_to_id', Auth::id())
            ->where('shared_by', '!=', Auth::id())
            ->where('shareable_from_type', Brief::class)

            ->get()
            ->pluck('shareableFrom');
    }

    /**
     * Freigaben, die dieser Benutzer erstellt hat
     */
    public function sentFreigaben()
    {
        return $this->hasMany(Freigabe::class, 'shared_by');
    }

    /**
     * Briefe, die der Benutzer selbst erstellt (an sich freigegeben) hat
     */
    public function ownLetters()
    {
        return Freigabe::where('shareable_to_type', self::class)
            ->where('shareable_to_id', Auth::id())
            ->where('shared_by', Auth::id())
            ->where('shareable_from_type', Brief::class)
            ->get()
            ->pluck('shareableFrom');
    }

    public function standorte(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_has_personens', 'personen_id', 'standort_id', 'person_id', 'id');
    }

    public function adresse()
    {
        return $this->hasOne(Adresse::class);
    }

    public function projekte(): BelongsToMany
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id', 'person_id', 'id')->distinct();
    }

    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id');
    }

    public function staffAccountInvitations(): HasMany
    {
        return $this->hasMany(StaffAccountInvitation::class);
    }

    public function latestStaffAccountInvitation()
    {
        return $this->hasOne(StaffAccountInvitation::class)->latestOfMany();
    }

    public function abteilungsassistent()
    {
        return $this->hasOne(Abteilungsassistent::class);
    }

    public function teilnehmerProfil()
    {
        return $this->hasOne(Teilnehmer::class);
    }

    /* public function scopeFilter($query, array $filters)
     {
         $query->when($filters['search'] ?? null, function ($query, $search) {
             $query->where(function ($query) use ($search) {
                 $query->where('name', 'like', '%'.$search.'%')
                     //->orWhere('last_name', 'like', '%'.$search.'%')
                    //->orWhere('first_name', 'like', '%'.$search.'%')
                     ->orWhere('email', 'like', '%'.$search.'%');
             });
         })->when($filters['role'] ?? null, function ($query, $role) {
             $query->whereRole($role);
         })->when($filters['trashed'] ?? null, function ($query, $trashed) {
             if ($trashed === 'with') {
                 $query->withTrashed();
             } elseif ($trashed === 'only') {
                 $query->onlyTrashed();
             }
         });
     }*/
}

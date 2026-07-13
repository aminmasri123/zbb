<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Brief;
use App\Models\Projekt;
use App\Models\Freigabe;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\Abteilung;
use App\Models\Teilnehmer;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Abteilungsassistent;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\HasProfilePhoto;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;


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
        'password',
        'person_id',
        'lang',
        'theme',
        'current_team_id',
        'profile_photo_url',
        'default_projekt_id',
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
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
        $fullName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : ($this->username ?? $this->email ?? '');
    }


     /**
     * Freigaben, die dieser Benutzer erhalten hat
     */


     public function person(){
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
            ->where('shareable_to_id',  Auth::id())
            ->where('shared_by','!=',  Auth::id())
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
            ->where('shareable_to_id',  Auth::id())
            ->where('shared_by',  Auth::id())
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

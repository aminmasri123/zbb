<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Projekt;
use App\Models\Standort;
use App\Models\Abteilung;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Abteilungsassistent;
use Laravel\Jetstream\HasProfilePhoto;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
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
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'lang',
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
    /*
    protected $appends = [
        'profile_photo_url',
    ];*/
    public function standorte(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_has_users', 'user_id', 'standort_id');
    }

    public function adresse()
    {
        return $this->hasOne(Adresse::class);
    }


    public function projekte(): BelongsToMany
    {
        return $this->belongsToMany(Projekt::class, 'user_has_projekts', 'user_id', 'projekt_id');
    }


    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id');
    }
    public function abteilungsassistent()
    {
        return $this->hasOne(Abteilungsassistent::class);
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

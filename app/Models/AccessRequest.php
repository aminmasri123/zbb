<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROVISIONED = 'provisioned';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'requested_for_person_id',
        'requested_for_name',
        'requested_by_user_id',
        'requested_by_name',
        'access_profile_id',
        'profile_snapshot',
        'valid_from',
        'valid_until',
        'reason',
        'status',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'decision_note',
        'activated_by_user_id',
        'activated_at',
        'technical_reference',
        'activation_note',
        'revoked_by_user_id',
        'revoked_at',
        'revocation_note',
    ];

    protected $casts = [
        'profile_snapshot' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $appends = [
        'effective_status',
    ];

    public function requestedFor()
    {
        return $this->belongsTo(Personen::class, 'requested_for_person_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function profile()
    {
        return $this->belongsTo(AccessProfile::class, 'access_profile_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function events()
    {
        return $this->hasMany(AccessRequestEvent::class)->orderByDesc('created_at');
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status !== self::STATUS_PROVISIONED) {
            return $this->status;
        }

        if ($this->valid_until?->isPast()) {
            return 'expired';
        }

        if ($this->valid_from?->isFuture()) {
            return 'scheduled';
        }

        return 'effective';
    }
}

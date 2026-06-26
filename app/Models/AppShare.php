<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'shared_by_user_id',
        'person_id',
        'email',
        'permission',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function shareable()
    {
        return $this->morphTo();
    }

    public function person()
    {
        return $this->belongsTo(Personen::class);
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}

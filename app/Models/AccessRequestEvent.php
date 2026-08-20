<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'access_request_id',
        'actor_user_id',
        'actor_name',
        'event_type',
        'from_status',
        'to_status',
        'comment',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(AccessRequest::class, 'access_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

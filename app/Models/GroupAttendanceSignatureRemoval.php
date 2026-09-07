<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupAttendanceSignatureRemoval extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['signature_ciphertext'];

    protected $casts = ['removed_at' => 'datetime', 'restored_at' => 'datetime'];
}

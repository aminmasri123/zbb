<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiWorkspaceRun extends Model
{
    protected $fillable = ['user_id','run_uuid','task','instruction','source_metadata','title','content','citations','warnings','status'];
    protected $casts = ['source_metadata'=>'array','citations'=>'array','warnings'=>'array'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupDailyTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['performed_on' => 'date:Y-m-d'];

    public function gruppe()
    {
        return $this->belongsTo(Gruppe::class);
    }

    public function assignments()
    {
        return $this->hasMany(GroupDailyTaskParticipant::class, 'task_id');
    }
}

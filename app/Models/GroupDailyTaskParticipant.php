<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupDailyTaskParticipant extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function task()
    {
        return $this->belongsTo(GroupDailyTask::class, 'task_id');
    }

    public function participation()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id');
    }
}

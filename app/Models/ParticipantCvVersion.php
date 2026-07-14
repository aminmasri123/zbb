<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantCvVersion extends Model{use HasFactory;public $timestamps=false;protected $fillable=['person_id','version','label','template_key','snapshot','snapshot_sha256','created_by_user_id','created_at'];protected $casts=['snapshot'=>'array','created_at'=>'datetime'];}

<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class ParticipantApplicationActivity extends Model {protected $fillable=['application_id','user_id','type','body','metadata','occurred_at'];protected $casts=['metadata'=>'array','occurred_at'=>'datetime'];}

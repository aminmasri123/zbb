<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantNotificationDelivery extends Model{use HasFactory;protected $fillable=['user_id','digest_date','content_sha256','status','sent_at','error'];protected $casts=['sent_at'=>'datetime'];}

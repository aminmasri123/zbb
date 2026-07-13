<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantNotificationPreference extends Model{use HasFactory;protected $fillable=['user_id','category','in_app_enabled','email_enabled','days_before'];protected $casts=['in_app_enabled'=>'boolean','email_enabled'=>'boolean','days_before'=>'integer'];}

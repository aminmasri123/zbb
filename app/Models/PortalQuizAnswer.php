<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class PortalQuizAnswer extends Model{use HasFactory;protected $fillable=['attempt_id','question_id','selected_option_ids','correct','earned_points'];protected $casts=['selected_option_ids'=>'array','correct'=>'boolean','earned_points'=>'decimal:2'];}

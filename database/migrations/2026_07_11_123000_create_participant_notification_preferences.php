<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('participant_notification_preferences',function(Blueprint $table){$table->id();$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();$table->enum('category',['task','application','course','course_session','message']);$table->boolean('in_app_enabled')->default(true);$table->boolean('email_enabled')->default(false);$table->unsignedTinyInteger('days_before')->default(14);$table->timestamps();$table->unique(['user_id','category'],'participant_notification_pref_unique');});
  Schema::create('participant_notification_deliveries',function(Blueprint $table){$table->id();$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();$table->date('digest_date');$table->char('content_sha256',64);$table->enum('status',['pending','sent','failed'])->default('pending');$table->dateTime('sent_at')->nullable();$table->text('error')->nullable();$table->timestamps();$table->unique(['user_id','digest_date','content_sha256'],'participant_digest_unique');});
 }
 public function down():void{Schema::dropIfExists('participant_notification_deliveries');Schema::dropIfExists('participant_notification_preferences');}
};

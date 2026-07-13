<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('attendance_correction_requests',function(Blueprint $table){$table->id();$table->foreignId('attendance_id')->constrained('gruppe_has_personens',indexName:'attendance_correction_entry_fk')->cascadeOnDelete();$table->foreignId('person_id')->constrained('personens',indexName:'attendance_correction_person_fk')->cascadeOnDelete();$table->text('message');$table->enum('status',['open','accepted','rejected'])->default('open');$table->text('resolution_note')->nullable();$table->foreignId('resolved_by_user_id')->nullable()->constrained('users',indexName:'attendance_correction_resolver_fk')->nullOnDelete();$table->timestamp('resolved_at')->nullable();$table->timestamps();$table->index(['person_id','status','created_at'],'attendance_correction_person_status');});}
 public function down():void{Schema::dropIfExists('attendance_correction_requests');}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('background_color', 20)->default('#ff7a00');
            $table->string('text_color', 20)->default('#ffffff');
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->timestamps();
        });

        Schema::create('app_calendar_styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->string('background_color', 20)->default('#ff7a00');
            $table->string('text_color', 20)->default('#ffffff');
            $table->timestamps();
            $table->unique(['owner_user_id', 'label']);
        });

        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->foreignId('calendar_id')->nullable()->after('owner_user_id')->constrained('app_calendars')->nullOnDelete();
            $table->string('background_color', 20)->nullable()->after('color');
            $table->string('text_color', 20)->nullable()->after('background_color');
        });
    }

    public function down(): void
    {
        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calendar_id');
            $table->dropColumn(['background_color', 'text_color']);
        });

        Schema::dropIfExists('app_calendar_styles');
        Schema::dropIfExists('app_calendars');
    }
};

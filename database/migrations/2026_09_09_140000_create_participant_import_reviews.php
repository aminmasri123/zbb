<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_import_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('projekt_id');
            $table->longText('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'projekt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_import_reviews');
    }
};

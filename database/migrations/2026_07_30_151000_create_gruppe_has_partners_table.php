<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gruppe_has_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gruppe_id', 'partner_id']);
        });

        if (Schema::hasColumn('gruppes', 'partner_id')) {
            DB::table('gruppes')
                ->whereNotNull('partner_id')
                ->orderBy('id')
                ->get(['id', 'partner_id'])
                ->each(function ($gruppe): void {
                    DB::table('gruppe_has_partners')->insertOrIgnore([
                        'gruppe_id' => $gruppe->id,
                        'partner_id' => $gruppe->partner_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gruppe_has_partners');
    }
};

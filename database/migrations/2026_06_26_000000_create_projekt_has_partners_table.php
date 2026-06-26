<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekt_has_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['projekt_id', 'partner_id']);
        });

        DB::table('projekt_has_ansprechpartners')
            ->join('partner_has_partnerschaftstypens', 'projekt_has_ansprechpartners.ansprechpartner_id', '=', 'partner_has_partnerschaftstypens.id')
            ->select(
                'projekt_has_ansprechpartners.projekt_id',
                'partner_has_partnerschaftstypens.partner_id'
            )
            ->distinct()
            ->orderBy('projekt_has_ansprechpartners.projekt_id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('projekt_has_partners')->updateOrInsert(
                        [
                            'projekt_id' => $row->projekt_id,
                            'partner_id' => $row->partner_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('projekt_has_partners');
    }
};

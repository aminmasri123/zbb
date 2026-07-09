<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->index();
            $table->string('label');
            $table->string('target_type');
            $table->string('target_value')->nullable();
            $table->string('scope')->default('none');
            $table->json('channels')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('exclude_actor')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        DB::table('notification_rules')->insert([
            [
                'event_key' => 'materialanforderung.eingereicht',
                'label' => 'Materialanforderung eingereicht',
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.sachlische_freigabe.index',
                'scope' => 'current_project',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.sachlich_genehmigt',
                'label' => 'Materialanforderung sachlich genehmigt',
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.kaufmännische_freigabe.update',
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.kaufmaennisch_genehmigt',
                'label' => 'Materialanforderung kaufmaennisch genehmigt',
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.bestellwesen.update',
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.zur_ueberarbeitung',
                'label' => 'Materialanforderung zur Ueberarbeitung',
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.stornieren',
                'label' => 'Materialanforderung storniert',
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.bestellt',
                'label' => 'Materialanforderung bestellt',
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.geliefert',
                'label' => 'Materialanforderung geliefert',
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'materialanforderung.teilweise_geliefert',
                'label' => 'Materialanforderung teilweise geliefert',
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'klassenbuch.woche.zur_pruefung',
                'label' => 'Klassenbuch Woche zur Pruefung',
                'target_type' => 'department_reviewers',
                'target_value' => null,
                'scope' => 'none',
                'channels' => json_encode(['database']),
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};

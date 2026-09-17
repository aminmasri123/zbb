<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_rules')) {
            Schema::create('purchase_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('approval_limit_cents')->default(50000);
                $table->unsignedBigInteger('quote_limit_cents')->default(50000);
                $table->unsignedTinyInteger('quote_count')->default(3);
                $table->json('location_ids');
                $table->json('approver_ids');
                $table->boolean('manual_referral')->default(true);
                $table->dateTime('effective_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('purchase_number_counters')) {
            Schema::create('purchase_number_counters', function (Blueprint $table) {
                $table->unsignedInteger('year')->primary();
                $table->unsignedBigInteger('last_number')->default(0);
            });
        }
        // Keep issued numbers even when a draft is deleted.
        if (! Schema::hasTable('purchase_numbers')) {
            Schema::create('purchase_numbers', function (Blueprint $table) {
                $table->id();
                $table->string('number', 100)->unique();
                $table->foreignId('request_id')->nullable()->constrained('materialanforderungs')->nullOnDelete();
            });
        }
        Schema::table('materialanforderungs', function (Blueprint $table) {
            $table->string('status', 40)->default('entwurf')->change();
        });
        $missingRequestColumns = collect([
            'bestellnummer', 'standort_id', 'versand_netto', 'versand_mwst', 'lieferant_adresse',
            'lieferantenreferenz', 'approval_policy', 'revision', 'selected_offer_id', 'order_snapshot', 'bestellt_am',
        ])->reject(fn (string $column) => Schema::hasColumn('materialanforderungs', $column))->all();
        if ($missingRequestColumns !== []) {
            Schema::table('materialanforderungs', function (Blueprint $table) use ($missingRequestColumns) {
                if (in_array('bestellnummer', $missingRequestColumns, true)) $table->string('bestellnummer', 100)->nullable()->index();
                if (in_array('standort_id', $missingRequestColumns, true)) $table->foreignId('standort_id')->nullable()->constrained('standorts')->restrictOnDelete();
                if (in_array('versand_netto', $missingRequestColumns, true)) $table->decimal('versand_netto', 10, 2)->default(0);
                if (in_array('versand_mwst', $missingRequestColumns, true)) $table->decimal('versand_mwst', 5, 2)->default(19);
                if (in_array('lieferant_adresse', $missingRequestColumns, true)) $table->text('lieferant_adresse')->nullable();
                if (in_array('lieferantenreferenz', $missingRequestColumns, true)) $table->string('lieferantenreferenz', 100)->nullable();
                if (in_array('approval_policy', $missingRequestColumns, true)) $table->json('approval_policy')->nullable();
                if (in_array('revision', $missingRequestColumns, true)) $table->unsignedInteger('revision')->default(1);
                if (in_array('selected_offer_id', $missingRequestColumns, true)) $table->unsignedBigInteger('selected_offer_id')->nullable();
                if (in_array('order_snapshot', $missingRequestColumns, true)) $table->json('order_snapshot')->nullable();
                if (in_array('bestellt_am', $missingRequestColumns, true)) $table->timestamp('bestellt_am')->nullable();
            });
        }
        if (! Schema::hasTable('purchase_offers')) {
            Schema::create('purchase_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('anforderung_id')->constrained('materialanforderungs')->cascadeOnDelete();
                $table->unsignedInteger('revision');
                $table->string('lieferant');
                $table->text('lieferant_adresse');
                $table->string('angebotsnummer', 100)->nullable();
                $table->date('angebotsdatum');
                $table->date('gueltig_bis')->nullable();
                $table->string('lieferzeit')->nullable();
                $table->text('bemerkung')->nullable();
                $table->boolean('empfohlen')->default(false);
                $table->json('positionen');
                $table->decimal('versand_netto', 10, 2)->default(0);
                $table->decimal('versand_mwst', 5, 2)->default(19);
                $table->decimal('netto', 12, 2);
                $table->decimal('brutto', 12, 2);
                $table->string('path');
                $table->string('original_name');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! DB::table('purchase_rules')->exists()) {
            DB::table('purchase_rules')->insert([
                'approval_limit_cents' => 50000, 'quote_limit_cents' => 50000, 'quote_count' => 3,
                'location_ids' => '[]', 'approver_ids' => '[]', 'manual_referral' => true,
                'effective_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Preserve all externally used legacy numbers, including historical duplicates.
        DB::table('materialanforderungs')->orderBy('id')->each(function ($request) {
            $legacy = trim((string) DB::table('materialanforderung_vergabevermerks')->where('anforderung_id', $request->id)->value('bestellnummer'));
            if ($legacy !== '') {
                DB::table('materialanforderungs')->where('id', $request->id)->update(['bestellnummer' => $legacy]);
                DB::table('purchase_numbers')->insertOrIgnore(['number' => $legacy, 'request_id' => $request->id]);
            }
            if (! in_array($request->status, ['entwurf', 'zur_ueberarbeitung'])) {
                DB::table('materialanforderungs')->where('id', $request->id)->update([
                    'approval_policy' => json_encode(['legacy' => true, 'gf_required' => false, 'min_quotes' => 0]),
                ]);
            }
        });
        // Reserve legacy numbers first, then fill gaps without renumbering existing orders.
        \App\Models\Materialanforderung::whereNull('bestellnummer')->orderBy('id')->eachById(function ($request) {
            app(\App\Services\Purchasing\PurchaseWorkflow::class)->number($request);
        });
        app(\Database\Seeders\PurchaseWorkflowPermissionSeeder::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_offers');
        Schema::dropIfExists('purchase_numbers');
        Schema::dropIfExists('purchase_number_counters');
        Schema::dropIfExists('purchase_rules');
        Schema::table('materialanforderungs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('standort_id');
            $table->dropColumn(['bestellnummer', 'versand_netto', 'versand_mwst', 'lieferant_adresse',
                'lieferantenreferenz', 'approval_policy', 'revision', 'selected_offer_id', 'order_snapshot', 'bestellt_am']);
        });
    }
};

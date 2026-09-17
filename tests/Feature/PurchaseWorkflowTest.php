<?php
namespace Tests\Feature;

use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\PurchaseRule;
use App\Models\Standort;
use App\Models\User;
use App\Notifications\UpdateMaterialanforderungNotification;
use App\Services\Purchasing\PurchaseWorkflow;
use App\Services\Purchasing\PurchaseOrderExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('local');
    }

    public function test_normal_flow_and_exactly_500_euros_need_no_director(): void
    {
        [$order, $creator, $head, $commercial, $buyer] = $this->context(500);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->assertFalse($order->fresh()->approval_policy['gf_required']);
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->assertSame('sachlich_genehmigt', $order->fresh()->status);
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt')->assertSessionHasNoErrors();
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
        $this->assertSame('bestellt', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->order_snapshot);
    }

    public function test_above_500_requires_three_different_offers_before_submission(): void
    {
        [$order, $creator] = $this->context(500.01);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasErrors('angebote');
        $this->assertSame('entwurf', $order->fresh()->status);
        foreach (['Supplier A', 'supplier a ', 'Supplier C'] as $name) $this->offer($creator, $order, $name);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasErrors('angebote');
        $this->offer($creator, $order, 'Supplier D');
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->approval_policy['gf_required']);
    }

    public function test_director_selects_offer_and_bypasses_commercial_approval_but_informs_them(): void
    {
        [$order, $creator, $head, $commercial, $buyer, $director] = $this->context(600);
        foreach (['A', 'B', 'C'] as $name) $this->offer($creator, $order, $name, 550);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->assertSame('gf_pruefung', $order->fresh()->status);
        Notification::assertSentTo($director, UpdateMaterialanforderungNotification::class, fn ($n) => $n->status === 'gf_pruefung');
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt')->assertForbidden();
        $this->transition($buyer, $order, 'bestellt')->assertForbidden();
        $chosen = $order->angebote()->where('lieferant', 'B')->firstOrFail();
        $this->transition($director, $order, 'gf_genehmigt', ['angebot_id' => $chosen->id, 'anmerkung' => 'Passende Lieferzeit.'])->assertSessionHasNoErrors();
        $this->assertSame('gf_genehmigt', $order->fresh()->status);
        $this->assertEquals(550, $order->fresh()->endsumme);
        $this->assertSame('B', $order->fresh()->vergabevermerk->lieferant);
        $this->assertEquals(550, $order->fresh()->artikeln->first()->einzelpreis);
        Notification::assertSentTo($commercial, UpdateMaterialanforderungNotification::class, fn ($n) => $n->status === 'gf_genehmigt');
        Notification::assertSentTo($buyer, UpdateMaterialanforderungNotification::class, fn ($n) => $n->status === 'gf_genehmigt');
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
        $this->assertSame('bestellt', $order->fresh()->status);
        $this->assertSame('B', $order->fresh()->order_snapshot['supplier']);
        $this->actingAs($commercial)->get(route('materialanforderung.show', $order))->assertOk();
        $this->actingAs($commercial)->get(route('materialanforderung.index'))->assertOk()->assertInertia(fn ($page) => $page->has('anforderungen', 1));
        $html = view('pdf.materialanforderung', ['anforderung' => $order->fresh()->load('genehmigungen.genehmiger', 'artikeln', 'vergabevermerk')])->render();
        $this->assertStringContainsString('Abschließend freigegeben. Kaufmännische Leitung informiert.', $html);
        $this->assertStringNotContainsString('Noch offen', $html);
        $this->actingAs($commercial)->get(route('materialanforderung.pdf', $order))->assertOk();
    }

    public function test_location_rule_routes_a_small_order_to_director_without_three_offers(): void
    {
        [$order, $creator, $head, $commercial, $buyer, $director] = $this->context(150);
        PurchaseRule::current()->update(['location_ids' => [$order->standort_id]]);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->assertSame('gf_pruefung', $order->fresh()->status);
        $this->transition($director, $order, 'gf_genehmigt')->assertSessionHasNoErrors();
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
    }

    public function test_commercial_can_refer_and_cannot_bypass_pending_director(): void
    {
        [$order, $creator, $head, $commercial, $buyer, $director] = $this->context(100);
        $this->transition($creator, $order, 'eingereicht');
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->transition($commercial, $order, 'gf_pruefung')->assertSessionHasErrors('anmerkung');
        $this->transition($commercial, $order, 'gf_pruefung', ['anmerkung' => 'Grundsatzentscheidung erforderlich.'])->assertSessionHasNoErrors();
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt')->assertForbidden();
        $this->transition($buyer, $order, 'bestellt')->assertForbidden();
        $this->transition($director, $order, 'gf_genehmigt')->assertSessionHasNoErrors();
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
    }

    public function test_director_selection_is_required_and_foreign_or_expired_offers_are_rejected(): void
    {
        [$order, $creator, $head, , , $director] = $this->context(600);
        foreach (['A', 'B', 'C'] as $name) $this->offer($creator, $order, $name);
        $this->transition($creator, $order, 'eingereicht');
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->transition($director, $order, 'gf_genehmigt')->assertSessionHasErrors('angebot_id');
        $this->transition($director, $order, 'gf_genehmigt', ['angebot_id' => 999999, 'anmerkung' => 'Test'])->assertNotFound();
        $first = $order->angebote()->first();
        $first->update(['gueltig_bis' => now()->subDay()]);
        $this->transition($director, $order, 'gf_genehmigt', ['angebot_id' => $first->id, 'anmerkung' => 'Test'])->assertSessionHasErrors('angebote');
        $this->assertSame('gf_pruefung', $order->fresh()->status);
    }

    public function test_shipping_is_included_and_rules_are_frozen_at_submission(): void
    {
        [$order, $creator, $head] = $this->context(500.99);
        $totals = app(PurchaseWorkflow::class)->totals([['stueck' => 1, 'einzelpreis' => 400, 'mwst' => 19]], 21, 19);
        $this->assertEquals([421.0, 500.99], $totals);
        foreach (['A', 'B', 'C'] as $name) $this->offer($creator, $order, $name);
        $this->transition($creator, $order, 'eingereicht');
        PurchaseRule::current()->update(['approval_limit_cents' => 60000, 'quote_limit_cents' => 60000]);
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->assertSame('gf_pruefung', $order->fresh()->status);
        $this->assertFalse(app(PurchaseWorkflow::class)->evaluate($order->fresh())['gf_required']);
    }

    public function test_offer_files_and_decisions_are_not_accessible_to_unrelated_users(): void
    {
        [$order, $creator, $head] = $this->context(600);
        foreach (['A', 'B', 'C'] as $name) $this->offer($creator, $order, $name);
        $offer = $order->angebote()->first();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('materialanforderung.offers.download', $offer))->assertForbidden();
        $this->actingAs($outsider)->delete(route('materialanforderung.offers.destroy', $offer))->assertForbidden();
        $this->transition($creator, $order, 'eingereicht');
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->transition($outsider, $order, 'gf_genehmigt', ['angebot_id' => $offer->id, 'anmerkung' => 'Test'])->assertForbidden();
        $this->actingAs($creator)->delete(route('materialanforderung.offers.destroy', $offer))->assertForbidden();
    }

    public function test_changed_request_invalidates_old_offers_and_forces_new_approvals(): void
    {
        [$order, $creator, $head, , , $director] = $this->context(600);
        foreach (['A', 'B', 'C'] as $name) $this->offer($creator, $order, $name);
        $this->transition($creator, $order, 'eingereicht');
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->transition($director, $order, 'zur_ueberarbeitung', ['anmerkung' => 'Menge prüfen.']);
        $item = $order->artikeln()->first();
        $cost = DB::table('kostenstelles')->insertGetId(['kostenstelle' => '12345', 'created_at' => now(), 'updated_at' => now()]);
        $order->projekt->kostenstellen()->attach($cost);
        $this->actingAs($creator)->put(route('materialanforderung.update'), [
            'id' => $order->id, 'revision' => 1, 'standort_id' => $order->standort_id,
            'kostenstelle' => '12345', 'prioritaet' => 'normal',
            'positionen' => [['id' => $item->id, 'pos' => 1, 'artikel' => 'Material', 'stueck' => 2, 'einzelpreis' => 600, 'mwst' => 0]],
            'vergabe' => ['lieferung_art' => 'Lieferleistung', 'lieferung_option' => 'per Abholung'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $order->fresh()->revision);
        $this->assertNull($order->fresh()->approval_policy);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasErrors('angebote');
    }

    public function test_numbers_are_unique_idempotent_and_never_reused_after_deletion(): void
    {
        [$order] = $this->context(100);
        $service = app(PurchaseWorkflow::class);
        $first = $service->number($order);
        $this->assertSame($first, $service->number($order->fresh()));
        $copy = $order->replicate(['bestellnummer']); $copy->save();
        $second = $service->number($copy);
        $this->assertNotSame($first, $second);
        $copy->delete();
        $copy2 = $order->replicate(['bestellnummer']); $copy2->save();
        $this->assertNotSame($second, $service->number($copy2));
        $this->assertDatabaseHas('purchase_numbers', ['number' => $second, 'request_id' => null]);
    }

    public function test_legacy_numbers_are_preserved(): void
    {
        [$order] = $this->context(100);
        $order->forceFill(['created_at' => '2026-01-01 12:00:00'])->save();
        $order->vergabevermerk()->update(['bestellnummer' => '1087/2026']);
        $this->assertSame('1087/2026', app(PurchaseWorkflow::class)->number($order));
        $copy = $order->replicate(['bestellnummer']); $copy->created_at = $order->created_at; $copy->save();
        $this->assertSame('1088/2026', app(PurchaseWorkflow::class)->number($copy));
    }

    public function test_settings_require_permission_and_support_later_configuration(): void
    {
        [$order, $creator] = $this->context(100);
        $this->actingAs($creator)->get(route('materialanforderung.settings'))->assertForbidden();
        $this->grantTestPermission($creator, 'materialanforderung.settings.manage');
        $this->get(route('materialanforderung.settings'))->assertOk();
        $this->post(route('materialanforderung.settings.store'), [
            'approval_limit' => 600, 'quote_limit' => 600, 'quote_count' => 3, 'location_ids' => [],
            'approver_ids' => [], 'manual_referral' => true, 'effective_at' => now()->setTimezone('Europe/Berlin')->toIso8601String(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(60000, PurchaseRule::current()->approval_limit_cents);
        $this->assertSame([], PurchaseRule::current()->approver_ids);
    }

    public function test_order_export_is_blocked_before_ordering_and_uses_frozen_data_afterwards(): void
    {
        [$order, $creator, $head, $commercial, $buyer] = $this->context(100);
        $this->actingAs($creator)->get(route('materialanforderung.order.export', [$order->id, 'docx']))->assertStatus(409);
        $this->transition($creator, $order, 'eingereicht');
        $this->transition($head, $order, 'sachlich_genehmigt');
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt');
        $this->transition($buyer, $order, 'bestellt');
        $order->vergabevermerk()->update(['lieferant' => 'Changed master data']);
        $this->actingAs($creator)->get(route('materialanforderung.order.export', [$order->id, 'docx']))->assertOk();
        $path = Storage::disk('local')->path('materialanforderungen/'.$order->id.'/bestellschein/Bestellschein.docx');
        $zip = new \ZipArchive(); $zip->open($path); $xml = $zip->getFromName('word/document.xml'); $zip->close();
        $this->assertStringContainsString('Testlieferant', $xml);
        $this->assertStringNotContainsString('Changed master data', $xml);
        $this->assertStringNotContainsString('${', $xml);
        $this->assertStringNotContainsString('Amazon.de', $xml);
    }

    public function test_new_request_gets_a_number_and_server_calculates_gross_including_shipping(): void
    {
        [$existing, $creator] = $this->context(100);
        $this->grantTestPermission($creator, 'materialanforderung.store');
        $creator->projekte()->attach($existing->projekt_id);
        $creator->update(['current_team_id' => $existing->projekt_id]);
        $cost = DB::table('kostenstelles')->insertGetId(['kostenstelle' => '12345', 'created_at' => now(), 'updated_at' => now()]);
        $existing->projekt->kostenstellen()->attach($cost);
        $this->actingAs($creator)->post(route('materialanforderung.store'), [
            'standort_id' => $existing->standort_id, 'kostenstelle' => '12345', 'prioritaet' => 'normal',
            'versand_netto' => 21, 'versand_mwst' => 19,
            'positionen' => [['pos' => 1, 'artikel' => 'Material', 'stueck' => 1, 'einzelpreis' => 400, 'mwst' => 19]],
            'vergabe' => ['lieferung_art' => 'Lieferleistung', 'lieferung_option' => 'per Abholung', 'bestellnummer' => 'MANIPULATED'],
        ])->assertSessionHasNoErrors();
        $created = Materialanforderung::latest('id')->first();
        $this->assertNotSame($existing->id, $created->id);
        $this->assertMatchesRegularExpression('/^\d+\/\d{4}$/', $created->bestellnummer);
        $this->assertEquals(421, $created->gesamtpreis);
        $this->assertEquals(500.99, $created->endsumme);
        $this->assertTrue(app(PurchaseWorkflow::class)->evaluate($created)['gf_required']);
    }

    public function test_missing_director_is_visible_and_configuration_can_be_completed_later(): void
    {
        [$order, $creator, $head, , , $director] = $this->context(100);
        PurchaseRule::current()->update(['approver_ids' => [], 'location_ids' => [$order->standort_id]]);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->assertFalse(app(PurchaseWorkflow::class)->summary($order->fresh())['director_configured']);
        $this->transition($director, $order, 'gf_genehmigt')->assertForbidden();
        PurchaseRule::current()->update(['approver_ids' => [$director->id]]);
        $this->actingAs($director)->get(route('materialanforderung.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('canOpenRequest', true)->has('anforderungen', 1));
        $this->actingAs($director)->get(route('materialanforderung.show', $order))->assertOk();
        $this->transition($director, $order, 'gf_genehmigt')->assertSessionHasNoErrors();
    }

    public function test_more_expensive_selected_offer_requires_three_quotes_even_if_estimate_was_lower(): void
    {
        [$order, $creator, $head, , , $director] = $this->context(100);
        PurchaseRule::current()->update(['location_ids' => [$order->standort_id]]);
        $this->offer($creator, $order, 'A', 600);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->transition($director, $order, 'gf_genehmigt', ['angebot_id' => $order->angebote()->first()->id, 'anmerkung' => 'Auswahl'])
            ->assertSessionHasErrors('angebote');
        $this->assertSame('gf_pruefung', $order->fresh()->status);
    }

    public function test_future_rules_do_not_apply_early_and_unrelated_users_cannot_read_requests(): void
    {
        [$order, $creator] = $this->context(100);
        $this->grantTestPermission($creator, 'materialanforderung.settings.manage');
        $this->actingAs($creator)->post(route('materialanforderung.settings.store'), [
            'approval_limit' => 700, 'quote_limit' => 700, 'quote_count' => 2, 'location_ids' => [],
            'approver_ids' => [], 'manual_referral' => false, 'effective_at' => now()->addMonth()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(50000, PurchaseRule::current()->approval_limit_cents);
        $this->assertSame(3, PurchaseRule::current()->quote_count);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('materialanforderung.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('materialanforderung.show', $order))->assertForbidden();
    }

    public function test_service_draft_saves_without_delivery_options_or_supplier_address(): void
    {
        [$existing, $creator] = $this->context(100);
        $this->grantTestPermission($creator, 'materialanforderung.store');
        $creator->projekte()->attach($existing->projekt_id);
        $creator->update(['current_team_id' => $existing->projekt_id]);
        $data = $this->serviceForm($existing);
        unset($data['id'], $data['positionen'][0]['id']);
        $data['lieferant_adresse'] = '';
        $data['versand_netto'] = 15;
        $data['versand_mwst'] = 19;
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasNoErrors();
        $created = Materialanforderung::latest('id')->first();
        $this->assertNotSame($existing->id, $created->id);
        $this->assertSame('Dienstleistung', $created->vergabevermerk->lieferung_art);
        $this->assertNull($created->vergabevermerk->lieferung_option);
        $this->assertNull($created->vergabevermerk->lieferadresse);
        $this->assertNull($created->lieferant_adresse);
        $this->assertSame('Werkstatt, Raum 2', $created->vergabevermerk->leistungsort);
        $this->assertEquals(117.85, $created->endsumme);
    }

    public function test_switch_to_service_clears_hidden_delivery_values_and_invalidates_old_quotes(): void
    {
        [$order, $creator] = $this->context(100);
        $this->offer($creator, $order, 'Anbieter A', 100);
        $data = $this->serviceForm($order);
        $data['vergabe']['lieferung_option'] = 'per Lieferung';
        $data['vergabe']['lieferadresse'] = 'ALTE LIEFERADRESSE';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(2, $order->revision);
        $this->assertNull($order->vergabevermerk->lieferung_option);
        $this->assertNull($order->vergabevermerk->lieferadresse);
        $this->assertSame('Teststraße 1, 66111 Saarbrücken', $order->lieferant_adresse);
        $this->assertSame(1, $order->angebote()->first()->revision);
        $data = $this->serviceForm($order);
        $data['vergabe']['leistungsort'] = 'Online';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame(3, $order->fresh()->revision);
    }

    public function test_goods_still_require_delivery_mode_and_delivery_address_but_collection_does_not(): void
    {
        [$order, $creator] = $this->context(100);
        $data = $this->serviceForm($order);
        $data['vergabe']['lieferung_art'] = 'Lieferleistung';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasErrors('vergabe.lieferung_option');
        $data['vergabe']['lieferung_option'] = 'per Lieferung';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasErrors('vergabe.lieferadresse');
        $data['vergabe']['lieferadresse'] = 'Warenannahme';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Warenannahme', $order->fresh()->vergabevermerk->lieferadresse);
        $this->assertNull($order->fresh()->vergabevermerk->leistungsort);
        $data['revision'] = $order->fresh()->revision;
        $data['vergabe']['lieferung_option'] = 'per Abholung';
        unset($data['vergabe']['lieferadresse']);
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->vergabevermerk->lieferadresse);
    }

    public function test_service_order_requires_supplier_address_but_no_delivery_address_or_service_location(): void
    {
        [$order, $creator, $head, $commercial, $buyer] = $this->context(100);
        // Existing service records may still contain the old default delivery mode.
        $order->vergabevermerk()->update(['lieferung_art' => 'Dienstleistung', 'lieferung_option' => 'per Lieferung', 'lieferadresse' => null]);
        $order->update(['lieferant_adresse' => null]);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt')->assertSessionHasNoErrors();
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasErrors('status');
        $order->update(['lieferant_adresse' => 'Dienstleisterstraße 10']);
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->order_snapshot['is_service']);
        $this->assertSame('', $order->fresh()->order_snapshot['delivery']);
        $this->assertSame('', $order->fresh()->order_snapshot['service_location']);
    }

    public function test_service_exports_use_supplier_address_and_service_location_without_delivery_fields(): void
    {
        [$order, $creator, $head, $commercial, $buyer] = $this->context(100);
        $order->vergabevermerk()->update(['lieferung_art' => 'Dienstleistung', 'lieferung_option' => 'per Lieferung',
            'lieferadresse' => 'ALTE LIEFERADRESSE', 'leistungsort' => 'Seminarraum & Online']);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->transition($head, $order, 'sachlich_genehmigt')->assertSessionHasNoErrors();
        $this->transition($commercial, $order, 'kaufmaennisch_genehmigt')->assertSessionHasNoErrors();
        $this->transition($buyer, $order, 'bestellt')->assertSessionHasNoErrors();
        $this->actingAs($creator)->get(route('materialanforderung.order.export', [$order->id, 'docx']))->assertOk();
        $path = Storage::disk('local')->path('materialanforderungen/'.$order->id.'/bestellschein/Bestellschein.docx');
        $zip = new \ZipArchive(); $zip->open($path); $xml = $zip->getFromName('word/document.xml'); $zip->close();
        foreach (['Leistungsort:', 'Seminarraum &amp; Online', 'Teststraße 1', 'folgende Leistungen:', 'Nebenkosten netto'] as $text) $this->assertStringContainsString($text, $xml);
        foreach (['Lieferanschrift', 'Abholung', 'ALTE LIEFERADRESSE', 'LIEFERSCHEINEN', '${'] as $text) $this->assertStringNotContainsString($text, $xml);
        $html = view('pdf.materialanforderung', ['anforderung' => $order->fresh()->load('genehmigungen.genehmiger')])->render();
        foreach (['Lieferart', 'Lieferadresse', 'ALTE LIEFERADRESSE'] as $text) $this->assertStringNotContainsString($text, $html);
        $this->assertStringContainsString('Leistungsort', $html);
        $this->assertStringContainsString('Teststraße 1', $html);
        $this->actingAs($creator)->get(route('materialanforderung.pdf', $order))->assertOk();
    }

    public function test_gross_web_price_is_saved_without_adding_vat_again_and_reopens_unchanged(): void
    {
        [$order, $creator] = $this->context(100);
        $this->grantTestPermission($creator, 'materialanforderung.store');
        $creator->projekte()->attach($order->projekt_id);
        $creator->update(['current_team_id' => $order->projekt_id]);
        $data = $this->serviceForm($order);
        unset($data['id'], $data['revision'], $data['positionen'][0]['id']);
        $data['preisart'] = 'brutto';
        $data['positionen'][0]['einzelpreis'] = 279;
        $data['positionen'][0]['mwst'] = 19;
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasNoErrors();
        $created = Materialanforderung::latest('id')->first();
        $this->assertEquals(279, $created->endsumme);
        $this->assertEquals(234.45, $created->gesamtpreis);
        $this->assertSame('brutto', $created->preisart);
        $item = $created->artikeln()->first();
        $this->assertEquals(279, $item->einzelpreis_brutto);
        $this->assertEquals(234.45, $item->einzelpreis);
        $data['id'] = $created->id;
        $data['revision'] = $created->revision;
        $data['positionen'][0]['id'] = $item->id;
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertEquals(279, $created->fresh()->endsumme);
        $this->assertEquals($created->revision, $created->fresh()->revision);
        $this->actingAs($creator)->get(route('materialanforderung.show', $created))->assertOk()
            ->assertInertia(fn ($page) => $page->where('anforderung.preisart', 'brutto')->where('anforderung.endsumme', '279.00'));
    }

    public function test_gross_prices_keep_quantity_rounding_shipping_and_mixed_taxes_exact(): void
    {
        [$order, $creator] = $this->context(100);
        $data = $this->serviceForm($order);
        $data['preisart'] = 'brutto';
        $data['versand_netto'] = 5.95;
        $data['versand_mwst'] = 19;
        $data['positionen'][0] = array_replace($data['positionen'][0], ['stueck' => 3, 'einzelpreis' => 279, 'mwst' => 19]);
        $data['positionen'][] = ['pos' => 2, 'artikel' => 'Buch', 'stueck' => 2, 'einzelpreis' => 10.70, 'mwst' => 7];
        $data['positionen'][] = ['pos' => 3, 'artikel' => 'Kleinbetrag', 'stueck' => 1, 'einzelpreis' => 0.03, 'mwst' => 19];
        $data['positionen'][] = ['pos' => 4, 'artikel' => 'Ohne Steuer', 'stueck' => 1, 'einzelpreis' => 10, 'mwst' => 0];
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertEquals(874.38, $fresh->endsumme);
        $this->assertEquals(738.39, $fresh->gesamtpreis);
        $this->assertEquals(5, $fresh->versand_netto);
        $this->assertEquals(5.95, $fresh->versand_brutto);
        $this->assertEquals(703.36, $fresh->artikeln()->orderBy('pos')->first()->gesamtpreis);
        $this->assertEquals(874.38, $fresh->berechneEndsumme());
        $this->assertEquals(0.03, $fresh->artikeln()->where('pos', 3)->first()->gesamtMitMwst());
    }

    public function test_gross_boundary_uses_entered_total_and_invalid_mode_is_rejected(): void
    {
        [$order, $creator] = $this->context(100);
        $data = $this->serviceForm($order);
        $data['preisart'] = 'brutto';
        $data['positionen'][0]['einzelpreis'] = 500;
        $data['positionen'][0]['mwst'] = 19;
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertFalse(app(PurchaseWorkflow::class)->evaluate($order->fresh())['gf_required']);
        $data['revision'] = $order->fresh()->revision;
        $data['versand_netto'] = 0.01;
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertEquals(500.01, $order->fresh()->endsumme);
        $this->assertTrue(app(PurchaseWorkflow::class)->evaluate($order->fresh())['gf_required']);
        $data['preisart'] = 'unknown';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasErrors('preisart');
        $this->assertEquals(500.01, $order->fresh()->endsumme);
    }

    public function test_net_input_and_legacy_requests_keep_their_price_basis(): void
    {
        [$order, $creator] = $this->context(100);
        $this->assertSame('netto', $order->fresh()->preisart);
        $data = $this->serviceForm($order);
        $data['positionen'][0]['einzelpreis'] = 279;
        $data['positionen'][0]['mwst'] = 19;
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertEquals(332.01, $order->fresh()->endsumme);
        $this->assertNull($order->fresh()->artikeln()->first()->einzelpreis_brutto);
        $data['revision'] = $order->fresh()->revision;
        $data['preisart'] = 'brutto';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertEquals(279, $order->fresh()->endsumme);
        $data['revision'] = $order->fresh()->revision;
        $data['preisart'] = 'netto';
        $this->actingAs($creator)->put(route('materialanforderung.update'), $data)->assertSessionHasNoErrors();
        $this->assertEquals(332.01, $order->fresh()->endsumme);
        $this->assertNull($order->fresh()->artikeln()->first()->einzelpreis_brutto);
        $this->assertNull($order->fresh()->versand_brutto);
    }

    public function test_gross_offer_selection_and_exports_preserve_entered_price_and_tax_rounding(): void
    {
        [$order, $creator] = $this->context(100);
        $item = $order->artikeln()->first();
        $item->update(['stueck' => 3]);
        $this->actingAs($creator)->post(route('materialanforderung.offers.store', $order), [
            'preisart' => 'brutto', 'revision' => $order->fresh()->revision, 'lieferant' => 'Anbieter',
            'lieferant_adresse' => 'Anbieterstraße 1', 'angebotsdatum' => today()->toDateString(),
            'versand_netto' => 0.03, 'versand_mwst' => 19,
            'positionen' => [['id' => $item->id, 'einzelpreis' => 27.90, 'mwst' => 19]],
            'datei' => UploadedFile::fake()->create('angebot.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $offer = $order->angebote()->first();
        $this->assertEquals(83.73, $offer->brutto);
        app(PurchaseWorkflow::class)->selectOffer($order->fresh(), $offer->id);
        $order = $order->fresh();
        $this->assertSame('brutto', $order->preisart);
        $this->assertEquals(27.90, $order->artikeln()->first()->einzelpreis_brutto);
        $this->assertEquals(83.73, $order->berechneEndsumme());
        $this->assertEquals(70.37, $order->gesamtpreis);
        $snapshot = app(PurchaseOrderExport::class)->snapshot($order);
        $path = Storage::disk('local')->path('gross.docx');
        app(PurchaseOrderExport::class)->write($snapshot, $path);
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('Einzel brutto', $xml);
        $this->assertStringContainsString('27,90 EUR', $xml);
        $this->assertStringContainsString('83,73 EUR', $xml);
        $this->assertStringContainsString('13,36 EUR', $xml);
        $html = view('pdf.materialanforderung', ['anforderung' => $order->load(['projekt', 'besteller.person', 'artikeln', 'vergabevermerk', 'genehmigungen.genehmiger'])])->render();
        $this->assertStringContainsString('Einzelpreis brutto', $html);
        $this->assertStringContainsString('27,90', $html);
        $this->actingAs($creator)->get(route('materialanforderung.pdf', $order))->assertOk();
    }

    public function test_new_request_and_multiple_quotes_are_saved_together_with_correct_item_mapping(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $data['positionen'][] = ['client_key' => 'second', 'pos' => 2, 'artikel' => 'Zubehör', 'stueck' => 2, 'einzelpreis' => 10.70, 'mwst' => 7];
        foreach (['Anbieter A', 'Anbieter B', 'Anbieter C'] as $i => $supplier) {
            $offer = $this->inlineQuote($supplier, 600 + 10 * $i);
            // Deliberately reverse quote rows; associate them by stable key, never array index.
            array_unshift($offer['positionen'], ['position_key' => 'second', 'einzelpreis' => 10.70, 'mwst' => 7]);
            $offer['empfohlen'] = $i === 1;
            $data['angebote'][] = $offer;
        }
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasNoErrors();
        $order = Materialanforderung::latest('id')->first();
        $this->assertNotSame($existing->id, $order->id);
        $this->assertSame('entwurf', $order->status);
        $this->assertEquals(621.40, $order->endsumme);
        $this->assertCount(3, $order->angebote);
        $mainId = $order->artikeln()->where('pos', 1)->value('id');
        $secondId = $order->artikeln()->where('pos', 2)->value('id');
        foreach ($order->angebote as $i => $offer) {
            $this->assertSame($order->revision, $offer->revision);
            $this->assertSame((int) $creator->id, (int) $offer->created_by);
            $this->assertEquals(621.40 + 10 * $i, $offer->brutto);
            $this->assertEquals(600 + 10 * $i, collect($offer->positionen)->firstWhere('id', $mainId)['einzelpreis_brutto']);
            $this->assertSame(2, collect($offer->positionen)->firstWhere('id', $secondId)['stueck']);
            Storage::disk('local')->assertExists($offer->getRawOriginal('path'));
        }
        $this->assertSame('Anbieter B', $order->angebote->firstWhere('empfohlen', true)->lieferant);
        $this->assertCount(3, Storage::disk('local')->allFiles());
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasNoErrors();
        $this->assertSame('eingereicht', $order->fresh()->status);
    }

    public function test_incomplete_inline_quote_is_reported_before_any_request_or_file_is_saved(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $data['angebote'] = [$this->inlineQuote('A'), $this->inlineQuote('B')];
        unset($data['angebote'][1]['datei']);
        $before = Materialanforderung::count();
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasErrors('angebote.1.datei');
        $this->assertSame($before, Materialanforderung::count());
        $this->assertDatabaseCount('purchase_offers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_inline_quote_dates_prices_and_document_types_are_validated(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $offer = $this->inlineQuote('A');
        $offer['gueltig_bis'] = today()->subDay()->toDateString();
        $offer['positionen'][0]['einzelpreis'] = -1;
        $offer['datei'] = UploadedFile::fake()->create('script.exe', 1, 'application/octet-stream');
        $data['angebote'] = [$offer];
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)
            ->assertSessionHasErrors(['angebote.0.gueltig_bis', 'angebote.0.positionen.0.einzelpreis', 'angebote.0.datei']);
        $this->assertDatabaseCount('purchase_offers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_stale_inline_item_key_rolls_back_request_number_and_already_uploaded_quote(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $data['angebote'] = [$this->inlineQuote('A'), $this->inlineQuote('B')];
        $data['angebote'][1]['positionen'][0]['position_key'] = 'removed-item';
        $before = Materialanforderung::count();
        $numbers = DB::table('purchase_numbers')->count();
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasErrors('angebote.1.positionen');
        $this->assertSame($before, Materialanforderung::count());
        $this->assertSame($numbers, DB::table('purchase_numbers')->count());
        $this->assertDatabaseCount('purchase_offers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_quote_write_failure_cleans_up_previous_files_and_rolls_back_new_request(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $data['angebote'] = [$this->inlineQuote('A'), $this->inlineQuote('B')];
        $writer = new \App\Services\Purchasing\PurchaseOfferWriter();
        $this->mock(\App\Services\Purchasing\PurchaseOfferWriter::class, function ($mock) use ($writer) {
            $mock->shouldReceive('store')->twice()->andReturnUsing(function ($order, $offer, $file, $userId, &$paths, $prefix) use ($writer) {
                if ($offer['lieferant'] === 'B') throw \Illuminate\Validation\ValidationException::withMessages([$prefix.'datei' => 'Datei konnte nicht gespeichert werden.']);
                return $writer->store($order, $offer, $file, $userId, $paths, $prefix);
            });
        });
        $before = Materialanforderung::count();
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasErrors('angebote.1.datei');
        $this->assertSame($before, Materialanforderung::count());
        $this->assertDatabaseCount('purchase_offers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_draft_with_fewer_inline_offers_can_be_saved_but_cannot_bypass_quote_requirement(): void
    {
        [$existing, $creator] = $this->context(100);
        $data = $this->inlineRequest($existing, $creator);
        $data['angebote'] = [$this->inlineQuote('A')];
        $this->actingAs($creator)->post(route('materialanforderung.store'), $data)->assertSessionHasNoErrors();
        $order = Materialanforderung::latest('id')->first();
        $this->assertSame('entwurf', $order->status);
        $this->assertCount(1, $order->angebote);
        $this->transition($creator, $order, 'eingereicht')->assertSessionHasErrors('angebote');
        $this->assertSame('entwurf', $order->fresh()->status);
    }

    private function inlineRequest(Materialanforderung $existing, User $creator): array
    {
        $this->grantTestPermission($creator, 'materialanforderung.store');
        $creator->projekte()->syncWithoutDetaching([$existing->projekt_id]);
        $creator->update(['current_team_id' => $existing->projekt_id]);
        $data = $this->serviceForm($existing);
        unset($data['id'], $data['revision'], $data['positionen'][0]['id']);
        $data['preisart'] = 'brutto';
        $data['positionen'][0] = array_replace($data['positionen'][0], ['client_key' => 'main', 'einzelpreis' => 600, 'mwst' => 19]);
        return $data;
    }

    private function inlineQuote(string $supplier, float $price = 600): array
    {
        return ['preisart' => 'brutto', 'lieferant' => $supplier, 'lieferant_adresse' => 'Anbieterstraße 1',
            'angebotsdatum' => today()->toDateString(), 'gueltig_bis' => today()->addWeek()->toDateString(),
            'versand_netto' => 0, 'versand_mwst' => 19, 'empfohlen' => false,
            'positionen' => [['position_key' => 'main', 'einzelpreis' => $price, 'mwst' => 19]],
            'datei' => UploadedFile::fake()->create('angebot.pdf', 10, 'application/pdf')];
    }

    private function serviceForm(Materialanforderung $order): array
    {
        $cost = DB::table('kostenstelles')->where('kostenstelle', '12345')->value('id')
            ?? DB::table('kostenstelles')->insertGetId(['kostenstelle' => '12345', 'created_at' => now(), 'updated_at' => now()]);
        $order->projekt->kostenstellen()->syncWithoutDetaching([$cost]);
        $item = $order->artikeln()->first();
        return ['id' => $order->id, 'revision' => $order->revision, 'standort_id' => $order->standort_id,
            'kostenstelle' => '12345', 'prioritaet' => 'normal', 'lieferant_adresse' => $order->lieferant_adresse,
            'positionen' => [['id' => $item->id, 'pos' => 1, 'artikel' => $item->artikel, 'stueck' => 1, 'einzelpreis' => 100, 'mwst' => 0]],
            'vergabe' => ['lieferung_art' => 'Dienstleistung', 'lieferant' => 'Testlieferant', 'leistungsort' => 'Werkstatt, Raum 2']];
    }

    private function context(float $gross): array
    {
        $project = Projekt::factory()->create();
        $site = Standort::factory()->create();
        $users = [];
        foreach (['creator', 'head', 'commercial', 'buyer', 'director'] as $key) $users[$key] = User::factory()->create();
        foreach (['materialanforderung.update', 'materialanforderung.show', 'materialanforderung.index'] as $p) $this->grantTestPermission($users['creator'], $p);
        $this->grantTestPermission($users['head'], 'materialanforderung.sachlische_freigabe.update');
        $this->grantTestPermission($users['commercial'], 'materialanforderung.kaufmännische_freigabe.update');
        $this->grantTestPermission($users['buyer'], 'materialanforderung.bestellwesen.update');
        $users['head']->projekte()->attach($project->id);
        PurchaseRule::current()->update(['approver_ids' => [$users['director']->id]]);
        $order = Materialanforderung::create(['projekt_id' => $project->id, 'standort_id' => $site->id,
            'kostenstelle' => '12345', 'status' => 'entwurf', 'gesamtpreis' => $gross, 'endsumme' => $gross,
            'lieferant_adresse' => 'Teststraße 1, 66111 Saarbrücken', 'ersteller_id' => $users['creator']->id]);
        $order->artikeln()->create(['pos' => 1, 'artikel' => 'Material', 'stueck' => 1, 'einzelpreis' => $gross, 'gesamtpreis' => $gross, 'mwst' => 0]);
        $order->vergabevermerk()->create(['lieferant' => 'Testlieferant', 'lieferung_art' => 'Lieferleistung', 'lieferung_option' => 'per Lieferung', 'lieferadresse' => 'Schulstraße 5']);
        return [$order, ...array_values($users)];
    }

    private function transition(User $user, Materialanforderung $order, string $status, array $data = [])
    {
        return $this->actingAs($user)->put(route('materialanforderung.genehmigen', [$order->id, $status]), $data);
    }

    private function offer(User $creator, Materialanforderung $order, string $supplier, float $price = 600): void
    {
        $this->actingAs($creator)->post(route('materialanforderung.offers.store', $order), [
            'revision' => $order->fresh()->revision, 'lieferant' => $supplier, 'lieferant_adresse' => 'Anbieterstraße 1',
            'angebotsdatum' => today()->toDateString(), 'versand_netto' => 0, 'versand_mwst' => 19,
            'positionen' => [['id' => $order->artikeln()->first()->id, 'einzelpreis' => $price, 'mwst' => 0]],
            'datei' => UploadedFile::fake()->create('angebot.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalCommunicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_explicit_members_can_read_and_write_a_direct_conversation(): void
    {
        Notification::fake();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $outsider = User::factory()->create();
        foreach ([$sender, $receiver, $outsider] as $user) {
            $this->grantTestPermission($user, 'chat.use');
        }

        $this->actingAs($sender)
            ->post(route('chat.conversations.store'), [
                'type' => 'direct',
                'member_ids' => [$receiver->id],
            ])
            ->assertRedirect();

        $conversation = StaffConversation::firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$sender->id, $receiver->id],
            $conversation->members()->pluck('users.id')->all()
        );

        $this->actingAs($sender)
            ->post(route('chat.messages.store', $conversation), ['body' => 'Interne Testnachricht'])
            ->assertRedirect(route('chat.index', ['conversation' => $conversation->id]));

        $message = StaffMessage::firstOrFail();
        $this->assertSame('Interne Testnachricht', $message->body);
        $this->assertNotNull($message->expires_at);

        $this->actingAs($receiver)
            ->get(route('chat.index', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedConversationId', $conversation->id)
                ->where('messages.0.body', 'Interne Testnachricht'));

        $this->actingAs($outsider)
            ->get(route('chat.index', ['conversation' => $conversation->id]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('chat.messages.store', $conversation), ['body' => 'Unzulässiger Zugriff'])
            ->assertForbidden();
    }

    public function test_participant_account_cannot_use_staff_chat_even_with_permission(): void
    {
        $participant = User::factory()->create();
        $participant->person->update(['typ' => 'teilnehmer']);
        $this->grantTestPermission($participant, 'chat.use');

        $this->actingAs($participant)->get(route('chat.index'))->assertForbidden();
    }

    public function test_material_request_link_is_rejected_when_any_chat_member_lacks_access(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create();
        $creator = User::factory()->create();
        $receiver = User::factory()->create();
        foreach ([$creator, $receiver] as $user) {
            $this->grantTestPermission($user, 'chat.use');
        }
        $conversation = StaffConversation::create([
            'type' => 'direct',
            'created_by_user_id' => $creator->id,
            'retention_days' => 365,
        ]);
        $conversation->members()->attach([$creator->id, $receiver->id], [
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $materialRequest = Materialanforderung::create([
            'projekt_id' => $project->id,
            'kostenstelle' => '4711',
            'prioritaet' => 'normal',
            'status' => 'entwurf',
            'gesamtpreis' => 10,
            'endsumme' => 11.90,
            'ersteller_id' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->post(route('chat.messages.store', $conversation), [
                'body' => 'Bitte prüfen.',
                'materialanforderung_id' => $materialRequest->id,
            ])
            ->assertSessionHasErrors('materialanforderung_id');

        $this->assertDatabaseCount('staff_messages', 0);
    }

    public function test_expired_chat_message_and_private_attachment_are_physically_purged(): void
    {
        Storage::fake('local');
        $sender = User::factory()->create();
        $conversation = StaffConversation::create([
            'type' => 'direct',
            'created_by_user_id' => $sender->id,
            'retention_days' => 30,
        ]);
        $conversation->members()->attach($sender->id, [
            'joined_at' => now()->subMonth(),
            'last_read_at' => now()->subMonth(),
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);
        $message = $conversation->messages()->create([
            'sender_user_id' => $sender->id,
            'body' => 'Abgelaufene Nachricht',
            'expires_at' => now()->subMinute(),
        ]);
        $path = "internal-chat/{$conversation->id}/{$message->id}/test.txt";
        Storage::disk('local')->put($path, 'vertraulich');
        $message->attachments()->create([
            'original_name' => 'test.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 12,
        ]);

        $this->artisan('chat:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('staff_messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_material_request_question_blocks_order_until_it_is_resolved(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create();
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $buyer = User::factory()->create();
        $this->grantTestPermission($buyer, 'materialanforderung.bestellwesen.update');

        $request = Materialanforderung::create([
            'projekt_id' => $project->id,
            'kostenstelle' => '4711',
            'prioritaet' => 'normal',
            'status' => 'kaufmaennisch_genehmigt',
            'gesamtpreis' => 100,
            'endsumme' => 119,
            'ersteller_id' => $creator->id,
        ]);
        $article = $request->artikeln()->create([
            'pos' => 1,
            'artikel' => 'Drucker',
            'stueck' => 1,
            'einzelpreis' => 100,
            'gesamtpreis' => 100,
            'mwst' => 19,
        ]);

        $this->actingAs($buyer)
            ->post(route('materialanforderung.kommentare.store', $request), [
                'artikel_id' => $article->id,
                'grund' => 'preis_geaendert',
                'body' => 'Der Preis ist auf 125 Euro gestiegen. Bitte bestätigen.',
                'vorgeschlagener_preis' => 125,
                'antwort_erforderlich' => true,
            ])
            ->assertRedirect();

        $question = $request->kommentare()->firstOrFail();
        $this->assertTrue($question->antwort_erforderlich);
        $this->assertNull($question->geklaert_am);

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$request->id, 'bestellt']), [
                'bestellnummer' => 'B-4711',
            ])
            ->assertSessionHasErrors('status');
        $this->assertSame('kaufmaennisch_genehmigt', $request->fresh()->status);

        $this->actingAs($creator)
            ->put(route('materialanforderung.kommentare.resolve', $question))
            ->assertRedirect();
        $this->assertNotNull($question->fresh()->geklaert_am);

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$request->id, 'bestellt']), [
                'bestellnummer' => 'B-4711',
            ])
            ->assertRedirect();
        $this->assertSame('bestellt', $request->fresh()->status);
    }

    public function test_bestellwesen_can_return_approved_request_for_revision_with_reason(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create();
        $creator = User::factory()->create();
        $buyer = User::factory()->create();
        $this->grantTestPermission($buyer, 'materialanforderung.bestellwesen.update');

        $request = Materialanforderung::create([
            'projekt_id' => $project->id,
            'kostenstelle' => '4711',
            'prioritaet' => 'normal',
            'status' => 'kaufmaennisch_genehmigt',
            'gesamtpreis' => 100,
            'endsumme' => 119,
            'ersteller_id' => $creator->id,
        ]);

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$request->id, 'zur_ueberarbeitung']), [
                'anmerkung' => 'Das gewünschte Produkt ist nicht mehr lieferbar.',
            ])
            ->assertRedirect();

        $this->assertSame('zur_ueberarbeitung', $request->fresh()->status);
        $this->assertDatabaseHas('materialanforderung_genehmigungs', [
            'anforderung_id' => $request->id,
            'genehmiger_id' => $buyer->id,
            'status' => 'zur_ueberarbeitung',
            'kommentar' => 'Das gewünschte Produkt ist nicht mehr lieferbar.',
        ]);
    }
}

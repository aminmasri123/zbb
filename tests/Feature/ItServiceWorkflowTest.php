<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\ItTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ItServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_create_it_ticket(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('it-service.tickets.store'), [
            'titel' => 'Notebook startet nicht',
            'kategorie' => 'hardware',
            'prioritaet' => 'normal',
            'beschreibung' => 'Das Geraet bleibt beim Startbildschirm stehen.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('it_tickets', ['titel' => 'Notebook startet nicht']);
    }

    public function test_user_with_permission_can_create_it_ticket(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->ensurePermission('it.ticket.update');
        $this->givePermission($user, 'it.ticket.store');

        $response = $this->actingAs($user)->post(route('it-service.tickets.store'), [
            'titel' => 'Drucker meldet Papierstau',
            'kategorie' => 'drucker',
            'prioritaet' => 'hoch',
            'beschreibung' => 'Papier wurde entfernt, Meldung bleibt bestehen.',
        ]);

        $response->assertCreated()->assertJsonPath('ticket.titel', 'Drucker meldet Papierstau');

        $ticket = ItTicket::query()->where('titel', 'Drucker meldet Papierstau')->firstOrFail();
        $this->assertSame('neu', $ticket->status);
        $this->assertSame($user->id, $ticket->gemeldet_von_user_id);
        $this->assertSame($user->person_id, $ticket->gemeldet_von_personen_id);
        $this->assertNotNull($ticket->ticket_nr);
    }

    public function test_ticket_update_sets_resolution_fields_when_status_is_resolved(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->givePermission($user, 'it.ticket.update');

        $ticket = ItTicket::create([
            'titel' => 'VPN Verbindung instabil',
            'kategorie' => 'netzwerk',
            'prioritaet' => 'normal',
            'status' => 'neu',
            'beschreibung' => 'Verbindung bricht mehrfach ab.',
        ]);

        $response = $this->actingAs($user)->put(route('it-service.tickets.update', $ticket), [
            'titel' => $ticket->titel,
            'kategorie' => $ticket->kategorie,
            'prioritaet' => $ticket->prioritaet,
            'status' => 'geloest',
            'beschreibung' => $ticket->beschreibung,
            'loesung' => 'VPN Profil wurde neu eingerichtet.',
        ]);

        $response->assertOk()->assertJsonPath('ticket.status', 'geloest');

        $ticket->refresh();
        $this->assertNotNull($ticket->geloest_at);
        $this->assertSame($user->person_id, $ticket->geloest_von_personen_id);
        $this->assertNull($ticket->geschlossen_at);
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $this->ensurePermission($permissionName);
        $user->givePermissionTo($permissionName);
    }

    private function ensurePermission(string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'IT-Service'],
            ['beschreibung' => '']
        )->id;

        Permission::query()->updateOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'web',
            ],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

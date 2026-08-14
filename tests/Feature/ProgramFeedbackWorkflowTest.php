<?php

namespace Tests\Feature;

use App\Models\ProgramFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProgramFeedbackWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_colleague_can_submit_feedback_without_special_permission(): void
    {
        Notification::fake();
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('program-feedback.store'), [
            'type' => 'bug',
            'title' => 'Speichern reagiert nicht',
            'description' => 'Beim Speichern passiert nichts.',
            'expected_result' => 'Die Daten sollen gespeichert werden.',
            'area' => 'Teilnehmer',
            'priority' => 'high',
            'page_url' => '/teilnehmer/12',
            'attachments' => [UploadedFile::fake()->image('fehler.png')],
        ]);

        $response->assertCreated()
            ->assertJsonPath('feedback.type', 'bug')
            ->assertJsonPath('feedback.user_id', $user->id);

        $feedback = ProgramFeedback::firstOrFail();
        $this->assertMatchesRegularExpression('/^FB-\d{4}-\d{4}$/', $feedback->reference);
        $this->assertCount(1, $feedback->attachments);
        Storage::disk('local')->assertExists($feedback->attachments->first()->path);
    }

    public function test_colleague_only_sees_own_feedback(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->feedbackFor($user, 'Meine Meldung');
        $this->feedbackFor($other, 'Fremde Meldung');

        $this->actingAs($user)
            ->get(route('program-feedback.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProgramFeedback/Index')
                ->where('canManage', false)
                ->has('feedbackItems', 1)
                ->where('feedbackItems.0.id', $mine->id));
    }

    public function test_colleague_cannot_manage_another_users_feedback(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $feedback = $this->feedbackFor($other, 'Fremde Meldung');

        $this->actingAs($user)->put(route('program-feedback.update', $feedback), [
            'status' => 'in_progress',
            'priority' => 'high',
        ])->assertForbidden();

        $this->assertSame('new', $feedback->fresh()->status);
    }

    public function test_manager_can_update_feedback_and_status_history_is_recorded(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $this->giveManagePermission($manager);
        $feedback = $this->feedbackFor($owner, 'Neue Idee');

        $this->actingAs($manager)->put(route('program-feedback.update', $feedback), [
            'status' => 'planned',
            'priority' => 'normal',
            'assigned_to_user_id' => $manager->id,
            'release_version' => '2026.08.2',
            'status_note' => 'Für den nächsten Sprint eingeplant.',
        ])->assertOk()->assertJsonPath('feedback.status', 'planned');

        $this->assertDatabaseHas('program_feedback_history', [
            'program_feedback_id' => $feedback->id,
            'from_status' => 'new',
            'to_status' => 'planned',
        ]);
    }

    public function test_public_comments_are_visible_to_owner_but_internal_notes_are_not(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $this->giveManagePermission($manager);
        $feedback = $this->feedbackFor($owner, 'Rückfrage nötig');

        $this->actingAs($manager)->post(route('program-feedback.comments.store', $feedback), [
            'body' => 'Öffentliche Rückfrage',
            'is_internal' => false,
        ])->assertOk();

        $this->actingAs($manager)->post(route('program-feedback.comments.store', $feedback), [
            'body' => 'Nur intern sichtbar',
            'is_internal' => true,
        ])->assertOk();

        $this->actingAs($owner)
            ->get(route('program-feedback.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('feedbackItems.0.comments', 1)
                ->where('feedbackItems.0.comments.0.body', 'Öffentliche Rückfrage'));
    }

    private function feedbackFor(User $user, string $title): ProgramFeedback
    {
        return ProgramFeedback::create([
            'reference' => 'FB-2026-' . str_pad((string) (ProgramFeedback::count() + 1), 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'type' => 'suggestion',
            'title' => $title,
            'description' => 'Ausführliche Beschreibung',
            'priority' => 'normal',
            'status' => 'new',
        ]);
    }

    private function giveManagePermission(User $user): void
    {
        $permission = Permission::findByName('program-feedback.manage');
        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

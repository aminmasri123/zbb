<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'user.profil');
        $this->actingAs($user);

        $response = $this->put('/user/profile-information', [
            'name' => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->username);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_page_is_forbidden_without_profile_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/user/profile')->assertForbidden();

        $this->put('/user/profile-information', [
            'name' => 'Nicht erlaubt',
            'email' => 'nicht-erlaubt@example.com',
        ])->assertForbidden();

        $this->assertNotSame('nicht-erlaubt@example.com', $user->fresh()->email);
    }

    public function test_profile_page_is_available_with_profile_permission(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'user.profil');
        $this->actingAs($user);

        $this->get('/user/profile')->assertOk();
    }
}

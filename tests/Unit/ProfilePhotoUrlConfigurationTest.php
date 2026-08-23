<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class ProfilePhotoUrlConfigurationTest extends TestCase
{
    public function test_profile_photo_uses_a_host_relative_storage_url(): void
    {
        $publicUrl = rtrim((string) config('app.url'), '/').'/storage';

        $this->assertSame($publicUrl, config('filesystems.disks.public.url'));

        config(['filesystems.disks.public.url' => 'https://stale.example.test/public/storage']);

        $user = new User();
        $user->profile_photo_path = 'profile-photos/example.png';

        $this->assertSame('/storage/profile-photos/example.png', $user->profile_photo_url);
        $this->assertStringNotContainsString('/public/storage/', $user->profile_photo_url);
    }
}

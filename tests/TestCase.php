<?php

namespace Tests;

use App\Models\Berechtigungskategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function grantTestPermission(User $user, string $permissionName): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Test-Berechtigungen'],
            ['beschreibung' => 'Automatisch fuer Feature-Tests angelegt.']
        );

        Permission::query()->updateOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $category->id,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permissionName);
    }
}

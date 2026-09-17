<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Berechtigungskategorie;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = Permission::where('name', 'kooperationspartner.index')->value('berechtigungskategorie_id')
            ?: Berechtigungskategorie::firstOrCreate(['name' => 'Kooperationspartner'])->id;
        $permission = Permission::firstOrCreate([
            'name' => 'kooperationspartner.details.view', 'guard_name' => 'web',
        ], [
            'berechtigungskategorie_id' => $categoryId,
            'beschreibung' => 'Schulkontakt- und Zusatzdaten ansehen: Ansprechpartner, Anschriften, Telefonnummern, E-Mail-Adressen und Beschreibungen in der Partnerübersicht.',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Role::whereIn('name', ['Administrator', 'Developer'])->get() as $role) {
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep explicit assignments when application code is rolled back.
    }
};

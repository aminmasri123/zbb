<?php
namespace Database\Seeders;

use App\Models\Berechtigungskategorie;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchaseWorkflowPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $category = Berechtigungskategorie::firstOrCreate(['name' => 'Bestellungen']);
        $permissions = collect([
            'materialanforderung.settings.manage' => 'Freigabegrenzen, Standorte und Geschäftsführung für Bestellungen konfigurieren.',
            'materialanforderung.gf_freigabe' => 'Materialanforderungen als Geschäftsführung genehmigen oder zurückgeben.',
        ])->mapWithKeys(function (string $description, string $name) use ($category) {
            $permission = Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'berechtigungskategorie_id' => $category->id, 'beschreibung' => $description,
            ]);
            return [$name => $permission];
        });
        // Spatie may already have cached the permission list before this seeder
        // runs. Refresh it before assigning the permissions created above.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Role::whereIn('name', ['Administrator', 'Developer'])->get() as $role) {
            $role->givePermissionTo($permissions->get('materialanforderung.settings.manage'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

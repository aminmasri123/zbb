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
        foreach ([
            'materialanforderung.settings.manage' => 'Freigabegrenzen, Standorte und Geschäftsführung für Bestellungen konfigurieren.',
            'materialanforderung.gf_freigabe' => 'Materialanforderungen als Geschäftsführung genehmigen oder zurückgeben.',
        ] as $name => $description) {
            Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'berechtigungskategorie_id' => $category->id, 'beschreibung' => $description,
            ]);
        }
        foreach (Role::whereIn('name', ['Administrator', 'Developer'])->get() as $role) {
            $role->givePermissionTo('materialanforderung.settings.manage');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

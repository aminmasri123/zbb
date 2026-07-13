<?php

namespace Tests\Unit;

use App\Support\RoutePermissionMap;
use Database\Seeders\UserSeeder;
use ReflectionMethod;
use Tests\TestCase;

class BopPlanningPermissionTest extends TestCase
{
    public function test_selection_routes_separate_crud_and_planning_actions(): void
    {
        $this->assertSame([
            'bereichsauswahl.index',
            'bereichsauswahl.store',
            'bereichsauswahl.update',
            'bereichsauswahl.planning',
        ], RoutePermissionMap::permissionsFor('bereichsauswahl.index'));
        $this->assertSame(
            ['bereichsauswahl.planning'],
            RoutePermissionMap::permissionsFor('bereichsauswahl.setting.update')
        );
        $this->assertSame([
            'bereichsauswahl.store',
            'bereichsauswahl.update',
        ], RoutePermissionMap::permissionsFor('bereichsauswahl.bop.radio.update'));
    }

    public function test_assignment_planning_covers_parameters_round_swaps_and_group_generation(): void
    {
        $this->assertSame(['einteilung.planning'], RoutePermissionMap::permissionsFor('einteilung.parameter.update'));
        $this->assertSame(['einteilung.planning'], RoutePermissionMap::permissionsFor('einteilung.runden.switch'));
        $this->assertSame(['einteilung.planning'], RoutePermissionMap::permissionsFor('gruppen.generieren'));
        $this->assertSame(['einteilung.store'], RoutePermissionMap::permissionsFor('einteilung.create'));
        $this->assertSame(['einteilung.export'], RoutePermissionMap::permissionsFor('einteilung.export.excel'));
    }

    public function test_catalog_contains_only_the_canonical_selection_and_assignment_permissions(): void
    {
        $seeder = app(UserSeeder::class);
        $method = new ReflectionMethod($seeder, 'permissionCatalog');
        $method->setAccessible(true);
        $catalog = collect($method->invoke($seeder))->keyBy('name');

        $canonical = [
            'bereichsauswahl.index',
            'bereichsauswahl.store',
            'bereichsauswahl.update',
            'bereichsauswahl.destroy',
            'bereichsauswahl.planning',
            'einteilung.index',
            'einteilung.store',
            'einteilung.update',
            'einteilung.destroy',
            'einteilung.export',
            'einteilung.planning',
        ];

        foreach ($canonical as $permission) {
            $this->assertTrue($catalog->has($permission), "{$permission} fehlt im Permission-Katalog.");
            $this->assertNotEmpty($catalog->get($permission)['beschreibung']);
        }

        foreach ([
            'bereichsauswahl.setting.update',
            'bereichsauswahl.bop.radio.update',
            'einteilung.show',
            'einteilung.create',
            'einteilung.parameter.update',
            'einteilung.runden.switch',
            'gruppen.generieren',
            'einteilung.export.excel',
        ] as $technicalPermission) {
            $this->assertFalse($catalog->has($technicalPermission));
        }
    }
}

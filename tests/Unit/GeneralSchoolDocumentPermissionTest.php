<?php

namespace Tests\Unit;

use App\Support\RoutePermissionMap;
use Database\Seeders\UserSeeder;
use ReflectionMethod;
use Tests\TestCase;

class GeneralSchoolDocumentPermissionTest extends TestCase
{
    public function test_schoolwide_document_routes_share_one_general_export_permission(): void
    {
        foreach ([
            'hausordnung.export.schule.pdf',
            'export.auswertungsbogenPA.schule.pdf',
            'export.auswertungsbogenPA.roland.schule.pdf',
            'export.zertifikat.schule.pobo',
            'export.zertifikat.schule.pobo.pdf',
            'export.auswertungBO.schule.pdf',
            'auswertungPoboModal',
        ] as $routeName) {
            $this->assertSame(['dokumente.schule.export'], RoutePermissionMap::permissionsFor($routeName));
        }
    }

    public function test_contact_document_and_participant_list_routes_remain_separate(): void
    {
        foreach ([
            'export.elterneinverstaendniserklaerung.schule',
            'export.auswertungBO.schule.pdf.tofolder',
            'export.auswertungPA.schule.pdf.tofolder',
            'alleTeilnehmer.folder.create',
        ] as $routeName) {
            $this->assertSame(['dokumente.ansprechpartner.manage'], RoutePermissionMap::permissionsFor($routeName));
        }

        $this->assertSame(
            ['teilnehmer.liste.export'],
            RoutePermissionMap::permissionsFor('export.teilnehmerliste.schule.excel')
        );
    }

    public function test_catalog_contains_detailed_general_permissions_and_no_technical_duplicates(): void
    {
        $seeder = app(UserSeeder::class);
        $method = new ReflectionMethod($seeder, 'permissionCatalog');
        $method->setAccessible(true);
        $catalog = collect($method->invoke($seeder))->keyBy('name');

        $expected = [
            'dokumente.schule.export' => 10,
            'teilnehmer.liste.export' => 5,
            'dokumente.ansprechpartner.manage' => 14,
        ];

        foreach ($expected as $permission => $categoryId) {
            $this->assertTrue($catalog->has($permission), "{$permission} fehlt im Permission-Katalog.");
            $this->assertSame($categoryId, $catalog->get($permission)['berechtigungskategorie_id']);
            $this->assertGreaterThan(300, strlen($catalog->get($permission)['beschreibung']));
        }

        foreach ([
            'hausordnung.export.schule.pdf',
            'export.auswertungsbogenPA.schule.pdf',
            'export.auswertungsbogenPA.roland.schule.pdf',
            'export.elterneinverstaendniserklaerung.schule',
            'export.teilnehmerliste.schule.excel',
            'alleTeilnehmer.folder.create',
            'export.zertifikat.schule.pobo',
            'export.zertifikat.schule.pobo.pdf',
            'export.auswertungBO.schule.pdf',
            'export.auswertungBO.schule.pdf.tofolder',
            'export.auswertungPA.schule.pdf.tofolder',
            'auswertungPoboModal',
        ] as $technicalPermission) {
            $this->assertFalse($catalog->has($technicalPermission));
        }
    }
}

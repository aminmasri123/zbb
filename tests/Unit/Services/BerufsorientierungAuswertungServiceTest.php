<?php

namespace Tests\Unit\Services;

use App\Models\Projekt;
use App\Services\BerufsorientierungAuswertungService;
use PHPUnit\Framework\TestCase;

class BerufsorientierungAuswertungServiceTest extends TestCase
{
    public function test_bop_projects_receive_the_legacy_observation_points_by_default(): void
    {
        $project = new Projekt(['name' => 'BOP 2026']);
        $config = (new BerufsorientierungAuswertungService)->config($project);

        $this->assertTrue($config['enabled']);
        $this->assertCount(10, $config['criteria']);
        $this->assertSame('einhaltung_der_regeln', $config['criteria'][0]['key']);
        $this->assertSame('Hervorragend', $config['scale'][5]);
    }

    public function test_a_project_can_define_its_own_ordered_observation_points(): void
    {
        $project = new Projekt([
            'name' => 'Eigenes Projekt',
            'berufsorientierung_auswertung_config' => [
                'enabled' => true,
                'criteria' => [
                    ['key' => 'b', 'label' => 'Zweiter Punkt', 'sort_order' => 2],
                    ['key' => 'a', 'label' => 'Erster Punkt', 'sort_order' => 1, 'required' => false],
                ],
            ],
        ]);
        $config = (new BerufsorientierungAuswertungService)->config($project);

        $this->assertSame(['a', 'b'], array_column($config['criteria'], 'key'));
        $this->assertFalse($config['criteria'][0]['required']);
    }

    public function test_saved_bop_configuration_excludes_the_retired_point_without_changing_stored_settings(): void
    {
        $saved = ['enabled' => true, 'criteria' => BerufsorientierungAuswertungService::BOP_CRITERIA];
        $project = new Projekt(['name' => 'BOP', 'berufsorientierung_auswertung_config' => $saved]);

        $config = (new BerufsorientierungAuswertungService)->config($project);

        $this->assertCount(10, $config['criteria']);
        $this->assertNotContains(BerufsorientierungAuswertungService::RETIRED_BOP_CRITERION, array_column($config['criteria'], 'key'));
        $this->assertSame($saved, $project->berufsorientierung_auswertung_config);
    }

    public function test_other_projects_keep_their_configured_criteria(): void
    {
        $project = new Projekt([
            'name' => 'Anderes Projekt',
            'berufsorientierung_auswertung_config' => [
                'enabled' => true,
                'criteria' => BerufsorientierungAuswertungService::BOP_CRITERIA,
            ],
        ]);

        $this->assertCount(11, (new BerufsorientierungAuswertungService)->config($project)['criteria']);
    }
}

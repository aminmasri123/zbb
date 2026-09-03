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
        $this->assertCount(11, $config['criteria']);
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
}

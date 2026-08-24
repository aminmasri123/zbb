<?php

namespace Tests\Unit;

use App\Models\PotenzialanalyseUebung;
use App\Services\PotenzialanalyseResultCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PotenzialanalyseResultCalculatorTest extends TestCase
{
    #[Test]
    public function it_deducts_errors_from_the_configured_maximum_and_keeps_a_snapshot(): void
    {
        $exercise = new PotenzialanalyseUebung([
            'name' => 'Pinsel',
            'hoechstwert' => 20,
            'mindestwert' => 0,
            'berechnungsregel' => 'fehler_abzug',
            'fehler_abzug' => 1,
        ]);

        $result = app(PotenzialanalyseResultCalculator::class)->calculate($exercise, ['fehler' => 3]);

        $this->assertNull($result['punkte']);
        $this->assertSame(3, $result['fehler']);
        $this->assertSame(17.0, $result['berechnete_punkte']);
        $this->assertSame(20.0, $result['maximalpunkte_snapshot']);
        $this->assertSame(1.0, $result['fehler_abzug_snapshot']);
        $this->assertSame('fehler_abzug', $result['berechnungs_snapshot']['regel']);
    }

    #[Test]
    public function it_distinguishes_zero_errors_from_a_missing_input_and_never_goes_below_minimum(): void
    {
        $exercise = new PotenzialanalyseUebung([
            'name' => 'Schere',
            'hoechstwert' => 20,
            'mindestwert' => 0,
            'berechnungsregel' => 'fehler_abzug',
            'fehler_abzug' => 2,
        ]);
        $calculator = app(PotenzialanalyseResultCalculator::class);

        $this->assertSame(20.0, $calculator->calculate($exercise, ['fehler' => 0])['berechnete_punkte']);
        $this->assertNull($calculator->calculate($exercise, ['fehler' => null])['berechnete_punkte']);
        $this->assertSame(0.0, $calculator->calculate($exercise, ['fehler' => 99])['berechnete_punkte']);
    }

    #[Test]
    public function it_converts_each_exercises_individual_time_limits_to_a_rating(): void
    {
        $exercise = new PotenzialanalyseUebung([
            'name' => 'Schrauben',
            'hoechstwert' => 5,
            'mindestwert' => 1,
            'berechnungsregel' => 'zeit',
            'berechnungs_config' => [
                'zeitgrenzen' => [
                    'stufe_5_bis' => 210,
                    'stufe_4_bis' => 270,
                    'stufe_3_bis' => 330,
                    'stufe_2_bis' => 420,
                ],
            ],
        ]);
        $calculator = app(PotenzialanalyseResultCalculator::class);

        $this->assertSame(5.0, $calculator->calculate($exercise, ['zeit_min' => 3, 'zeit_sec' => 30])['berechnete_punkte']);
        $this->assertSame(4.0, $calculator->calculate($exercise, ['zeit' => 211])['berechnete_punkte']);
        $this->assertSame(3.0, $calculator->calculate($exercise, ['zeit' => 271])['berechnete_punkte']);
        $this->assertSame(2.0, $calculator->calculate($exercise, ['zeit' => 331])['berechnete_punkte']);
        $this->assertSame(1.0, $calculator->calculate($exercise, ['zeit_min' => 7, 'zeit_sec' => 1])['berechnete_punkte']);
        $this->assertNull($calculator->calculate($exercise, ['zeit' => null])['berechnete_punkte']);
    }
}

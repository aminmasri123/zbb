<?php

namespace Tests\Unit;

use App\Services\PotenzialanalyseScoringService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PotenzialanalyseScoringServiceTest extends TestCase
{
    private PotenzialanalyseScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PotenzialanalyseScoringService();
    }

    #[Test]
    public function it_calculates_weighted_exercise_scores_per_competency(): void
    {
        $exercises = new Collection([
            [
                'id' => 1,
                'name' => 'Teamaufgabe',
                'ergebnis_typ' => 'punkte',
                'mindestwert' => 0,
                'hoechstwert' => 100,
                'kompetenzen' => [['merkmal' => 'teamfaehigkeit', 'gewichtung' => 2, 'aktiv' => true]],
            ],
            [
                'id' => 2,
                'name' => 'Gruppenplanung',
                'ergebnis_typ' => 'punkte',
                'mindestwert' => 0,
                'hoechstwert' => 100,
                'kompetenzen' => [['merkmal' => 'teamfaehigkeit', 'gewichtung' => 1, 'aktiv' => true]],
            ],
        ]);

        $scores = $this->service->scoreExercises($exercises, [
            1 => ['punkte' => 80],
            2 => ['punkte' => 40],
        ]);

        $this->assertSame(66.7, $scores['teamfaehigkeit']['percentage']);
        $this->assertSame(4, $scores['teamfaehigkeit']['rating']);
        $this->assertCount(2, $scores['teamfaehigkeit']['contributions']);
    }

    #[Test]
    public function it_normalizes_a_configurable_scale(): void
    {
        $scores = $this->service->scoreExercises(new Collection([[
            'id' => 8,
            'name' => 'Sorgfaltsaufgabe',
            'ergebnis_typ' => 'skala',
            'mindestwert' => 1,
            'hoechstwert' => 5,
            'kompetenzen' => [['merkmal' => 'sorgfalt', 'gewichtung' => 100, 'aktiv' => true]],
        ]]), [8 => ['punkte' => 3]]);

        $this->assertSame(50.0, $scores['sorgfalt']['percentage']);
        $this->assertSame(3, $scores['sorgfalt']['rating']);
    }

    #[Test]
    public function it_ignores_exercises_that_are_not_evaluable(): void
    {
        $scores = $this->service->scoreExercises(new Collection([[
            'id' => 9,
            'name' => 'Nur Zeitmessung',
            'auswertbar' => false,
            'ergebnis_typ' => 'punkte',
            'mindestwert' => 0,
            'hoechstwert' => 10,
            'kompetenzen' => [['merkmal' => 'sorgfalt', 'gewichtung' => 100, 'aktiv' => true]],
        ]]), [9 => ['punkte' => 10]]);

        $this->assertNull($scores['sorgfalt']['percentage']);
        $this->assertNull($scores['sorgfalt']['rating']);
    }

    #[Test]
    public function it_combines_only_available_sources_using_the_configured_weights(): void
    {
        $exerciseScores = collect(PotenzialanalyseScoringService::COMPETENCIES)
            ->mapWithKeys(fn (array $item) => [$item['key'] => $item + ['percentage' => null]])
            ->all();
        $exerciseScores['kommunikation']['percentage'] = 80;

        $scores = $this->service->combinedScores(
            $exerciseScores,
            [],
            ['kommunikation' => ['bewertung' => 3]],
            ['source_weights' => ['exercises' => 60, 'coach' => 30, 'self' => 10]],
        );

        $this->assertSame(75.7, $scores['kommunikation']['percentage']);
        $this->assertSame(4, $scores['kommunikation']['rating']);
        $this->assertCount(2, $scores['kommunikation']['sources']);
    }

    #[Test]
    public function it_generates_a_positive_editable_report_from_all_sources(): void
    {
        $combined = collect(PotenzialanalyseScoringService::COMPETENCIES)
            ->mapWithKeys(fn (array $item) => [$item['key'] => $item + ['percentage' => null, 'rating' => null, 'sources' => []]])
            ->all();
        $combined['teamfaehigkeit']['percentage'] = 90;
        $combined['teamfaehigkeit']['rating'] = 5;
        $combined['arbeitsplanung']['percentage'] = 10;
        $combined['arbeitsplanung']['rating'] = 1;

        $exerciseScores = collect(PotenzialanalyseScoringService::COMPETENCIES)
            ->mapWithKeys(fn (array $item) => [$item['key'] => $item + ['contributions' => []]])
            ->all();
        $exerciseScores['teamfaehigkeit']['contributions'] = [['exercise' => 'Teamaufgabe', 'percentage' => 90]];

        $report = $this->service->generateReport(
            ['vorname' => 'Mina'],
            $combined,
            $exerciseScores,
            ['teamfaehigkeit' => ['bewertung' => 5]],
            ['teamfaehigkeit' => ['bewertung' => 4]],
            ['staerken' => 'Mina arbeitet verlässlich mit anderen zusammen', 'empfehlung' => 'Diese Stärke sollte weiter genutzt werden'],
        );

        $this->assertStringStartsWith("Hallo Mina,\n\n", $report['text']);
        $this->assertStringContainsString('Teamfähigkeit', $report['text']);
        $this->assertStringContainsString('Diese Stärke sollte weiter genutzt werden', $report['text']);
        $this->assertStringNotContainsString('Teamaufgabe', $report['text']);
        $this->assertStringNotContainsString('weiter stärken', $report['text']);
        $this->assertStringNotContainsString('...', $report['text']);
        $this->assertLessThanOrEqual(500, mb_strlen($report['text']));
    }
}

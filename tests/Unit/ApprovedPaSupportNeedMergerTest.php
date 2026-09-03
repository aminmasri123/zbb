<?php

namespace Tests\Unit;

use App\Services\Ai\ApprovedPaSupportNeedMerger;
use App\Services\Ai\Tools\GetParticipantPotentialAnalysisSupportNeedsTool;
use PHPUnit\Framework\TestCase;

class ApprovedPaSupportNeedMergerTest extends TestCase
{
    public function test_it_adds_approved_pa_fields_omitted_by_the_model(): void
    {
        $result = (new ApprovedPaSupportNeedMerger)->merge($this->report(), $this->toolResults([
            $this->entry('personal', 'competence.personal.support_need', 'support_need', 'Selbstständiges Arbeiten fällt noch schwer.', 'Selbstständiges Beginnen neuer Aufgaben einüben.'),
            $this->entry('social', 'competence.social.support_need', 'no_support_need', 'Kommuniziert zunehmend situationsgerecht.', 'Im Bereich Sozial-kommunikative Kompetenz wurde kein zusätzlicher Förderbedarf festgestellt.'),
        ]));

        $sections = collect($result['sections'])->keyBy(fn (array $section) => $this->fieldKey($section['heading']));

        $this->assertTrue($sections->has('competence.personal.support_need'));
        $this->assertTrue($sections->has('competence.social.support_need'));
        $this->assertSame(
            ['potential-analysis-support-17-personal'],
            $sections['competence.personal.support_need']['claims'][0]['source_ids'],
        );
        $this->assertStringContainsString(
            'Daraus wurde folgender Förderbedarf fachlich abgeleitet:',
            $sections['competence.personal.support_need']['claims'][0]['text'],
        );
        $this->assertStringContainsString(
            'Ein zusätzlicher Förderbedarf wurde fachlich nicht festgestellt.',
            $sections['competence.social.support_need']['claims'][0]['text'],
        );
        $this->assertStringContainsString('automatisch zugeordnet', implode(' ', $result['warnings']));
    }

    public function test_it_keeps_a_professional_model_text_when_it_cites_the_approved_pa_source(): void
    {
        $report = $this->report();
        $report['sections'][] = [
            'heading' => '[competence.personal.support_need] Personale Kompetenz – Förderbedarf',
            'claims' => [[
                'claim_id' => 'professional-pa-text',
                'text' => 'Die teilnehmende Person soll darin unterstützt werden, neue Aufgaben schrittweise selbstständig zu beginnen.',
                'status' => 'supported',
                'source_ids' => ['potential-analysis-support-17-personal'],
            ]],
        ];

        $result = (new ApprovedPaSupportNeedMerger)->merge($report, $this->toolResults([
            $this->entry('personal', 'competence.personal.support_need', 'support_need', '', 'Selbstständiges Beginnen neuer Aufgaben einüben.'),
        ]));
        $matches = collect($result['sections'])->filter(
            fn (array $section) => $this->fieldKey($section['heading']) === 'competence.personal.support_need'
        );

        $this->assertCount(1, $matches);
        $this->assertCount(1, $matches->first()['claims']);
        $this->assertSame('professional-pa-text', $matches->first()['claims'][0]['claim_id']);
    }

    public function test_it_consolidates_multiple_pa_entries_for_one_final_luv_field(): void
    {
        $result = (new ApprovedPaSupportNeedMerger)->merge($this->report('final'), $this->toolResults([
            $this->entry('personal', 'support.description', 'support_need', '', 'Selbstständiges Arbeiten weiter stärken.'),
            $this->entry('social', 'support.description', 'support_need', '', 'Mündlichen Ausdruck fördern.'),
        ]));
        $matches = collect($result['sections'])->filter(
            fn (array $section) => $this->fieldKey($section['heading']) === 'support.description'
        );

        $this->assertCount(1, $matches);
        $this->assertCount(2, $matches->first()['claims']);
        $this->assertSame(
            ['potential-analysis-support-17-personal', 'potential-analysis-support-17-social'],
            collect($matches->first()['claims'])->flatMap(fn (array $claim) => $claim['source_ids'])->all(),
        );
    }

    /** @return array<string, mixed> */
    private function report(string $type = 'luv'): array
    {
        return [
            'report_type' => $type,
            'title' => 'LuV-Entwurf',
            'sections' => [[
                'heading' => '[competence.school.assessment] Schulische Basiskompetenzen – Einschätzung',
                'claims' => [[
                    'claim_id' => 'school-1',
                    'text' => 'Eine schulische Beobachtung liegt vor.',
                    'status' => 'supported',
                    'source_ids' => ['participant-development-summary'],
                ]],
            ]],
            'warnings' => [],
        ];
    }

    /** @param list<array<string, mixed>> $entries */
    private function toolResults(array $entries): array
    {
        return [[
            'role' => 'tool',
            'tool_name' => GetParticipantPotentialAnalysisSupportNeedsTool::NAME,
            'content' => [
                'source_id' => 'participant-potential-analysis-support-summary',
                'entries' => $entries,
            ],
        ]];
    }

    /** @return array<string, mixed> */
    private function entry(string $key, string $fieldKey, string $decision, string $observation, string $supportNeed): array
    {
        return [
            'source_id' => "potential-analysis-support-17-{$key}",
            'field_key' => $fieldKey,
            'category_key' => $key,
            'category' => match ($key) {
                'personal' => 'Personale Kompetenz',
                'social' => 'Sozial-kommunikative Kompetenz',
                default => 'Methodische Kompetenz',
            },
            'decision' => $decision,
            'observation' => $observation,
            'support_need' => $supportNeed,
        ];
    }

    private function fieldKey(string $heading): string
    {
        preg_match('/^\[([^]]+)\]/', $heading, $match);

        return $match[1] ?? '';
    }
}

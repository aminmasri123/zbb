<?php

namespace App\Services\Ai\Tools;

use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetParticipantDevelopmentDataTool implements AiTool
{
    use AuthorizesParticipantTool;

    public const NAME = 'get_participant_development_data';

    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        $this->assertNoArguments($arguments);
        $participation = $this->participation($user, $context);

        $internships = ! ($context->sourceSettings['internships'] ?? true) ? [] : PersonenHasBildungsmassnahmen::query()
            ->where('projekt_person_id', $participation->id)
            ->whereNull('archived_at')
            ->where(function ($query) use ($context): void {
                $query->whereNull('end')->orWhereDate('end', '>=', $context->fromDate);
            })
            ->where(function ($query) use ($context): void {
                $query->whereNull('start')->orWhereDate('start', '<=', $context->untilDate);
            })
            ->orderBy('start')
            ->limit(50)
            ->get()
            ->map(fn ($entry) => [
                'source_id' => 'development-internship-'.$entry->id,
                'type' => $entry->typ,
                'placement_type' => $entry->placement_type,
                'organization' => $entry->traeger,
                'occupation' => $entry->occupation,
                'from' => $entry->start?->toDateString(),
                'until' => $entry->end?->toDateString(),
                'objective' => $entry->objective,
                'activities' => $entry->activities,
                'assessment' => $entry->assessment,
                'result' => $entry->result,
                'status' => $entry->status,
            ])->all();

        $completionReports = ! ($context->sourceSettings['education'] ?? true) ? [] : $participation->completionReports()
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('version')
            ->get()
            ->map(fn ($report) => [
                'source_id' => 'development-completion-'.$report->id,
                'version' => $report->version,
                'completion_type' => $report->completion_type,
                'exit_date' => $report->exit_date?->toDateString(),
                'outcome' => $report->outcome,
                'summary' => $report->summary,
                'recommendations' => $report->recommendations,
            ])->all();

        $consents = ! ($context->sourceSettings['consents'] ?? true) ? [] : $participation->consentEvents()
            ->whereIn('action', ['granted', 'revoked'])
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('definition_key')
            ->map(fn ($events) => ($event = $events->last()) ? [
                'source_id' => 'development-consent-'.$event->id,
                'key' => $event->definition_key,
                'title' => $event->title_snapshot,
                'status' => $event->action,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ] : null)
            ->filter()
            ->values()
            ->all();

        return [
            'source_id' => 'participant-development-summary',
            'period' => ['from' => $context->fromDate, 'until' => $context->untilDate],
            'internships_and_measures' => $internships,
            'completion_reports' => $completionReports,
            'consents' => $consents,
        ];
    }
}

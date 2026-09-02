<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetParticipantIdentitySummaryTool implements AiTool
{
    use AuthorizesParticipantTool;

    public const NAME = 'get_participant_identity_summary';

    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        $this->assertNoArguments($arguments);
        $participation = $this->participation($user, $context);
        $participation->loadMissing(['teilnehmer.sozialedaten', 'teilnehmer.kontaktes.kontakttyp', 'meta.betreuer', 'meta.projektbegleiter', 'zeitraume', 'projekt']);
        $person = $participation->teilnehmer;
        $period = $participation->zeitraume->sortByDesc('id')->first();

        return [
            'source_id' => 'participant-identity',
            'participant_id' => (int) $person->id,
            'first_name' => $person->vorname,
            'last_name' => $person->nachname,
            'birth_date' => $person->geburtsdatum?->toDateString(),
            'customer_number' => $person->sozialedaten?->kundennummer,
            'contacts' => $person->kontaktes->map(fn ($contact) => [
                'type' => $contact->kontakttyp?->name,
                'value' => $contact->wert,
            ])->values()->all(),
            'project' => $participation->projekt?->name,
            'project_status' => $participation->status,
            'assignment_period' => [
                'from' => $period?->starttermin?->toDateString() ?? $period?->anfangsdatum?->toDateString(),
                'until' => $period?->endtermin?->toDateString() ?? $period?->enddatum?->toDateString(),
            ],
            'case_staff' => [
                'supervisor' => trim(($participation->meta?->betreuer?->vorname ?? '').' '.($participation->meta?->betreuer?->nachname ?? '')) ?: null,
                'project_companion' => trim(($participation->meta?->projektbegleiter?->vorname ?? '').' '.($participation->meta?->projektbegleiter?->nachname ?? '')) ?: null,
            ],
        ];
    }
}

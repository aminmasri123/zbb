<?php

namespace App\Http\Controllers;

use App\Models\InternshipEmailTemplate;
use App\Models\Personen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InternshipEmailController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function prepare(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $validated = $request->validate([
            'template_key' => ['required', Rule::in(array_keys(InternshipEmailTemplate::LABELS))],
        ]);

        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && $project->featureEnabled('internship_management'), 404);
        abort_unless($measure->typ === 'Praktikum' && $measure->placement_type !== 'internal', 422, 'E-Mails an Betriebe sind nur für externe Praktika verfügbar.');
        abort_if($measure->archived_at, 422, 'Für archivierte Praktika kann keine E-Mail vorbereitet werden.');
        abort_unless(filter_var($measure->contact_email, FILTER_VALIDATE_EMAIL), 422, 'Beim Praktikumsbetrieb ist keine gültige E-Mail-Adresse hinterlegt.');

        $participation = ProjektHasPersonen::query()
            ->where('projekt_id', $project->id)
            ->where('personen_id', $measure->person_id)
            ->firstOrFail();
        abort_unless((int) $measure->projekt_person_id === (int) $participation->id, 404);

        $participant = Personen::query()
            ->teilnehmer()
            ->visibleForUser($request->user())
            ->findOrFail($measure->person_id);
        $template = InternshipEmailTemplate::query()->where('key', $validated['template_key'])->firstOrFail();
        $sender = $request->user();
        $values = [
            '{{teilnehmer_name}}' => trim("{$participant->vorname} {$participant->nachname}"),
            '{{teilnehmer_vorname}}' => (string) $participant->vorname,
            '{{teilnehmer_nachname}}' => (string) $participant->nachname,
            '{{betrieb}}' => (string) $measure->traeger,
            '{{ansprechpartner}}' => (string) $measure->contact_name,
            '{{startdatum}}' => $measure->start?->format('d.m.Y') ?? '',
            '{{enddatum}}' => $measure->end?->format('d.m.Y') ?? '',
            '{{absender_name}}' => $sender->name,
            '{{absender_email}}' => (string) $sender->email,
        ];

        return response()->json([
            'label' => InternshipEmailTemplate::LABELS[$template->key],
            'recipient' => $measure->contact_email,
            'sender_name' => $sender->name,
            'sender_email' => $sender->email,
            'subject' => strtr($template->subject, $values),
            'body' => strtr($template->body, $values),
            'attachment' => $template->attachment_path && Storage::disk('local')->exists($template->attachment_path)
                ? [
                    'name' => $template->attachment_original_name,
                    'size' => $template->attachment_size,
                    'download_url' => route('internship-email-templates.attachment.download', $template),
                ]
                : null,
        ]);
    }
}

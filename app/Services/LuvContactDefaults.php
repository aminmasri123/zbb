<?php

namespace App\Services;

use App\Models\ProjektHasPersonen;

final class LuvContactDefaults
{
    public function fields(ProjektHasPersonen $participation): array
    {
        $participation->loadMissing('meta.betreuer.kontaktes.kontakttyp');
        $staff = $participation->meta?->betreuer;
        return [
            'contact.name' => $staff ? trim($staff->vorname.' '.$staff->nachname) : '',
            'contact.phone' => $staff ? $this->contact($staff->kontaktes, false) : '',
            'contact.email' => $staff ? $this->contact($staff->kontaktes, true) : '',
        ];
    }

    private function contact(\Illuminate\Support\Collection $contacts, bool $email): string
    {
        $values = $contacts->filter(function ($contact) use ($email) {
            $label = mb_strtolower(($contact->kontakttyp?->name ?? '').' '.($contact->bemerkung ?? ''));
            if (str_contains($label, 'privat') || ! preg_match('/dienst|geschäft|geschaeft|beruf|arbeit|büro|buero/', $label)) {
                return false;
            }
            return $email
                ? (bool) filter_var(trim($contact->wert), FILTER_VALIDATE_EMAIL)
                : (bool) preg_match('/telefon|phone|mobil|handy|tel\b/', $label)
                    && ! str_contains($contact->wert, '@');
        })->pluck('wert')->map(fn ($value) => trim($value))->filter()->unique()->values();
        return $values->count() === 1 ? $values->first() : '';
    }

    public function mergeReport(array $report, ProjektHasPersonen $participation): array
    {
        $fields = $this->fields($participation);
        // Contact fields come from the assigned staff record, never model guesses.
        $report['sections'] = array_values(array_filter($report['sections'] ?? [], fn ($section) => ! preg_match('/^\[contact\.(name|phone|email)\]/', $section['heading'] ?? '')));
        $labels = ['contact.name' => 'Kontaktperson beim Maßnahmeträger', 'contact.phone' => 'Telefonnummer', 'contact.email' => 'E-Mail'];
        foreach ($fields as $key => $value) {
            if ($value === '') {
                continue;
            }
            $report['sections'][] = [
                'heading' => '['.$key.'] '.$labels[$key],
                'claims' => [[
                    'claim_id' => str_replace('.', '-', $key),
                    'text' => $value,
                    'status' => 'supported',
                    'source_ids' => ['assigned-staff-contact-'.$participation->id],
                ]],
            ];
        }
        return $report;
    }
}

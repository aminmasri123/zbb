<?php

namespace App\Services\Jobs;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class BundesagenturJobSearchService
{
    public function search(array $filters): array
    {
        $params = array_filter([
            'was' => $filters['was'] ?? null,
            'wo' => $filters['wo'] ?? null,
            'umkreis' => $filters['umkreis'] ?? null,
            'angebotsart' => $filters['angebotsart'] ?? 1,
            'arbeitszeit' => $filters['arbeitszeit'] ?? null,
            'veroeffentlichtseit' => $filters['veroeffentlichtseit'] ?? null,
            'page' => $filters['page'] ?? 1,
            'size' => min((int) ($filters['size'] ?? 20), 50),
            'pav' => 'false',
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::baseUrl(rtrim(config('services.ba_jobsuche.base_url'), '/'))
            ->acceptJson()
            ->withHeaders(['X-API-Key' => config('services.ba_jobsuche.api_key')])
            ->timeout(config('services.ba_jobsuche.timeout'))
            ->retry(2, 250, throw: false)
            ->get('/pc/v6/jobs', $params);

        $response->throw();
        $payload = $response->json();
        $items = $payload['ergebnisliste'] ?? $payload['stellenangebote'] ?? $payload['jobs'] ?? [];

        return [
            'items' => collect($items)->map(fn (array $job) => $this->mapJob($job))->filter(fn ($job) => $job['external_ref'])->values()->all(),
            'page' => (int) ($params['page'] ?? 1),
            'total' => (int) ($payload['maxErgebnisse'] ?? $payload['total'] ?? count($items)),
            'source' => 'Bundesagentur für Arbeit – Jobsuche',
        ];
    }

    private function mapJob(array $job): array
    {
        $externalRef = (string) ($job['referenznummer'] ?? $job['refnr'] ?? '');
        $workplace = Arr::get($job, 'stellenlokationen.0.adresse', $job['arbeitsort'] ?? []);
        $location = is_array($workplace)
            ? trim(implode(' ', array_filter([Arr::get($workplace, 'plz'), Arr::get($workplace, 'ort'), Arr::get($workplace, 'region')])))
            : (string) $workplace;

        return [
            'external_ref' => $externalRef,
            'title' => (string) ($job['stellenangebotsTitel'] ?? $job['titel'] ?? $job['beruf'] ?? 'Stellenangebot'),
            'employer' => (string) ($job['arbeitgeber'] ?? $job['firma'] ?? ''),
            'location' => $location,
            'published_at' => Arr::get($job, 'veroeffentlichungszeitraum.von')
                ?? $job['datumErsteVeroeffentlichung']
                ?? $job['aktuelleVeroeffentlichungsdatum']
                ?? $job['veroeffentlichtAm']
                ?? null,
            'source_url' => $job['externeURL']
                ?? $job['externeUrl']
                ?? $job['url']
                ?? ($externalRef !== '' ? 'https://www.arbeitsagentur.de/jobsuche/jobdetail/'.rawurlencode($externalRef) : null),
        ];
    }
}

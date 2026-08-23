<?php

namespace App\Services\Ai;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AgentTurnPayload
{
    private const REPORT_TYPES = ['luv', 'interim', 'final'];

    /**
     * @param  list<string>  $allowedTools
     * @param  list<array{role: string, tool_name: string, content: array<string, mixed>}>  $toolResults
     */
    public function __construct(
        public string $runId,
        public int $projectId,
        public int $participantId,
        public string $reportType,
        public string $fromDate,
        public string $untilDate,
        public string $userRequest,
        public array $allowedTools,
        public array $toolResults = [],
    ) {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->runId) !== 1) {
            throw new InvalidArgumentException('Ungueltige KI-Lauf-ID.');
        }

        if ($this->projectId < 1 || $this->participantId < 1) {
            throw new InvalidArgumentException('Projekt- und Teilnehmer-ID muessen positiv sein.');
        }

        if (! in_array($this->reportType, self::REPORT_TYPES, true)) {
            throw new InvalidArgumentException('Ungueltiger Berichtstyp.');
        }

        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $this->fromDate);
        $until = DateTimeImmutable::createFromFormat('!Y-m-d', $this->untilDate);
        if (! $from || $from->format('Y-m-d') !== $this->fromDate
            || ! $until || $until->format('Y-m-d') !== $this->untilDate
            || $until < $from) {
            throw new InvalidArgumentException('Ungueltiger KI-Berichtszeitraum.');
        }

        $requestLength = mb_strlen(trim($this->userRequest));
        if ($requestLength < 1 || $requestLength > 4000) {
            throw new InvalidArgumentException('Die KI-Anfrage muss 1 bis 4000 Zeichen enthalten.');
        }

        if ($this->allowedTools === [] || count($this->allowedTools) !== count(array_unique($this->allowedTools))) {
            throw new InvalidArgumentException('Die KI-Tool-Allowlist ist leer oder enthaelt Duplikate.');
        }

        foreach ($this->allowedTools as $tool) {
            if (! is_string($tool) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $tool) !== 1) {
                throw new InvalidArgumentException('Ungueltiger Toolname in der KI-Allowlist.');
            }
        }

        if (count($this->toolResults) > 30) {
            throw new InvalidArgumentException('Zu viele KI-Tool-Ergebnisse.');
        }

        foreach ($this->toolResults as $result) {
            if (($result['role'] ?? null) !== 'tool'
                || ! in_array($result['tool_name'] ?? null, $this->allowedTools, true)
                || ! is_array($result['content'] ?? null)) {
                throw new InvalidArgumentException('Ungueltiges KI-Tool-Ergebnis.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'project_id' => $this->projectId,
            'participant_id' => $this->participantId,
            'report_type' => $this->reportType,
            'period' => ['from_date' => $this->fromDate, 'until_date' => $this->untilDate],
            'user_request' => $this->userRequest,
            'allowed_tools' => $this->allowedTools,
            'tool_results' => $this->toolResults,
        ];
    }
}

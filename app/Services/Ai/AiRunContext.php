<?php

namespace App\Services\Ai;

use InvalidArgumentException;

final readonly class AiRunContext
{
    /**
     * @param  list<string>  $allowedTools
     */
    public function __construct(
        public int $userId,
        public int $projectId,
        public array $allowedTools,
    ) {
        if ($this->userId < 1 || $this->projectId < 1) {
            throw new InvalidArgumentException('Benutzer- und Projekt-ID muessen positiv sein.');
        }

        if ($this->allowedTools === [] || array_is_list($this->allowedTools) === false) {
            throw new InvalidArgumentException('Mindestens ein erlaubtes Tool ist erforderlich.');
        }

        foreach ($this->allowedTools as $tool) {
            if (! is_string($tool) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $tool) !== 1) {
                throw new InvalidArgumentException('Die Tool-Allowlist enthaelt einen ungueltigen Namen.');
            }
        }

        if (count(array_unique($this->allowedTools)) !== count($this->allowedTools)) {
            throw new InvalidArgumentException('Die Tool-Allowlist darf keine Duplikate enthalten.');
        }
    }

    public function allows(string $tool): bool
    {
        return in_array($tool, $this->allowedTools, true);
    }
}

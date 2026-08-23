<?php

namespace App\Console\Commands;

use App\Models\AiWorkspaceRun;
use Illuminate\Console\Command;

class PurgeExpiredAiWorkspaceRuns extends Command
{
    protected $signature = 'ai-workspace:purge-expired';

    protected $description = 'Löscht KI-Arbeitsbereichsverläufe, die mindestens sieben Tage alt sind';

    public function handle(): int
    {
        $deleted = AiWorkspaceRun::query()
            ->where('created_at', '<=', now()->subDays(7))
            ->delete();

        $this->info("Gelöschte KI-Verläufe: {$deleted}");

        return self::SUCCESS;
    }
}

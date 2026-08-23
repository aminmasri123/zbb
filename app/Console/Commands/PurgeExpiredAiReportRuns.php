<?php

namespace App\Console\Commands;

use App\Models\AiReportRun;
use Illuminate\Console\Command;

class PurgeExpiredAiReportRuns extends Command
{
    protected $signature = 'ai-report:purge-expired';

    protected $description = 'Löscht KI-LUV-Läufe, die mindestens sieben Tage alt sind';

    public function handle(): int
    {
        $deleted = AiReportRun::query()
            ->where('status', 'completed')
            ->where('created_at', '<=', now()->subDays(7))
            ->delete();

        $this->info("Gelöschte KI-LUV-Läufe: {$deleted}");

        return self::SUCCESS;
    }
}


<?php

namespace App\Console\Commands;

use App\Models\PaAttendanceListDraft;
use Illuminate\Console\Command;

class PurgeExpiredPaAttendanceDrafts extends Command
{
    protected $signature = 'pa:purge-expired-attendance-drafts';

    protected $description = 'Delete expired raw PA attendance list drafts.';

    public function handle(): int
    {
        $deleted = PaAttendanceListDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired PA attendance draft(s).");

        return self::SUCCESS;
    }
}

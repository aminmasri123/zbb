<?php

namespace App\Console\Commands;

use App\Models\BibbAttendanceListDraft;
use Illuminate\Console\Command;

class PurgeExpiredBibbAttendanceDrafts extends Command
{
    protected $signature = 'bibb:purge-expired-attendance-drafts';

    protected $description = 'Delete expired raw BIBB attendance list drafts.';

    public function handle(): int
    {
        $deleted = BibbAttendanceListDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired BIBB attendance draft(s).");

        return self::SUCCESS;
    }
}

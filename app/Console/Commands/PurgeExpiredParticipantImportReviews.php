<?php

namespace App\Console\Commands;

use App\Models\ParticipantImportReview;
use Illuminate\Console\Command;

class PurgeExpiredParticipantImportReviews extends Command
{
    protected $signature = 'participants:purge-expired-import-reviews';

    protected $description = 'Remove only expired temporary participant import copies.';

    public function handle(): int
    {
        $count = ParticipantImportReview::where('expires_at', '<=', now())->delete();
        $this->info("{$count} abgelaufene Importkopien entfernt.");

        return self::SUCCESS;
    }
}

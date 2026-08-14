<?php

namespace App\Console\Commands;

use App\Models\StaffMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredInternalChatMessages extends Command
{
    protected $signature = 'chat:purge-expired';

    protected $description = 'Löscht interne Chatnachrichten und Anhänge nach Ablauf ihrer Aufbewahrungsfrist.';

    public function handle(): int
    {
        $deleted = 0;

        StaffMessage::query()
            ->where('expires_at', '<=', now())
            ->with('attachments')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use (&$deleted) {
                foreach ($messages as $message) {
                    foreach ($message->attachments as $attachment) {
                        Storage::disk('local')->delete($attachment->path);
                    }
                    Storage::disk('local')->deleteDirectory("internal-chat/{$message->conversation_id}/{$message->id}");
                    $message->delete();
                    $deleted++;
                }
            });

        $this->info("{$deleted} abgelaufene Chatnachrichten wurden gelöscht.");

        return self::SUCCESS;
    }
}

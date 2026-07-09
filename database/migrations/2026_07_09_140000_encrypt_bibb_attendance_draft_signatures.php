<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SIGNATURE_ENCRYPTION_PREFIX = 'enc:v1:';

    public function up(): void
    {
        if (!Schema::hasTable('bibb_attendance_list_drafts')) {
            return;
        }

        DB::table('bibb_attendance_list_drafts')
            ->select(['id', 'payload'])
            ->chunkById(50, function ($drafts) {
                foreach ($drafts as $draft) {
                    $payload = json_decode((string) $draft->payload, true);
                    if (!is_array($payload) || !is_array($payload['signatures'] ?? null)) {
                        continue;
                    }

                    $encryptedPayload = $this->transformSignatures($payload, true);
                    if ($encryptedPayload === $payload) {
                        continue;
                    }

                    DB::table('bibb_attendance_list_drafts')
                        ->where('id', $draft->id)
                        ->update(['payload' => json_encode($encryptedPayload, JSON_UNESCAPED_SLASHES)]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bibb_attendance_list_drafts')) {
            return;
        }

        DB::table('bibb_attendance_list_drafts')
            ->select(['id', 'payload'])
            ->chunkById(50, function ($drafts) {
                foreach ($drafts as $draft) {
                    $payload = json_decode((string) $draft->payload, true);
                    if (!is_array($payload) || !is_array($payload['signatures'] ?? null)) {
                        continue;
                    }

                    $decryptedPayload = $this->transformSignatures($payload, false);
                    if ($decryptedPayload === $payload) {
                        continue;
                    }

                    DB::table('bibb_attendance_list_drafts')
                        ->where('id', $draft->id)
                        ->update(['payload' => json_encode($decryptedPayload, JSON_UNESCAPED_SLASHES)]);
                }
            });
    }

    private function transformSignatures(array $payload, bool $encrypt): array
    {
        foreach ($payload['signatures'] as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $payload['signatures'][$key] = $encrypt
                ? $this->encryptSignature($value)
                : $this->decryptSignature($value);
        }

        return $payload;
    }

    private function encryptSignature(string $value): string
    {
        if (Str::startsWith($value, self::SIGNATURE_ENCRYPTION_PREFIX)) {
            return $value;
        }

        return self::SIGNATURE_ENCRYPTION_PREFIX . Crypt::encryptString($value);
    }

    private function decryptSignature(string $value): string
    {
        if (!Str::startsWith($value, self::SIGNATURE_ENCRYPTION_PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(Str::after($value, self::SIGNATURE_ENCRYPTION_PREFIX));
        } catch (DecryptException) {
            return $value;
        }
    }
};

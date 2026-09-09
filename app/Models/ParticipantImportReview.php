<?php

namespace App\Models;

use App\Services\Participants\ParticipantImportReader;
use Illuminate\Database\Eloquent\Model;

class ParticipantImportReview extends Model
{
    protected $fillable = ['user_id', 'projekt_id', 'payload', 'expires_at'];

    protected $casts = ['payload' => 'encrypted:array', 'expires_at' => 'datetime'];

    public function csv(): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ParticipantImportReader::FIELDS, ';', '"', '');
        foreach ($this->payload['rows'] as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}

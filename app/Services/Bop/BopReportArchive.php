<?php

namespace App\Services\Bop;

use App\Models\AppFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BopReportArchive
{
    /** Store the original PDF bytes without changing the report's layout. */
    public function store(int $ownerId, int $projectId, array $folders, string $filename, string $pdf): AppFile
    {
        $path = 'apps/files/bop-reports/'.Str::uuid().'.pdf';
        if (! Storage::put($path, $pdf)) {
            throw new RuntimeException('Der Bericht konnte nicht im Dateimanager gespeichert werden.');
        }

        try {
            return DB::transaction(function () use ($ownerId, $projectId, $folders, $filename, $pdf, $path) {
                $parentId = null;
                foreach (array_merge(['BOP', 'Schulen'], $folders) as $name) {
                    // Never reuse somebody else's folder or a shared/public folder.
                    $folder = AppFile::firstOrCreate([
                        'owner_user_id' => $ownerId,
                        'project_id' => $projectId,
                        'visibility' => 'private',
                        'parent_id' => $parentId,
                        'type' => 'folder',
                        'name' => $name,
                    ]);
                    $parentId = $folder->id;
                }

                return AppFile::create([
                    'owner_user_id' => $ownerId,
                    'project_id' => $projectId,
                    'visibility' => 'private',
                    'parent_id' => $parentId,
                    'type' => 'file',
                    'name' => $filename,
                    'original_name' => $filename,
                    'path' => $path,
                    'mime_type' => 'application/pdf',
                    'size' => strlen($pdf),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }
    }

    public function folders(string $school, int $schoolId, string $year, string $part, string $class, string $person, int $personId, string $kind): array
    {
        return [
            $school.' ('.$schoolId.')',
            str_replace('/', '-', $year),
            preg_match('/^Teil\b/iu', $part) ? $part : 'Teil '.$part,
            trim($class) ?: 'ohne Klasse',
            trim($person).' ('.$personId.')',
            $kind,
        ];
    }
}

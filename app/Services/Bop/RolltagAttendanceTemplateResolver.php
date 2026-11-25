<?php

namespace App\Services\Bop;

use App\Models\Dokumente;
use Illuminate\Validation\ValidationException;

class RolltagAttendanceTemplateResolver
{
    public const CLASS_EXPORT_PERMISSION = 'bop.rolltag.klasse.template';

    public const GROUP_EXPORT_PERMISSION = 'bop.rolltag.gruppe.template';

    public function classTemplate(int $projectId): string
    {
        return $this->resolve($projectId, self::CLASS_EXPORT_PERMISSION, 'Klassen');
    }

    public function groupTemplate(int $projectId): string
    {
        return $this->resolve($projectId, self::GROUP_EXPORT_PERMISSION, 'Gruppen/Räume');
    }

    private function resolve(int $projectId, string $permission, string $variant): string
    {
        $document = Dokumente::query()
            ->where('export_permission', $permission)
            ->where('typ', 'excel')
            ->where(function ($query) use ($projectId) {
                $query->whereHas('projekte', fn ($project) => $project->whereKey($projectId))
                    ->orWhereHas('kategorien.projekte', fn ($project) => $project->whereKey($projectId));
            })
            ->first();

        if (! $document) {
            throw ValidationException::withMessages([
                'vorlage' => "Dem aktiven BOP-Projekt ist keine Rolltag-Vorlage für {$variant} zugeordnet.",
            ]);
        }
        if (! $document->aktiv) {
            throw ValidationException::withMessages([
                'vorlage' => "Die Rolltag-Vorlage für {$variant} ist deaktiviert.",
            ]);
        }
        if (! in_array('xlsx', $document->ausgabeformate ?? [], true) || ! $document->dateipfad) {
            throw ValidationException::withMessages([
                'vorlage' => "Die Rolltag-Vorlage für {$variant} muss eine Excel-Vorlage mit XLSX-Ausgabe sein.",
            ]);
        }

        $path = storage_path(ltrim($document->dateipfad, '/\\'));
        if (! is_file($path)) {
            throw ValidationException::withMessages([
                'vorlage' => "Die Datei der Rolltag-Vorlage für {$variant} wurde nicht gefunden.",
            ]);
        }

        return $path;
    }
}

<?php

namespace App\Services;

use App\Models\Dienstwagen;
use App\Models\DienstwagenVerlauf;
use Illuminate\Database\Eloquent\Model;

class DienstwagenVerlaufService
{
    public function record(
        ?Dienstwagen $dienstwagen,
        string $aktion,
        string $titel,
        ?string $beschreibung = null,
        array $changes = [],
        ?Model $related = null
    ): void {
        DienstwagenVerlauf::create([
            'dienstwagen_id' => $dienstwagen?->id,
            'user_id' => auth()->id(),
            'person_id' => auth()->user()?->person_id,
            'aktion' => $aktion,
            'titel' => $titel,
            'beschreibung' => $beschreibung,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'changes_json' => $changes ?: null,
            'created_at' => now(),
        ]);
    }

    public function changes(Model $model, array $ignore = []): array
    {
        $changes = [];
        $dirty = collect($model->getChanges())->except(array_merge($ignore, ['updated_at']))->all();

        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}

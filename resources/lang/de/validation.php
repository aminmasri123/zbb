<?php

return [

    'required' => 'Das Feld :attribute ist erforderlich.',
    'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
    'exists' => 'Das ausgewählte :attribute ist ungültig.',
    'date_format' => 'Dieses Feld muss dem Format :format entsprechen.',
    'after' => 'Die :attribute muss nach :date liegen.',
    'before' => 'Die :attribute muss vor :date liegen.',

    // eigene Übersetzungen (empfohlen)
    'custom' => [
        'startzeit' => [
            'required' => 'Die Startzeit ist erforderlich.',
        ],
        'endzeit' => [
            'required' => 'Die Endzeit ist erforderlich.',
            'after' => 'Die Endzeit muss nach der Startzeit liegen.',
        ],
        'tag' => [
            'required' => 'Das Datum ist erforderlich.',
        ],
        'personen_id' => [
            'exists' => 'Diese Person wurde nicht gefunden.',
        ],
    ],

    'attributes' => [
        'startzeit' => 'Startzeit',
        'endzeit' => 'Endzeit',
        'tag' => 'Datum',
    ],

];

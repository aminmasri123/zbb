<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projekt_has_dokumentes', 'gruppen_export')) {
            Schema::table('projekt_has_dokumentes', function (Blueprint $table) {
                $table->boolean('gruppen_export')->default(true);
            });
        }

        if (! Schema::hasColumn('projekt_has_dokumentes', 'serienbrief')) {
            Schema::table('projekt_has_dokumentes', function (Blueprint $table) {
                $table->boolean('serienbrief')->default(true);
            });
        }

        if (! Schema::hasColumn('projekt_has_dokumentes', 'sort_order')) {
            Schema::table('projekt_has_dokumentes', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        $this->statementIgnoreDuplicate('ALTER TABLE projekt_has_dokumentes ADD PRIMARY KEY (projekt_id, dokument_id)');

        $bopDokumente = [
            [
                'name' => 'BOP Auswertungsbogen',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/Auswertungsbogen_BOP.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Vorlage fuer Gruppen-Serienbrief.',
            ],
            [
                'name' => 'BOP Auswertung PA',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/Auswertung_PA.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Vorlage fuer Gruppen-Serienbrief.',
            ],
            [
                'name' => 'BOP Zertifikat POBO',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Zertifikat fuer Gruppen-Serienbrief.',
            ],
            [
                'name' => 'BOP Zertifikat POBO klein',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO_klein_text.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Zertifikat fuer Gruppen-Serienbrief.',
            ],
            [
                'name' => 'BOP Teilnehmendenliste BO A4',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A4.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Teilnehmendenliste als Gruppen-Vorlage.',
            ],
            [
                'name' => 'BOP Teilnehmendenliste BO A3',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A3.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-Teilnehmendenliste als Gruppen-Vorlage.',
            ],
            [
                'name' => 'BOP Anwesenheitsliste PA',
                'typ' => 'word',
                'version' => null,
                'dateipfad' => '/vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx',
                'dateipfadName' => null,
                'beschreibung' => 'BOP-PA-Anwesenheitsliste als Gruppen-Vorlage.',
            ],
        ];

        foreach ($bopDokumente as $index => $dokument) {
            DB::table('dokumentes')->updateOrInsert(
                ['dateipfad' => $dokument['dateipfad']],
                [
                    ...$dokument,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $projektId = DB::table('projekts')
                ->whereRaw('LOWER(name) = ?', ['bop'])
                ->value('id');

            $dokumentId = DB::table('dokumentes')
                ->where('dateipfad', $dokument['dateipfad'])
                ->value('id');

            if ($projektId && $dokumentId) {
                DB::table('projekt_has_dokumentes')->updateOrInsert(
                    [
                        'projekt_id' => $projektId,
                        'dokument_id' => $dokumentId,
                    ],
                    [
                        'gruppen_export' => 1,
                        'serienbrief' => 1,
                        'sort_order' => $index + 1,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $columns = array_filter(
            ['sort_order', 'serienbrief', 'gruppen_export'],
            fn (string $column) => Schema::hasColumn('projekt_has_dokumentes', $column)
        );

        if ($columns !== []) {
            Schema::table('projekt_has_dokumentes', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function statementIgnoreDuplicate(string $statement): void
    {
        try {
            DB::statement($statement);
        } catch (QueryException $exception) {
            $mysqlCode = (int) ($exception->errorInfo[1] ?? 0);

            if (!in_array($mysqlCode, [1061, 1062], true)) {
                throw $exception;
            }
        }
    }
};

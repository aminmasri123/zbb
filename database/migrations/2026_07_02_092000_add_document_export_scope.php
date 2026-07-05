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
        if (! Schema::hasColumn('dokumentes', 'einsatzbereich')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->string('einsatzbereich', 30)->default('gruppe')->after('kontext');
            });
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS dokument_has_bereiches (
                dokument_id BIGINT UNSIGNED NOT NULL,
                bereich_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (dokument_id, bereich_id),
                CONSTRAINT dokument_has_bereiches_dokument_id_foreign FOREIGN KEY (dokument_id) REFERENCES dokumentes(id) ON DELETE CASCADE,
                CONSTRAINT dokument_has_bereiches_bereich_id_foreign FOREIGN KEY (bereich_id) REFERENCES bereiches(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $potenzialanalyseId = DB::table('bereiches')->where('name', 'Potenzialanalyse')->value('id');
        if (!$potenzialanalyseId) {
            $potenzialanalyseId = DB::table('bereiches')->insertGetId([
                'name' => 'Potenzialanalyse',
                'beschreibung' => 'BOP Potenzialanalyse',
                'code' => 'PA',
                'aktiv' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $bopProjektId = DB::table('projekts')->whereRaw('LOWER(name) = ?', ['bop'])->value('id');
        if ($bopProjektId && $potenzialanalyseId) {
            DB::table('projekt_has_bereiches')->updateOrInsert([
                'projekt_id' => $bopProjektId,
                'bereich_id' => $potenzialanalyseId,
            ], [
                'aktiv' => 1,
            ]);
        }

        DB::table('dokumentes')
            ->where('dateipfad', 'like', '/vorlage/projekte/bop/%')
            ->update(['einsatzbereich' => 'partner']);

        $paDokumentIds = DB::table('dokumentes')
            ->where('dateipfad', 'like', '/vorlage/projekte/bop/%')
            ->where(function ($query) {
                $query
                    ->where('name', 'like', '% PA%')
                    ->orWhere('name', 'like', '%PA %')
                    ->orWhere('name', 'like', '%Potenzial%')
                    ->orWhere('dateipfad', 'like', '%/pa/%');
            })
            ->pluck('id');

        foreach ($paDokumentIds as $dokumentId) {
            DB::table('dokumentes')
                ->where('id', $dokumentId)
                ->update(['einsatzbereich' => 'gruppe']);

            DB::table('dokument_has_bereiches')->updateOrInsert([
                'dokument_id' => $dokumentId,
                'bereich_id' => $potenzialanalyseId,
            ]);
        }
    }

    public function down(): void
    {
        $this->statementIgnoreMissing('DROP TABLE IF EXISTS dokument_has_bereiches');

        if (Schema::hasColumn('dokumentes', 'einsatzbereich')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->dropColumn('einsatzbereich');
            });
        }
    }

    private function statementIgnoreMissing(string $statement): void
    {
        try {
            DB::statement($statement);
        } catch (QueryException $exception) {
            $mysqlCode = (int) ($exception->errorInfo[1] ?? 0);

            if (!in_array($mysqlCode, [1050, 1051, 1060, 1061, 1091, 1826], true)) {
                throw $exception;
            }
        }
    }
};

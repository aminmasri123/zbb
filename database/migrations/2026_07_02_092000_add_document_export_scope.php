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

        if (! Schema::hasTable('dokument_has_bereiches')) {
            Schema::create('dokument_has_bereiches', function (Blueprint $table) {
                $table->foreignId('dokument_id')->constrained('dokumentes')->cascadeOnDelete();
                $table->foreignId('bereich_id')->constrained('bereiches')->cascadeOnDelete();

                $table->primary(['dokument_id', 'bereich_id']);
            });
        }

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
        if (DB::getDriverName() !== 'mysql') {
            DB::statement($statement);
            return;
        }

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

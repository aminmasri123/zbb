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
        if (! Schema::hasColumn('dokumentes', 'kontext')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->string('kontext', 30)->default('teilnehmer')->after('typ');
            });
        }

        if (! Schema::hasColumn('dokumentes', 'ausgabeformate')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->json('ausgabeformate')->nullable()->after('kontext');
            });
        }

        if (! Schema::hasColumn('dokumentes', 'aktiv')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->boolean('aktiv')->default(true)->after('beschreibung');
            });
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS dokument_kategories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                beschreibung TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY dokument_kategories_name_unique (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS dokument_has_kategories (
                dokument_id BIGINT UNSIGNED NOT NULL,
                dokument_kategorie_id BIGINT UNSIGNED NOT NULL,
                gruppen_export TINYINT(1) NOT NULL DEFAULT 1,
                serienbrief TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (dokument_id, dokument_kategorie_id),
                CONSTRAINT dokument_has_kategories_dokument_id_foreign FOREIGN KEY (dokument_id) REFERENCES dokumentes(id) ON DELETE CASCADE,
                CONSTRAINT dokument_has_kategories_kategorie_id_foreign FOREIGN KEY (dokument_kategorie_id) REFERENCES dokument_kategories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS projekt_has_dokument_kategories (
                projekt_id BIGINT UNSIGNED NOT NULL,
                dokument_kategorie_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (projekt_id, dokument_kategorie_id),
                CONSTRAINT projekt_has_dokument_kategories_projekt_id_foreign FOREIGN KEY (projekt_id) REFERENCES projekts(id) ON DELETE CASCADE,
                CONSTRAINT projekt_has_dokument_kategories_kategorie_id_foreign FOREIGN KEY (dokument_kategorie_id) REFERENCES dokument_kategories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        foreach (['AGH', 'SGB II', 'SGB III', 'ESF', 'BOP', 'INTEQRA'] as $name) {
            DB::table('dokument_kategories')->updateOrInsert(
                ['name' => $name],
                ['beschreibung' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $bopKategorieId = DB::table('dokument_kategories')->where('name', 'BOP')->value('id');
        $bopProjektId = DB::table('projekts')->whereRaw('LOWER(name) = ?', ['bop'])->value('id');

        if ($bopKategorieId && $bopProjektId) {
            $this->seedBopVorlagen();

            DB::table('projekt_has_dokument_kategories')->updateOrInsert([
                'projekt_id' => $bopProjektId,
                'dokument_kategorie_id' => $bopKategorieId,
            ]);

            $bopDokumentIds = DB::table('dokumentes')
                ->where('dateipfad', 'like', '/vorlage/projekte/bop/%')
                ->pluck('id');

            foreach ($bopDokumentIds as $dokumentId) {
                DB::table('dokument_has_kategories')->updateOrInsert(
                    [
                        'dokument_id' => $dokumentId,
                        'dokument_kategorie_id' => $bopKategorieId,
                    ],
                    [
                        'gruppen_export' => 1,
                        'serienbrief' => 1,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $this->statementIgnoreMissing('DROP TABLE IF EXISTS projekt_has_dokument_kategories');
        $this->statementIgnoreMissing('DROP TABLE IF EXISTS dokument_has_kategories');
        $this->statementIgnoreMissing('DROP TABLE IF EXISTS dokument_kategories');

        $columns = array_filter(
            ['aktiv', 'ausgabeformate', 'kontext'],
            fn (string $column) => Schema::hasColumn('dokumentes', $column)
        );

        if ($columns !== []) {
            Schema::table('dokumentes', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function seedBopVorlagen(): void
    {
        $basePath = storage_path('vorlage/projekte/bop');

        if (!is_dir($basePath)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['docx', 'xlsx', 'pdf'], true)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($basePath) + 1));
            $storagePath = '/vorlage/projekte/bop/' . $relative;
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $typ = match ($extension) {
                'docx' => 'word',
                'xlsx' => 'excel',
                default => 'pdf',
            };
            $ausgabeformate = match ($typ) {
                'word' => ['docx', 'pdf'],
                'excel' => ['xlsx', 'pdf'],
                default => ['pdf'],
            };

            DB::table('dokumentes')->updateOrInsert(
                ['dateipfad' => $storagePath],
                [
                    'name' => str_replace(['_', '-'], ' ', $name),
                    'typ' => $typ,
                    'kontext' => $typ === 'word' ? 'teilnehmer' : 'gruppe',
                    'ausgabeformate' => json_encode($ausgabeformate),
                    'version' => null,
                    'dateipfadName' => $file->getFilename(),
                    'beschreibung' => 'BOP-Vorlage aus dem zentralen Export-Manager.',
                    'aktiv' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
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

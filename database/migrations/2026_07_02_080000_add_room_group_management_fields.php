<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE raeumes ADD COLUMN IF NOT EXISTS parent_id BIGINT UNSIGNED NULL AFTER standort_id');
        DB::statement("ALTER TABLE raeumes ADD COLUMN IF NOT EXISTS belegungsart VARCHAR(30) NOT NULL DEFAULT 'frei' AFTER typ");
        DB::statement('ALTER TABLE raeumes ADD COLUMN IF NOT EXISTS standard_personen_id BIGINT UNSIGNED NULL AFTER belegungsart');
        DB::statement('ALTER TABLE raeumes ADD COLUMN IF NOT EXISTS aktiv TINYINT(1) NOT NULL DEFAULT 1 AFTER standard_personen_id');
        $this->statementIgnoreDuplicate('ALTER TABLE raeumes ADD CONSTRAINT raeumes_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES raeumes(id) ON DELETE SET NULL');
        $this->statementIgnoreDuplicate('ALTER TABLE raeumes ADD CONSTRAINT raeumes_standard_personen_id_foreign FOREIGN KEY (standard_personen_id) REFERENCES personens(id) ON DELETE SET NULL');

        DB::statement("ALTER TABLE gruppes ADD COLUMN IF NOT EXISTS ort_typ VARCHAR(20) NOT NULL DEFAULT 'raum' AFTER projekt_id");
        DB::statement('ALTER TABLE gruppes ADD COLUMN IF NOT EXISTS externer_ort VARCHAR(255) NULL AFTER raum_id');

        DB::statement("UPDATE raeumes SET standard_personen_id = NULL WHERE belegungsart IN ('frei', 'blockiert')");

        $this->setGruppeRaumNullable(true);

        DB::statement("
            CREATE TABLE IF NOT EXISTS raum_meldungen (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                raum_id BIGINT UNSIGNED NULL,
                projekt_id BIGINT UNSIGNED NULL,
                gruppe_id BIGINT UNSIGNED NULL,
                gemeldet_von_user_id BIGINT UNSIGNED NULL,
                gemeldet_von_personen_id BIGINT UNSIGNED NULL,
                titel VARCHAR(255) NOT NULL,
                kategorie VARCHAR(60) NOT NULL DEFAULT 'sonstiges',
                prioritaet VARCHAR(30) NOT NULL DEFAULT 'normal',
                status VARCHAR(30) NOT NULL DEFAULT 'offen',
                beschreibung TEXT NULL,
                erledigt_am TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX raum_meldungen_raum_id_status_index (raum_id, status),
                INDEX raum_meldungen_projekt_id_status_index (projekt_id, status),
                CONSTRAINT raum_meldungen_raum_id_foreign FOREIGN KEY (raum_id) REFERENCES raeumes(id) ON DELETE SET NULL,
                CONSTRAINT raum_meldungen_projekt_id_foreign FOREIGN KEY (projekt_id) REFERENCES projekts(id) ON DELETE SET NULL,
                CONSTRAINT raum_meldungen_gruppe_id_foreign FOREIGN KEY (gruppe_id) REFERENCES gruppes(id) ON DELETE SET NULL,
                CONSTRAINT raum_meldungen_gemeldet_von_user_id_foreign FOREIGN KEY (gemeldet_von_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT raum_meldungen_gemeldet_von_personen_id_foreign FOREIGN KEY (gemeldet_von_personen_id) REFERENCES personens(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('raum_meldungen');

        Schema::table('gruppes', function (Blueprint $table) {
            if (Schema::hasColumn('gruppes', 'externer_ort')) {
                $table->dropColumn('externer_ort');
            }

            if (Schema::hasColumn('gruppes', 'ort_typ')) {
                $table->dropColumn('ort_typ');
            }
        });

        $this->setGruppeRaumNullable(false);

        Schema::table('raeumes', function (Blueprint $table) {
            if (Schema::hasColumn('raeumes', 'standard_personen_id')) {
                $table->dropConstrainedForeignId('standard_personen_id');
            }

            if (Schema::hasColumn('raeumes', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }

            if (Schema::hasColumn('raeumes', 'aktiv')) {
                $table->dropColumn('aktiv');
            }

            if (Schema::hasColumn('raeumes', 'belegungsart')) {
                $table->dropColumn('belegungsart');
            }
        });
    }

    private function setGruppeRaumNullable(bool $nullable): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE gruppes MODIFY raum_id BIGINT UNSIGNED ' . ($nullable ? 'NULL' : 'NOT NULL'));
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE gruppes ALTER COLUMN raum_id ' . ($nullable ? 'DROP NOT NULL' : 'SET NOT NULL'));
        }
    }

    private function statementIgnoreDuplicate(string $statement): void
    {
        try {
            DB::statement($statement);
        } catch (QueryException $exception) {
            $mysqlCode = (int) ($exception->errorInfo[1] ?? 0);

            if (!in_array($mysqlCode, [1005, 1061, 1062, 1826], true)) {
                throw $exception;
            }
        }
    }
};

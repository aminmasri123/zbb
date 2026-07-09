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
        Schema::table('raeumes', function (Blueprint $table) {
            if (! Schema::hasColumn('raeumes', 'raumnummer')) {
                $table->string('raumnummer', 60)->nullable()->after('name');
            }

            if (! Schema::hasColumn('raeumes', 'etage')) {
                $table->string('etage', 60)->nullable()->after('raumnummer');
            }

            if (! Schema::hasColumn('raeumes', 'flaeche_qm')) {
                $table->decimal('flaeche_qm', 8, 2)->nullable()->after('kapazitaet');
            }

            if (! Schema::hasColumn('raeumes', 'status')) {
                $table->string('status', 40)->default('verfuegbar')->after('belegungsart');
            }

            if (! Schema::hasColumn('raeumes', 'buchbar')) {
                $table->boolean('buchbar')->default(true)->after('aktiv');
            }

            if (! Schema::hasColumn('raeumes', 'verantwortliche_personen_id')) {
                $table->unsignedBigInteger('verantwortliche_personen_id')->nullable()->after('standard_personen_id');
            }
        });

        $this->statementIgnoreDuplicate('ALTER TABLE raeumes ADD CONSTRAINT raeumes_verantwortliche_personen_id_foreign FOREIGN KEY (verantwortliche_personen_id) REFERENCES personens(id) ON DELETE SET NULL');

        Schema::table('raum_meldungen', function (Blueprint $table) {
            if (! Schema::hasColumn('raum_meldungen', 'zugewiesen_an_personen_id')) {
                $table->unsignedBigInteger('zugewiesen_an_personen_id')->nullable()->after('gemeldet_von_personen_id');
            }

            if (! Schema::hasColumn('raum_meldungen', 'behoben_von_personen_id')) {
                $table->unsignedBigInteger('behoben_von_personen_id')->nullable()->after('zugewiesen_an_personen_id');
            }

            if (! Schema::hasColumn('raum_meldungen', 'faellig_am')) {
                $table->date('faellig_am')->nullable()->after('status');
            }

            if (! Schema::hasColumn('raum_meldungen', 'behoben_am')) {
                $table->timestamp('behoben_am')->nullable()->after('erledigt_am');
            }

            if (! Schema::hasColumn('raum_meldungen', 'massnahme')) {
                $table->text('massnahme')->nullable()->after('beschreibung');
            }

            if (! Schema::hasColumn('raum_meldungen', 'kosten')) {
                $table->decimal('kosten', 10, 2)->nullable()->after('massnahme');
            }

            if (! Schema::hasColumn('raum_meldungen', 'interne_notiz')) {
                $table->text('interne_notiz')->nullable()->after('kosten');
            }
        });

        $this->statementIgnoreDuplicate('ALTER TABLE raum_meldungen ADD CONSTRAINT raum_meldungen_zugewiesen_an_personen_id_foreign FOREIGN KEY (zugewiesen_an_personen_id) REFERENCES personens(id) ON DELETE SET NULL');
        $this->statementIgnoreDuplicate('ALTER TABLE raum_meldungen ADD CONSTRAINT raum_meldungen_behoben_von_personen_id_foreign FOREIGN KEY (behoben_von_personen_id) REFERENCES personens(id) ON DELETE SET NULL');

        if (! Schema::hasTable('raum_buchungen')) {
            Schema::create('raum_buchungen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('raum_id')->constrained('raeumes')->cascadeOnDelete();
                $table->foreignId('projekt_id')->nullable()->constrained('projekts')->nullOnDelete();
                $table->foreignId('gruppe_id')->nullable()->constrained('gruppes')->nullOnDelete();
                $table->unsignedBigInteger('gebucht_von_user_id')->nullable();
                $table->unsignedBigInteger('gebucht_von_personen_id')->nullable();
                $table->string('titel');
                $table->string('typ', 40)->default('buchung');
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->unsignedInteger('teilnehmerzahl')->nullable();
                $table->string('status', 40)->default('reserviert');
                $table->text('zweck')->nullable();
                $table->text('bemerkung')->nullable();
                $table->timestamps();

                $table->index(['raum_id', 'start_at', 'end_at']);
                $table->index(['projekt_id', 'start_at']);
                $table->index(['status', 'start_at']);
            });

            $this->statementIgnoreDuplicate('ALTER TABLE raum_buchungen ADD CONSTRAINT raum_buchungen_gebucht_von_user_id_foreign FOREIGN KEY (gebucht_von_user_id) REFERENCES users(id) ON DELETE SET NULL');
            $this->statementIgnoreDuplicate('ALTER TABLE raum_buchungen ADD CONSTRAINT raum_buchungen_gebucht_von_personen_id_foreign FOREIGN KEY (gebucht_von_personen_id) REFERENCES personens(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raum_buchungen');

        $this->statementIgnoreDuplicate('ALTER TABLE raum_meldungen DROP FOREIGN KEY raum_meldungen_zugewiesen_an_personen_id_foreign');
        $this->statementIgnoreDuplicate('ALTER TABLE raum_meldungen DROP FOREIGN KEY raum_meldungen_behoben_von_personen_id_foreign');
        $this->statementIgnoreDuplicate('ALTER TABLE raeumes DROP FOREIGN KEY raeumes_verantwortliche_personen_id_foreign');

        Schema::table('raum_meldungen', function (Blueprint $table) {
            foreach ([
                'interne_notiz',
                'kosten',
                'massnahme',
                'behoben_am',
                'faellig_am',
                'behoben_von_personen_id',
                'zugewiesen_an_personen_id',
            ] as $column) {
                if (Schema::hasColumn('raum_meldungen', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('raeumes', function (Blueprint $table) {
            foreach ([
                'verantwortliche_personen_id',
                'buchbar',
                'status',
                'flaeche_qm',
                'etage',
                'raumnummer',
            ] as $column) {
                if (Schema::hasColumn('raeumes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function statementIgnoreDuplicate(string $statement): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement($statement);
        } catch (QueryException $exception) {
            $mysqlCode = (int) ($exception->errorInfo[1] ?? 0);

            if (! in_array($mysqlCode, [1005, 1061, 1062, 1091, 1826], true)) {
                throw $exception;
            }
        }
    }
};

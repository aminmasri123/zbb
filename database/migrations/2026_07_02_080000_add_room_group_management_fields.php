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
        if (! Schema::hasColumn('raeumes', 'parent_id')) {
            Schema::table('raeumes', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('standort_id');
            });
        }

        if (! Schema::hasColumn('raeumes', 'belegungsart')) {
            Schema::table('raeumes', function (Blueprint $table) {
                $table->string('belegungsart', 30)->default('frei')->after('typ');
            });
        }

        if (! Schema::hasColumn('raeumes', 'standard_personen_id')) {
            Schema::table('raeumes', function (Blueprint $table) {
                $table->unsignedBigInteger('standard_personen_id')->nullable()->after('belegungsart');
            });
        }

        if (! Schema::hasColumn('raeumes', 'aktiv')) {
            Schema::table('raeumes', function (Blueprint $table) {
                $table->boolean('aktiv')->default(true)->after('standard_personen_id');
            });
        }

        $this->statementIgnoreDuplicate('ALTER TABLE raeumes ADD CONSTRAINT raeumes_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES raeumes(id) ON DELETE SET NULL');
        $this->statementIgnoreDuplicate('ALTER TABLE raeumes ADD CONSTRAINT raeumes_standard_personen_id_foreign FOREIGN KEY (standard_personen_id) REFERENCES personens(id) ON DELETE SET NULL');

        if (! Schema::hasColumn('gruppes', 'ort_typ')) {
            Schema::table('gruppes', function (Blueprint $table) {
                $table->string('ort_typ', 20)->default('raum')->after('projekt_id');
            });
        }

        if (! Schema::hasColumn('gruppes', 'externer_ort')) {
            Schema::table('gruppes', function (Blueprint $table) {
                $table->string('externer_ort')->nullable()->after('raum_id');
            });
        }

        DB::statement("UPDATE raeumes SET standard_personen_id = NULL WHERE belegungsart IN ('frei', 'blockiert')");

        $this->setGruppeRaumNullable(true);

        if (! Schema::hasTable('raum_meldungen')) {
            Schema::create('raum_meldungen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('raum_id')->nullable()->constrained('raeumes')->nullOnDelete();
                $table->foreignId('projekt_id')->nullable()->constrained('projekts')->nullOnDelete();
                $table->foreignId('gruppe_id')->nullable()->constrained('gruppes')->nullOnDelete();
                $table->foreignId('gemeldet_von_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('gemeldet_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
                $table->string('titel');
                $table->string('kategorie', 60)->default('sonstiges');
                $table->string('prioritaet', 30)->default('normal');
                $table->string('status', 30)->default('offen');
                $table->text('beschreibung')->nullable();
                $table->timestamp('erledigt_am')->nullable();
                $table->timestamps();

                $table->index(['raum_id', 'status']);
                $table->index(['projekt_id', 'status']);
            });
        }
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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

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

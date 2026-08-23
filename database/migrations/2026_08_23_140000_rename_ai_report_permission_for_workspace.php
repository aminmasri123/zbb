<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'ai.report.use';

    public function up(): void
    {
        $values = [
            'beschreibung' => 'Erlaubt den Zugriff auf den KI-Arbeitsbereich, das Erzeugen von Inhalten sowie den Zugriff auf die eigenen KI-Verlaeufe und Exporte.',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('permissions', 'display_name')) {
            $values['display_name'] = 'KI-Arbeitsbereich verwenden';
        }

        DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->update($values);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $values = [
            'beschreibung' => 'Erlaubt das Erzeugen eines projektgebundenen KI-Berichtsentwurfs. Eine menschliche Freigabe bleibt erforderlich.',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('permissions', 'display_name')) {
            $values['display_name'] = 'KI-Berichtsentwurf erstellen';
        }

        DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->update($values);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

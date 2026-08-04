<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dokumentes', 'gruppen_export_modus')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->string('gruppen_export_modus', 30)->default('einzelne_dateien')->after('export_permission');
            });
        }

        DB::table('dokumentes')
            ->where('typ', 'word')
            ->where('kontext', 'gruppe')
            ->update(['gruppen_export_modus' => 'eine_datei']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('dokumentes', 'gruppen_export_modus')) {
            Schema::table('dokumentes', function (Blueprint $table) {
                $table->dropColumn('gruppen_export_modus');
            });
        }
    }
};

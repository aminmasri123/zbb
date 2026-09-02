<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = collect([
            'luv_type', 'form_version', 'template_format', 'field_schema', 'source_settings', 'schedule_settings',
        ])->reject(fn (string $column) => Schema::hasColumn('projekt_luv_templates', $column))->all();

        Schema::table('projekt_luv_templates', function (Blueprint $table) use ($missing): void {
            if (in_array('luv_type', $missing, true)) $table->string('luv_type', 20)->nullable()->after('projekt_id');
            if (in_array('form_version', $missing, true)) $table->string('form_version', 60)->default('legacy')->after('name');
            if (in_array('template_format', $missing, true)) $table->string('template_format', 20)->nullable()->after('original_filename');
            if (in_array('field_schema', $missing, true)) $table->json('field_schema')->nullable()->after('sections');
            if (in_array('source_settings', $missing, true)) $table->json('source_settings')->nullable()->after('field_schema');
            if (in_array('schedule_settings', $missing, true)) $table->json('schedule_settings')->nullable()->after('source_settings');
        });

        $indexes = collect(Schema::getIndexes('projekt_luv_templates'))->pluck('name');
        if (! $indexes->contains('projekt_luv_templates_projekt_id_index')) {
            Schema::table('projekt_luv_templates', fn (Blueprint $table) => $table->index('projekt_id', 'projekt_luv_templates_projekt_id_index'));
        }
        if ($indexes->contains('projekt_luv_templates_projekt_id_version_unique')) {
            Schema::table('projekt_luv_templates', fn (Blueprint $table) => $table->dropUnique('projekt_luv_templates_projekt_id_version_unique'));
        }

        $indexes = collect(Schema::getIndexes('projekt_luv_templates'))->pluck('name');
        Schema::table('projekt_luv_templates', function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains('projekt_luv_type_version_unique')) {
                $table->unique(['projekt_id', 'luv_type', 'version'], 'projekt_luv_type_version_unique');
            }
            if (! $indexes->contains('projekt_luv_type_active_index')) {
                $table->index(['projekt_id', 'luv_type', 'is_active'], 'projekt_luv_type_active_index');
            }
        });

        foreach (DB::table('projekt_luv_templates')->select(['id', 'original_filename', 'file_path'])->get() as $template) {
            $extension = strtolower(pathinfo((string) ($template->original_filename ?: $template->file_path), PATHINFO_EXTENSION));
            if (in_array($extension, ['docx', 'pdf'], true)) {
                DB::table('projekt_luv_templates')->where('id', $template->id)->update(['template_format' => $extension]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('projekt_luv_templates', function (Blueprint $table): void {
            $table->dropIndex('projekt_luv_type_active_index');
            $table->dropUnique('projekt_luv_type_version_unique');
            $table->unique(['projekt_id', 'version']);
            $table->dropIndex('projekt_luv_templates_projekt_id_index');
            $table->dropColumn([
                'luv_type',
                'form_version',
                'template_format',
                'field_schema',
                'source_settings',
                'schedule_settings',
            ]);
        });
    }
};

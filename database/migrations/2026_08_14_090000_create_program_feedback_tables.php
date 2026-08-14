<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->index();
            $table->string('title', 160);
            $table->text('description');
            $table->text('expected_result')->nullable();
            $table->string('area', 100)->nullable()->index();
            $table->string('priority', 30)->default('normal')->index();
            $table->string('status', 40)->default('new')->index();
            $table->string('page_url', 2048)->nullable();
            $table->string('browser', 500)->nullable();
            $table->string('app_version', 80)->nullable();
            $table->string('release_version', 80)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'type', 'priority']);
        });

        Schema::create('program_feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_feedback_id')->constrained('program_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('program_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_feedback_id')->constrained('program_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('program_feedback_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_feedback_id')->constrained('program_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Programm-Feedback')
            ->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'Programm-Feedback',
                'beschreibung' => 'Verbesserungsvorschlaege und Fehlermeldungen zum Programm.',
            ]);
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'program-feedback.manage', 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => 'Erlaubt das Einsehen und Bearbeiten aller Programm-Meldungen inklusive interner Notizen.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'program-feedback.manage')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer', 'IT'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_berechtigungskategories')->insertOrIgnore([
                'role_id' => $roleId,
                'berechtigungskategorie_id' => $categoryId,
            ]);
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'program-feedback.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('program_feedback_history');
        Schema::dropIfExists('program_feedback_attachments');
        Schema::dropIfExists('program_feedback_comments');
        Schema::dropIfExists('program_feedback');

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Programm-Feedback')
            ->value('id');

        if ($categoryId) {
            DB::table('role_berechtigungskategories')->where('berechtigungskategorie_id', $categoryId)->delete();
            DB::table('berechtigungskategories')->where('id', $categoryId)->delete();
        }

        $this->clearPermissionCache();
    }

    private function clearPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};

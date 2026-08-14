<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('direct')->index();
            $table->string('name', 160)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('retention_days')->default(365);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('staff_conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('staff_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'staff_conversation_member_unique');
            $table->index(['user_id', 'last_read_at'], 'staff_conversation_member_unread');
        });

        Schema::create('staff_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('staff_conversations')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('materialanforderung_id')->nullable()->constrained('materialanforderungs')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'staff_message_conversation_time');
        });

        Schema::create('staff_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('staff_messages')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path', 1024);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('materialanforderung_kommentare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anforderung_id')->constrained('materialanforderungs')->cascadeOnDelete();
            $table->foreignId('artikel_id')->nullable()->constrained('materialanforderung_artikels')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('materialanforderung_kommentare')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grund', 50)->default('allgemein')->index();
            $table->text('body');
            $table->decimal('vorgeschlagener_preis', 10, 2)->nullable();
            $table->text('vorgeschlagener_link')->nullable();
            $table->boolean('antwort_erforderlich')->default(false)->index();
            $table->timestamp('geklaert_am')->nullable()->index();
            $table->foreignId('geklaert_von_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['anforderung_id', 'created_at'], 'material_comment_request_time');
        });

        Schema::create('materialanforderung_kommentar_anhaenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kommentar_id')->constrained('materialanforderung_kommentare')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path', 1024);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        $categoryId = DB::table('berechtigungskategories')->where('name', 'Interne Kommunikation')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'Interne Kommunikation',
                'beschreibung' => 'Datenschutzgeschuetzte interne Einzel-, Gruppen- und Projektkommunikation.',
            ]);
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'chat.use', 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => 'Erlaubt die Nutzung des internen Mitarbeitenden-Chats.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $chatPermissionId = DB::table('permissions')->where('name', 'chat.use')->where('guard_name', 'web')->value('id');
        if ($chatPermissionId) {
            $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id');
            foreach ($roleIds as $roleId) {
                DB::table('role_berechtigungskategories')->insertOrIgnore([
                    'role_id' => $roleId,
                    'berechtigungskategorie_id' => $categoryId,
                ]);
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $chatPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'chat.use')->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('materialanforderung_kommentar_anhaenge');
        Schema::dropIfExists('materialanforderung_kommentare');
        Schema::dropIfExists('staff_message_attachments');
        Schema::dropIfExists('staff_messages');
        Schema::dropIfExists('staff_conversation_members');
        Schema::dropIfExists('staff_conversations');

        $categoryId = DB::table('berechtigungskategories')->where('name', 'Interne Kommunikation')->value('id');
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

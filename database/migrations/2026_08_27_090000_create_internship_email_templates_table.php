<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('internship_email_templates')->insert([
            [
                'key' => 'initial',
                'subject' => 'Praktikum von {{teilnehmer_name}} bei {{betrieb}}',
                'body' => "Sehr geehrte Damen und Herren,\n\nwir nehmen bezüglich des Praktikums von {{teilnehmer_name}} bei {{betrieb}} im Zeitraum vom {{startdatum}} bis {{enddatum}} Kontakt mit Ihnen auf.\n\nMit freundlichen Grüßen\n{{absender_name}}\n{{absender_email}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'reminder_1',
                'subject' => 'Erinnerung: Praktikum von {{teilnehmer_name}} bei {{betrieb}}',
                'body' => "Sehr geehrte Damen und Herren,\n\nwir möchten freundlich an unsere Nachricht zum Praktikum von {{teilnehmer_name}} bei {{betrieb}} im Zeitraum vom {{startdatum}} bis {{enddatum}} erinnern.\n\nFür eine kurze Rückmeldung bedanken wir uns.\n\nMit freundlichen Grüßen\n{{absender_name}}\n{{absender_email}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'reminder_2',
                'subject' => 'Zweite Erinnerung: Praktikum von {{teilnehmer_name}} bei {{betrieb}}',
                'body' => "Sehr geehrte Damen und Herren,\n\nwir erinnern erneut an unsere Nachricht zum Praktikum von {{teilnehmer_name}} bei {{betrieb}} im Zeitraum vom {{startdatum}} bis {{enddatum}}.\n\nBitte geben Sie uns hierzu eine kurze Rückmeldung.\n\nMit freundlichen Grüßen\n{{absender_name}}\n{{absender_email}}",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_email_templates');
    }
};

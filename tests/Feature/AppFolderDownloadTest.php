<?php

namespace Tests\Feature;

use App\Models\AppFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class AppFolderDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_preserves_nested_files_and_empty_folders_but_excludes_private_children(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $root = $this->item($owner, null, 'Berichte');
        $nested = $this->item($owner, $root, 'Klasse 8');
        $this->item($owner, $root, 'Leer');
        $this->item($owner, $nested, 'PA.pdf', 'first');
        $this->item($owner, $nested, 'PA.pdf', 'second');
        $this->item($other, $root, 'privat.pdf', 'secret');
        $hidden = $this->item($other, $root, 'Geheim');
        $this->item($owner, $hidden, 'nicht-erreichbar.pdf', 'secret');
        $response = $this->actingAs($owner)->get(route('apps.files.download', $root))->assertOk();
        $path = $response->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertSame(5, $zip->numFiles);
            $this->assertSame('first', $zip->getFromName('Berichte/Klasse 8/PA.pdf'));
            $this->assertSame('second', $zip->getFromName('Berichte/Klasse 8/PA (1).pdf'));
            $this->assertNotFalse($zip->locateName('Berichte/Leer/'));
            $zip->close();
        } finally { unlink($path); }
        $this->actingAs($other)->get(route('apps.files.download', $root))->assertNotFound();
    }

    public function test_zip_sanitizes_paths_and_supports_empty_folders(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $root = $this->item($owner, null, '../Berichte');
        $this->item($owner, $root, '../../bericht.pdf', 'pdf');
        $response = $this->actingAs($owner)->get(route('apps.files.download', $root))->assertOk();
        $path = $response->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $zip->open($path);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $this->assertStringNotContainsString('../', $name);
                $this->assertStringNotContainsString('\\', $name);
                $this->assertFalse(str_starts_with($name, '/'));
            }
            $zip->close();
        } finally { unlink($path); }
        $empty = $this->item($owner, null, 'Leer');
        $response = $this->get(route('apps.files.download', $empty))->assertOk();
        unlink($response->getFile()->getPathname());
    }

    public function test_missing_source_aborts_instead_of_delivering_incomplete_zip(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $root = $this->item($owner, null, 'Berichte');
        $file = $this->item($owner, $root, 'PA.pdf', 'pdf');
        Storage::delete($file->path);
        $this->actingAs($owner)->get(route('apps.files.download', $root))->assertStatus(409);
    }

    private function item(User $owner, ?AppFile $parent, string $name, ?string $contents = null): AppFile
    {
        $path = $contents === null ? null : 'tests/'.\Illuminate\Support\Str::uuid();
        if ($path) Storage::put($path, $contents);
        return AppFile::create([
            'owner_user_id' => $owner->id, 'parent_id' => $parent?->id,
            'type' => $contents === null ? 'folder' : 'file', 'name' => $name,
            'original_name' => $name, 'path' => $path, 'visibility' => 'private',
        ]);
    }
}

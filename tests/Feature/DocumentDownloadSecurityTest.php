<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Dokumente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Storage::delete('export-vorlagen/test-template.pdf');

        parent::tearDown();
    }

    public function test_download_permission_is_sufficient_for_a_managed_template(): void
    {
        Storage::put('export-vorlagen/test-template.pdf', 'PDF');
        $dokument = $this->document('/app/export-vorlagen/test-template.pdf');
        $user = User::factory()->create();
        $this->givePermission($user, 'dokumente.download');

        $this->actingAs($user)
            ->get(route('dokumente.download', $dokument))
            ->assertOk()
            ->assertDownload('test-template.pdf');
    }

    public function test_document_path_cannot_escape_the_storage_directory(): void
    {
        $dokument = $this->document('/app/../../.env');
        $user = User::factory()->create();
        $this->givePermission($user, 'dokumente.download');

        $this->actingAs($user)
            ->get(route('dokumente.download', $dokument))
            ->assertNotFound();
    }

    public function test_document_download_requires_its_specific_permission(): void
    {
        $dokument = $this->document('/app/export-vorlagen/missing.pdf');
        $this->ensurePermission('dokumente.download');

        $this->actingAs(User::factory()->create())
            ->get(route('dokumente.download', $dokument))
            ->assertForbidden();
    }

    public function test_all_named_download_and_export_routes_are_authenticated_and_permission_protected(): void
    {
        $sensitiveRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                $haystack = strtolower(($route->getName() ?? '') . ' ' . $route->uri());

                return preg_match('/download|export|pdf|excel|folder/', $haystack) === 1;
            });

        $this->assertNotEmpty($sensitiveRoutes);

        foreach ($sensitiveRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Route {$route->getName()} muss authentifiziert sein.");
            $this->assertContains('routePermission', $middleware, "Route {$route->getName()} muss eine Permission pruefen.");
        }
    }

    private function document(string $path): Dokumente
    {
        return Dokumente::query()->create([
            'name' => 'Testvorlage',
            'typ' => 'pdf',
            'dateipfad' => $path,
            'dateipfadName' => 'test-template.pdf',
            'version' => '1',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Test'],
            ['beschreibung' => '']
        );

        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $category->id,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

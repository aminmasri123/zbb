<?php

namespace Tests\Feature;

use App\Models\Dokumente;
use App\Models\Projekt;
use App\Services\Bop\RolltagAttendanceTemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolltagManagedTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolltag_resolves_both_project_bound_managed_templates(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $templates = Dokumente::query()
            ->whereIn('export_permission', [
                RolltagAttendanceTemplateResolver::CLASS_EXPORT_PERMISSION,
                RolltagAttendanceTemplateResolver::GROUP_EXPORT_PERMISSION,
            ])
            ->get();
        $this->assertCount(2, $templates);

        $project->dokumente()->attach($templates->pluck('id')->all(), [
            'gruppen_export' => true,
            'serienbrief' => false,
            'sort_order' => 0,
        ]);

        $resolver = app(RolltagAttendanceTemplateResolver::class);
        $this->assertFileExists($resolver->classTemplate($project->id));
        $this->assertFileExists($resolver->groupTemplate($project->id));

        $templates->firstWhere('export_permission', RolltagAttendanceTemplateResolver::CLASS_EXPORT_PERMISSION)
            ->update(['aktiv' => false]);

        $this->expectException(ValidationException::class);
        $resolver->classTemplate($project->id);
    }
}

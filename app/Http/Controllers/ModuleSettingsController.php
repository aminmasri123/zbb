<?php

namespace App\Http\Controllers;

use App\Models\SystemModule;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleSettingsController extends Controller
{
    public function index(ModuleStateResolver $resolver)
    {
        return Inertia::render('Einstellung/Modules/Index', [
            'modules' => SystemModule::query()
                ->where('visible_in_settings', true)
                ->with(['assignments.activatedBy:id,username'])
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(fn (SystemModule $module) => [
                    'id' => $module->id,
                    'key' => $module->key,
                    'name' => $module->name,
                    'description' => $module->description,
                    'category' => $module->category,
                    'is_system_module' => $module->is_system_module,
                    'is_enforced' => $module->is_enforced,
                    'supports_location_scope' => $module->supports_location_scope,
                    'default_enabled' => $module->default_enabled,
                    'global_enabled' => $resolver->enabled($module->key),
                    'assignments' => $module->assignments,
                ]),
        ]);
    }

    public function update(Request $request, SystemModule $module, ModuleStateResolver $resolver)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        abort_if($module->is_system_module && !$validated['enabled'], 422, 'Systemmodule koennen nicht deaktiviert werden.');
        abort_unless($module->is_enforced, 422, 'Dieses Modul ist noch nicht vollstaendig an den Backend-Schutz angeschlossen.');
        $resolver->set(
            $module,
            (bool) $validated['enabled'],
            null,
            $request->user()?->id
        );

        return back()->with('success', 'Modulstatus wurde aktualisiert.');
    }
}

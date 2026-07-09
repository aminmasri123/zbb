<?php

namespace App\Http\Controllers;

use App\Models\NotificationRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class NotificationRuleController extends Controller
{
    public function index()
    {
        NotificationRule::ensureDefaultRules();

        return Inertia::render('Einstellung/NotificationRules/Index', [
            'rules' => NotificationRule::query()
                ->orderBy('sort_order')
                ->orderBy('event_key')
                ->orderBy('id')
                ->get(),
            'events' => NotificationRule::events(),
            'targetTypes' => NotificationRule::TARGET_TYPES,
            'scopes' => NotificationRule::SCOPES,
            'channels' => NotificationRule::CHANNELS,
            'targetRoles' => Role::orderBy('name')->get(['id', 'name']),
            'targetPermissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        NotificationRule::create($this->validatedRuleData($request));

        return back()->with('success', 'Benachrichtigungsregel wurde angelegt.');
    }

    public function update(Request $request, NotificationRule $notificationRule)
    {
        $notificationRule->update($this->validatedRuleData($request));

        return back()->with('success', 'Benachrichtigungsregel wurde gespeichert.');
    }

    public function destroy(NotificationRule $notificationRule)
    {
        $notificationRule->delete();

        return back()->with('success', 'Benachrichtigungsregel wurde entfernt.');
    }

    private function validatedRuleData(Request $request): array
    {
        $data = $request->validate([
            'event_key' => ['required', 'string', Rule::in(array_keys(NotificationRule::events()))],
            'label' => ['required', 'string', 'max:255'],
            'target_type' => ['required', 'string', Rule::in(array_keys(NotificationRule::TARGET_TYPES))],
            'target_value' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', Rule::in(array_keys(NotificationRule::SCOPES))],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(array_keys(NotificationRule::CHANNELS))],
            'active' => ['boolean'],
            'exclude_actor' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (in_array($data['target_type'], ['creator', 'department_reviewers'], true)) {
            $data['target_value'] = null;
        }

        if ($data['target_type'] === 'permission') {
            $request->validate([
                'target_value' => ['required', 'string', 'exists:permissions,name'],
            ]);
        }

        if ($data['target_type'] === 'role') {
            $request->validate([
                'target_value' => ['required', 'string', 'exists:roles,name'],
            ]);
        }

        $data['channels'] = $data['channels'] ?? ['database'];
        $data['active'] = (bool) ($data['active'] ?? false);
        $data['exclude_actor'] = (bool) ($data['exclude_actor'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 100);

        return $data;
    }
}

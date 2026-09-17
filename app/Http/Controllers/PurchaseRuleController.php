<?php
namespace App\Http\Controllers;

use App\Models\PurchaseRule;
use App\Models\Standort;
use App\Models\User;
use App\Services\Purchasing\PurchaseWorkflow;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseRuleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('materialanforderung.settings.manage'), 403);
        return Inertia::render('Bestellungen/Materialanforderung/Settings', [
            'current' => PurchaseRule::current(),
            'versions' => PurchaseRule::orderByDesc('effective_at')->orderByDesc('id')->limit(20)->get(),
            'standorte' => Standort::orderBy('name')->get(['id', 'name']),
            'users' => User::with('person')->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->sortBy('name')->values(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('materialanforderung.settings.manage'), 403);
        $data = $request->validate([
            'approval_limit' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quote_limit' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quote_count' => ['required', 'integer', 'between:1,10'],
            'location_ids' => ['present', 'array'], 'location_ids.*' => ['integer', 'distinct', 'exists:standorts,id'],
            'approver_ids' => ['present', 'array'], 'approver_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'manual_referral' => ['required', 'boolean'],
            'effective_at' => ['required', 'date', 'after_or_equal:today'],
        ]);
        PurchaseRule::create([
            'approval_limit_cents' => PurchaseWorkflow::cents($data['approval_limit']),
            'quote_limit_cents' => PurchaseWorkflow::cents($data['quote_limit']),
            'quote_count' => $data['quote_count'], 'location_ids' => array_map('intval', $data['location_ids']),
            'approver_ids' => array_map('intval', $data['approver_ids']), 'manual_referral' => $data['manual_referral'],
            'effective_at' => \Illuminate\Support\Carbon::parse($data['effective_at'])->setTimezone(config('app.timezone'))->toDateTimeString(),
            'created_by' => $request->user()->id,
        ]);
        return back()->with('success', 'Die Freigaberegeln wurden als neue Version gespeichert. Bereits eingereichte Vorgänge behalten ihre Regeln.');
    }
}

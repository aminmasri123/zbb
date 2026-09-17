<?php
namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\PurchaseOffer;
use App\Services\Purchasing\PurchaseOfferWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PurchaseOfferController extends Controller
{
    private function editable(Request $request, Materialanforderung $order): void
    {
        abort_unless($request->user()->can('materialanforderung.update')
            && (int) $order->ersteller_id === (int) $request->user()->id
            && in_array($order->status, ['entwurf', 'zur_ueberarbeitung'], true), 403);
    }

    public function store(Request $request, Materialanforderung $materialanforderung, PurchaseOfferWriter $writer)
    {
        $this->editable($request, $materialanforderung);
        $data = $request->validate(PurchaseOfferWriter::rules() + ['revision' => ['required', 'integer']]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $materialanforderung, $writer, $data, &$paths) {
                $order = Materialanforderung::whereKey($materialanforderung->id)->lockForUpdate()->firstOrFail();
                $this->editable($request, $order);
                if ((int) $data['revision'] !== $order->revision) throw ValidationException::withMessages(['angebote' => 'Der Vorgang wurde geändert. Bitte laden Sie die Seite neu.']);
                $writer->store($order, $data, $request->file('datei'), (int) $request->user()->id, $paths);
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success', 'Das Vergleichsangebot wurde gespeichert.');
    }

    public function destroy(Request $request, PurchaseOffer $offer)
    {
        $path = DB::transaction(function () use ($request, $offer) {
            $order = Materialanforderung::whereKey($offer->anforderung_id)->lockForUpdate()->firstOrFail();
            $this->editable($request, $order);
            abort_unless($offer->revision === $order->revision, 403);
            $offer->delete();
            return $offer->path;
        });
        Storage::disk('local')->delete($path);
        return back()->with('success', 'Das Angebot wurde entfernt.');
    }

    public function download(Request $request, PurchaseOffer $offer)
    {
        abort_unless(app(MaterialanforderungController::class)->mayView($request->user(), $offer->anforderung), 403);
        abort_unless(Storage::disk('local')->exists($offer->path), 404);
        return Storage::disk('local')->download($offer->path, $offer->original_name);
    }
}

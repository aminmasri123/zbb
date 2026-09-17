<?php
namespace App\Services\Purchasing;

use App\Models\Materialanforderung;
use App\Models\PurchaseOffer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PurchaseOfferWriter
{
    public static function uploadLimits(): array
    {
        $file = ini_parse_quantity(ini_get('upload_max_filesize'));
        $post = ini_parse_quantity(ini_get('post_max_size'));
        return [
            'file_bytes' => min(10 * 1024 * 1024, $file > 0 ? $file : PHP_INT_MAX),
            // Leave room for the material request fields and multipart headers.
            'total_bytes' => $post > 0 ? max(0, $post - 1024 * 1024) : null,
            'max_offers' => max(1, min(20, (int) ini_get('max_file_uploads'))),
        ];
    }

    public static function rules(string $prefix = '', string $lineKey = 'id'): array
    {
        $rules = [
            'preisart' => ['sometimes', 'required', 'in:brutto,netto'],
            'lieferant' => ['required', 'string', 'max:255'],
            'lieferant_adresse' => ['required', 'string', 'max:1000'],
            'angebotsnummer' => ['nullable', 'string', 'max:100'],
            'angebotsdatum' => ['required', 'date'],
            'gueltig_bis' => ['nullable', 'date', 'after_or_equal:'.$prefix.'angebotsdatum', 'after_or_equal:today'],
            'lieferzeit' => ['nullable', 'string', 'max:255'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
            'empfohlen' => ['boolean'],
            'versand_netto' => ['required', 'numeric', 'between:0,99999999'],
            'versand_mwst' => ['required', 'numeric', 'between:0,100'],
            'positionen' => ['required', 'array', 'min:1'],
            'positionen.*.'.$lineKey => ['required', $lineKey === 'id' ? 'integer' : 'string', 'distinct'],
            'positionen.*.einzelpreis' => ['required', 'numeric', 'between:0,99999999'],
            'positionen.*.mwst' => ['required', 'numeric', 'between:0,100'],
            'datei' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png', 'max:'.intdiv(self::uploadLimits()['file_bytes'], 1024)],
        ];
        $result = [];
        foreach ($rules as $key => $value) $result[$prefix.$key] = $value;
        return $result;
    }

    /** The caller owns the DB transaction and deletes tracked files on rollback. */
    public function store(Materialanforderung $order, array $data, UploadedFile $file, int $userId, array &$paths, string $prefix = ''): PurchaseOffer
    {
        $items = $order->artikeln()->orderBy('id')->get();
        $prices = collect($data['positionen'])->keyBy('id');
        if (count($data['positionen']) !== $items->count()
            || $prices->keys()->map(fn ($id) => (int) $id)->sort()->values()->all() !== $items->pluck('id')->all()) {
            throw ValidationException::withMessages([$prefix.'positionen' => 'Das Angebot muss alle aktuellen Positionen dieser Materialanforderung genau einmal enthalten.']);
        }
        $lines = $items->map(fn ($item) => [
            'id' => $item->id, 'pos' => $item->pos, 'artikel' => $item->artikel,
            'art_nr' => $item->art_nr, 'stueck' => $item->stueck,
            'einzelpreis' => $prices[$item->id]['einzelpreis'], 'mwst' => $prices[$item->id]['mwst'],
        ])->all();
        $workflow = app(PurchaseWorkflow::class);
        $values = $workflow->normalizePrices(array_replace(Arr::only($data, [
            'preisart', 'lieferant', 'lieferant_adresse', 'angebotsnummer', 'angebotsdatum',
            'gueltig_bis', 'lieferzeit', 'bemerkung', 'empfohlen', 'versand_netto', 'versand_mwst',
        ]), ['positionen' => $lines]));
        [$net, $gross] = $workflow->totals($values['positionen'], $values['versand_netto'], $values['versand_mwst'], $values['versand_brutto']);
        if ($values['empfohlen'] ?? false) $order->angebote()->where('revision', $order->revision)->update(['empfohlen' => false]);
        $path = $file->store('materialanforderungen/'.$order->id.'/angebote', 'local');
        if (!$path) throw ValidationException::withMessages([$prefix.'datei' => 'Die Angebotsdatei konnte nicht gespeichert werden. Bitte erneut versuchen.']);
        $paths[] = $path;
        return $order->angebote()->create($values + [
            'revision' => $order->revision, 'netto' => $net, 'brutto' => $gross,
            'path' => $path, 'original_name' => $file->getClientOriginalName(), 'created_by' => $userId,
        ]);
    }
}

<?php
namespace App\Services\Purchasing;

use App\Models\Materialanforderung;
use App\Models\PurchaseOffer;
use App\Models\PurchaseRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseWorkflow
{
    public static function cents($amount): int { return (int) round((float) $amount * 100); }

    /** Net and gross cents; retain the entered gross price to avoid unit-rounding drift. */
    public static function amounts($price, $quantity, $tax, bool $gross = false): array
    {
        $value = self::cents($price) * (int) $quantity;
        $rate = self::cents($tax);
        if ($gross) return [(int) round($value * 10000 / (10000 + $rate)), $value];
        return [$value, $value + (int) round($value * $rate / 10000)];
    }

    public static function lineAmounts(array $line): array
    {
        $gross = isset($line['einzelpreis_brutto']);
        return self::amounts($gross ? $line['einzelpreis_brutto'] : $line['einzelpreis'], $line['stueck'], $line['mwst'], $gross);
    }

    /** Convert validated input once. Existing net fields keep their original meaning. */
    public function normalizePrices(array $data): array
    {
        $data['preisart'] = $data['preisart'] ?? 'netto';
        $gross = $data['preisart'] === 'brutto';
        foreach ($data['positionen'] as &$line) {
            $input = self::cents($line['einzelpreis']) / 100;
            $line['mwst'] = self::cents($line['mwst']) / 100;
            $line['einzelpreis_brutto'] = $gross ? $input : null;
            $line['einzelpreis'] = self::amounts($input, 1, $line['mwst'], $gross)[0] / 100;
            $line['gesamtpreis'] = self::amounts($input, $line['stueck'], $line['mwst'], $gross)[0] / 100;
        }
        unset($line);
        $shipping = self::cents($data['versand_netto'] ?? 0) / 100;
        $data['versand_mwst'] = self::cents($data['versand_mwst'] ?? 19) / 100;
        $data['versand_brutto'] = $gross ? $shipping : null;
        $data['versand_netto'] = self::amounts($shipping, 1, $data['versand_mwst'], $gross)[0] / 100;
        return $data;
    }

    public function totals(array $lines, $shipping = 0, $shippingTax = 19, $shippingGross = null): array
    {
        [$net, $gross] = self::amounts($shippingGross ?? $shipping, 1, $shippingTax, $shippingGross !== null);
        foreach ($lines as $line) {
            [$lineNet, $lineGross] = self::lineAmounts($line);
            $net += $lineNet;
            $gross += $lineGross;
        }
        return [$net / 100, $gross / 100];
    }

    public function number(Materialanforderung $request): string
    {
        return DB::transaction(function () use ($request) {
            $locked = Materialanforderung::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->bestellnummer) return $locked->bestellnummer;
            $legacy = trim((string) $locked->vergabevermerk?->bestellnummer);
            if ($legacy !== '') {
                DB::table('purchase_numbers')->insertOrIgnore(['number' => $legacy, 'request_id' => $locked->id]);
                $locked->update(['bestellnummer' => $legacy]);
                return $legacy;
            }
            $year = (int) ($locked->created_at?->year ?? now()->year);
            DB::table('purchase_number_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $counter = DB::table('purchase_number_counters')->where('year', $year)->lockForUpdate()->first();
            $last = (int) $counter->last_number;
            if ($last === 0) {
                foreach (DB::table('purchase_numbers')->where('number', 'like', '%/'.$year)->pluck('number') as $number) {
                    if (preg_match('/^(\d+)\/'.$year.'$/', $number, $match)) $last = max($last, (int) $match[1]);
                }
            }
            do { $number = (++$last).'/'.$year; } while (DB::table('purchase_numbers')->where('number', $number)->exists());
            DB::table('purchase_number_counters')->where('year', $year)->update(['last_number' => $last]);
            DB::table('purchase_numbers')->insert(['number' => $number, 'request_id' => $locked->id]);
            $locked->update(['bestellnummer' => $number]);
            $request->bestellnummer = $number;
            return $number;
        }, 3);
    }

    public function evaluate(Materialanforderung $request): array
    {
        $rule = PurchaseRule::current();
        $amount = self::cents($request->endsumme);
        $reasons = [];
        if ($amount > $rule->approval_limit_cents) $reasons[] = 'Betrag über '.number_format($rule->approval_limit_cents / 100, 2, ',', '.').' € brutto';
        if (in_array((int) $request->standort_id, $rule->location_ids, true)) $reasons[] = 'Standortregel';
        if ($amount > $rule->quote_limit_cents && !$reasons) $reasons[] = 'Entscheidung über Vergleichsangebote';
        return ['rule_id' => $rule->id, 'revision' => $request->revision, 'gf_required' => count($reasons) > 0,
            'reasons' => $reasons, 'min_quotes' => $amount > $rule->quote_limit_cents ? $rule->quote_count : 0,
            'quote_limit_cents' => $rule->quote_limit_cents, 'quote_count' => $rule->quote_count];
    }

    public function requireOffers(Materialanforderung $request, array $policy, ?PurchaseOffer $selected = null): void
    {
        $required = (int) ($policy['min_quotes'] ?? 0);
        if ($selected && self::cents($selected->brutto) > ($policy['quote_limit_cents'] ?? PHP_INT_MAX)) {
            $required = max($required, (int) $policy['quote_count']);
        }
        $offers = $request->angebote()->where('revision', $request->revision)
            ->where(fn ($q) => $q->whereNull('gueltig_bis')->orWhereDate('gueltig_bis', '>=', today()))->get();
        $count = $offers->unique(fn ($offer) => mb_strtolower(trim($offer->lieferant)))->count();
        if ($count < $required) throw ValidationException::withMessages(['angebote' => "Es werden {$required} gültige Angebote unterschiedlicher Lieferanten benötigt. Vorhanden: {$count}."]);
        if ($selected && ! $offers->contains('id', $selected->id)) throw ValidationException::withMessages(['angebot_id' => 'Das Angebot ist abgelaufen oder gehört zu einer älteren Fassung.']);
    }

    public function isDirector(User $user): bool
    {
        return in_array((int) $user->id, PurchaseRule::current()->approver_ids, true)
            || $user->can('materialanforderung.gf_freigabe');
    }

    public function directors()
    {
        return User::with('person')->whereIn('id', PurchaseRule::current()->approver_ids)->get()
            ->merge(User::permission('materialanforderung.gf_freigabe')->with('person')->get())->unique('id')->values();
    }

    public function selectOffer(Materialanforderung $request, int $offerId): void
    {
        $offer = $request->angebote()->whereKey($offerId)->firstOrFail();
        $this->requireOffers($request, $request->approval_policy ?? [], $offer);
        foreach ($offer->positionen as $line) {
            $request->artikeln()->whereKey($line['id'])->update([
                'einzelpreis' => $line['einzelpreis'], 'einzelpreis_brutto' => $line['einzelpreis_brutto'] ?? null, 'mwst' => $line['mwst'],
                'gesamtpreis' => self::lineAmounts($line)[0] / 100,
            ]);
        }
        $request->update(['selected_offer_id' => $offer->id, 'gesamtpreis' => $offer->netto,
            'preisart' => $offer->preisart ?? 'netto', 'versand_brutto' => $offer->versand_brutto,
            'endsumme' => $offer->brutto, 'versand_netto' => $offer->versand_netto,
            'versand_mwst' => $offer->versand_mwst, 'lieferant_adresse' => $offer->lieferant_adresse]);
        $request->vergabevermerk()->updateOrCreate(['anforderung_id' => $request->id], ['lieferant' => $offer->lieferant]);
    }

    public function summary(Materialanforderung $request): array
    {
        $policy = $request->approval_policy ?: $this->evaluate($request);
        return $policy + ['director_configured' => $this->directors()->isNotEmpty(),
            'manual_referral' => PurchaseRule::current()->manual_referral];
    }

    public function contentHash(Materialanforderung $request): string
    {
        return hash('sha256', json_encode([
            $request->standort_id, $request->kostenstelle, $request->lieferant_adresse,
            (string) $request->versand_netto, (string) $request->versand_mwst,
            $request->preisart, $request->versand_brutto,
            $request->vergabevermerk?->only(['lieferant', 'lieferung_art', 'lieferadresse', 'lieferung_option', 'leistungsort']),
            $request->artikeln()->orderBy('id')->get()->map(fn ($item) => [
                $item->id, $item->artikel, $item->art_nr, (int) $item->stueck,
                self::cents($item->einzelpreis), self::cents($item->mwst),
                $item->einzelpreis_brutto === null ? null : self::cents($item->einzelpreis_brutto),
            ])->all(),
        ]));
    }
}

<?php
namespace App\Services\Purchasing;

use App\Models\Materialanforderung;
use App\Models\User;
use App\Services\Documents\OfficeToPdfConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class PurchaseOrderExport
{
    public function snapshot(Materialanforderung $order, ?User $buyer = null): array
    {
        $order->load(['artikeln', 'vergabevermerk', 'projekt', 'standort', 'genehmigungen.genehmiger']);
        $approval = $order->genehmigungen->whereIn('status', ['gf_genehmigt', 'kaufmaennisch_genehmigt'])->sortByDesc('id')->first();
        $service = $order->vergabevermerk?->lieferung_art === 'Dienstleistung';
        return [
            'number' => $order->bestellnummer, 'date' => ($order->bestellt_am ?? $order->updated_at)->format('d.m.Y'),
            'supplier' => $order->vergabevermerk?->lieferant ?? '', 'supplier_address' => $order->lieferant_adresse ?? '',
            'cost_center' => $order->kostenstelle, 'project' => $order->projekt?->name,
            'is_service' => $service, 'service_location' => $service ? ($order->vergabevermerk?->leistungsort ?? '') : '',
            'delivery' => $service ? '' : ($order->vergabevermerk?->lieferadresse ?? ''),
            'collection' => !$service && $order->vergabevermerk?->lieferung_option === 'per Abholung',
            'reference' => $order->lieferantenreferenz,
            'net' => $order->gesamtpreis, 'gross' => $order->endsumme,
            'shipping_gross' => $order->versand_brutto, 'price_mode' => $order->preisart ?? 'netto',
            'shipping' => $order->versand_netto, 'shipping_tax' => $order->versand_mwst,
            'signers' => collect([$approval?->genehmiger?->name, $buyer?->name])->filter()->unique()->implode(' · '),
            'lines' => $order->artikeln->sortBy('pos')->map(fn ($line) => $line->only(['pos', 'artikel', 'art_nr', 'stueck', 'einzelpreis', 'einzelpreis_brutto', 'gesamtpreis', 'mwst']))->values()->all(),
        ];
    }

    public function export(Materialanforderung $order, string $format): string
    {
        app(PurchaseWorkflow::class)->number($order);
        DB::transaction(function () use ($order) {
            $locked = Materialanforderung::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$locked->order_snapshot) $locked->update(['order_snapshot' => $this->snapshot($locked)]);
        });
        $data = $order->fresh()->order_snapshot;
        $dir = Storage::disk('local')->path('materialanforderungen/'.$order->id.'/bestellschein');
        File::ensureDirectoryExists($dir);
        $lock = fopen($dir.'/export.lock', 'c');
        try {
            if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Der Bestellschein konnte nicht gesperrt werden.');
            $docx = $dir.'/Bestellschein.docx';
            if (!is_file($docx)) $this->write($data, $docx);
            if ($format === 'docx') return $docx;
            $pdf = $dir.'/Bestellschein.pdf';
            return is_file($pdf) ? $pdf : app(OfficeToPdfConverter::class)->convert($docx, $dir);
        } finally {
            if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }

    public function write(array $data, string $path): void
    {
        Settings::setOutputEscapingEnabled(true);
        $service = (bool) ($data['is_service'] ?? false);
        $grossInput = ($data['price_mode'] ?? 'netto') === 'brutto';
        $template = new TemplateProcessor(storage_path('vorlage/bestellungen/Bestellschein.docx'));
        $template->setValues([
            'lieferant' => $data['supplier'], 'lieferant_adresse' => $data['supplier_address'],
            'datum' => $data['date'], 'bestellnummer' => $data['number'],
            'kostenstelle' => $data['cost_center'], 'gesamtpreis' => $this->euro($data['gross']),
            'unterzeichner' => $data['signers'],
            'bestellgegenstand' => $service ? 'folgende Leistungen:' : 'folgende Artikel aus Ihrem Programm:',
            'belegarten' => $service ? '(BEI RECHNUNGEN UND SCHRIFTVERKEHR BITTE STETS ANGEBEN)' : '(BEI LIEFERSCHEINEN, RECHNUNGEN UND SCHRIFTVERKEHR BITTE STETS ANGEBEN)',
        ]);
        $font = ['name' => 'Arial', 'size' => 9, 'color' => '000000'];
        $table = new Table(['layout' => 'fixed', 'cellMarginTop' => 65, 'cellMarginBottom' => 65, 'cellMarginLeft' => 0, 'cellMarginRight' => 90]);
        $widths = [650, 4650, 1000, 1550, 1700];
        $table->addRow(null, ['tblHeader' => true]);
        foreach (['Pos.', $service ? 'Leistung' : 'Artikel', 'Menge', $grossInput ? 'Einzel brutto' : 'Einzel netto', $grossInput ? 'Gesamt brutto' : 'Gesamt netto'] as $i => $label) {
            $table->addCell($widths[$i])->addText($label, $font + ['bold' => true, 'italic' => true], ['alignment' => $i < 2 ? 'left' : 'right', 'spaceAfter' => 0]);
        }
        $taxes = [];
        foreach ($data['lines'] as $line) {
            $table->addRow(null, ['cantSplit' => true]);
            $values = [$line['pos'], $line['artikel'].($line['art_nr'] ? ' (Art.-Nr.: '.$line['art_nr'].')' : ''),
                $line['stueck'], $this->euro($grossInput ? $line['einzelpreis_brutto'] : $line['einzelpreis']),
                $this->euro($grossInput ? PurchaseWorkflow::cents($line['einzelpreis_brutto']) * (int) $line['stueck'] / 100 : $line['gesamtpreis'])];
            foreach ($values as $i => $value) $table->addCell($widths[$i])->addText((string) $value, $font, ['alignment' => $i < 2 ? 'left' : 'right', 'spaceAfter' => 0]);
            $rate = (string) (float) $line['mwst'];
            $taxCents = isset($line['einzelpreis_brutto'])
                ? PurchaseWorkflow::cents($line['einzelpreis_brutto']) * (int) $line['stueck'] - PurchaseWorkflow::cents($line['gesamtpreis'])
                : (int) round(PurchaseWorkflow::cents($line['gesamtpreis']) * (float) $line['mwst'] / 100);
            $taxes[$rate] = ($taxes[$rate] ?? 0) + $taxCents;
        }
        $summary = [$service ? 'Nebenkosten netto' : 'Versand netto' => $data['shipping'], 'Summe netto' => $data['net']];
        $rate = (string) (float) $data['shipping_tax'];
        $shippingTax = isset($data['shipping_gross'])
            ? PurchaseWorkflow::cents($data['shipping_gross']) - PurchaseWorkflow::cents($data['shipping'])
            : (int) round(PurchaseWorkflow::cents($data['shipping']) * (float) $rate / 100);
        $taxes[$rate] = ($taxes[$rate] ?? 0) + $shippingTax;
        foreach ($taxes as $rate => $cents) $summary['MwSt. '.number_format((float) $rate, 2, ',', '.').' %'] = $cents / 100;
        foreach ($summary as $label => $amount) {
            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell(array_sum(array_slice($widths, 0, 4)), ['gridSpan' => 4])->addText($label, $font, ['alignment' => 'right', 'spaceAfter' => 0]);
            $table->addCell($widths[4])->addText($this->euro($amount), $font, ['alignment' => 'right', 'spaceAfter' => 0]);
        }
        $template->setComplexBlock('positionen', $table);
        $delivery = new Table(['layout' => 'fixed', 'cellMarginRight' => 180]);
        $delivery->addRow();
        $cell = $delivery->addCell(5000);
        $location = $service ? ($data['service_location'] ?? '') : $data['delivery'];
        $heading = $service ? ($location !== '' ? 'Leistungsort:' : 'Dienstleistung') : ($data['collection'] ? 'Abholung' : 'Lieferanschrift:');
        $cell->addText($heading, $font + ['bold' => true]);
        foreach (explode("\n", $data['project']."\n".$location) as $line) $cell->addText(trim($line), $font, ['spaceAfter' => 0]);
        $documents = $service ? 'Rechnungen' : 'Lieferscheinen und Rechnungen';
        $delivery->addCell(4550)->addText('Bitte geben Sie auf allen '.$documents.' die o.g. Bestellnummer an, da wir sonst keinen Rechnungsausgleich vornehmen können!', $font);
        $template->setComplexBlock('lieferung', $delivery);
        File::ensureDirectoryExists(dirname($path));
        $template->saveAs($path);
    }

    private function euro($value): string { return number_format((float) $value, 2, ',', '.').' EUR'; }
}

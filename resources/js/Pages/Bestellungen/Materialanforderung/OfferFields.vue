<script setup>
import { computed } from 'vue'
import PriceInputMode from './PriceInputMode.vue'
import { purchaseTotals } from './purchaseTotals'
const props = defineProps({
    offer: Object, items: Array, isService: Boolean,
    errors: { type: Object, default: () => ({}) }, prefix: { type: String, default: '' },
    name: { type: String, default: 'offer-price-mode' }, maxFileBytes: { type: Number, default: 10485760 },
})
const rows = computed(() => props.offer.positionen.map(line => ({ line, item: props.items.find(item => line.id != null ? item.id === line.id : item.client_key === line.position_key) || {} })))
const totals = computed(() => purchaseTotals(rows.value.map(({ line, item }) => ({ ...line, stueck: item.stueck || 0 })), props.offer.versand_netto, props.offer.versand_mwst, props.offer.preisart))
const offerErrors = computed(() => Object.entries(props.errors).filter(([key]) => !props.prefix || key.startsWith(props.prefix)).map(([, message]) => message))
const euro = value => Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
function selectFile(event) {
    const input = event.target
    const file = input.files[0]
    input.setCustomValidity(file && file.size > props.maxFileBytes ? 'Die Datei ist zu groß. Bitte eine kleinere Datei auswählen.' : '')
    props.offer.datei = file || null
    if (!input.checkValidity()) input.reportValidity()
}
</script>
<template>
    <div class="space-y-4">
        <PriceInputMode :form="offer" :name="name" />
        <ul v-if="offerErrors.length" role="alert" class="list-inside list-disc text-sm text-red-700"><li v-for="(error, i) in offerErrors" :key="i">{{ error }}</li></ul>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">Lieferant / Dienstleister *<input v-model="offer.lieferant" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm">Angebotsnummer<input v-model="offer.angebotsnummer" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm md:col-span-2">Anschrift des Lieferanten / Dienstleisters *<textarea v-model="offer.lieferant_adresse" required maxlength="1000" rows="2" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm">Angebotsdatum *<input v-model="offer.angebotsdatum" required type="date" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm">Gültig bis<input v-model="offer.gueltig_bis" type="date" :min="offer.angebotsdatum" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm">{{ isService ? 'Ausführungszeit' : 'Lieferzeit' }}<input v-model="offer.lieferzeit" maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="min-w-0 text-sm">Angebotsdatei *<input required type="file" accept=".pdf,.docx,.png,.jpg,.jpeg" class="mt-1 block w-full min-w-0 text-sm" @change="selectFile" /><span class="mt-1 block text-xs text-gray-500">PDF, Word oder Bild, höchstens {{ (maxFileBytes / 1048576).toLocaleString('de-DE', { maximumFractionDigits: 1 }) }} MB</span></label>
        </div>
        <div class="space-y-3">
            <article v-for="({ line, item }, index) in rows" :key="line.id ?? line.position_key" class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-4">
                <div class="col-span-2 min-w-0 text-sm md:col-span-2"><strong class="block break-words">{{ item.artikel || ('Position ' + (index + 1)) }}</strong><span class="text-gray-600">Menge: {{ item.stueck || 0 }} · aus der Materialanforderung</span></div>
                <label class="text-sm">Einzelpreis {{ offer.preisart }} (€) *<input v-model.number="line.einzelpreis" required type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                <label class="text-sm">MwSt. (%) *<input v-model.number="line.mwst" required type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            </article>
        </div>
        <p class="text-xs text-gray-600">Positionen und Mengen gelten für alle Angebote. Hier trägst du die Preise dieses Anbieters ein.</p>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">{{ isService ? 'Nebenkosten' : 'Versand' }} {{ offer.preisart }} (€)<input v-model.number="offer.versand_netto" required type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm">{{ isService ? 'MwSt. auf Nebenkosten (%)' : 'MwSt. auf Versand (%)' }}<input v-model.number="offer.versand_mwst" required type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
        </div>
        <p class="rounded-lg bg-gray-50 p-3 text-sm">Netto: <strong>{{ euro(totals.net) }}</strong> · MwSt.: <strong>{{ euro(totals.tax) }}</strong> · Gesamt brutto: <strong>{{ euro(totals.gross) }}</strong></p>
        <label class="block text-sm">Begründung / Unterschiede im Leistungsumfang<textarea v-model="offer.bemerkung" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
    </div>
</template>

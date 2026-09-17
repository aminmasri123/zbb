<script setup>
import { computed, watch } from 'vue'
import OfferFields from './OfferFields.vue'
import { purchaseTotals, cents } from './purchaseTotals'
const props = defineProps({ form: Object, rules: Object, limits: { type: Object, default: () => ({ file_bytes: 10485760, total_bytes: 40894464, max_offers: 20 }) } })
let sequence = 0
const requiredCount = computed(() => cents(purchaseTotals(props.form.positionen, props.form.versand_netto, props.form.versand_mwst, props.form.preisart).gross) > props.rules.quote_limit_cents ? props.rules.quote_count : 0)
const namedSuppliers = computed(() => new Set(props.form.angebote.map(o => o.lieferant.trim().toLocaleLowerCase('de-DE')).filter(Boolean)).size)
const duplicateSuppliers = computed(() => props.form.angebote.filter(o => o.lieferant.trim()).length > namedSuppliers.value)
const euro = value => Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
function newLine(item) { return { position_key: item.client_key, einzelpreis: '', mwst: Number(item.mwst ?? 19) } }
function addOffer() {
    if (props.form.angebote.length >= props.limits.max_offers) return
    const now = new Date()
    props.form.angebote.push({ _key: ++sequence, preisart: 'brutto', lieferant: '', lieferant_adresse: '', angebotsnummer: '',
        angebotsdatum: [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-'),
        gueltig_bis: '', lieferzeit: '', bemerkung: '', empfohlen: false, versand_netto: 0, versand_mwst: 19, datei: null,
        positionen: props.form.positionen.map(newLine) })
    clearOfferErrors()
}
function clearOfferErrors() { for (const key of Object.keys(props.form.errors)) if (key === 'angebote' || key.startsWith('angebote.')) props.form.clearErrors(key) }
function removeOffer(index) { props.form.angebote.splice(index, 1); clearOfferErrors() }
function recommend(offer) { const value = !offer.empfohlen; for (const item of props.form.angebote) item.empfohlen = false; offer.empfohlen = value }
function total(offer) { return purchaseTotals(offer.positionen.map(line => ({ ...line, stueck: props.form.positionen.find(item => item.client_key === line.position_key)?.stueck || 0 })), offer.versand_netto, offer.versand_mwst, offer.preisart).gross }
watch(() => props.form.positionen.map(item => item.client_key), () => {
    for (const offer of props.form.angebote) {
        const previous = new Map(offer.positionen.map(line => [line.position_key, line]))
        offer.positionen = props.form.positionen.map(item => previous.get(item.client_key) || newLine(item))
    }
    clearOfferErrors()
})
</script>
<template>
    <section id="angebote-anlegen" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-lg font-semibold text-gray-900">Angebote / Kostenvoranschläge</h2><p class="mt-1 text-sm text-gray-600">Direkt erfassen und gemeinsam mit der Materialanforderung speichern.</p></div>
            <button type="button" :disabled="form.processing || form.angebote.length >= limits.max_offers" class="rounded-lg border border-orange-500 px-4 py-2 text-sm font-semibold text-orange-800 disabled:opacity-50" @click="addOffer">Angebot hinzufügen</button>
        </div>
        <p v-if="requiredCount" role="status" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Über {{ euro(rules.quote_limit_cents / 100) }} brutto werden {{ requiredCount }} gültige Angebote unterschiedlicher Anbieter benötigt. Bisher: {{ namedSuppliers }} Anbieter angegeben.</p>
        <p v-else class="mt-3 text-sm text-gray-600">Du kannst auch unterhalb der Betragsgrenze Angebote hinzufügen.</p>
        <p class="mt-2 text-sm text-gray-600">Ein Zwischenstand mit weniger Angeboten kann als Entwurf gespeichert werden. Vor dem Einreichen müssen die erforderlichen Angebote vollständig sein.</p>
        <p v-if="duplicateSuppliers" role="status" class="mt-2 text-sm text-amber-800">Mehrere Angebote desselben Anbieters zählen als ein Anbieter.</p>
        <p v-if="form.errors.angebote" role="alert" class="mt-2 text-sm text-red-700">{{ form.errors.angebote }}</p>
        <div v-if="form.angebote.length" class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full text-left text-sm"><thead class="bg-gray-50"><tr><th class="p-3">Angebot</th><th class="p-3">Anbieter</th><th class="p-3 text-right">Gesamt brutto</th></tr></thead><tbody><tr v-for="(offer, i) in form.angebote" :key="offer._key" class="border-t"><td class="p-3">{{ i + 1 }}</td><td class="p-3">{{ offer.lieferant || 'Noch nicht angegeben' }}<span v-if="offer.empfohlen" class="block text-xs text-blue-700">Deine Empfehlung</span></td><td class="p-3 text-right font-semibold">{{ offer.positionen.some(line => line.einzelpreis === '') ? 'Preise ergänzen' : euro(total(offer)) }}</td></tr></tbody></table>
        </div>
        <fieldset v-for="(offer, i) in form.angebote" :key="offer._key" :disabled="form.processing" class="mt-5 min-w-0 rounded-xl border border-gray-200 p-4 sm:p-5">
            <legend class="px-2 font-semibold">Angebot {{ i + 1 }}</legend>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" :checked="offer.empfohlen" @change="recommend(offer)" />Diesen Anbieter empfehlen</label>
                <button type="button" class="text-sm text-red-700" @click="removeOffer(i)">Angebot {{ i + 1 }} entfernen</button>
            </div>
            <OfferFields :offer="offer" :items="form.positionen" :is-service="form.vergabe.lieferung_art === 'Dienstleistung'" :errors="form.errors" :prefix="'angebote.' + i + '.'" :name="'draft-offer-price-' + offer._key" :max-file-bytes="limits.file_bytes" />
        </fieldset>
        <p v-if="!form.angebote.length" class="mt-4 rounded-lg border border-dashed border-gray-300 p-5 text-sm text-gray-500">Noch keine Angebote hinzugefügt. Du kannst die Kostenvoranschläge hier vor dem ersten Speichern erfassen.</p>
        <p v-if="form.angebote.length" class="mt-3 text-xs text-gray-500">Je hinzugefügtem Angebot sind Anbieter, Anschrift, Datum, Preise und Datei erforderlich.{{ limits.total_bytes !== null ? ' Alle Dateien zusammen: höchstens ' + Math.floor(limits.total_bytes / 1048576) + ' MB.' : '' }} Die Geschäftsführung entscheidet über die Auswahl.</p>
    </section>
</template>

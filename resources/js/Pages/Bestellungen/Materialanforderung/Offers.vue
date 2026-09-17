<script setup>
import { computed, ref, watch } from 'vue'
import OfferFields from './OfferFields.vue'
import { inputPrice } from './purchaseTotals'
import { router, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
const props = defineProps({ order: Object, editable: Boolean, approval: Object, director: Boolean })
const isService = computed(() => props.order.vergabevermerk?.lieferung_art === 'Dienstleistung')
const expanded = ref(false)
const currentOffers = computed(() => (props.order.angebote || []).filter(o => o.revision === props.order.revision))
const oldOffers = computed(() => (props.order.angebote || []).filter(o => o.revision !== props.order.revision))
const offerData = () => ({ preisart: 'brutto', revision: props.order.revision, lieferant: '', lieferant_adresse: '', angebotsnummer: '', angebotsdatum: new Date().toISOString().slice(0, 10), gueltig_bis: '', lieferzeit: '', bemerkung: '', empfohlen: false, versand_netto: 0, versand_mwst: 19, datei: null,
    positionen: props.order.artikeln.map(item => ({ id: item.id, einzelpreis: inputPrice(item, 'brutto'), mwst: Number(item.mwst) })) })
const form = useForm(offerData())
watch(() => props.order.revision, () => { form.defaults(offerData()); form.reset(); form.clearErrors() })
const decision = useForm({ angebot_id: '', anmerkung: '' })
const euro = n => Number(n).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
function submit() { form.transform(data => ({ ...data, empfohlen: data.empfohlen ? 1 : 0 })).post(route('materialanforderung.offers.store', props.order.id), { preserveScroll: true, onSuccess: () => { form.reset(); expanded.value = false } }) }
function approve() { decision.put(route('materialanforderung.genehmigen', { id: props.order.id, status: 'gf_genehmigt' }), { preserveScroll: true }) }
async function remove(offer) { const result = await Swal.fire({ title: 'Angebot entfernen?', text: offer.lieferant, icon: 'question', showCancelButton: true, confirmButtonText: 'Entfernen', cancelButtonText: 'Abbrechen' }); if (result.isConfirmed) router.delete(route('materialanforderung.offers.destroy', offer.id), { preserveScroll: true }) }
</script>
<template>
    <section id="angebote" class="rounded-xl border bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Vergleichsangebote</h2><p class="mt-1 text-sm text-gray-600">{{ currentOffers.length }} erfasst · {{ approval.min_quotes || 0 }} erforderlich · Preise brutto inklusive {{ isService ? 'Nebenkosten' : 'Versand' }}</p></div><button v-if="editable" class="rounded-lg border border-orange-400 px-4 py-2 text-sm font-semibold text-orange-800" @click="expanded = !expanded">{{ expanded ? 'Formular schließen' : 'Angebot hinzufügen' }}</button></div>
        <p v-if="approval.min_quotes" class="mt-3 text-sm text-gray-600">Die Angebote müssen denselben Bedarf abdecken und von unterschiedlichen Lieferanten stammen. Die Geschäftsführung entscheidet über den Anbieter.</p>
        <div v-if="currentOffers.length" class="mt-4 overflow-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50"><tr><th class="p-3">Lieferant</th><th class="p-3">Gesamt brutto</th><th class="p-3">{{ isService ? 'Ausführungszeit' : 'Lieferzeit' }} / Gültigkeit</th><th class="p-3">Dokument</th><th class="p-3"></th></tr></thead><tbody><tr v-for="offer in currentOffers" :key="offer.id" class="border-t" :class="order.selected_offer_id === offer.id ? 'bg-green-50' : ''"><td class="p-3"><strong>{{ offer.lieferant }}</strong><span v-if="offer.empfohlen" class="block text-xs text-blue-700">Empfehlung des Antragstellers</span><span v-if="order.selected_offer_id === offer.id" class="block text-xs text-green-800">Von der Geschäftsführung ausgewählt</span><p class="mt-1 whitespace-pre-wrap text-xs text-gray-600">{{ offer.bemerkung }}</p></td><td class="p-3 font-semibold">{{ euro(offer.brutto) }}</td><td class="p-3">{{ offer.lieferzeit || '–' }}<br /><span class="text-xs">{{ offer.gueltig_bis ? 'Gültig bis ' + new Date(offer.gueltig_bis).toLocaleDateString('de-DE') : 'Keine Befristung angegeben' }}</span></td><td class="p-3"><a :href="route('materialanforderung.offers.download', offer.id)" class="text-blue-700 underline">Angebot öffnen</a></td><td class="p-3"><button v-if="editable" class="text-red-700" @click="remove(offer)">Entfernen</button></td></tr></tbody></table></div>
        <form v-if="editable && expanded" class="mt-5 space-y-4 border-t pt-4" @submit.prevent="submit">
            <h3 class="font-semibold">Neues Vergleichsangebot</h3>
            <OfferFields :offer="form" :items="order.artikeln" :is-service="isService" :errors="form.errors" />
            <label class="flex gap-2 text-sm"><input v-model="form.empfohlen" type="checkbox" class="rounded" />Diesen Anbieter empfehlen</label>
            <button :disabled="form.processing" class="rounded-lg bg-orange-600 px-4 py-2 font-semibold text-white disabled:opacity-50">Angebot speichern</button>
        </form>
        <form v-if="director && order.status === 'gf_pruefung'" class="mt-5 space-y-3 border-t pt-5" @submit.prevent="approve">
            <h3 class="font-semibold">Entscheidung der Geschäftsführung</h3>
            <label v-if="currentOffers.length" class="block text-sm">Anbieter auswählen *<select v-model="decision.angebot_id" required class="mt-1 block w-full rounded border-gray-300"><option value="">Bitte auswählen</option><option v-for="offer in currentOffers" :key="offer.id" :value="offer.id">{{ offer.lieferant }} · {{ euro(offer.brutto) }}</option></select></label>
            <label class="block text-sm">Entscheidungsbegründung<textarea v-model="decision.anmerkung" :required="currentOffers.length > 0" rows="2" class="mt-1 block w-full rounded border-gray-300" /></label>
            <p v-for="(error, key) in decision.errors" :key="key" role="alert" class="text-sm text-red-700">{{ error }}</p>
            <button :disabled="decision.processing" class="rounded-lg bg-green-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Genehmigen und zur Bestellung freigeben</button>
            <p class="text-xs text-gray-600">Die kaufmännische Leitung wird informiert. Eine weitere Genehmigung ist nicht erforderlich.</p>
        </form>
        <details v-if="oldOffers.length" class="mt-5 text-sm"><summary class="cursor-pointer text-gray-600">{{ oldOffers.length }} Angebote aus früheren Fassungen</summary><ul class="mt-2 space-y-2"><li v-for="offer in oldOffers" :key="offer.id"><a :href="route('materialanforderung.offers.download', offer.id)" class="text-blue-700 underline">{{ offer.lieferant }} · Fassung {{ offer.revision }}</a></li></ul><p class="mt-2 text-gray-600">Nach Änderungen am Bedarf müssen die Angebote für die aktuelle Fassung neu erfasst werden.</p></details>
    </section>
</template>

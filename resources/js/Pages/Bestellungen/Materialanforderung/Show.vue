<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
    anforderung: Object,
    kostenstellen: { type: Array, default: () => [] },
    canEditMaterialanforderung: Boolean,
    canConfirmKaufmaenisch: Boolean,
    canConfirmSachlich: Boolean,
    canBestellen: Boolean,
    canDeleteMaterialanforderung: Boolean,
    canDeleteOrderedMaterialanforderung: Boolean,
    verlauf: { type: Array, default: () => [] },
})

const editing = ref(new URLSearchParams(window.location.search).get('edit') === '1')
const deliveryOpen = ref(false)
const deleting = ref(false)
const liefermengen = ref(Object.fromEntries(props.anforderung.artikeln.map((item) => [item.id, Number(item.gelieferte_menge || 0)])))
const vergabe = props.anforderung.vergabevermerk || {}
const form = useForm({
    id: props.anforderung.id,
    kostenstelle: props.anforderung.kostenstelle,
    benoetigt_am: props.anforderung.benoetigt_am?.substring(0, 10) || '',
    prioritaet: props.anforderung.prioritaet || 'normal',
    bemerkungen: props.anforderung.bemerkungen || '',
    positionen: props.anforderung.artikeln.map((item) => ({
        id: item.id,
        pos: item.pos,
        artikel: item.artikel,
        link: item.link || '',
        stueck: Number(item.stueck),
        art_nr: item.art_nr || '',
        einzelpreis: Number(item.einzelpreis),
        mwst: Number(item.mwst),
    })),
    vergabe: {
        kurzbeschreibung: vergabe.kurzbeschreibung || '',
        lieferung_art: vergabe.lieferung_art || 'Lieferleistung',
        begruendung_optionen: vergabe.begruendung_optionen || [],
        begruendung: vergabe.begruendung || '',
        lieferant: vergabe.lieferant || '',
        lieferung_option: vergabe.lieferung_option || 'per Lieferung',
        lieferadresse: vergabe.lieferadresse || '',
        bestellnummer: vergabe.bestellnummer || '',
    },
})

const statusMeta = {
    entwurf: ['Entwurf', 'bg-gray-100 text-gray-700'],
    eingereicht: ['Eingereicht', 'bg-blue-100 text-blue-700'],
    sachlich_genehmigt: ['Sachlich genehmigt', 'bg-violet-100 text-violet-700'],
    kaufmaennisch_genehmigt: ['Kaufmännisch genehmigt', 'bg-emerald-100 text-emerald-700'],
    bestellt: ['Bestellt', 'bg-cyan-100 text-cyan-700'],
    teilweise_geliefert: ['Teilweise geliefert', 'bg-amber-100 text-amber-800'],
    geliefert: ['Geliefert', 'bg-green-100 text-green-800'],
    zur_ueberarbeitung: ['Zur Überarbeitung', 'bg-orange-100 text-orange-800'],
    zurueckgezogen: ['Einreichung zurückgezogen', 'bg-amber-100 text-amber-800'],
    storniert: ['Storniert', 'bg-red-100 text-red-700'],
}
const editable = computed(() => props.canEditMaterialanforderung && ['entwurf', 'zur_ueberarbeitung'].includes(props.anforderung.status))
const netto = computed(() => form.positionen.reduce((sum, item) => sum + item.stueck * item.einzelpreis, 0))
const mwst = computed(() => form.positionen.reduce((sum, item) => sum + item.stueck * item.einzelpreis * item.mwst / 100, 0))
const begruendungen = [
    ['nur_ein_anbieter', 'Es existiert nur ein Anbieter'],
    ['besondere_gruende', 'Aufgrund besonderer Gründe'],
    ['besondere_dringlichkeit', 'Besondere Dringlichkeit'],
    ['zubehoer_ersatzteile', 'Zubehör oder Ersatzteile zu vorhandenen Geräten'],
    ['vertragliche_gruende', 'Aus vertraglichen Gründen'],
    ['guenstigster_anbieter', 'Günstigster Anbieter'],
]

function euro(value) {
    return Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
}

function date(value) {
    return value ? new Intl.DateTimeFormat('de-DE').format(new Date(value)) : '–'
}

function addPosition() {
    form.positionen.push({ id: null, pos: form.positionen.length + 1, artikel: '', link: '', stueck: 1, art_nr: '', einzelpreis: 0, mwst: 19 })
}

function removePosition(index) {
    if (form.positionen.length === 1) return
    form.positionen.splice(index, 1)
    form.positionen.forEach((item, i) => { item.pos = i + 1 })
}

function save() {
    form.put(route('materialanforderung.update'), {
        preserveScroll: true,
        onSuccess: () => { editing.value = false },
    })
}

async function changeStatus(status, label, requireComment = false) {
    const result = await Swal.fire({
        title: label,
        text: `Materialanforderung #${props.anforderung.id}`,
        input: requireComment ? 'textarea' : undefined,
        inputLabel: requireComment ? 'Begründung / Hinweis' : undefined,
        inputValidator: requireComment ? (value) => !value?.trim() ? 'Bitte eine Begründung eintragen.' : undefined : undefined,
        showCancelButton: true,
        confirmButtonText: 'Bestätigen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#ea580c',
    })
    if (!result.isConfirmed) return

    router.put(route('materialanforderung.genehmigen', { id: props.anforderung.id, status }), {
        anmerkung: result.value || '',
    }, { preserveScroll: true })
}

async function deleteDraft() {
    const result = await Swal.fire({
        title: 'Entwurf löschen?',
        text: 'Die Materialanforderung und alle Positionen werden endgültig gelöscht.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    })
    if (!result.isConfirmed) return

    deleteMaterialanforderung()
}

function deletionErrorMessage(errors) {
    const firstError = Object.values(errors || {})[0]

    if (Array.isArray(firstError)) return firstError[0]

    return firstError || 'Die Materialanforderung konnte nicht gelöscht werden. Es wurden keine Daten entfernt.'
}

function deleteMaterialanforderung(data = undefined) {
    router.delete(route('materialanforderung.destroy', props.anforderung.id), {
        data,
        preserveScroll: false,
        onStart: () => { deleting.value = true },
        onSuccess: (page) => {
            const flash = page.props.flash || {}

            if (!flash.success && !flash.error) {
                Swal.fire({
                    title: 'Gelöscht',
                    text: `Materialanforderung #${props.anforderung.id} wurde erfolgreich gelöscht.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                })
            }
        },
        onError: (errors) => {
            Swal.fire({
                title: 'Nicht gelöscht',
                text: deletionErrorMessage(errors),
                icon: 'error',
                confirmButtonText: 'Schließen',
            })
        },
        onFinish: () => { deleting.value = false },
    })
}

function escapeHtml(value) {
    const element = document.createElement('div')
    element.textContent = String(value ?? '')
    return element.innerHTML
}

async function deleteOrderedMaterialanforderung() {
    const expectedConfirmation = `LÖSCHEN #${props.anforderung.id}`
    const statusLabel = statusMeta[props.anforderung.status]?.[0] || props.anforderung.status
    const result = await Swal.fire({
        title: 'Bestellten Vorgang endgültig löschen?',
        icon: 'warning',
        width: 680,
        html: `
            <div class="text-left text-sm text-slate-700">
                <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-red-800">
                    Die Materialanforderung und alle zugehörigen Daten werden endgültig gelöscht. Nur das unabhängige Löschprotokoll bleibt erhalten.
                </div>
                <dl class="mb-4 grid grid-cols-2 gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                    <div><dt class="text-xs text-slate-500">Vorgang</dt><dd class="font-semibold">#${props.anforderung.id}</dd></div>
                    <div><dt class="text-xs text-slate-500">Projekt</dt><dd class="font-semibold">${escapeHtml(props.anforderung.projekt?.name || '–')}</dd></div>
                    <div><dt class="text-xs text-slate-500">Status</dt><dd class="font-semibold">${escapeHtml(statusLabel)}</dd></div>
                    <div><dt class="text-xs text-slate-500">Bestellnummer</dt><dd class="font-semibold">${escapeHtml(vergabe.bestellnummer || '–')}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">Endsumme</dt><dd class="font-semibold">${escapeHtml(euro(props.anforderung.endsumme))}</dd></div>
                </dl>
                <label for="delete-reason" class="block font-semibold">Löschbegründung *</label>
                <textarea id="delete-reason" rows="3" class="mt-1 w-full rounded border-slate-300" placeholder="Mindestens 10 Zeichen"></textarea>
                <label for="delete-confirmation" class="mt-4 block font-semibold">Zur Bestätigung exakt eingeben: ${escapeHtml(expectedConfirmation)}</label>
                <input id="delete-confirmation" class="mt-1 w-full rounded border-slate-300" autocomplete="off" placeholder="${escapeHtml(expectedConfirmation)}" />
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Endgültig löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
        focusCancel: true,
        preConfirm: () => {
            const begruendung = document.getElementById('delete-reason')?.value.trim() || ''
            const bestaetigung = document.getElementById('delete-confirmation')?.value.trim() || ''

            if (begruendung.length < 10) {
                Swal.showValidationMessage('Bitte geben Sie eine Löschbegründung mit mindestens 10 Zeichen ein.')
                return false
            }
            if (bestaetigung !== expectedConfirmation) {
                Swal.showValidationMessage(`Bitte geben Sie exakt „${expectedConfirmation}“ ein.`)
                return false
            }

            return { begruendung, bestaetigung }
        },
    })

    if (!result.isConfirmed) return

    deleteMaterialanforderung(result.value)
}

async function markOrdered() {
    const result = await Swal.fire({
        title: 'Als bestellt markieren',
        input: 'text',
        inputLabel: 'Bestellnummer *',
        inputValue: vergabe.bestellnummer || '',
        inputValidator: (value) => !value?.trim() ? 'Bitte die Bestellnummer eintragen.' : undefined,
        showCancelButton: true,
        confirmButtonText: 'Bestellung speichern',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#0e7490',
    })
    if (!result.isConfirmed) return
    router.put(route('materialanforderung.genehmigen', { id: props.anforderung.id, status: 'bestellt' }), {
        bestellnummer: result.value.trim(),
    }, { preserveScroll: true })
}

function submitPartialDelivery() {
    router.put(route('materialanforderung.genehmigen', { id: props.anforderung.id, status: 'teilweise_geliefert' }), {
        liefermengen: liefermengen.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { deliveryOpen.value = false },
        onError: (errors) => {
            const message = errors.liefermengen
                || Object.entries(errors).find(([key]) => key.startsWith('liefermengen.'))?.[1]
                || 'Die Teillieferung konnte nicht gespeichert werden. Bitte prüfen Sie die eingegebenen Mengen.'

            Swal.fire({
                title: 'Teillieferung nicht möglich',
                text: message,
                icon: 'warning',
                confirmButtonText: 'Eingaben korrigieren',
                confirmButtonColor: '#d97706',
            })
        },
    })
}
</script>

<template>
    <Head :title="`Materialanforderung #${anforderung.id}`" />
    <AppLayout>
        <template #header>Materialanforderung #{{ anforderung.id }}</template>

        <div class="mx-auto max-w-7xl space-y-5 pb-12">
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900">{{ anforderung.projekt?.name }}</h1>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusMeta[anforderung.status]?.[1] || 'bg-gray-100'">{{ statusMeta[anforderung.status]?.[0] || anforderung.status }}</span>
                            <span v-if="anforderung.prioritaet === 'dringend'" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">Dringend</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Erstellt am {{ date(anforderung.created_at) }} · benötigt am {{ date(anforderung.benoetigt_am) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="route('materialanforderung.pdf', anforderung.id)" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700"><i class="las la-file-pdf text-red-600"></i> PDF</a>
                        <button v-if="editable && !editing" type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold" @click="editing = true"><i class="las la-edit mr-1"></i> Bearbeiten</button>
                        <button v-if="canDeleteMaterialanforderung && !editing" type="button" :disabled="deleting" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 disabled:cursor-wait disabled:opacity-60" @click="deleteDraft">{{ deleting ? 'Wird gelöscht …' : 'Löschen' }}</button>
                        <button v-if="canDeleteOrderedMaterialanforderung && !editing" type="button" :disabled="deleting" class="rounded-lg border border-red-600 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-600 hover:text-white disabled:cursor-wait disabled:opacity-60" @click="deleteOrderedMaterialanforderung"><i class="las la-trash mr-1"></i> {{ deleting ? 'Wird gelöscht …' : 'Bestellten Vorgang löschen' }}</button>
                        <Link :href="route('materialanforderung.index')" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold">Zur Übersicht</Link>
                    </div>
                </div>

                <div v-if="!editing" class="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-5">
                    <button v-if="['entwurf', 'zur_ueberarbeitung'].includes(anforderung.status) && canEditMaterialanforderung" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white" @click="changeStatus('eingereicht', 'Materialanforderung einreichen?')">Einreichen</button>
                    <button v-if="anforderung.status === 'eingereicht' && canEditMaterialanforderung" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800" @click="changeStatus('zurueckgezogen', 'Einreichung zurückziehen?', true)">Einreichung zurückziehen</button>
                    <button v-if="anforderung.status === 'eingereicht' && canConfirmSachlich" class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-semibold text-white" @click="changeStatus('sachlich_genehmigt', 'Sachlich genehmigen?')">Sachlich genehmigen</button>
                    <button v-if="anforderung.status === 'eingereicht' && canConfirmSachlich" class="rounded-lg border border-orange-300 px-4 py-2 text-sm font-semibold text-orange-700" @click="changeStatus('zur_ueberarbeitung', 'Zur Überarbeitung zurückgeben?', true)">Zurückgeben</button>
                    <button v-if="anforderung.status === 'sachlich_genehmigt' && canConfirmKaufmaenisch" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" @click="changeStatus('kaufmaennisch_genehmigt', 'Kaufmännisch genehmigen?')">Kaufmännisch genehmigen</button>
                    <button v-if="anforderung.status === 'sachlich_genehmigt' && canConfirmKaufmaenisch" class="rounded-lg border border-orange-300 px-4 py-2 text-sm font-semibold text-orange-700" @click="changeStatus('zur_ueberarbeitung', 'Zur Überarbeitung zurückgeben?', true)">Zurückgeben</button>
                    <template v-if="canBestellen && ['kaufmaennisch_genehmigt', 'bestellt', 'teilweise_geliefert'].includes(anforderung.status)">
                        <button v-if="anforderung.status === 'kaufmaennisch_genehmigt'" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="markOrdered">Als bestellt markieren</button>
                        <button v-if="['bestellt', 'teilweise_geliefert'].includes(anforderung.status)" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white" @click="deliveryOpen = true">Teillieferung erfassen</button>
                        <button v-if="['bestellt', 'teilweise_geliefert'].includes(anforderung.status)" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white" @click="changeStatus('geliefert', 'Als vollständig geliefert markieren?')">Vollständig geliefert</button>
                    </template>
                    <button v-if="['entwurf', 'eingereicht', 'zur_ueberarbeitung'].includes(anforderung.status) && canEditMaterialanforderung" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700" @click="changeStatus('storniert', 'Materialanforderung stornieren?', true)">Stornieren</button>
                </div>
            </section>

            <template v-if="!editing">
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <h2 class="mb-4 text-lg font-semibold">Artikel</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-[760px] w-full text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Pos.</th><th class="px-3 py-3">Artikel</th><th class="px-3 py-3">Stück</th><th v-if="['bestellt', 'teilweise_geliefert', 'geliefert'].includes(anforderung.status)" class="px-3 py-3">Geliefert</th><th class="px-3 py-3">Art.-Nr.</th><th class="px-3 py-3 text-right">Einzelpreis</th><th class="px-3 py-3 text-right">MwSt.</th><th class="px-3 py-3 text-right">Gesamt</th></tr></thead>
                                <tbody class="divide-y divide-gray-100"><tr v-for="item in anforderung.artikeln" :key="item.id"><td class="px-3 py-3">{{ item.pos }}</td><td class="px-3 py-3 font-medium"><a v-if="item.link" :href="item.link" target="_blank" class="text-blue-700 hover:underline">{{ item.artikel }}</a><span v-else>{{ item.artikel }}</span></td><td class="px-3 py-3">{{ item.stueck }}</td><td v-if="['bestellt', 'teilweise_geliefert', 'geliefert'].includes(anforderung.status)" class="px-3 py-3 font-semibold">{{ item.gelieferte_menge || 0 }}</td><td class="px-3 py-3">{{ item.art_nr || '–' }}</td><td class="px-3 py-3 text-right">{{ euro(item.einzelpreis) }}</td><td class="px-3 py-3 text-right">{{ item.mwst }} %</td><td class="px-3 py-3 text-right font-semibold">{{ euro(item.gesamtpreis) }}</td></tr></tbody>
                            </table>
                        </div>
                    </section>
                    <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 text-lg font-semibold">Summe</h2>
                        <dl class="space-y-3 text-sm"><div class="flex justify-between"><dt class="text-gray-500">Netto</dt><dd class="font-medium">{{ euro(anforderung.gesamtpreis) }}</dd></div><div class="flex justify-between"><dt class="text-gray-500">MwSt.</dt><dd class="font-medium">{{ euro(anforderung.endsumme - anforderung.gesamtpreis) }}</dd></div><div class="flex justify-between border-t pt-3 text-base"><dt class="font-semibold">Endsumme</dt><dd class="font-bold text-orange-600">{{ euro(anforderung.endsumme) }}</dd></div></dl>
                        <dl class="mt-6 space-y-3 border-t pt-5 text-sm"><div><dt class="text-gray-500">Kostenstelle</dt><dd class="font-medium">{{ anforderung.kostenstelle }}</dd></div><div><dt class="text-gray-500">Antragsteller</dt><dd class="font-medium">{{ anforderung.besteller?.name }}</dd></div><div><dt class="text-gray-500">Bemerkungen</dt><dd class="whitespace-pre-wrap">{{ anforderung.bemerkungen || '–' }}</dd></div></dl>
                    </aside>
                </div>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Vergabevermerk und Lieferung</h2>
                    <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2"><div class="md:col-span-2"><dt class="text-gray-500">Kurzbeschreibung</dt><dd class="whitespace-pre-wrap font-medium">{{ vergabe.kurzbeschreibung || '–' }}</dd></div><div><dt class="text-gray-500">Art der Leistung</dt><dd class="font-medium">{{ vergabe.lieferung_art || '–' }}</dd></div><div><dt class="text-gray-500">Lieferung</dt><dd class="font-medium">{{ vergabe.lieferung_option || '–' }}</dd></div><div><dt class="text-gray-500">Lieferant</dt><dd class="font-medium">{{ vergabe.lieferant || '–' }}</dd></div><div><dt class="text-gray-500">Bestellnummer</dt><dd class="font-medium">{{ vergabe.bestellnummer || '–' }}</dd></div><div class="md:col-span-2"><dt class="text-gray-500">Lieferadresse</dt><dd class="whitespace-pre-wrap">{{ vergabe.lieferadresse || '–' }}</dd></div><div class="md:col-span-2"><dt class="text-gray-500">Begründung</dt><dd class="whitespace-pre-wrap">{{ vergabe.begruendung || '–' }}</dd></div></dl>
                </section>
            </template>

            <form v-else class="space-y-5" @submit.prevent="save">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Grunddaten bearbeiten</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3"><label class="text-sm">Kostenstelle *<select v-model="form.kostenstelle" class="mt-1 w-full rounded-lg border-gray-300"><option v-for="item in kostenstellen" :key="item.id" :value="item.kostenstelle">{{ item.kostenstelle }}</option></select></label><label class="text-sm">Benötigt am<input v-model="form.benoetigt_am" type="date" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm">Priorität<select v-model="form.prioritaet" class="mt-1 w-full rounded-lg border-gray-300"><option value="normal">Normal</option><option value="dringend">Dringend</option></select></label><label class="text-sm md:col-span-3">Bemerkungen<textarea v-model="form.bemerkungen" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label></div>
                </section>
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold">Artikel bearbeiten</h2><button type="button" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white" @click="addPosition">+ Artikel</button></div>
                    <div class="space-y-3"><article v-for="(item, index) in form.positionen" :key="item.id || index" class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 p-4 md:grid-cols-8"><label class="text-sm md:col-span-2">Artikel *<input v-model="item.artikel" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm">Stück *<input v-model.number="item.stueck" type="number" min="1" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm">Art.-Nr.<input v-model="item.art_nr" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm">Einzelpreis *<input v-model.number="item.einzelpreis" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm">MwSt. *<input v-model.number="item.mwst" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border-gray-300" /></label><label class="text-sm md:col-span-2">Link<input v-model="item.link" type="url" class="mt-1 w-full rounded-lg border-gray-300" /></label><div class="col-span-2 flex items-center justify-between md:col-span-8"><strong>{{ euro(item.stueck * item.einzelpreis) }}</strong><button v-if="form.positionen.length > 1" type="button" class="text-sm text-red-600" @click="removePosition(index)">Entfernen</button></div></article></div>
                </section>
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Vergabevermerk bearbeiten</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2"><label class="text-sm md:col-span-2">Kurzbeschreibung<textarea v-model="form.vergabe.kurzbeschreibung" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label><label class="text-sm">Art der Leistung<select v-model="form.vergabe.lieferung_art" class="mt-1 w-full rounded-lg border-gray-300"><option>Lieferleistung</option><option>Dienstleistung</option></select></label><label class="text-sm">Lieferung<select v-model="form.vergabe.lieferung_option" class="mt-1 w-full rounded-lg border-gray-300"><option>per Lieferung</option><option>per Abholung</option></select></label><fieldset class="md:col-span-2"><legend class="text-sm">Begründungsmerkmale</legend><div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2"><label v-for="option in begruendungen" :key="option[0]" class="flex gap-2 rounded-lg border p-2 text-sm"><input v-model="form.vergabe.begruendung_optionen" type="checkbox" :value="option[0]" /> {{ option[1] }}</label></div></fieldset><label class="text-sm md:col-span-2">Ergänzende Begründung<textarea v-model="form.vergabe.begruendung" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label><label class="text-sm">Lieferant<input v-model="form.vergabe.lieferant" class="mt-1 w-full rounded-lg border-gray-300" /></label><label v-if="form.vergabe.lieferung_option === 'per Lieferung'" class="text-sm">Lieferadresse *<textarea v-model="form.vergabe.lieferadresse" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label><label class="text-sm">Bestellnummer<input v-model="form.vergabe.bestellnummer" class="mt-1 w-full rounded-lg border-gray-300" /></label></div>
                </section>
                <div class="sticky bottom-3 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between"><div class="text-sm"><span class="text-gray-500">Endsumme:</span> <strong class="text-lg text-orange-600">{{ euro(netto + mwst) }}</strong></div><div class="flex gap-2"><button type="button" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 font-semibold" @click="editing = false">Abbrechen</button><button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-orange-600 px-4 py-2 font-semibold text-white">Speichern</button></div></div>
            </form>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Bearbeitungsverlauf</h2>
                <ol v-if="verlauf.length" class="space-y-4"><li v-for="item in verlauf" :key="item.id" class="flex gap-3"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-orange-500"></span><div><p class="text-sm font-medium">{{ item.genehmiger?.name || 'Unbekannt' }} · {{ statusMeta[item.status]?.[0] || item.status }}</p><p class="text-xs text-gray-500">{{ new Date(item.created_at).toLocaleString('de-DE') }}</p><p v-if="item.kommentar" class="mt-1 whitespace-pre-wrap text-sm text-gray-700">{{ item.kommentar }}</p></div></li></ol>
                <p v-else class="text-sm text-gray-500">Noch keine Statusänderungen vorhanden.</p>
            </section>

            <div v-if="deliveryOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="deliveryOpen = false">
                <form class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white shadow-2xl" @submit.prevent="submitPartialDelivery">
                    <div class="sticky top-0 flex items-center justify-between border-b bg-white p-5"><div><h2 class="text-lg font-semibold">Teillieferung erfassen</h2><p class="text-sm text-gray-500">Kumulierte gelieferte Menge je Position</p></div><button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" @click="deliveryOpen = false"><i class="las la-times text-xl"></i></button></div>
                    <div class="space-y-3 p-5"><label v-for="item in anforderung.artikeln" :key="item.id" class="grid grid-cols-[1fr_120px] items-center gap-4 rounded-lg border border-gray-200 p-3"><span><strong class="block">{{ item.pos }}. {{ item.artikel }}</strong><small class="text-gray-500">Bestellt: {{ item.stueck }}</small></span><input v-model.number="liefermengen[item.id]" type="number" min="0" :max="item.stueck" class="w-full rounded-lg border-gray-300" /></label></div>
                    <div class="sticky bottom-0 flex justify-end gap-2 border-t bg-white p-4"><button type="button" class="rounded-lg border border-gray-300 px-4 py-2 font-semibold" @click="deliveryOpen = false">Abbrechen</button><button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Teillieferung speichern</button></div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    user: Object,
    projekt: Object,
    kostenstellen: { type: Array, default: () => [] },
})

const emptyPosition = (pos) => ({
    pos,
    artikel: '',
    link: '',
    stueck: 1,
    art_nr: '',
    einzelpreis: 0,
    mwst: 19,
})

const form = useForm({
    kostenstelle: props.kostenstellen[0]?.kostenstelle ?? '',
    benoetigt_am: '',
    prioritaet: 'normal',
    bemerkungen: '',
    positionen: [emptyPosition(1)],
    vergabe: {
        kurzbeschreibung: '',
        lieferung_art: 'Lieferleistung',
        begruendung_optionen: [],
        begruendung: '',
        lieferant: '',
        lieferung_option: 'per Lieferung',
        lieferadresse: '',
        bestellnummer: '',
    },
})

const begruendungen = [
    ['nur_ein_anbieter', 'Es existiert nur ein Anbieter'],
    ['besondere_gruende', 'Aufgrund besonderer Gründe'],
    ['besondere_dringlichkeit', 'Besondere Dringlichkeit'],
    ['zubehoer_ersatzteile', 'Zubehör oder Ersatzteile zu vorhandenen Geräten'],
    ['vertragliche_gruende', 'Aus vertraglichen Gründen'],
    ['guenstigster_anbieter', 'Günstigster Anbieter'],
]

const netto = computed(() => form.positionen.reduce(
    (sum, p) => sum + (Number(p.stueck) || 0) * (Number(p.einzelpreis) || 0), 0
))
const mwst = computed(() => form.positionen.reduce((sum, p) => {
    const positionsNetto = (Number(p.stueck) || 0) * (Number(p.einzelpreis) || 0)
    return sum + positionsNetto * ((Number(p.mwst) || 0) / 100)
}, 0))
const brutto = computed(() => netto.value + mwst.value)

function euro(value) {
    return Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
}

function addPosition() {
    form.positionen.push(emptyPosition(form.positionen.length + 1))
}

function removePosition(index) {
    if (form.positionen.length === 1) return
    form.positionen.splice(index, 1)
    form.positionen.forEach((position, positionIndex) => { position.pos = positionIndex + 1 })
}

function fieldError(path) {
    return form.errors[path]
}

function submit() {
    form.post(route('materialanforderung.store'), { preserveScroll: true })
}
</script>

<template>
    <Head title="Materialanforderung anlegen" />

    <AppLayout>
        <template #header>Materialanforderung anlegen</template>

        <form class="mx-auto max-w-7xl space-y-5 pb-28" @submit.prevent="submit">
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-5 flex items-start gap-3">
                    <div class="rounded-lg bg-orange-50 p-2 text-orange-600"><i class="las la-file-alt text-2xl"></i></div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Grunddaten</h2>
                        <p class="text-sm text-gray-500">Die Anforderung wird zunächst als Entwurf gespeichert.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Projekt</span>
                        <input :value="projekt.name" disabled class="mt-1 w-full rounded-lg border-gray-200 bg-gray-50 text-gray-600" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Antragsteller</span>
                        <input :value="`${user?.vorname || ''} ${user?.nachname || ''}`" disabled class="mt-1 w-full rounded-lg border-gray-200 bg-gray-50 text-gray-600" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Kostenstelle *</span>
                        <select v-model="form.kostenstelle" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="" disabled>Bitte auswählen</option>
                            <option v-for="item in kostenstellen" :key="item.id" :value="item.kostenstelle">{{ item.kostenstelle }}</option>
                        </select>
                        <span v-if="fieldError('kostenstelle')" class="mt-1 block text-xs text-red-600">{{ fieldError('kostenstelle') }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Benötigt am</span>
                        <input v-model="form.benoetigt_am" type="date" class="mt-1 w-full rounded-lg border-gray-300" />
                        <span v-if="fieldError('benoetigt_am')" class="mt-1 block text-xs text-red-600">{{ fieldError('benoetigt_am') }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Priorität *</span>
                        <select v-model="form.prioritaet" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="normal">Normal</option>
                            <option value="dringend">Dringend</option>
                        </select>
                    </label>
                    <label class="block md:col-span-2 xl:col-span-1">
                        <span class="text-sm font-medium text-gray-700">Bemerkungen</span>
                        <input v-model="form.bemerkungen" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Zusätzliche Hinweise" />
                    </label>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Artikel und Preise</h2>
                        <p class="text-sm text-gray-500">Mindestens eine vollständig ausgefüllte Position ist erforderlich.</p>
                    </div>
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white" @click="addPosition">
                        <i class="las la-plus"></i> Artikel hinzufügen
                    </button>
                </div>

                <div class="space-y-4 p-4 md:hidden">
                    <article v-for="(position, index) in form.positionen" :key="index" class="rounded-lg border border-gray-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="font-semibold text-gray-900">Position {{ position.pos }}</span>
                            <button v-if="form.positionen.length > 1" type="button" class="text-sm text-red-600" @click="removePosition(index)">Entfernen</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="col-span-2 text-sm">Artikel *<input v-model="position.artikel" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                            <label class="text-sm">Stück *<input v-model.number="position.stueck" min="1" type="number" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                            <label class="text-sm">Art.-Nr.<input v-model="position.art_nr" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                            <label class="text-sm">Einzelpreis *<input v-model.number="position.einzelpreis" min="0" step="0.01" type="number" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                            <label class="text-sm">MwSt. % *<input v-model.number="position.mwst" min="0" max="100" step="0.01" type="number" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                            <label class="col-span-2 text-sm">Link<input v-model="position.link" type="url" class="mt-1 w-full rounded-lg border-gray-300" placeholder="https://…" /></label>
                        </div>
                        <div class="mt-3 text-right font-semibold">{{ euro(position.stueck * position.einzelpreis) }} netto</div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-[1050px] w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-3 py-3">Pos.</th><th class="px-3 py-3">Artikel *</th><th class="px-3 py-3">Link</th><th class="px-3 py-3">Stück *</th><th class="px-3 py-3">Art.-Nr.</th><th class="px-3 py-3">Einzelpreis *</th><th class="px-3 py-3">MwSt. *</th><th class="px-3 py-3 text-right">Gesamt</th><th></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(position, index) in form.positionen" :key="index">
                                <td class="px-3 py-3 font-semibold">{{ position.pos }}</td>
                                <td class="px-3 py-3"><input v-model="position.artikel" class="w-52 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3"><input v-model="position.link" type="url" class="w-40 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3"><input v-model.number="position.stueck" min="1" type="number" class="w-20 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3"><input v-model="position.art_nr" class="w-32 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3"><input v-model.number="position.einzelpreis" min="0" step="0.01" type="number" class="w-28 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3"><input v-model.number="position.mwst" min="0" max="100" step="0.01" type="number" class="w-20 rounded-lg border-gray-300" /></td>
                                <td class="px-3 py-3 text-right font-semibold">{{ euro(position.stueck * position.einzelpreis) }}</td>
                                <td class="px-3 py-3"><button v-if="form.positionen.length > 1" type="button" class="rounded-lg p-2 text-red-600 hover:bg-red-50" @click="removePosition(index)"><i class="las la-trash text-lg"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="fieldError('positionen')" class="px-6 pb-4 text-sm text-red-600">{{ fieldError('positionen') }}</p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">Vergabevermerk und Lieferung</h2>
                    <p class="text-sm text-gray-500">Angaben aus der bisherigen Materialanforderungs-Vorlage.</p>
                </div>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <label class="block lg:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Kurzbeschreibung von Art und Umfang</span>
                        <textarea v-model="form.vergabe.kurzbeschreibung" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea>
                    </label>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Art der Leistung *</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label class="flex items-center gap-2"><input v-model="form.vergabe.lieferung_art" type="radio" value="Lieferleistung" /> Lieferleistung</label>
                            <label class="flex items-center gap-2"><input v-model="form.vergabe.lieferung_art" type="radio" value="Dienstleistung" /> Dienstleistung</label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Lieferung *</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label class="flex items-center gap-2"><input v-model="form.vergabe.lieferung_option" type="radio" value="per Abholung" /> Abholung</label>
                            <label class="flex items-center gap-2"><input v-model="form.vergabe.lieferung_option" type="radio" value="per Lieferung" /> Lieferung</label>
                        </div>
                    </fieldset>
                    <fieldset class="lg:col-span-2">
                        <legend class="text-sm font-medium text-gray-700">Marktbeschreibung / Begründung</legend>
                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                            <label v-for="option in begruendungen" :key="option[0]" class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                                <input v-model="form.vergabe.begruendung_optionen" type="checkbox" :value="option[0]" class="mt-0.5" /> {{ option[1] }}
                            </label>
                        </div>
                    </fieldset>
                    <label class="block lg:col-span-2"><span class="text-sm font-medium text-gray-700">Ergänzende Begründung</span><textarea v-model="form.vergabe.begruendung" rows="3" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700">Vorgesehener Lieferant</span><input v-model="form.vergabe.lieferant" class="mt-1 w-full rounded-lg border-gray-300" /></label>
                    <label v-if="form.vergabe.lieferung_option === 'per Lieferung'" class="block"><span class="text-sm font-medium text-gray-700">Lieferadresse *</span><textarea v-model="form.vergabe.lieferadresse" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea><span v-if="fieldError('vergabe.lieferadresse')" class="mt-1 block text-xs text-red-600">{{ fieldError('vergabe.lieferadresse') }}</span></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700">Bestellnummer</span><input v-model="form.vergabe.bestellnummer" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Kann später ergänzt werden" /></label>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur sm:pl-[var(--sidebar-width,1rem)]">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div><span class="block text-xs text-gray-500">Netto</span><strong>{{ euro(netto) }}</strong></div>
                        <div><span class="block text-xs text-gray-500">MwSt.</span><strong>{{ euro(mwst) }}</strong></div>
                        <div><span class="block text-xs text-gray-500">Endsumme</span><strong class="text-orange-600">{{ euro(brutto) }}</strong></div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('materialanforderung.index')" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-semibold sm:flex-none">Abbrechen</Link>
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-orange-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 sm:flex-none">
                            <i class="las la-save mr-1"></i> Als Entwurf speichern
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>

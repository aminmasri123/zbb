<template>
    <Head title="Lager" />

    <AppLayout>
        <template #header>Internes Lager</template>

        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="border border-gray-200 bg-white p-4">
                    <p class="text-sm text-gray-500">Artikel</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ artikel.length }}</p>
                </div>
                <div class="border border-gray-200 bg-white p-4">
                    <p class="text-sm text-gray-500">Verfuegbar</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ verfuegbareArtikel }}</p>
                </div>
                <div class="border border-gray-200 bg-white p-4">
                    <p class="text-sm text-gray-500">Reserviert</p>
                    <p class="mt-1 text-2xl font-semibold text-sky-700">{{ reservierteArtikel }}</p>
                </div>
                <div class="border border-gray-200 bg-white p-4">
                    <p class="text-sm text-gray-500">Mindestbestand</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-700">{{ kritischeArtikel }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <section class="border border-gray-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <i class="la la-search text-lg text-gray-500"></i>
                            <input
                                v-model="search"
                                type="search"
                                class="w-full border border-gray-300 px-3 py-2 text-sm focus:border-zbb focus:outline-none"
                                placeholder="Artikel, Kategorie, Art.-Nr., Lagerort suchen"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="filter" class="border border-gray-300 px-3 py-2 text-sm focus:border-zbb focus:outline-none">
                                <option value="alle">Alle</option>
                                <option value="verfuegbar">Verfuegbar</option>
                                <option value="reserviert">Reserviert</option>
                                <option value="mindestbestand">Mindestbestand</option>
                                <option v-if="lagerPermissions.canUpdateArtikel" value="inaktiv">Inaktiv</option>
                            </select>
                            <button
                                v-if="lagerPermissions.canCreateArtikel"
                                type="button"
                                class="inline-flex items-center gap-2 bg-zbb px-3 py-2 text-sm font-semibold text-white"
                                @click="startCreate"
                            >
                                <i class="la la-plus"></i>
                                Artikel
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Artikel</th>
                                    <th class="px-4 py-3">Kategorie</th>
                                    <th class="px-4 py-3 text-right">Bestand</th>
                                    <th class="px-4 py-3 text-right">Reserviert</th>
                                    <th class="px-4 py-3 text-right">Verfuegbar</th>
                                    <th class="px-4 py-3">Lagerort</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="item in filteredArtikel"
                                    :key="item.id"
                                    class="cursor-pointer hover:bg-gray-50"
                                    :class="selectedArtikel?.id === item.id ? 'bg-sky-50' : ''"
                                    @click="selectArtikel(item)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                        <div class="text-xs text-gray-500">{{ item.artikelnummer || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ item.kategorie || '-' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900">
                                        {{ formatAmount(item.bestand) }} {{ item.einheit }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sky-700">
                                        {{ formatAmount(item.reserviert) }} {{ item.einheit }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold" :class="Number(item.verfuegbar) > 0 ? 'text-emerald-700' : 'text-red-700'">
                                        {{ formatAmount(item.verfuegbar) }} {{ item.einheit }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ item.lagerort || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium"
                                            :class="statusClass(item)"
                                        >
                                            {{ statusLabel(item) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredArtikel.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        Keine Lagerartikel gefunden.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section v-if="lagerPermissions.canCreateArtikel || selectedArtikel" class="border border-gray-200 bg-white">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                            <h2 class="text-base font-semibold text-gray-900">
                                {{ artikelFormMode === 'create' ? 'Artikel anlegen' : 'Artikelstamm' }}
                            </h2>
                            <button
                                v-if="selectedArtikel && lagerPermissions.canUpdateArtikel"
                                type="button"
                                class="inline-flex items-center gap-1 text-sm text-zbb"
                                @click="startEdit(selectedArtikel)"
                            >
                                <i class="la la-edit"></i>
                                Bearbeiten
                            </button>
                        </div>

                        <form class="space-y-3 p-4" @submit.prevent="submitArtikel">
                            <div>
                                <label class="text-xs font-medium uppercase text-gray-500">Name</label>
                                <input v-model="artikelForm.name" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                <p v-if="artikelForm.errors.name" class="mt-1 text-xs text-red-600">{{ artikelForm.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Kategorie</label>
                                    <input v-model="artikelForm.kategorie" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Art.-Nr.</label>
                                    <input v-model="artikelForm.artikelnummer" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Einheit</label>
                                    <input v-model="artikelForm.einheit" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Bestand</label>
                                    <input v-model.number="artikelForm.bestand" :disabled="artikelFormMode !== 'create'" type="number" step="0.01" min="0" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Mindest.</label>
                                    <input v-model.number="artikelForm.mindestbestand" :disabled="!canEditArtikelForm" type="number" step="0.01" min="0" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Lagerort</label>
                                    <input v-model="artikelForm.lagerort" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Lieferant</label>
                                    <input v-model="artikelForm.lieferant" :disabled="!canEditArtikelForm" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-medium uppercase text-gray-500">Beschreibung</label>
                                <textarea v-model="artikelForm.beschreibung" :disabled="!canEditArtikelForm" rows="3" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm"></textarea>
                            </div>

                            <label v-if="lagerPermissions.canUpdateArtikel" class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="artikelForm.aktiv" :disabled="!canEditArtikelForm" type="checkbox" class="border-gray-300 text-zbb" />
                                Aktiv
                            </label>

                            <div v-if="canEditArtikelForm" class="flex gap-2">
                                <button type="submit" class="inline-flex items-center gap-2 bg-zbb px-3 py-2 text-sm font-semibold text-white" :disabled="artikelForm.processing">
                                    <i class="la la-save"></i>
                                    Speichern
                                </button>
                                <button type="button" class="border border-gray-300 px-3 py-2 text-sm" @click="cancelArtikelForm">
                                    Abbrechen
                                </button>
                                <button
                                    v-if="artikelFormMode === 'edit' && lagerPermissions.canDeleteArtikel"
                                    type="button"
                                    class="ml-auto border border-red-200 px-3 py-2 text-sm text-red-700"
                                    @click="deleteArtikel"
                                >
                                    Deaktivieren
                                </button>
                            </div>
                        </form>
                    </section>

                    <section v-if="selectedArtikel" class="border border-gray-200 bg-white">
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h2 class="text-base font-semibold text-gray-900">Intern reservieren</h2>
                        </div>
                        <form class="space-y-3 p-4" @submit.prevent="submitReservierung">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Menge</label>
                                    <input v-model.number="reservierungForm.menge" :disabled="!lagerPermissions.canReserve" type="number" step="0.01" min="0.01" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                    <p v-if="reservierungForm.errors.menge" class="mt-1 text-xs text-red-600">{{ reservierungForm.errors.menge }}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Verfuegbar</label>
                                    <div class="mt-1 border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900">
                                        {{ formatAmount(selectedArtikel.verfuegbar) }} {{ selectedArtikel.einheit }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase text-gray-500">Zweck</label>
                                <input v-model="reservierungForm.zweck" :disabled="!lagerPermissions.canReserve" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase text-gray-500">Bemerkung</label>
                                <textarea v-model="reservierungForm.bemerkung" :disabled="!lagerPermissions.canReserve" rows="2" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <button
                                v-if="lagerPermissions.canReserve"
                                type="submit"
                                class="inline-flex items-center gap-2 bg-emerald-700 px-3 py-2 text-sm font-semibold text-white"
                                :disabled="reservierungForm.processing || Number(selectedArtikel.verfuegbar) <= 0"
                            >
                                <i class="la la-bookmark"></i>
                                Reservieren
                            </button>
                        </form>
                    </section>

                    <section v-if="selectedArtikel && lagerPermissions.canBookBewegung" class="border border-gray-200 bg-white">
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h2 class="text-base font-semibold text-gray-900">Lagerbewegung</h2>
                        </div>
                        <form class="space-y-3 p-4" @submit.prevent="submitBewegung">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Typ</label>
                                    <select v-model="bewegungForm.typ" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm">
                                        <option value="eingang">Eingang</option>
                                        <option value="ausgang">Ausgang</option>
                                        <option value="korrektur">Korrektur auf Bestand</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">Menge</label>
                                    <input v-model.number="bewegungForm.menge" type="number" step="0.01" min="0.01" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm" />
                                    <p v-if="bewegungForm.errors.menge" class="mt-1 text-xs text-red-600">{{ bewegungForm.errors.menge }}</p>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase text-gray-500">Bemerkung</label>
                                <textarea v-model="bewegungForm.bemerkung" rows="2" class="mt-1 w-full border border-gray-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 px-3 py-2 text-sm font-semibold text-white" :disabled="bewegungForm.processing">
                                <i class="la la-exchange-alt"></i>
                                Buchen
                            </button>
                        </form>
                    </section>

                    <section v-if="selectedArtikel" class="border border-gray-200 bg-white">
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h2 class="text-base font-semibold text-gray-900">Reservierungen</h2>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="reservierung in aktiveReservierungen(selectedArtikel)" :key="reservierung.id" class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ formatAmount(reservierung.menge) }} {{ selectedArtikel.einheit }}
                                        </div>
                                        <div class="text-sm text-gray-600">{{ personenName(reservierung) }}</div>
                                        <div class="text-xs text-gray-500">{{ reservierung.zweck || '-' }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="lagerPermissions.canUpdateReservierung"
                                            type="button"
                                            class="border border-emerald-200 px-2 py-1 text-xs font-medium text-emerald-700"
                                            @click="updateReservierung(reservierung, 'ausgegeben')"
                                        >
                                            Ausgeben
                                        </button>
                                        <button
                                            v-if="lagerPermissions.canUpdateReservierung || reservierung.angefordert_von_user_id === props.currentUserId"
                                            type="button"
                                            class="border border-red-200 px-2 py-1 text-xs font-medium text-red-700"
                                            @click="updateReservierung(reservierung, 'storniert')"
                                        >
                                            Storno
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="aktiveReservierungen(selectedArtikel).length === 0" class="p-4 text-sm text-gray-500">
                                Keine aktiven Reservierungen.
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    artikel: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    lagerPermissions: { type: Object, default: () => ({}) },
})

const search = ref('')
const filter = ref('alle')
const selectedArtikel = ref(props.artikel[0] || null)
const artikelFormMode = ref(selectedArtikel.value ? 'view' : 'create')

const emptyArtikel = () => ({
    name: '',
    kategorie: '',
    artikelnummer: '',
    einheit: 'Stk',
    bestand: 0,
    mindestbestand: 0,
    lagerort: '',
    lieferant: '',
    beschreibung: '',
    aktiv: true,
})

const artikelForm = useForm(emptyArtikel())
const reservierungForm = useForm({
    menge: 1,
    zweck: '',
    bemerkung: '',
})
const bewegungForm = useForm({
    typ: 'eingang',
    menge: 1,
    bemerkung: '',
})
const reservierungUpdateForm = useForm({
    status: '',
    bemerkung: '',
})

const lagerPermissions = computed(() => ({
    canCreateArtikel: false,
    canUpdateArtikel: false,
    canDeleteArtikel: false,
    canBookBewegung: false,
    canReserve: false,
    canUpdateReservierung: false,
    ...props.lagerPermissions,
}))

const canEditArtikelForm = computed(() =>
    artikelFormMode.value === 'create' || artikelFormMode.value === 'edit'
)

const filteredArtikel = computed(() => {
    const q = search.value.trim().toLowerCase()

    return props.artikel.filter((item) => {
        const haystack = [
            item.name,
            item.kategorie,
            item.artikelnummer,
            item.lagerort,
            item.lieferant,
        ].join(' ').toLowerCase()

        const matchesSearch = !q || haystack.includes(q)
        const matchesFilter = filter.value === 'alle'
            || (filter.value === 'verfuegbar' && Number(item.verfuegbar) > 0 && item.aktiv)
            || (filter.value === 'reserviert' && Number(item.reserviert) > 0)
            || (filter.value === 'mindestbestand' && item.unter_mindestbestand)
            || (filter.value === 'inaktiv' && !item.aktiv)

        return matchesSearch && matchesFilter
    })
})

const verfuegbareArtikel = computed(() =>
    props.artikel.filter((item) => Number(item.verfuegbar) > 0 && item.aktiv).length
)
const reservierteArtikel = computed(() =>
    props.artikel.filter((item) => Number(item.reserviert) > 0).length
)
const kritischeArtikel = computed(() =>
    props.artikel.filter((item) => item.unter_mindestbestand).length
)

watch(() => props.artikel, (items) => {
    if (!selectedArtikel.value) {
        selectedArtikel.value = items[0] || null
        if (selectedArtikel.value) {
            fillArtikelForm(selectedArtikel.value)
            artikelFormMode.value = 'view'
        }
        return
    }

    const updated = items.find((item) => item.id === selectedArtikel.value.id)
    selectedArtikel.value = updated || items[0] || null
    if (selectedArtikel.value && artikelFormMode.value === 'view') {
        fillArtikelForm(selectedArtikel.value)
    }
}, { deep: true })

if (selectedArtikel.value) {
    fillArtikelForm(selectedArtikel.value)
}

const selectArtikel = (item) => {
    selectedArtikel.value = item
    artikelFormMode.value = 'view'
    fillArtikelForm(item)
    reservierungForm.reset()
    bewegungForm.reset()
}

const startCreate = () => {
    selectedArtikel.value = null
    artikelFormMode.value = 'create'
    fillArtikelForm(emptyArtikel())
}

const startEdit = (item) => {
    selectedArtikel.value = item
    artikelFormMode.value = 'edit'
    fillArtikelForm(item)
}

const cancelArtikelForm = () => {
    if (selectedArtikel.value) {
        artikelFormMode.value = 'view'
        fillArtikelForm(selectedArtikel.value)
        return
    }

    startCreate()
}

const submitArtikel = () => {
    if (artikelFormMode.value === 'create') {
        artikelForm.post(route('lager.artikel.store'), {
            preserveScroll: true,
            onSuccess: () => {
                artikelForm.reset()
                artikelFormMode.value = 'view'
            },
        })
        return
    }

    if (!selectedArtikel.value) return

    artikelForm.put(route('lager.artikel.update', selectedArtikel.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            artikelFormMode.value = 'view'
        },
    })
}

const deleteArtikel = () => {
    if (!selectedArtikel.value) return
    if (!window.confirm('Lagerartikel deaktivieren oder löschen?')) return

    router.delete(route('lager.artikel.destroy', selectedArtikel.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedArtikel.value = null
            startCreate()
        },
    })
}

const submitReservierung = () => {
    if (!selectedArtikel.value) return

    reservierungForm.post(route('lager.reservierung.store', selectedArtikel.value.id), {
        preserveScroll: true,
        onSuccess: () => reservierungForm.reset(),
    })
}

const submitBewegung = () => {
    if (!selectedArtikel.value) return

    bewegungForm.post(route('lager.bewegung.store', selectedArtikel.value.id), {
        preserveScroll: true,
        onSuccess: () => bewegungForm.reset(),
    })
}

const updateReservierung = (reservierung, status) => {
    reservierungUpdateForm.status = status
    reservierungUpdateForm.bemerkung = ''
    reservierungUpdateForm.put(route('lager.reservierung.update', reservierung.id), {
        preserveScroll: true,
    })
}

function fillArtikelForm(item) {
    const values = {
        ...emptyArtikel(),
        ...item,
        bestand: Number(item.bestand ?? 0),
        mindestbestand: Number(item.mindestbestand ?? 0),
        aktiv: Boolean(item.aktiv ?? true),
    }

    Object.keys(emptyArtikel()).forEach((key) => {
        artikelForm[key] = values[key]
    })
    artikelForm.clearErrors()
}

function activeStatus(item) {
    if (!item.aktiv) return 'inaktiv'
    if (item.unter_mindestbestand) return 'mindestbestand'
    if (Number(item.verfuegbar) <= 0) return 'nicht_verfuegbar'
    return 'verfuegbar'
}

function statusLabel(item) {
    return {
        inaktiv: 'Inaktiv',
        mindestbestand: 'Mindestbestand',
        nicht_verfuegbar: 'Nicht verfuegbar',
        verfuegbar: 'Verfuegbar',
    }[activeStatus(item)]
}

function statusClass(item) {
    return {
        inaktiv: 'bg-gray-100 text-gray-600',
        mindestbestand: 'bg-amber-100 text-amber-800',
        nicht_verfuegbar: 'bg-red-100 text-red-700',
        verfuegbar: 'bg-emerald-100 text-emerald-700',
    }[activeStatus(item)]
}

function aktiveReservierungen(item) {
    return (item?.reservierungen || []).filter((reservierung) => reservierung.status === 'reserviert')
}

function personenName(reservierung) {
    const person = reservierung.angefordert_von_person
    if (person) {
        return `${person.vorname || ''} ${person.nachname || ''}`.trim()
    }

    return reservierung.angefordert_von_user?.username || reservierung.angefordert_von_user?.email || 'Unbekannt'
}

function formatAmount(value) {
    return Number(value || 0).toLocaleString('de-DE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })
}
</script>

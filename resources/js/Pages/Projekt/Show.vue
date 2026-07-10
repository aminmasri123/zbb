<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import MultiSelect from 'primevue/multiselect';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    projekt: Object,
    fehlendeMitarbeiter: Array,
    alleStandorte: Array,
});

const selectedStandorte = reactive({});
const projektMitarbeiter = ref([...(props.projekt.mitarbeiter || [])]);
const fehlendeMitarbeiterListe = ref([...(props.fehlendeMitarbeiter || [])]);
const paUebungen = ref(JSON.parse(JSON.stringify(props.projekt.potenzialanalyse_uebungen || [])));
const paUebungForm = reactive({
    name: '',
    tag: null,
    beschreibung: '',
    hoechstwert: null,
    auswertbar: false,
    sort_order: 0,
    aktiv: true,
});
const paKriteriumForms = reactive({});
const savingPa = ref(false);

const paAktiv = computed(() => Boolean(props.projekt.potenzialanalyse_aktiv));

const standortById = computed(() => {
    return new Map((props.alleStandorte || []).map((standort) => [standort.id, standort]));
});

const zugewieseneMitarbeiter = computed(() => {
    const grouped = new Map();

    for (const person of projektMitarbeiter.value) {
        if (!grouped.has(person.id)) {
            grouped.set(person.id, {
                ...person,
                standorte: [],
            });
        }

        const standortId = person.pivot?.standort_id;
        const standort = standortById.value.get(standortId);

        if (standort && !grouped.get(person.id).standorte.some((item) => item.id === standort.id)) {
            grouped.get(person.id).standorte.push(standort);
        }
    }

    return Array.from(grouped.values()).sort((a, b) => {
        return `${a.nachname} ${a.vorname}`.localeCompare(`${b.nachname} ${b.vorname}`);
    });
});

const formatDate = (date) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('de-DE');
};

const roleNames = (person) => {
    return person.user?.roles?.map((role) => role.name).join(', ') || '-';
};

const resetUebungForm = () => {
    paUebungForm.name = '';
    paUebungForm.tag = null;
    paUebungForm.beschreibung = '';
    paUebungForm.hoechstwert = null;
    paUebungForm.auswertbar = false;
    paUebungForm.sort_order = 0;
    paUebungForm.aktiv = true;
};

const kriteriumForm = (uebungId) => {
    if (!paKriteriumForms[uebungId]) {
        paKriteriumForms[uebungId] = {
            name: '',
            beschreibung: '',
            skala_min: 1,
            skala_max: 5,
            sort_order: 0,
            aktiv: true,
        };
    }

    return paKriteriumForms[uebungId];
};

const resetKriteriumForm = (uebungId) => {
    paKriteriumForms[uebungId] = {
        name: '',
        beschreibung: '',
        skala_min: 1,
        skala_max: 5,
        sort_order: 0,
        aktiv: true,
    };
};

const updatePaUebungen = (uebungen) => {
    paUebungen.value = JSON.parse(JSON.stringify(uebungen || []));
};

const paPayload = (item) => ({
    name: item.name,
    tag: item.tag || null,
    beschreibung: item.beschreibung || null,
    hoechstwert: item.hoechstwert || null,
    auswertbar: Boolean(item.auswertbar),
    sort_order: item.sort_order || 0,
    aktiv: Boolean(item.aktiv),
});

const kriteriumPayload = (item) => ({
    name: item.name,
    beschreibung: item.beschreibung || null,
    skala_min: item.skala_min || 1,
    skala_max: item.skala_max || 5,
    sort_order: item.sort_order || 0,
    aktiv: Boolean(item.aktiv),
});

const storeUebung = async () => {
    if (!paUebungForm.name) {
        Swal.fire('Fehler', 'Bitte einen Namen fuer die Uebung eintragen.', 'error');
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.post(
            route('potenzialanalyse.projekt.uebungen.store', props.projekt.id),
            paPayload(paUebungForm)
        );

        updatePaUebungen(response.data.uebungen);
        resetUebungForm();
        Swal.fire('Gespeichert', response.data.message || 'Uebung wurde gespeichert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const updateUebung = async (uebung) => {
    savingPa.value = true;

    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.uebungen.update', uebung.id),
            paPayload(uebung)
        );

        updatePaUebungen(response.data.uebungen);
        Swal.fire('Gespeichert', response.data.message || 'Uebung wurde aktualisiert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht aktualisiert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const destroyUebung = async (uebung) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Uebung loeschen?',
        text: 'Erfasste Punkte und Zeiten zu dieser Uebung werden ebenfalls geloescht.',
        showCancelButton: true,
        confirmButtonText: 'Loeschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.delete(route('potenzialanalyse.projekt.uebungen.destroy', uebung.id));
        updatePaUebungen(response.data.uebungen);
        Swal.fire('Geloescht', response.data.message || 'Uebung wurde geloescht.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht geloescht werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const storeKriterium = async (uebung) => {
    const form = kriteriumForm(uebung.id);
    if (!form.name) {
        Swal.fire('Fehler', 'Bitte einen Namen fuer das Kriterium eintragen.', 'error');
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.post(
            route('potenzialanalyse.projekt.kriterien.store', uebung.id),
            kriteriumPayload(form)
        );

        updatePaUebungen(response.data.uebungen);
        resetKriteriumForm(uebung.id);
        Swal.fire('Gespeichert', response.data.message || 'Kriterium wurde gespeichert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const updateKriterium = async (kriterium) => {
    savingPa.value = true;

    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.kriterien.update', kriterium.id),
            kriteriumPayload(kriterium)
        );

        updatePaUebungen(response.data.uebungen);
        Swal.fire('Gespeichert', response.data.message || 'Kriterium wurde aktualisiert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht aktualisiert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const destroyKriterium = async (kriterium) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Kriterium loeschen?',
        text: 'Erfasste Bewertungen zu diesem Kriterium werden ebenfalls geloescht.',
        showCancelButton: true,
        confirmButtonText: 'Loeschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.delete(route('potenzialanalyse.projekt.kriterien.destroy', kriterium.id));
        updatePaUebungen(response.data.uebungen);
        Swal.fire('Geloescht', response.data.message || 'Kriterium wurde geloescht.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht geloescht werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const addMitarbeiter = (person) => {
    const standortIds = selectedStandorte[person.id] || [];

    if (!standortIds.length) {
        Swal.fire('Fehler', 'Bitte mindestens einen Standort auswahlen.', 'error');
        return;
    }

    axios.post(route('projekthaspersonen.store'), {
        user_id: person.id,
        zuweisungen: [
            {
                projekt_id: props.projekt.id,
                standort_id: standortIds,
            },
        ],
    })
        .then(() => {
            for (const standortId of standortIds) {
                projektMitarbeiter.value.push({
                    ...person,
                    pivot: {
                        ...(person.pivot || {}),
                        standort_id: standortId,
                        status: 'aktiv',
                    },
                });
            }

            fehlendeMitarbeiterListe.value = fehlendeMitarbeiterListe.value.filter((item) => item.id !== person.id);
            selectedStandorte[person.id] = [];
            Swal.fire('Gespeichert!', 'Mitarbeiter wurde dem Projekt zugewiesen.', 'success');
        })
        .catch(() => {
            Swal.fire('Fehler', 'Zuweisung konnte nicht gespeichert werden.', 'error');
        });
};
</script>

<template>
    <Head :title="`Projekt ${projekt.name}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>{{ projekt.name }}</span>
                <Link :href="route('projekt.index')" class="text-sm text-zbb hover:underline">
                    Zuruck zur Projektliste
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <section class="bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-5">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Projekt</p>
                        <p class="font-semibold">{{ projekt.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Abteilung</p>
                        <p class="font-semibold">{{ projekt.abteilung?.name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Bereiche</p>
                        <p class="font-semibold">{{ projekt.bereiche?.map((bereich) => bereich.name).join(', ') || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Kostenstellen</p>
                        <p class="font-semibold">{{ projekt.kostenstellen?.map((kostenstelle) => kostenstelle.kostenstelle).join(', ') || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Potenzialanalyse</p>
                        <p class="font-semibold">
                            {{ projekt.potenzialanalyse_aktiv ? `Ja (${projekt.potenzialanalyse_tage || '?'} Tage)` : 'Nein' }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Potenzialanalyse</h2>
                        <p class="text-sm text-gray-500">
                            {{ paAktiv ? `${projekt.potenzialanalyse_tage || '?'} Tage` : 'Nicht aktiv' }}
                        </p>
                    </div>
                    <span
                        class="rounded px-2 py-1 text-xs font-medium"
                        :class="paAktiv ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ paAktiv ? 'Aktiv' : 'Aus' }}
                    </span>
                </div>

                <div v-if="paAktiv" class="space-y-5">
                    <div class="rounded border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Übung anlegen</h3>
                        <div class="grid gap-3 md:grid-cols-[1fr_120px_150px_120px]">
                            <label class="text-sm text-gray-600">
                                Name
                                <input v-model="paUebungForm.name" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                PA-Tag
                                <input
                                    v-model.number="paUebungForm.tag"
                                    type="number"
                                    min="1"
                                    :max="projekt.potenzialanalyse_tage || 60"
                                    class="mt-1 w-full rounded border-gray-300 text-sm"
                                />
                            </label>
                            <label class="text-sm text-gray-600">
                                Erreichbare Punktzahl
                                <input v-model.number="paUebungForm.hoechstwert" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                Reihenfolge
                                <input v-model.number="paUebungForm.sort_order" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                        </div>
                        <label class="mt-3 block text-sm text-gray-600">
                            Beschreibung
                            <textarea v-model="paUebungForm.beschreibung" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        </label>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="paUebungForm.auswertbar" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Auswertbar
                            </label>
                            <button
                                type="button"
                                class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-60"
                                :disabled="savingPa"
                                @click="storeUebung"
                            >
                                Übung speichern
                            </button>
                        </div>
                    </div>

                    <div v-if="!paUebungen.length" class="rounded border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
                        Noch keine Übungen angelegt.
                    </div>

                    <div
                        v-for="uebung in paUebungen"
                        :key="uebung.id"
                        class="rounded border border-gray-200 p-4"
                    >
                        <div class="grid gap-3 md:grid-cols-[1fr_110px_150px_110px_auto_auto]">
                            <label class="text-sm text-gray-600">
                                Uebung
                                <input v-model="uebung.name" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                PA-Tag
                                <input
                                    v-model.number="uebung.tag"
                                    type="number"
                                    min="1"
                                    :max="projekt.potenzialanalyse_tage || 60"
                                    class="mt-1 w-full rounded border-gray-300 text-sm"
                                />
                            </label>
                            <label class="text-sm text-gray-600">
                                Erreichbare Punktzahl
                                <input v-model.number="uebung.hoechstwert" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                Reihenfolge
                                <input v-model.number="uebung.sort_order" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.auswertbar" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Auswertbar
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.aktiv" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Aktiv
                            </label>
                        </div>
                        <label class="mt-3 block text-sm text-gray-600">
                            Beschreibung
                            <textarea v-model="uebung.beschreibung" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        </label>
                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="rounded border border-zbb px-3 py-2 text-sm text-zbb disabled:opacity-60"
                                :disabled="savingPa"
                                @click="updateUebung(uebung)"
                            >
                                Übung aktualisieren
                            </button>
                            <button
                                type="button"
                                class="rounded border border-red-200 px-3 py-2 text-sm text-red-600 disabled:opacity-60"
                                :disabled="savingPa"
                                @click="destroyUebung(uebung)"
                            >
                                Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Zeitraume</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Antrag</th>
                                <th class="px-3 py-2">Starttermin</th>
                                <th class="px-3 py-2">Anfang</th>
                                <th class="px-3 py-2">Endtermin</th>
                                <th class="px-3 py-2">Ende</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="zeitraum in projekt.zeitraume" :key="zeitraum.id" class="border-b">
                                <td class="px-3 py-2">{{ formatDate(zeitraum.antragsdatum) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.starttermin) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.anfangsdatum) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.endtermin) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.enddatum) }}</td>
                            </tr>
                            <tr v-if="!projekt.zeitraume?.length">
                                <td colspan="5" class="px-3 py-3 text-gray-500">Keine Zeitraume hinterlegt.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Mitarbeiter im Projekt</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">E-Mail</th>
                                <th class="px-3 py-2">Rollen</th>
                                <th class="px-3 py-2">Standorte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="person in zugewieseneMitarbeiter" :key="person.id" class="border-b">
                                <td class="px-3 py-2 font-medium">{{ person.vorname }} {{ person.nachname }}</td>
                                <td class="px-3 py-2">{{ person.user?.email || '-' }}</td>
                                <td class="px-3 py-2">{{ roleNames(person) }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        v-for="standort in person.standorte"
                                        :key="standort.id"
                                        class="mr-1 inline-block rounded bg-gray-100 px-2 py-1 text-xs"
                                    >
                                        {{ standort.name }}
                                    </span>
                                    <span v-if="!person.standorte.length">-</span>
                                </td>
                            </tr>
                            <tr v-if="!zugewieseneMitarbeiter.length">
                                <td colspan="4" class="px-3 py-3 text-gray-500">Noch keine Mitarbeiter zugewiesen.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Fehlende Mitarbeiter hinzufugen</h2>
                <div class="space-y-3">
                    <div
                        v-for="person in fehlendeMitarbeiterListe"
                        :key="person.id"
                        class="grid gap-3 border-b pb-3 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <div>
                            <p class="font-medium">{{ person.vorname }} {{ person.nachname }}</p>
                            <p class="text-sm text-gray-500">{{ person.user?.email || '-' }}</p>
                        </div>
                        <MultiSelect
                            v-model="selectedStandorte[person.id]"
                            :options="alleStandorte"
                            optionLabel="name"
                            optionValue="id"
                            display="chip"
                            filter
                            placeholder="Standorte auswahlen"
                            class="w-full"
                        />
                        <button
                            type="button"
                            @click="addMitarbeiter(person)"
                            class="self-start rounded bg-zbb px-4 py-2 text-sm text-white"
                        >
                            Hinzufugen
                        </button>
                    </div>
                    <p v-if="!fehlendeMitarbeiterListe.length" class="text-sm text-gray-500">
                        Alle aktiven Mitarbeiter sind diesem Projekt bereits zugewiesen.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

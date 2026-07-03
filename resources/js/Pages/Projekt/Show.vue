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
                <div class="grid gap-4 md:grid-cols-4">
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

<script setup>
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    alle_teilnehmer: Array,
    alle_bereiche: Array,
    selectionCount: Number,
    search: String,
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
});
// Reactive Variablen für Radios
const wahl = ref({});

// Funktion zum Senden der Wahl
const updateWahl = async (teilnehmerId, wahlNummer, bereichId) => {
    try {
        const response = await axios.post(route('bereichsauswahl.bop.radio.update'), {
            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            teilnehmer_id: teilnehmerId,
            wahl: `bereich_id${wahlNummer}`,
            orientierung: bereichId,
        });

        Swal.fire({
            title: "Erfolg!",
            text: "Die Einteilung wurde erfolgreich erstellt.",
            icon: "success",
            timer: 800,
            timerProgressBar: true,
        });
    } catch (error) {
        Swal.fire({
            title: "Fehler!",
            text: "Die Speicherung ist fehlgeschlagen:"  + error.response.data.error || error.response.data.message || 'Unbekannter Fehler',
            icon: "error",
            timer: 4000,
            timerProgressBar: true,
        });
    }
};

// Funktion um zu prüfen, ob Radio ausgewählt sein soll
const isChecked = (teilnehmer, wahlNummer, bereichId) => {
    if (!teilnehmer.bereichsauswahl) return false;
    return teilnehmer.bereichsauswahl[`bereich_id${wahlNummer}`] === bereichId;
};

const choicesFor = (teilnehmer) => ([1, 2, 3, 4].map((number) => (
    teilnehmer.bereichsauswahl?.[`bereich_id${number}`] ?? ''
)));

const activeChoices = (row) => row.choices.slice(0, props.selectionCount);
const isComplete = (row) => activeChoices(row).every(Boolean);
const hasDuplicates = (row) => {
    const filled = activeChoices(row).filter(Boolean);
    return new Set(filled.map(Number)).size !== filled.length;
};

const rows = ref((props.alle_teilnehmer ?? []).map((teilnehmer) => ({
    ...teilnehmer,
    choices: choicesFor(teilnehmer),
    saved: true,
    saving: false,
})));

watch(
    () => props.selectionCount,
    (count) => {
        rows.value = rows.value.map((row) => ({
            ...row,
            choices: row.choices.map((choice, index) => index < count ? choice : ''),
        }));
    }
);

const normalize = (value) => String(value ?? '').toLowerCase();

const filteredRows = computed(() => {
    const term = normalize(props.search).trim();

    if (!term) {
        return rows.value;
    }

    return rows.value.filter((row) => [
        row.id,
        row.person?.vorname,
        row.person?.nachname,
        row.klasse,
        row.bereichsauswahl?.access_code,
    ].map(normalize).join(' ').includes(term));
});

const isOptionDisabled = (row, bereichId, choiceIndex) => (
    activeChoices(row).some((choice, index) => index !== choiceIndex && Number(choice) === Number(bereichId))
);
const hasSavedChoices = (row) => [1, 2, 3, 4].some(
    (field) => row.bereichsauswahl?.[`bereich_id${field}`] != null
);
const canEditRow = (row) => hasSavedChoices(row) ? props.canUpdate : props.canCreate;

const saveRow = async (row, showSuccess = true) => {
    if (!isComplete(row)) {
        return;
    }

    if (hasDuplicates(row)) {
        Swal.fire({
            title: 'Doppelte Auswahl',
            text: 'Jeder Bereich darf pro Teilnehmer nur einmal ausgewaehlt werden.',
            icon: 'warning',
        });
        return;
    }

    row.saving = true;

    try {
        const response = await axios.post(route('bereichsauswahl.bop.radio.update'), {
            teilnehmer_id: row.id,
            choices: activeChoices(row).map(Number),
        });

        row.choices = [1, 2, 3, 4].map((number, index) => response.data.choices[index] ?? '');
        row.bereichsauswahl = {
            ...(row.bereichsauswahl ?? {}),
            access_code: response.data.access_code ?? row.bereichsauswahl?.access_code,
            bereich_id1: row.choices[0] || null,
            bereich_id2: row.choices[1] || null,
            bereich_id3: row.choices[2] || null,
            bereich_id4: row.choices[3] || null,
        };
        row.saved = true;

        if (showSuccess) {
            Swal.fire({
                title: 'Gespeichert',
                icon: 'success',
                timer: 900,
                showConfirmButton: false,
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || error.response?.data?.errors?.choices?.[0] || 'Die Auswahl konnte nicht gespeichert werden.',
            icon: 'error',
        });
    } finally {
        row.saving = false;
    }
};

const setChoice = (row, choiceIndex, bereichId) => {
    if (!canEditRow(row) || isOptionDisabled(row, bereichId, choiceIndex) || row.saving) return;

    row.choices[choiceIndex] = Number(bereichId);
    row.saved = false;

    if (isComplete(row) && !hasDuplicates(row)) {
        saveRow(row, false);
    }
};

const statusText = (row) => {
    if (row.saving) return 'Speichert';
    if (!isComplete(row)) return 'Offen';
    if (row.saved) return 'Gespeichert';
    return 'Geändert';
};

const statusClass = (row) => {
    if (row.saving) return 'bg-blue-50 text-blue-700 border-blue-200';
    if (!isComplete(row)) return 'bg-yellow-50 text-yellow-700 border-yellow-200';
    if (row.saved) return 'bg-green-50 text-green-700 border-green-200';
    return 'bg-orange-50 text-orange-700 border-orange-200';
};

const showOpenModal = ref(false);

const openRows = computed(() => rows.value.filter((row) => !isComplete(row)));

const selectionProgress = (row) => activeChoices(row).filter(Boolean).length;

const copyCode = async (code) => {
    if (!code) return;

    try {
        await navigator.clipboard.writeText(code);
        Swal.fire({
            title: 'Code kopiert',
            icon: 'success',
            timer: 900,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            title: 'Code',
            text: code,
            icon: 'info',
        });
    }
};
</script>

<template>
    <div class="mb-3 flex items-center justify-end">
        <button
            type="button"
            class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm font-semibold text-yellow-800 hover:bg-yellow-100"
            @click="showOpenModal = true"
        >
            <i class="las la-list-ul"></i>
            <span>Offene Teilnehmer</span>
            <span class="inline-flex min-w-6 items-center justify-center bg-yellow-200 px-2 py-0.5 text-xs text-yellow-900">
                {{ openRows.length }}
            </span>
        </button>
    </div>

    <div class="overflow-x-auto bg-white border border-gray-300 shadow-sm">
        <table class="min-w-full table-auto text-left text-sm text-gray-700">
            <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-3 border-b border-gray-300 w-20">ID</th>
                    <th class="px-3 py-3 border-b border-gray-300">Teilnehmer</th>
                    <th class="px-3 py-3 border-b border-gray-300 w-28">Klasse</th>
                    <th class="px-3 py-3 border-b border-gray-300 w-44">Code</th>
                    <th
                        v-for="index in selectionCount"
                        :key="index"
                        class="px-3 py-3 border-b border-gray-300 min-w-[190px]"
                    >
                        Bereich {{ index }}
                    </th>
                    <th class="px-3 py-3 border-b border-gray-300 w-32 text-right">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in filteredRows"
                    :key="row.id"
                    class="border-b border-gray-200 hover:bg-gray-50"
                >
                    <td class="px-3 py-3 align-middle font-semibold text-gray-500">{{ row.id }}</td>
                    <td class="px-3 py-3 align-middle">
                        <div class="font-semibold text-gray-900">
                            {{ row.person?.nachname }}, {{ row.person?.vorname }}
                        </div>
                    </td>
                    <td class="px-3 py-3 align-middle">{{ row.klasse }}</td>
                    <td class="px-3 py-3 align-middle">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 font-mono text-xs border border-gray-300 px-2 py-1 bg-white hover:bg-gray-100"
                            @click="copyCode(row.bereichsauswahl?.access_code)"
                        >
                            <span>{{ row.bereichsauswahl?.access_code || '-' }}</span>
                            <i class="las la-copy"></i>
                        </button>
                    </td>
                    <td
                        v-for="(_, index) in activeChoices(row)"
                        :key="`${row.id}-${index}`"
                        class="px-3 py-3 align-middle min-w-[260px]"
                    >
                        <div class="flex flex-wrap gap-1.5">
                            <label
                                v-for="bereich in alle_bereiche"
                                :key="bereich.id"
                                class="inline-flex cursor-pointer items-center gap-1.5 border px-2 py-1 text-xs font-semibold transition"
                                :class="[
                                    Number(row.choices[index]) === Number(bereich.id)
                                        ? 'border-zbb bg-zbb text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:border-zbb hover:text-zbb',
                                    !canEditRow(row) || isOptionDisabled(row, bereich.id, index) || row.saving
                                        ? 'cursor-not-allowed opacity-40 hover:border-gray-300 hover:text-gray-700'
                                        : ''
                                ]"
                            >
                                <input
                                    type="radio"
                                    class="h-3.5 w-3.5 border-gray-300 text-zbb focus:ring-zbb"
                                    :name="`bereich-${row.id}-${index}`"
                                    :value="bereich.id"
                                    :checked="Number(row.choices[index]) === Number(bereich.id)"
                                    :disabled="!canEditRow(row) || isOptionDisabled(row, bereich.id, index) || row.saving"
                                    @change="setChoice(row, index, bereich.id)"
                                />
                                <span>{{ bereich.code || bereich.name }}</span>
                            </label>
                        </div>
                    </td>
                    <td class="px-3 py-3 align-middle text-right">
                        <span
                            class="inline-flex items-center justify-center border px-2.5 py-1 text-xs font-semibold"
                            :class="statusClass(row)"
                        >
                            {{ statusText(row) }}
                        </span>
                    </td>
                </tr>
                <tr v-if="filteredRows.length === 0">
                    <td :colspan="selectionCount + 5" class="px-3 py-8 text-center text-gray-500">
                        Keine Teilnehmer gefunden.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div
        v-if="showOpenModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-6"
        @click.self="showOpenModal = false"
    >
        <div class="w-full max-w-3xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Offene Teilnehmer</h2>
                    <p class="text-sm text-gray-500">{{ openRows.length }} Teilnehmer noch nicht vollstaendig gewaehlt</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-100"
                    @click="showOpenModal = false"
                >
                    <i class="las la-times"></i>
                </button>
            </div>

            <div class="max-h-[65vh] overflow-y-auto p-5">
                <table v-if="openRows.length" class="w-full text-left text-sm">
                    <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Teilnehmer</th>
                            <th class="px-3 py-2">Klasse</th>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2 text-right">Auswahl</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in openRows"
                            :key="row.id"
                            class="border-b border-gray-200"
                        >
                            <td class="px-3 py-3 font-semibold text-gray-500">{{ row.id }}</td>
                            <td class="px-3 py-3 font-semibold text-gray-900">
                                {{ row.person?.nachname }}, {{ row.person?.vorname }}
                            </td>
                            <td class="px-3 py-3">{{ row.klasse }}</td>
                            <td class="px-3 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 font-mono text-xs border border-gray-300 px-2 py-1 bg-white hover:bg-gray-100"
                                    @click="copyCode(row.bereichsauswahl?.access_code)"
                                >
                                    <span>{{ row.bereichsauswahl?.access_code || '-' }}</span>
                                    <i class="las la-copy"></i>
                                </button>
                            </td>
                            <td class="px-3 py-3 text-right font-semibold text-yellow-700">
                                {{ selectionProgress(row) }} / {{ selectionCount }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="py-10 text-center text-gray-500">
                    Alle Teilnehmer sind vollstaendig gespeichert.
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-200 px-5 py-4">
                <button
                    type="button"
                    class="bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                    @click="showOpenModal = false"
                >
                    Schließen
                </button>
            </div>
        </div>
    </div>

    <div v-if="false">
<div class="overflow-x-auto">
    <table class="min-w-full table-auto text-left border text-gray-700">
        <thead class="bg-gray-200">
            <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Vorname</th>
                <th class="px-4 py-2">Nachname</th>
                <th class="px-4 py-2">Klasse</th>
                <th class="px-4 py-2">Bereich 1</th>
                <th class="px-4 py-2">Bereich 2</th>
                <th class="px-4 py-2">Bereich 3</th>
                <th class="px-4 py-2">Bereich 4</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="teilnehmer in alle_teilnehmer" :key="teilnehmer.id" class="bg-white border-b">
                <td class="px-4 py-2">{{teilnehmer.id}}</td>
                <td class="px-4 py-2">{{teilnehmer.person.vorname}}</td>
                <td class="px-4 py-2">{{teilnehmer.person.nachname}}</td>
                <td class="px-4 py-2">{{teilnehmer.klasse}}</td>

                <!-- 4 Bereichsauswahlen -->
                <td v-for="i in 4" :key="i" class="px-4 py-2">
                    <div class="flex flex-col space-y-1">
                        <label v-for="bereich in alle_bereiche" :key="bereich.id" class="flex items-center space-x-2">
                            <input
                                type="radio"
                                :name="`wahl${i}_${teilnehmer.id}`"
                                :value="bereich.id"
                                :checked="isChecked(teilnehmer, i, bereich.id)"
                                @change="updateWahl(teilnehmer.id, i, bereich.id)"
                                class="form-radio text-blue-600"
                            />
                            <span>{{bereich.name}}</span>
                        </label>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
    </div>
</template>

<style scoped>
.form-radio {
    accent-color: #3b82f6; /* Tailwind blau */
}
</style>

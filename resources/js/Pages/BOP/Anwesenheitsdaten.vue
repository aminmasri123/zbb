<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    partner: Object,
    schueler: Array,
    schuljahr: String,
    teil: String,
    tage: Object,
    summenTage: Array,
    gesamtAnwesenheitstage: Number,
    paAnzahl: Number,
});

const dayKeys = Object.keys(props.tage);
const search = ref('');
const sortState = reactive({ key: 'nummer', direction: 'asc' });
const storageKey = `zbb:anwesenheitsdaten:${props.partner.id}:${props.schuljahr}:${props.teil}`;

function defaultStatus(key) {
    return key === 'bo1' ? 'absent' : 'present';
}

function createDefaultStatuses() {
    return props.schueler.reduce((data, student) => {
        data[student.id] = {};
        dayKeys.forEach((key) => {
            data[student.id][key] = defaultStatus(key);
        });
        return data;
    }, {});
}

function loadStatuses() {
    const defaults = createDefaultStatuses();
    try {
        const stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
        props.schueler.forEach((student) => {
            defaults[student.id] = {
                ...defaults[student.id],
                ...(stored[student.id] || {}),
            };
        });
        return defaults;
    } catch (error) {
        return defaults;
    }
}

const statuses = reactive(loadStatuses());

const filteredStudents = computed(() => {
    const term = search.value.trim().toLowerCase();
    const filtered = props.schueler.filter((student) => {
        if (!term) return true;
        return [
            student.nummer,
            student.nachname,
            student.vorname,
            student.geschlecht,
            student.klasse,
        ].join(' ').toLowerCase().includes(term);
    });

    return [...filtered].sort((a, b) => {
        const av = sortState.key === 'summe' ? rowSum(a.id) : a[sortState.key];
        const bv = sortState.key === 'summe' ? rowSum(b.id) : b[sortState.key];
        const result = ['nummer', 'summe'].includes(sortState.key)
            ? Number(av) - Number(bv)
            : String(av ?? '').localeCompare(String(bv ?? ''), 'de', { numeric: true, sensitivity: 'base' });

        return sortState.direction === 'asc' ? result : -result;
    });
});

const totalPresent = computed(() => {
    return props.schueler.reduce((total, student) => total + rowSum(student.id), 0);
});

function persist() {
    localStorage.setItem(storageKey, JSON.stringify(statuses));
}

function rowSum(studentId) {
    return props.summenTage.filter((key) => statuses[studentId]?.[key] === 'present').length;
}

function statusSymbol(status) {
    if (status === 'present') return '\u2713';
    if (status === 'absent') return '\u2716';
    return '\u2013';
}

function statusClass(status) {
    if (status === 'present') return 'text-green-600';
    if (status === 'absent') return 'text-rose-600';
    return 'text-gray-800';
}

function nextStatus(current) {
    if (current === 'present') return 'absent';
    if (current === 'absent') return 'empty';
    return 'present';
}

function feedback(status) {
    if (status === 'present') {
        Swal.fire({ title: 'Success', text: 'Haken wurde gesetzt.', icon: 'success', timer: 1200, showConfirmButton: false });
        return;
    }

    if (status === 'absent') {
        Swal.fire({ title: 'Error', text: 'Kreuz wurde gesetzt.', icon: 'error', timer: 1200, showConfirmButton: false });
        return;
    }

    Swal.fire({ title: 'Info', text: 'Status wurde geleert.', icon: 'info', timer: 1200, showConfirmButton: false });
}

function setStatus(studentId, dayKey, status, showMessage = true) {
    statuses[studentId][dayKey] = status;
    persist();
    if (showMessage) feedback(status);
}

function toggleStatus(studentId, dayKey) {
    setStatus(studentId, dayKey, nextStatus(statuses[studentId][dayKey]));
}

function setVisibleRows(status) {
    filteredStudents.value.forEach((student) => {
        dayKeys.forEach((key) => {
            statuses[student.id][key] = status;
        });
    });
    persist();
    feedback(status);
}

function toggleRow(student) {
    const allPresent = dayKeys.every((key) => statuses[student.id][key] === 'present');
    const next = allPresent ? 'absent' : 'present';
    dayKeys.forEach((key) => {
        statuses[student.id][key] = next;
    });
    persist();
    feedback(next);
}

function sortBy(key) {
    sortState.direction = sortState.key === key && sortState.direction === 'asc' ? 'desc' : 'asc';
    sortState.key = key;
}

async function confirmGenerate() {
    const result = await Swal.fire({
        title: 'Generieren bestaetigen',
        text: 'Bist du sicher, dass du generieren moechtest? Bestehende Haken und Kreuze werden ueberschrieben.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ja, generieren',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#ff7a00',
    });

    if (!result.isConfirmed) return;

    props.schueler.forEach((student) => {
        dayKeys.forEach((key) => {
            statuses[student.id][key] = defaultStatus(key);
        });
    });
    persist();
    Swal.fire({ title: 'Success', text: 'Anwesenheitsdaten wurden generiert.', icon: 'success', timer: 1500, showConfirmButton: false });
}

function exportCsv() {
    const header = ['ID', 'Nachname', 'Vorname', 'W/M', 'Klasse', ...Object.values(props.tage), 'Summe'];
    const lines = [header];

    props.schueler.forEach((student) => {
        const row = [student.nummer, student.nachname, student.vorname, student.geschlecht, student.klasse];
        dayKeys.forEach((key) => {
            const status = statuses[student.id][key];
            row.push(status === 'present' ? 'x' : status === 'absent' ? '-' : '');
        });
        row.push(rowSum(student.id));
        lines.push(row);
    });

    const csv = lines.map((line) => line.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(';')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Anwesenheitsdaten_${props.partner.id}_${props.schuljahr}_Teil_${props.teil}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function submitExcelExport() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('export.anwesenheitsdaten.schule.excel', {
        schulId: props.partner.id,
        schuljahr: props.schuljahr,
        teil: props.teil,
    });

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrf;
    form.appendChild(token);

    const payload = document.createElement('input');
    payload.type = 'hidden';
    payload.name = 'status_payload';
    payload.value = JSON.stringify(statuses);
    form.appendChild(payload);

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="Anwesenheitsdaten" />
    <AppLayout title="Anwesenheitsdaten">
        <template #header>Anwesenheitsdaten</template>

        <div class="space-y-5">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-medium text-[var(--primary)]">Anwesenheitsdaten</h1>
                        <i class="las la-table text-3xl text-[var(--primary)]"></i>
                    </div>
                    <div class="mt-1 text-xs text-[var(--secondary)]">
                        Dashboard / {{ partner.name }} / Anwesenheitsdaten
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-zbb px-4 py-2 text-xs text-white hover:bg-zbb/80" @click="confirmGenerate">
                        <i class="las la-sync"></i>
                        Generieren
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-zbb px-4 py-2 text-xs text-white hover:bg-zbb/80" @click="submitExcelExport">
                        <i class="las la-file-excel"></i>
                        Excel
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-zbb px-4 py-2 text-xs text-white hover:bg-zbb/80" @click="exportCsv">
                        <i class="las la-file-csv"></i>
                        CSV
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-zbb px-4 py-2 text-xs text-white hover:bg-zbb/80" @click="printPage">
                        <i class="las la-print"></i>
                        Drucken
                    </button>
                </div>
            </section>

            <section class="flex flex-wrap gap-4 text-sm font-semibold text-[var(--primary)]">
                <span>Gesamtanzahl Anwesenheitstage: {{ totalPresent }}</span>
                <span>Schueleranzahl PA: {{ paAnzahl }}</span>
                <span>Schule: {{ partner.name }}</span>
                <span>Schuljahr: {{ schuljahr }}</span>
                <span>Teil: {{ teil }}</span>
            </section>

            <section class="flex flex-wrap items-center gap-2">
                <input v-model="search" type="search" class="w-full rounded border border-gray-300 px-3 py-2 text-sm lg:w-80" placeholder="Nach Name, Klasse oder ID filtern">
                <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-100" @click="setVisibleRows('present')">
                    <i class="las la-check"></i> Sichtbare ankreuzen
                </button>
                <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-100" @click="setVisibleRows('absent')">
                    <i class="las la-times"></i> Sichtbare abkreuzen
                </button>
                <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-100" @click="setVisibleRows('empty')">
                    <i class="las la-eraser"></i> Sichtbare leeren
                </button>
            </section>

            <section class="overflow-auto border border-gray-300 bg-white">
                <table class="w-full min-w-[1500px] border-collapse text-xs">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('nummer')">ID <span class="text-gray-400">⇅</span></th>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('nachname')">Nachname <span class="text-gray-400">⇅</span></th>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('vorname')">Vorname <span class="text-gray-400">⇅</span></th>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('geschlecht')">W/M <span class="text-gray-400">⇅</span></th>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('klasse')">Klasse <span class="text-gray-400">⇅</span></th>
                            <th v-for="label in tage" :key="label" class="border border-gray-300 px-2 py-2">{{ label }}</th>
                            <th class="cursor-pointer border border-gray-300 px-2 py-2" @click="sortBy('summe')">Summe <span class="text-gray-400">⇅</span></th>
                            <th class="border border-gray-300 px-2 py-2"><i class="las la-cog"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!filteredStudents.length">
                            <td :colspan="dayKeys.length + 7" class="border border-gray-300 px-3 py-10 text-center text-gray-500">Keine Teilnehmer gefunden.</td>
                        </tr>
                        <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-orange-50">
                            <td class="border border-gray-300 px-2 py-2 text-center text-blue-600">{{ student.nummer }}</td>
                            <td class="border border-gray-300 px-2 py-2">{{ student.nachname }}</td>
                            <td class="border border-gray-300 px-2 py-2">{{ student.vorname }}</td>
                            <td class="border border-gray-300 px-2 py-2 text-center font-semibold" :class="student.geschlecht === 'w' ? 'text-rose-600' : 'text-green-600'">{{ student.geschlecht }}</td>
                            <td class="border border-gray-300 px-2 py-2 text-center">{{ student.klasse }}</td>
                            <td
                                v-for="(label, key) in tage"
                                :key="`${student.id}-${key}`"
                                class="cursor-pointer border border-gray-300 px-2 py-2 text-center text-base font-bold"
                                :class="statusClass(statuses[student.id][key])"
                                role="button"
                                tabindex="0"
                                @click="toggleStatus(student.id, key)"
                                @keydown.enter.prevent="toggleStatus(student.id, key)"
                                @keydown.space.prevent="toggleStatus(student.id, key)"
                            >
                                {{ statusSymbol(statuses[student.id][key]) }}
                            </td>
                            <td class="border border-gray-300 px-2 py-2 text-center">{{ rowSum(student.id) }}</td>
                            <td class="border border-gray-300 px-2 py-2 text-center">
                                <button type="button" class="text-lg text-gray-500 hover:text-zbb" title="Zeile umschalten" @click="toggleRow(student)">
                                    <i class="las la-ellipsis-v"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>

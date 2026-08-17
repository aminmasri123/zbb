<script setup>
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    internships: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
});

const statusOptions = [
    { value: 'present', label: 'Anwesend' },
    { value: 'absent_excused', label: 'Entschuldigt abwesend' },
    { value: 'absent_unexcused', label: 'Unentschuldigt abwesend' },
    { value: 'sick', label: 'Krank' },
    { value: 'vacation', label: 'Urlaub' },
    { value: 'school', label: 'Berufsschule / Betreuungstag' },
    { value: 'holiday', label: 'Feiertag / Betrieb geschlossen' },
];
const weekdayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

const selectedInternshipId = ref(
    props.internships.find((item) => item.status === 'laufend')?.id
    || props.internships.find((item) => item.status === 'geplant')?.id
    || props.internships[0]?.id
    || null
);
const attendanceState = ref(Object.fromEntries(
    props.internships.map((item) => [item.id, JSON.parse(JSON.stringify(item.attendances || []))])
));
const selectedInternship = computed(() => props.internships.find((item) => Number(item.id) === Number(selectedInternshipId.value)) || null);
const weekStart = ref('');
const rows = ref([]);
const saving = ref(false);

const parseDate = (value) => new Date(`${String(value).slice(0, 10)}T12:00:00`);
const isoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const addDays = (value, amount) => {
    const date = typeof value === 'string' ? parseDate(value) : new Date(value);
    date.setDate(date.getDate() + amount);
    return date;
};
const mondayFor = (value) => {
    const date = parseDate(value);
    const weekday = date.getDay() || 7;
    date.setDate(date.getDate() - weekday + 1);
    return isoDate(date);
};
const formatDate = (value) => parseDate(value).toLocaleDateString('de-DE');
const internshipLabel = (item) => `${item.placement_type === 'internal' ? 'Intern' : 'Extern'} · ${item.host_project?.name || item.traeger || 'Praktikum'} · ${formatDate(item.start)}–${formatDate(item.end)}`;
const isWithinInternship = (date) => Boolean(selectedInternship.value)
    && date >= String(selectedInternship.value.start).slice(0, 10)
    && date <= String(selectedInternship.value.end).slice(0, 10);

const setInitialWeek = () => {
    const internship = selectedInternship.value;
    if (!internship) {
        weekStart.value = '';
        rows.value = [];
        return;
    }
    const today = isoDate(new Date());
    const start = String(internship.start).slice(0, 10);
    const end = String(internship.end).slice(0, 10);
    weekStart.value = mondayFor(today >= start && today <= end ? today : start);
};

const buildRows = () => {
    const internship = selectedInternship.value;
    if (!internship || !weekStart.value) {
        rows.value = [];
        return;
    }
    const byDate = new Map((attendanceState.value[internship.id] || []).map((entry) => [String(entry.attendance_date).slice(0, 10), entry]));
    const defaultHours = internship.weekly_hours ? Math.round((Number(internship.weekly_hours) / 5) * 100) / 100 : null;
    rows.value = weekdayNames.map((weekday, index) => {
        const date = isoDate(addDays(weekStart.value, index));
        const existing = byDate.get(date);
        return {
            weekday,
            date,
            status: existing?.status || '',
            planned_hours: existing?.planned_minutes != null ? Number(existing.planned_minutes) / 60 : defaultHours,
            actual_hours: existing?.actual_minutes != null ? Number(existing.actual_minutes) / 60 : null,
            note: existing?.note || '',
        };
    });
};

watch(selectedInternshipId, setInitialWeek);
watch(weekStart, buildRows);
setInitialWeek();

const weekLabel = computed(() => weekStart.value
    ? `${formatDate(weekStart.value)} bis ${formatDate(isoDate(addDays(weekStart.value, 4)))}`
    : '');
const weeklySummary = computed(() => ({
    recorded: rows.value.filter((row) => isWithinInternship(row.date) && row.status).length,
    present: rows.value.filter((row) => row.status === 'present').length,
    actualHours: rows.value.reduce((sum, row) => sum + (Number(row.actual_hours) || 0), 0),
}));

const shiftWeek = (weeks) => {
    weekStart.value = isoDate(addDays(weekStart.value, weeks * 7));
};

const statusChanged = (row) => {
    if (row.status === 'present' && (row.actual_hours === null || row.actual_hours === '')) {
        row.actual_hours = row.planned_hours;
    }
    if (row.status && row.status !== 'present' && !['school'].includes(row.status)) {
        row.actual_hours = null;
    }
};

const saveWeek = async () => {
    if (!selectedInternship.value || !weekStart.value) return;
    saving.value = true;
    try {
        const response = await axios.put(route('teilnehmer.praktikum.attendance.week', selectedInternship.value.id), {
            week_start: weekStart.value,
            days: rows.value
                .filter((row) => isWithinInternship(row.date))
                .map((row) => ({
                    date: row.date,
                    status: row.status || null,
                    planned_hours: row.planned_hours === '' ? null : row.planned_hours,
                    actual_hours: row.actual_hours === '' ? null : row.actual_hours,
                    note: row.note || null,
                })),
        });
        attendanceState.value[selectedInternship.value.id] = response.data.attendances;
        buildRows();
        Swal.fire({ icon: 'success', title: 'Woche gespeichert', text: response.data.message, timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' });
    } catch (error) {
        const messages = error.response?.data?.errors;
        Swal.fire({
            icon: 'error',
            title: 'Speichern nicht möglich',
            html: messages ? Object.values(messages).flat().join('<br>') : (error.response?.data?.message || 'Die Praktikumswoche konnte nicht gespeichert werden.'),
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <section v-if="internships.length" class="mx-auto mt-4 w-5/6 rounded-2xl border border-orange-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Praktikumsanwesenheit</p>
                <h3 class="mt-1 text-lg font-semibold text-gray-900">Wochenweise Tage erfassen</h3>
                <p class="text-sm text-gray-500">Diese Einträge gehören zum Praktikum und nicht zur BOP-Gruppenanwesenheit.</p>
            </div>
            <select v-model="selectedInternshipId" class="min-w-72 rounded-lg border-gray-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="internship in internships" :key="internship.id" :value="internship.id">{{ internshipLabel(internship) }}</option>
            </select>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-orange-50 p-3">
            <button type="button" class="rounded-lg border border-orange-300 bg-white px-3 py-2 text-sm text-orange-800" @click="shiftWeek(-1)">← Vorherige Woche</button>
            <div class="text-center"><p class="font-semibold text-gray-900">{{ weekLabel }}</p><p class="text-xs text-gray-500">Montag bis Freitag</p></div>
            <button type="button" class="rounded-lg border border-orange-300 bg-white px-3 py-2 text-sm text-orange-800" @click="shiftWeek(1)">Nächste Woche →</button>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Tag</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Sollstunden</th><th class="px-3 py-2">Iststunden</th><th class="px-3 py-2">Bemerkung</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in rows" :key="row.date" :class="isWithinInternship(row.date) ? 'bg-white' : 'bg-gray-100 text-gray-400'">
                        <td class="whitespace-nowrap px-3 py-3"><p class="font-semibold">{{ row.weekday }}</p><p class="text-xs">{{ formatDate(row.date) }}</p><p v-if="!isWithinInternship(row.date)" class="mt-1 text-xs">außerhalb des Zeitraums</p></td>
                        <td class="px-3 py-3"><select v-model="row.status" :disabled="!canEdit || !isWithinInternship(row.date)" class="min-w-52 rounded-lg border-gray-300 text-sm" @change="statusChanged(row)"><option value="">Kein Eintrag / löschen</option><option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option></select></td>
                        <td class="px-3 py-3"><input v-model="row.planned_hours" :disabled="!canEdit || !isWithinInternship(row.date)" type="number" min="0" max="24" step="0.25" class="w-24 rounded-lg border-gray-300 text-sm" /></td>
                        <td class="px-3 py-3"><input v-model="row.actual_hours" :disabled="!canEdit || !isWithinInternship(row.date)" type="number" min="0" max="24" step="0.25" class="w-24 rounded-lg border-gray-300 text-sm" /></td>
                        <td class="px-3 py-3"><input v-model="row.note" :disabled="!canEdit || !isWithinInternship(row.date)" maxlength="500" class="min-w-64 rounded-lg border-gray-300 text-sm" placeholder="optional" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
            <p class="text-sm text-gray-600">{{ weeklySummary.recorded }} Tage erfasst · {{ weeklySummary.present }} anwesend · {{ weeklySummary.actualHours.toLocaleString('de-DE', { maximumFractionDigits: 2 }) }} Iststunden</p>
            <button v-if="canEdit" type="button" :disabled="saving" class="rounded-lg bg-zbb px-4 py-2 font-semibold text-white disabled:opacity-50" @click="saveWeek">{{ saving ? 'Speichert …' : 'Woche speichern' }}</button>
        </div>
    </section>
</template>

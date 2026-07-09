<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Swal from 'sweetalert2';
import { formatDateTime } from '@/utils/dateFormat.js';

const props = defineProps({
    bookings: Array,
    vehicles: Array,
    drivers: Array,
});

const localBookings = ref([...props.bookings]);
watch(() => props.bookings, (value) => {
    localBookings.value = [...value];
});
const showModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);

const emptyForm = () => ({
    dienstwagen_id: '',
    person_id: '',
    start_at: '',
    end_at: '',
    ziel: '',
    zweck: '',
    status: 'geplant',
    start_km: '',
    end_km: '',
    notizen: '',
});

const form = ref(emptyForm());

const statusOptions = [
    { label: 'Geplant', value: 'geplant' },
    { label: 'Bestätigt', value: 'bestaetigt' },
    { label: 'Abgelehnt', value: 'abgelehnt' },
    { label: 'Storniert', value: 'storniert' },
    { label: 'Abgeschlossen', value: 'abgeschlossen' },
];

function asDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function openCreate() {
    isEditing.value = false;
    editId.value = null;
    form.value = emptyForm();
    showModal.value = true;
}

function openEdit(record) {
    isEditing.value = true;
    editId.value = record.id;
    form.value = {
        dienstwagen_id: record.dienstwagen_id,
        person_id: record.person_id || '',
        start_at: asDateTimeLocal(record.start_at),
        end_at: asDateTimeLocal(record.end_at),
        ziel: record.ziel || '',
        zweck: record.zweck || '',
        status: record.status || 'geplant',
        start_km: record.start_km || '',
        end_km: record.end_km || '',
        notizen: record.notizen || '',
    };
    showModal.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            Swal.fire('Gespeichert', 'Die Buchung wurde gespeichert.', 'success');
            router.reload({ only: ['bookings'] });
        },
        onError: (errors) => {
            Swal.fire('Fehler', Object.values(errors)[0] || 'Die Buchung konnte nicht gespeichert werden.', 'error');
        },
    };

    if (isEditing.value) {
        router.put(route('dienstwagen.buchungen.update', editId.value), form.value, options);
        return;
    }

    router.post(route('dienstwagen.buchungen.store'), form.value, options);
}

function destroy(record) {
    Swal.fire({
        title: 'Buchung löschen?',
        text: `${record.dienstwagen?.kennzeichen || ''} ${formatDateTime(record.start_at)}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('dienstwagen.buchungen.destroy', record.id), {
            preserveScroll: true,
            onSuccess: () => {
                localBookings.value = localBookings.value.filter((item) => item.id !== record.id);
            },
        });
    });
}
</script>

<template>
    <Head title="Dienstwagen Buchungen" />

    <AppLayout>
        <template #header>Dienstwagen Buchungen</template>

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-900">Reservierungen und Nutzungsplanung</h2>
                <button class="rounded bg-zbb px-4 py-2 font-semibold text-white hover:bg-orange-500" @click="openCreate">
                    Neue Buchung
                </button>
            </div>

            <div class="overflow-x-auto rounded border bg-white shadow-sm">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="table-head">Zeitraum</th>
                            <th class="table-head">Fahrzeug</th>
                            <th class="table-head">Fahrer</th>
                            <th class="table-head">Ziel</th>
                            <th class="table-head">Zweck</th>
                            <th class="table-head">Status</th>
                            <th class="table-head">KM</th>
                            <th class="table-head text-right">*</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="record in localBookings" :key="record.id" class="border-t hover:bg-gray-50">
                            <td class="table-cell">{{ formatDateTime(record.start_at) }}<br>{{ formatDateTime(record.end_at) }}</td>
                            <td class="table-cell font-semibold">{{ record.dienstwagen?.kennzeichen }}</td>
                            <td class="table-cell">{{ record.person?.nachname || '-' }} {{ record.person?.vorname || '' }}</td>
                            <td class="table-cell">{{ record.ziel || '-' }}</td>
                            <td class="table-cell">{{ record.zweck }}</td>
                            <td class="table-cell">{{ record.status }}</td>
                            <td class="table-cell">{{ record.start_km || '-' }} / {{ record.end_km || '-' }}</td>
                            <td class="table-cell text-right">
                                <button class="mr-3 text-blue-600 hover:text-blue-800" @click="openEdit(record)">Bearbeiten</button>
                                <button class="text-red-600 hover:text-red-800" @click="destroy(record)">Löschen</button>
                            </td>
                        </tr>
                        <tr v-if="localBookings.length === 0">
                            <td colspan="8" class="table-cell text-center text-gray-500">Keine Buchungen vorhanden.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="isEditing ? 'Buchung bearbeiten' : 'Neue Buchung'" :style="{ width: '48rem' }">
            <form class="grid grid-cols-2 gap-5 pt-2" @submit.prevent="submit">
                <FloatLabel variant="on">
                    <Select v-model="form.dienstwagen_id" :options="vehicles.map(v => ({ label: `${v.kennzeichen} - ${v.marke} ${v.modell}`, value: v.id }))" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Fahrzeug</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select v-model="form.person_id" :options="drivers.map(d => ({ label: `${d.nachname} ${d.vorname}`, value: d.id }))" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Fahrer</label>
                </FloatLabel>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Start</label>
                    <input
                        v-model="form.start_at"
                        type="datetime-local"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-gray-800 shadow-sm focus:border-zbb focus:ring-zbb"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Ende</label>
                    <input
                        v-model="form.end_at"
                        type="datetime-local"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-gray-800 shadow-sm focus:border-zbb focus:ring-zbb"
                    />
                </div>

                <FloatLabel variant="on">
                    <InputText v-model="form.ziel" class="w-full" />
                    <label>Ziel</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <InputText v-model="form.zweck" class="w-full" />
                    <label>Zweck</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Status</label>
                </FloatLabel>

                <div class="grid grid-cols-2 gap-3">
                    <FloatLabel variant="on">
                        <InputText v-model="form.start_km" type="number" class="w-full" />
                        <label>Start km</label>
                    </FloatLabel>
                    <FloatLabel variant="on">
                        <InputText v-model="form.end_km" type="number" class="w-full" />
                        <label>Ende km</label>
                    </FloatLabel>
                </div>

                <FloatLabel variant="on" class="col-span-2">
                    <Textarea v-model="form.notizen" rows="4" class="w-full" />
                    <label>Notizen</label>
                </FloatLabel>

                <div class="col-span-2 flex justify-end gap-3">
                    <button type="button" class="rounded border px-4 py-2" @click="showModal = false">Abbrechen</button>
                    <button type="submit" class="rounded bg-zbb px-4 py-2 font-semibold text-white">Speichern</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.table-head {
    @apply px-4 py-3 text-xs font-semibold uppercase tracking-wide;
}
.table-cell {
    @apply px-4 py-3 text-gray-800;
}
</style>

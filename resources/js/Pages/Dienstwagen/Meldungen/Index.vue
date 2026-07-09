<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Swal from 'sweetalert2';
import { formatDateTime } from '@/utils/dateFormat.js';

const props = defineProps({
    records: Array,
    vehicles: Array,
    currentPerson: Object,
});

const localRecords = ref([...props.records]);
watch(() => props.records, (value) => {
    localRecords.value = [...value];
});
const showModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const selectedResponsible = computed(() => {
    if (!isEditing.value) {
        return props.currentPerson;
    }

    return localRecords.value.find(item => item.id === editId.value)?.verantwortlich || props.currentPerson;
});

const emptyForm = () => ({
    dienstwagen_id: '',
    titel: '',
    kategorie: 'sonstiges',
    prioritaet: 'normal',
    status: 'offen',
    beschreibung: '',
    attachment: null,
    remove_attachment: false,
});

const form = ref(emptyForm());

const categories = [
    { label: 'Reparatur', value: 'reparatur' },
    { label: 'Reifen', value: 'reifen' },
    { label: 'Öl', value: 'oel' },
    { label: 'Unfall', value: 'unfall' },
    { label: 'Reinigung', value: 'reinigung' },
    { label: 'Dokument', value: 'dokument' },
    { label: 'Sonstiges', value: 'sonstiges' },
];

const priorities = [
    { label: 'Niedrig', value: 'niedrig' },
    { label: 'Normal', value: 'normal' },
    { label: 'Hoch', value: 'hoch' },
    { label: 'Kritisch', value: 'kritisch' },
];

const statuses = [
    { label: 'Offen', value: 'offen' },
    { label: 'In Bearbeitung', value: 'in_bearbeitung' },
    { label: 'Erledigt', value: 'erledigt' },
];

function badgeClass(record) {
    if (record.status === 'erledigt') return 'bg-green-100 text-green-700';
    if (record.prioritaet === 'kritisch') return 'bg-red-100 text-red-700';
    if (record.prioritaet === 'hoch') return 'bg-orange-100 text-orange-700';
    return 'bg-gray-100 text-gray-700';
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
        titel: record.titel || '',
        kategorie: record.kategorie || 'sonstiges',
        prioritaet: record.prioritaet || 'normal',
        status: record.status || 'offen',
        beschreibung: record.beschreibung || '',
        attachment: null,
        remove_attachment: false,
    };
    showModal.value = true;
}

function submit() {
    const payload = { ...form.value };

    if (isEditing.value) {
        router.post(route('dienstwagen.meldungen.update', editId.value), {
            ...payload,
            _method: 'put',
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: saved,
            onError: failed,
        });
        return;
    }

    router.post(route('dienstwagen.meldungen.store'), payload, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: saved,
        onError: failed,
    });
}

function saved() {
    showModal.value = false;
    Swal.fire('Gespeichert', 'Die Meldung wurde gespeichert.', 'success');
    router.reload({ only: ['records'] });
}

function failed(errors) {
    Swal.fire('Fehler', Object.values(errors)[0] || 'Die Meldung konnte nicht gespeichert werden.', 'error');
}

function destroy(record) {
    Swal.fire({
        title: 'Meldung löschen?',
        text: record.titel,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('dienstwagen.meldungen.destroy', record.id), {
            preserveScroll: true,
            onSuccess: () => {
                localRecords.value = localRecords.value.filter((item) => item.id !== record.id);
            },
        });
    });
}
</script>

<template>
    <Head title="Dienstwagen Meldungen" />

    <AppLayout>
        <template #header>Dienstwagen Meldungen</template>

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-900">Schäden, Reparaturen und Aufgaben</h2>
                <button class="rounded bg-zbb px-4 py-2 font-semibold text-white hover:bg-orange-500" @click="openCreate">
                    Neue Meldung
                </button>
            </div>

            <div class="overflow-x-auto rounded border bg-white shadow-sm">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="table-head">Meldung</th>
                            <th class="table-head">Fahrzeug</th>
                            <th class="table-head">Kategorie</th>
                            <th class="table-head">Status</th>
                            <th class="table-head">Verantwortlich</th>
                            <th class="table-head">Gemeldet</th>
                            <th class="table-head text-right">*</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="record in localRecords" :key="record.id" class="border-t hover:bg-gray-50">
                            <td class="table-cell">
                                <div class="font-semibold">{{ record.titel }}</div>
                                <div class="text-xs text-gray-500">{{ record.beschreibung || '-' }}</div>
                                <a v-if="record.attachment_url" :href="record.attachment_url" target="_blank" class="text-xs text-blue-600">Anhang öffnen</a>
                            </td>
                            <td class="table-cell font-semibold">{{ record.dienstwagen?.kennzeichen }}</td>
                            <td class="table-cell">{{ record.kategorie }}</td>
                            <td class="table-cell">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="badgeClass(record)">
                                    {{ record.status }} / {{ record.prioritaet }}
                                </span>
                            </td>
                            <td class="table-cell">{{ record.verantwortlich?.nachname || '-' }} {{ record.verantwortlich?.vorname || '' }}</td>
                            <td class="table-cell">{{ formatDateTime(record.created_at) }}</td>
                            <td class="table-cell text-right">
                                <button class="mr-3 text-blue-600 hover:text-blue-800" @click="openEdit(record)">Bearbeiten</button>
                                <button class="text-red-600 hover:text-red-800" @click="destroy(record)">Löschen</button>
                            </td>
                        </tr>
                        <tr v-if="localRecords.length === 0">
                            <td colspan="7" class="table-cell text-center text-gray-500">Keine Meldungen vorhanden.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:visible="showModal" modal :header="isEditing ? 'Meldung bearbeiten' : 'Neue Meldung'" :style="{ width: '48rem' }">
            <form class="grid grid-cols-2 gap-5 pt-2" @submit.prevent="submit">
                <FloatLabel variant="on">
                    <Select v-model="form.dienstwagen_id" :options="vehicles.map(v => ({ label: `${v.kennzeichen} - ${v.marke} ${v.modell}`, value: v.id }))" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Fahrzeug</label>
                </FloatLabel>

                <div class="rounded border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Verantwortlich</div>
                    <div class="mt-1 font-semibold text-gray-900">
                        {{ selectedResponsible?.nachname || '-' }} {{ selectedResponsible?.vorname || '' }}
                    </div>
                </div>

                <FloatLabel variant="on" class="col-span-2">
                    <InputText v-model="form.titel" class="w-full" />
                    <label>Titel</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select v-model="form.kategorie" :options="categories" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Kategorie</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select v-model="form.prioritaet" :options="priorities" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Priorität</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select v-model="form.status" :options="statuses" optionLabel="label" optionValue="value" class="w-full" />
                    <label>Status</label>
                </FloatLabel>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Foto oder PDF</label>
                    <input type="file" accept="image/*,.pdf" class="w-full rounded border px-3 py-2" @change="form.attachment = $event.target.files[0] || null" />
                    <label v-if="isEditing" class="mt-2 flex items-center gap-2 text-xs">
                        <input type="checkbox" v-model="form.remove_attachment" />
                        vorhandenen Anhang entfernen
                    </label>
                </div>

                <FloatLabel variant="on" class="col-span-2">
                    <Textarea v-model="form.beschreibung" rows="5" class="w-full" />
                    <label>Beschreibung</label>
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

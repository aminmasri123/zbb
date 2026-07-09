<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { formatDate } from '@/utils/dateFormat.js';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';

import ModalCreate from './ModalCreate.vue';
import ModalEdit from './ModalEdit.vue';

const props = defineProps({
    entries: Array,
    vehicles: Array,
    drivers: Array,
    selectedVehicle: Object,
    selectedVehicleId: Number,
});

const localEntries = ref([...props.entries]);
const selectedVehicleId = computed(() => props.selectedVehicleId ? Number(props.selectedVehicleId) : null);
const reportQuery = computed(() => selectedVehicleId.value ? { dienstwagen_id: selectedVehicleId.value } : {});

watch(() => props.entries, (newVal) => {
    localEntries.value = [...newVal];
});

/* -------------------------------
   MODAL: CREATE
--------------------------------*/
const showCreate = ref(false);
function openModalCreate() {
    showCreate.value = true;
}

/* -------------------------------
   MODAL: EDIT
--------------------------------*/
const showEdit = ref(false);
const selectedEntry = ref(null);

function openModalEdit(entry) {
    selectedEntry.value = entry;
    showEdit.value = true;
}

/* -------------------------------
   LÖSCHEN
--------------------------------*/
const showModalLöschen = ref(false);

const fahrtToDelete = ref({
    id: null,
    name: null, // beim Modal heißt es name – wir nutzen schon date
});

// Modal öffnen
const confirmDelete = (entry) => {
    fahrtToDelete.value = {
        id: entry.id,
        name: entry.date, // wird im Modal angezeigt
    };

    showModalLöschen.value = true;
};

// Wird ausgeführt, wenn Modal confirm Delete auslöst
const handleDelete = (id) => {
    // Liste sofort aktualisieren
    localEntries.value = localEntries.value.filter(e => e.id !== id);

    showModalLöschen.value = false;
};

</script>

<template>
    <Head title="Fahrtenbuch" />

    <AppLayout>
        <template #header>📘 Fahrtenbuch</template>

        <div class="space-y-10">

            <!-- Kopf + Button -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <button
                        @click="openModalCreate"
                        class="bg-zbb hover:bg-orange-300 text-white px-4 py-2 rounded-lg font-semibold shadow-md"
                    >
                        ➕ Neue Fahrt erfassen
                    </button>
                    <div v-if="selectedVehicle" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        Fahrtenbuch für
                        <strong>{{ selectedVehicle.kennzeichen }}</strong>
                        <span class="text-blue-700">({{ selectedVehicle.marke }} {{ selectedVehicle.modell }})</span>
                        <Link :href="route('dienstwagen.fahrtenbuch.index')" class="ml-3 font-semibold text-blue-700 hover:text-blue-900">
                            Alle Fahrzeuge anzeigen
                        </Link>
                    </div>
                </div>
                    <div class="flex gap-3">
                        <a :href="route('dienstwagen.fahrtenbuch.report.pdf', reportQuery)" class="rounded border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                            PDF Export
                        </a>
                        <a :href="route('dienstwagen.fahrtenbuch.report.excel', reportQuery)" class="rounded border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                            Excel Export
                        </a>
                    </div>
	            </div>

            <!-- TABELLE -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border p-6">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="table-head">Datum</th>
                            <th class="table-head">Fahrzeug</th>
                            <th class="table-head">Fahrer</th>
	                            <th class="table-head">Start (km)</th>
	                            <th class="table-head">Ende (km)</th>
	                            <th class="table-head">Distanz</th>
                                <th class="table-head">Startort</th>
	                            <th class="table-head">Ziel</th>
                                <th class="table-head">Art</th>
	                            <th class="table-head">Zweck</th>
	                            <th class="table-head text-center">*</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="e in localEntries"
                            :key="e.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900 transition"
                        >
                            <td class="table-cell">{{ formatDate(e.date) }}</td>

                            <td class="table-cell font-semibold">
                                {{ e.dienstwagen?.kennzeichen || '–' }}
                            </td>

                            <td class="table-cell">
                                {{ e.fahrer?.nachname || '-' }} {{ e.fahrer?.vorname || '-' }}

                            </td>

                            <td class="table-cell">{{ e.start_km }} km</td>
                            <td class="table-cell">{{ e.end_km }} km</td>

	                            <td class="table-cell">
	                                {{ (e.end_km - e.start_km) > 0 ? (e.end_km - e.start_km) + ' km' : '0 km' }}
	                            </td>

                                <td class="table-cell">{{ e.startort || '-' }}</td>
	                            <td class="table-cell">{{ e.ziel }}</td>
                                <td class="table-cell">{{ e.fahrtart || '-' }}</td>
	                            <td class="table-cell">{{ e.zweck }}</td>
                            <td class="w-10 px-6 py-4 text-center m-auto">
                                <!-- Dropdown für Aktion -->
                                <Dropdown >
                                    <template #trigger>
                                        <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                            <span class="cursor-pointer">
                                                <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                            </span>
                                        </button>
                                    </template>

                                    <template #content >
                                        <!-- Gefilterte Projektauswahl -->
                                        <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"  @click="confirmDelete(e)">
                                            {{ $t('Löschen') }} <i class="las la-trash-alt "></i>
                                        </span>
                                        <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"  @click="openModalEdit(e)">
                                            {{ $t('Bearbeiten') }}  <i class="las la-edit  "></i>
                                        </span>

                                    </template>
                                </Dropdown>
                            </td>

                        </tr>

                        <tr v-if="localEntries.length === 0">
	                            <td colspan="12" class="table-cell text-center text-gray-500">
                                Noch keine Fahrten erfasst.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <!-- MODAL: CREATE -->
 <ModalCreate
    :visible="showCreate"
    :vehicles="vehicles"
    :drivers="drivers"
    :selectedVehicle="selectedVehicle"
    :selectedVehicleId="selectedVehicleId"
    @update:visible="showCreate = $event"
    @close="showCreate = false"
/>


    <!-- MODAL: EDIT -->
<ModalEdit
    v-if="selectedEntry"
    :visible="showEdit"
    :vehicles="vehicles"
    :drivers="drivers"
    :entry="selectedEntry"
    @update:visible="showEdit = $event"
    @close="showEdit = false"
/>

<ModalDestroy
    v-if="showModalLöschen"
    :toDelete="fahrtToDelete"
    seite="dienstwagen.fahrtenbuch"
    @delete="handleDelete"
    @close="showModalLöschen = false"
/>

</template>

<style scoped>
.table-head {
    @apply px-4 py-2 text-sm font-semibold uppercase tracking-wide;
}
.table-cell {
    @apply px-4 py-2 text-gray-800 dark:text-gray-200;
}
</style>

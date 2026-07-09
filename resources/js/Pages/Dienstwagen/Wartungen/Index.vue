<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import { formatDate } from '@/utils/dateFormat.js';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import axios from 'axios';

const props = defineProps({
    records: Array,
    vehicles: Array
});

// 🔹 Lokale Kopie der Datensätze
const localRecords = ref([...props.records]);
watch(() => props.records, (value) => {
    localRecords.value = [...value];
});

// 🔹 Sichtbarkeiten
const seite = 'dienstwagen.wartung';
const showModalLöschen = ref(false);
const showModal = ref(false);
const dienstwagenwartungToDelete = ref(null);

// 🔹 Bearbeitungsstatus
const isEditing = ref(false);
const editId = ref(null);

// 🔹 Formular
const form = ref({
    dienstwagen_id: "",
    art: "",
    datum: "",
    kilometerstand: "",
    werkstatt: "",
    kosten: "",
    notizen: "",
});

const servicetypen = ref([
    { label: 'Inspektion', value: 'Inspektion' },
    { label: 'Reparatur', value: 'Reparatur' },
    { label: 'Ölwechsel', value: 'Ölwechsel' },
    { label: 'Reifenwechsel', value: 'Reifenwechsel' },
    { label: 'Sonstiges', value: 'Sonstiges' },
]);

// 🔹 Öffnet Modal für Neuen Eintrag
function openModalCreate() {
    isEditing.value = false;
    editId.value = null;
    form.value = {
        dienstwagen_id: "",
        art: "",
        datum: "",
        kilometerstand: "",
        werkstatt: "",
        kosten: "",
        notizen: "",
    };
    showModal.value = true;
}

// 🔹 Öffnet Modal zum Bearbeiten
function openModalEdit(record) {
    isEditing.value = true;
    editId.value = record.id;

    form.value = {
        dienstwagen_id: record.dienstwagen_id,
        art: record.art,
        datum: record.datum,
        kilometerstand: record.kilometerstand,
        werkstatt: record.werkstatt,
        kosten: record.kosten,
        notizen: record.notizen,
    };
    showModal.value = true;
}


// 🟢 Neuer Eintrag mit direktem Hinzufügen
const submit = async () => {
  if (isEditing.value && editId.value) {
    router.put(route('dienstwagen.wartung.update', editId.value), form.value, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Aktualisiert!',
          text: 'Wartungseintrag erfolgreich aktualisiert!',
          timer: 2000,
          showConfirmButton: false,
        });
        showModal.value = false;
        router.reload({ only: ['records'] });
      },
      onError: () => Swal.fire('Fehler', 'Aktualisieren fehlgeschlagen', 'error'),
    });
    return;
  }

  try {
    const response = await axios.post(route('dienstwagen.wartung.store'), form.value);

    if (response.data.success) {
      localRecords.value.unshift(response.data.record);

      Swal.fire({
        icon: 'success',
        title: 'Gespeichert!',
        text: 'Wartungseintrag erfolgreich hinzugefügt!',
        timer: 2000,
        showConfirmButton: false,
      });

      showModal.value = false;
      form.value = {
        dienstwagen_id: '',
        art: '',
        datum: '',
        kilometerstand: '',
        werkstatt: '',
        kosten: '',
        notizen: '',
      };
    }
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};


// 🔹 Lösch-Modal öffnen
const confirmDelete = (record) => {
    dienstwagenwartungToDelete.value = {
        name: `${record.art} (${record.dienstwagen.kennzeichen})`,
        id: record.id
    };
    showModalLöschen.value = true;
};

// 🔹 Nach Löschung lokale Liste aktualisieren
const handleDelete = (id) => {
    localRecords.value = localRecords.value.filter(item => item.id !== id);
    showModalLöschen.value = false;

    Swal.fire({
        icon: 'success',
        title: 'Gelöscht!',
        text: 'Der Wartungseintrag wurde erfolgreich entfernt.',
        timer: 1500,
        showConfirmButton: false,
    });
};
</script>

<template>
    <Head title="Dienstwagenverwaltung" />

    <AppLayout>
        <template #header>🚗 {{ $t('Wartungsmanagement') }}</template>

        <div class="space-y-10">
            <!-- TABLE CARD -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        📋 Wartungshistorie
                    </h2>
                    <button
                        @click="openModalCreate"
                        class="bg-zbb hover:bg-orange-300 text-white px-4 py-2 rounded-lg font-semibold transition shadow-md"
                    >
                        ➕ Neuer Eintrag
                    </button>
                </div>

                <div>
                    <table class="w-full min-h-60 text-left border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="table-head">Datum</th>
                                <th class="table-head">Fahrzeug</th>
                                <th class="table-head">Art</th>
                                <th class="table-head">km</th>
                                <th class="table-head">Werkstatt</th>
                                <th class="table-head">Kosten</th>
                                <th class="table-head w-24 text-center">*</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in localRecords"
                                :key="r.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-900 transition"
                            >
                                <td class="table-cell">{{ formatDate(r.datum) }}</td>
                                <td class="table-cell font-semibold">{{ r.dienstwagen.kennzeichen }}</td>
                                <td class="table-cell">{{ r.art }}</td>
                                <td class="table-cell">{{ r.kilometerstand?.toLocaleString() }} km</td>
                                <td class="table-cell">{{ r.werkstatt || '-' }}</td>
                                <td class="table-cell">{{ r.kosten ? r.kosten + ' €' : '-' }}</td>
                                <td class="border-gray-300 px-6 py-4 text-center m-auto">
                                    <Dropdown>
                                        <template #trigger>
                                            <button
                                                class="items-center text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150"
                                            >
                                                <i class="transform transition-transform duration-300 la la-ellipsis-v la-lg"></i>
                                            </button>
                                        </template>

                                        <template #content>
                                            <span
                                                class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                                                @click="openModalEdit(r)"
                                            >
                                                {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                            </span>
                                            <span
                                                class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                                                @click="confirmDelete(r)"
                                            >
                                                {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                            </span>
                                        </template>
                                    </Dropdown>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL: ERSTELLEN / BEARBEITEN -->
            <Dialog
                v-model:visible="showModal"
                modal
                :header="isEditing ? '📝 Wartungseintrag bearbeiten' : '🧰 Neuer Wartungseintrag'"
                :style="{ width: '45rem' }"
                class="modern-modal"
            >
                <form @submit.prevent="submit" class="grid md:grid-cols-2 gap-5 mt-2">
                    <FloatLabel variant="on">
                        <Select
                            v-model="form.art"
                            :options="servicetypen"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <label>Art der Wartung</label>
                    </FloatLabel>

                    <FloatLabel variant="on">
                        <Select
                            v-model="form.dienstwagen_id"
                            :options="vehicles.map(v => ({
                                label: `${v.kennzeichen} – ${v.marke} ${v.modell}`,
                                value: v.id
                            }))"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <label>Fahrzeug wählen</label>
                    </FloatLabel>

                    <FloatLabel variant="on">
                        <DatePicker v-model="form.datum" dateFormat="yy-mm-dd" class="w-full" showIcon />
                        <label>Datum</label>
                    </FloatLabel>

                    <FloatLabel variant="on">
                        <InputText v-model="form.kilometerstand" class="w-full" />
                        <label>Kilometerstand*</label>
                    </FloatLabel>

                    <FloatLabel variant="on">
                        <InputText v-model="form.werkstatt" class="w-full" />
                        <label>Werkstatt*</label>
                    </FloatLabel>

                    <FloatLabel variant="on">
                        <InputText v-model="form.kosten" class="w-full" />
                        <label>Kosten (€)*</label>
                    </FloatLabel>

                    <FloatLabel variant="on" class="col-span-2">
                        <Textarea v-model="form.notizen" class="w-full" />
                        <label>Notizen*</label>
                    </FloatLabel>

                    <div class="col-span-2 flex justify-end gap-3 mt-4">
                        <button type="button" class="hover:bg-gray-50 border border-zbb px-5 py-2 rounded-lg" @click="showModal = false">Abbrechen</button>
                        <button type="submit" class="bg-zbb hover:bg-orange-300 text-white font-semibold px-5 py-2 rounded-lg transition">
                            {{ isEditing ? '💾 Änderungen speichern' : '💾 Speichern' }}
                        </button>
                    </div>
                </form>
            </Dialog>
        </div>

        <!-- MODAL: LÖSCHEN -->
        <ModalDestroy
            v-if="showModalLöschen"
            @delete="handleDelete"
            @close="showModalLöschen = false"
            :seite="seite"
            :toDelete="dienstwagenwartungToDelete"
        />
    </AppLayout>
</template>

<style scoped>
.table-head {
    @apply px-4 py-2 text-sm font-semibold uppercase tracking-wide;
}
.table-cell {
    @apply px-4 py-2 text-gray-800 dark:text-gray-200;
}
:deep(.p-dialog) {
    border-radius: 1rem !important;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.8);
}
:deep(.dark .p-dialog) {
    background: rgba(17, 24, 39, 0.8);
}
</style>

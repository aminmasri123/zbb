<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    costs: Array,
    vehicles: Array,
});

// 🔹 Lokale Kopie der Kostendaten
const localCosts = ref([...props.costs]);

// 🔹 Modal & Status
const showModal = ref(false);
const showModalLöschen = ref(false);
const costToDelete = ref(null);
const isEditing = ref(false);
const editId = ref(null);

// 🔹 FORMULAR — AN DEINE FELDER ANGEPASST
const form = ref({
    dienstwagen_id: "",
    art: "",
    datum: "",
    betrag: "",
    beschreibung: "",
});

// 🔹 Neuer Eintrag
function openModalCreate() {
    isEditing.value = false;
    editId.value = null;

    form.value = {
        dienstwagen_id: "",
        art: "",
        datum: "",
        betrag: "",
        beschreibung: "",
    };

    showModal.value = true;
}

// 🔹 Bearbeiten
function openModalEdit(record) {
    isEditing.value = true;
    editId.value = record.id;

    form.value = {
        dienstwagen_id: record.dienstwagen_id,
        art: record.art,
        datum: record.datum,
        betrag: record.betrag,
        beschreibung: record.beschreibung,
    };

    showModal.value = true;
}

// 🔹 Speichern / Update
function submit() {
    if (isEditing.value && editId.value) {
        // UPDATE
        router.put(route("fleet.costs.update", editId.value), form.value, {
            onSuccess: () => {
                Swal.fire({
                    icon: "success",
                    title: "Aktualisiert!",
                    text: "Der Kosteneintrag wurde aktualisiert.",
                    timer: 2000,
                    showConfirmButton: false,
                });

                const index = localCosts.value.findIndex(r => r.id === editId.value);

                if (index !== -1) {
                    const vehicle = props.vehicles.find(v => v.id === form.value.dienstwagen_id);

                    localCosts.value[index] = {
                        ...localCosts.value[index],
                        ...form.value,
                        vehicle: vehicle ?? localCosts.value[index].vehicle,
                    };

                    localCosts.value = [...localCosts.value];
                }

                showModal.value = false;
            },
        });

    } else {
        // CREATE
        router.post(route("dienstwagen.kosten.store"), form.value, {
            onSuccess: () => {
                Swal.fire({
                    icon: "success",
                    title: "Gespeichert!",
                    text: "Kosteneintrag wurde hinzugefügt.",
                    timer: 2000,
                    showConfirmButton: false,
                });

                showModal.value = false;

                form.value = {
                    dienstwagen_id: "",
                    art: "",
                    datum: "",
                    betrag: "",
                    beschreibung: "",
                };
            },
        });
    }
}

// 🔹 Löschen
const confirmDelete = (record) => {
    costToDelete.value = {
        name: `${record.art} (${record.dienstwagen.kennzeichen})`,
        id: record.id
    };
    showModalLöschen.value = true;
};

const handleDelete = (id) => {
    localCosts.value = localCosts.value.filter(item => item.id !== id);

    showModalLöschen.value = false;

    Swal.fire({
        icon: 'success',
        title: 'Gelöscht!',
        text: 'Kosteneintrag wurde entfernt.',
        timer: 1500,
        showConfirmButton: false,
    });
};

// 🔹 SUMME — angepasst auf betrag
const totalAmount = computed(() =>
    localCosts.value.reduce((sum, c) => sum + parseFloat(c.betrag || 0), 0).toFixed(2)
);
</script>

<template>
    <Head title="Kostenverwaltung" />

    <AppLayout>
        <template #header>💰 Kostenverwaltung</template>

        <div class="space-y-10">

            <!-- SUMME -->
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">
                    Gesamtkosten:
                    <span class="text-blue-600">{{ totalAmount }} €</span>
                </h2>

                <button
                    @click="openModalCreate"
                    class="bg-zbb hover:bg-orange-300 text-white px-4 py-2 rounded-lg font-semibold shadow-md"
                >
                    ➕ Neuer Eintrag
                </button>
            </div>

            <!-- TABELLE -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border p-6">

                <table class="w-full text-left">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="table-head">Datum</th>
                            <th class="table-head">Fahrzeug</th>
                            <th class="table-head">Art</th>
                            <th class="table-head">Betrag</th>
                            <th class="table-head">Beschreibung</th>
                            <th class="table-head text-center">*</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="c in localCosts"
                            :key="c.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900 transition"
                        >
                            <td class="table-cell">{{ c.datum }}</td>

                            <td class="table-cell font-semibold">
                                {{ c.dienstwagen.kennzeichen }}
                            </td>

                            <td class="table-cell">{{ c.art }}</td>

                            <td class="table-cell">{{ c.betrag }} €</td>

                            <td class="table-cell">{{ c.beschreibung || '-' }}</td>

                            <td class="table-cell text-center">
                                <Dropdown>
                                    <template #trigger>
                                        <button class="text-gray-500 hover:text-gray-700">
                                            <i class="la la-ellipsis-v la-lg"></i>
                                        </button>
                                    </template>

                                    <template #content>
                                        <span
                                            class="flex justify-between py-1 px-6 hover:bg-gray-100 cursor-pointer"
                                            @click="openModalEdit(c)"
                                        >
                                            Bearbeiten <i class="las la-edit"></i>
                                        </span>

                                        <span
                                            class="flex justify-between py-1 px-6 hover:bg-gray-100 cursor-pointer"
                                            @click="confirmDelete(c)"
                                        >
                                            Löschen <i class="las la-trash-alt"></i>
                                        </span>
                                    </template>
                                </Dropdown>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>

            <!-- MODAL: FORMULAR -->
            <Dialog
                v-model:visible="showModal"
                modal
                :header="isEditing ? '📝 Kosteneintrag bearbeiten' : '💰 Neuer Kosteneintrag'"
                :style="{ width: '40rem' }"
            >
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-5 mt-2">

                    <!-- Fahrzeug -->
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
                        <label>Fahrzeug</label>
                    </FloatLabel>

                    <!-- Art -->
                    <FloatLabel variant="on">
                        <Select
                            v-model="form.art"
                            :options="[
                                { label: 'Kraftstoff', value: 'Kraftstoff' },
                                { label: 'Reparatur', value: 'Reparatur' },
                                { label: 'Versicherung', value: 'Versicherung' },
                                { label: 'Leasing', value: 'Leasing' },
                                { label: 'Steuern', value: 'Steuern' },
                                { label: 'Sonstiges', value: 'Sonstiges' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <label>Kostenart</label>
                    </FloatLabel>

                    <!-- Datum -->
                    <FloatLabel variant="on">
                        <DatePicker v-model="form.datum" dateFormat="yy-mm-dd" showIcon class="w-full" />
                        <label>Datum</label>
                    </FloatLabel>

                    <!-- Betrag -->
                    <FloatLabel variant="on">
                        <InputText v-model="form.betrag" type="number" class="w-full" />
                        <label>Betrag (€)</label>
                    </FloatLabel>

                    <!-- Beschreibung -->
                    <FloatLabel variant="on" class="col-span-2">
                        <Textarea v-model="form.beschreibung" class="w-full" />
                        <label>Beschreibung</label>
                    </FloatLabel>

                    <!-- Buttons -->
                    <div class="col-span-2 flex justify-end gap-3 mt-4">
                        <button
                            type="button"
                            class="border border-zbb px-5 py-2 rounded-lg"
                            @click="showModal = false"
                        >
                            Abbrechen
                        </button>

                        <button
                            type="submit"
                            class="bg-zbb text-white px-5 py-2 rounded-lg"
                        >
                            {{ isEditing ? '💾 Änderungen speichern' : '💾 Speichern' }}
                        </button>
                    </div>

                </form>
            </Dialog>

            <!-- LÖSCHEN -->
            <ModalDestroy
                v-if="showModalLöschen"
                @delete="handleDelete"
                @close="showModalLöschen = false"
                seite="fleet.costs"
                :toDelete="costToDelete"
            />

        </div>
    </AppLayout>
</template>

<style scoped>
.table-head {
    @apply px-4 py-2 text-sm font-semibold uppercase tracking-wide;
}
.table-cell {
    @apply px-4 py-2 text-gray-800 dark:text-gray-200;
}
</style>

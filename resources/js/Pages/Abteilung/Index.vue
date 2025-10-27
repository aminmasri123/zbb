<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, defineProps, watch } from 'vue';
import Swal from 'sweetalert2';
import { router, Link, Head } from '@inertiajs/vue3';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Abteilung/ModalCreate.vue';
import ModalEdit from '@/Pages/Abteilung/ModalEdit.vue';

// Props definieren
const props = defineProps({
    abteilungen: Object,
    users: Object,
    filters: Object
});

let seite = 'abteilung';
let search = ref(props.filters?.search || '');
let abteilungToDelete = ref(null);
let showModalLöschen = ref(false);
let abteilungToEdit = ref(null);
let isModalEditOpen = ref(false);
let isModalOpen = ref(false);

// Lokale Kopien erstellen
let localAbteilungen = ref([...props.abteilungen.data]);
let filteredAbteilungen = ref([...localAbteilungen.value]);

// --- 🔁 Abteilungen regelmäßig aktualisieren ---
/*
*/
// --- 🔍 Suche ---
const applySearchFilter = () => {
    const query = search.value.toLowerCase();
    filteredAbteilungen.value = query
        ? localAbteilungen.value.filter(a => a.name.toLowerCase().includes(query))
        : [...localAbteilungen.value];
};

watch(search, (newVal) => {
    router.get('/abteilung', { search: newVal }, { preserveState: true, replace: true });
    applySearchFilter();
});

// --- 🗑️ Löschen ---
const confirmDelete = (abteilung) => {
    abteilungToDelete.value = { id: abteilung.id, name: abteilung.name };
    showModalLöschen.value = true;
};

const handleDelete = (abteilungId) => {
    localAbteilungen.value = localAbteilungen.value.filter(a => a.id !== abteilungId);
    showModalLöschen.value = false;
};

// --- ✏️ Bearbeiten ---
const openModalEdit = (abteilung) => {
    abteilungToEdit.value = abteilung;
    isModalEditOpen.value = true;
};

const closeModalEdit = () => {
    isModalEditOpen.value = false;
};

// --- ➕ Neue Abteilung ---
let newAbteilung = ref({
    name: '',
    abteilungsleiter: '',
    assistenten: []
});

const resetForm = () => {
    newAbteilung.value = { name: '', abteilungsleiter: '', assistenten: [] };
};

const openModal = () => { isModalOpen.value = true; };
const closeModal = () => { isModalOpen.value = false; resetForm(); };

const addAbteilung = async () => {
    if (!newAbteilung.value.name || !newAbteilung.value.abteilungsleiter) {
        Swal.fire('Fehler!', 'Bitte füllen Sie alle Pflichtfelder aus.', 'error');
        return;
    }

    try {
        const response = await axios.post(route('abteilung.store'), newAbteilung.value);
        localAbteilungen.value.unshift(response.data.abteilung);
        applySearchFilter();
        Swal.fire('Erfolg!', 'Abteilung erfolgreich angelegt!', 'success');
        closeModal();
    } catch (error) {
        Swal.fire('Fehler!', error.response?.data?.message || 'Erstellen fehlgeschlagen.', 'error');
    }
};
</script>


<template>
    <Head title="Abteilung" />
    <AppLayout>
        <template #header>{{ $t('abteilungen') }}</template>

        <!-- Suchfeld & Buttons -->
        <div class="flex justify-around items-center mb-3">
            <button @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 hover:text-white hover:bg-zbb hover:border-orange-500"></i>
            </button>

            <input
                v-model="search"
                type="text"
                class="border border-gray-300 text-gray-900 text-sm focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                placeholder="Suchen..."
            />

            <Link :href="route('abteilung.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 hover:text-white hover:bg-zbb hover:border-orange-500"></i>
            </Link>
        </div>

        <!-- Tabelle -->
        <div class="relative overflow-x-auto mb-10">
            <table class="w-full text-sm text-left text-gray-500 shadow-sm">
                <thead class="text-md text-gray-600 uppercase bg-gray-200">
                    <tr class="font-bold">
                        <th class="border px-6 py-3 text-center">ID</th>
                        <th class="border px-6 py-3">Abteilung</th>
                        <th class="border px-6 py-3">Abteilungsleiter</th>
                        <th class="border px-6 py-3 text-center">*</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="abteilung in filteredAbteilungen" :key="abteilung.id" class="bg-white border">
                        <td class="border px-6 py-4 text-center">{{ abteilung.id }}</td>
                        <td class="border px-6 py-4">{{ abteilung.name }}</td>
                        <td class="border px-6 py-4">
                            <p>{{ abteilung.personen?.vorname }} {{ abteilung.personen?.nachname }}</p>
                            <span v-for="assist in abteilung.abteilungsassistente" :key="assist.id">
                                <span v-for="perso in assist.personen" :key="perso.id" class="text-xs bg-orange-200 rounded p-1 mr-2" >
                                    {{ perso.vorname }} {{ perso.nachname }}
                                </span>
                            </span>
                        </td>
                        <td class="border px-6 py-4 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <button>
                                        <i class="la la-ellipsis-v la-lg"></i>
                                    </button>
                                </template>
                                <template #content>
                                    <span class="flex justify-between px-6 cursor-pointer" @click="confirmDelete(abteilung)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                    <span class="flex justify-between px-6 cursor-pointer" @click="openModalEdit(abteilung)">
                                        {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                    </span>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modals -->
        <ModalCreate
            :visible="isModalOpen"
            :users="users"
            @close="closeModal"
            @add-abteilung="addAbteilung"
        />

        <ModalEdit
            :visible="isModalEditOpen"
            :users="users"
            :toEdit="abteilungToEdit"
            @close="closeModalEdit"
            @updated="updatedAbteilung => {
                const index = localAbteilungen.value.findIndex(a => a.id === updatedAbteilung.id);
                if (index !== -1) localAbteilungen.value[index] = updatedAbteilung;
                applySearchFilter();
            }"
        />

        <ModalDestroy
            v-if="showModalLöschen"
            @delete="handleDelete"
            @close="showModalLöschen = false"
            :seite="seite"
            :toDelete="abteilungToDelete"
        />
    </AppLayout>
</template>

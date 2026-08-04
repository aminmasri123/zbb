<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, defineProps, watch } from 'vue';
import Swal from 'sweetalert2';
import { router, Link, Head } from '@inertiajs/vue3';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Abteilung/ModalCreate.vue';
import ModalEdit from '@/Pages/Abteilung/ModalEdit.vue';
import { usePermissions } from '@/utils/permissions';

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
const { can } = usePermissions();
const canCreateAbteilung = computed(() => can('abteilung.store'));
const canUpdateAbteilung = computed(() => can('abteilung.update'));
const canDeleteAbteilung = computed(() => can('abteilung.destroy'));
const canManageAbteilung = computed(() => canUpdateAbteilung.value || canDeleteAbteilung.value);

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
    if (!canDeleteAbteilung.value) return;
    abteilungToDelete.value = { id: abteilung.id, name: abteilung.name };
    showModalLöschen.value = true;
};

const handleDelete = (abteilungId) => {
    localAbteilungen.value = localAbteilungen.value.filter(a => a.id !== abteilungId);
    showModalLöschen.value = false;
};

// --- ✏️ Bearbeiten ---
const openModalEdit = (abteilung) => {
    if (!canUpdateAbteilung.value) return;
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

const openModal = () => {
    if (!canCreateAbteilung.value) return;
    isModalOpen.value = true;
};
const closeModal = () => { isModalOpen.value = false; resetForm(); };

const addAbteilung = async () => {
    if (!canCreateAbteilung.value) return;

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
        <div class="flex items-stretch mb-3 overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
            <button
                v-if="canCreateAbteilung"
                type="button"
                @click="openModal"
                class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Abteilung anlegen"
            >
                <i class="la la-plus"></i>
            </button>

            <input
                v-model="search"
                type="text"
                class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                placeholder="Suchen..."
            />

            <Link
                :href="route('abteilung.index')"
                class="flex w-14 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Aktualisieren"
            >
                <i class="la la-refresh"></i>
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
                        <th v-if="canManageAbteilung" class="border px-6 py-3 text-center">*</th>
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
                        <td v-if="canManageAbteilung" class="border px-6 py-4 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <button>
                                        <i class="la la-ellipsis-v la-lg"></i>
                                    </button>
                                </template>
                                <template #content>
                                    <span v-if="canDeleteAbteilung" class="flex justify-between px-6 cursor-pointer" @click="confirmDelete(abteilung)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                    <span v-if="canUpdateAbteilung" class="flex justify-between px-6 cursor-pointer" @click="openModalEdit(abteilung)">
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
            v-if="canCreateAbteilung"
            :visible="isModalOpen"
            :users="users"
            @close="closeModal"
            @add-abteilung="addAbteilung"
        />

        <ModalEdit
            v-if="canUpdateAbteilung"
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

        <template v-if="canDeleteAbteilung">
        <ModalDestroy
            v-if="showModalLöschen"
            @delete="handleDelete"
            @close="showModalLöschen = false"
            :seite="seite"
            :toDelete="abteilungToDelete"
        />
        </template>
    </AppLayout>
</template>

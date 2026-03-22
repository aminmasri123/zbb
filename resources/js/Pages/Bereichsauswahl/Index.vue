<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed, defineProps } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Bereich/ModalCreate.vue';
import ModalEdit from '@/Pages/Bereich/ModalEdit.vue';
import Bereichewaelen from './Bereichewaelen.vue';
const props = defineProps({
    projekt: Object,
    alle_teilnehmer: Array,
});

// Reactive Variablen
let search = ref('');
let localBereiche = ref([...props.projekt.bereiche]);
let filteredBereiche = ref([...localBereiche.value]);

let bereichToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let bereichToEdit = ref(null);

let newBereich = ref({
    name: '',
    beschreibung: '',
});

// Modal-Funktionen
const openModalCreate = () => isModalCreateOpen.value = true;
const closeModalCreate = () => isModalCreateOpen.value = false;

const openModalEdit = (bereich) => {
    bereichToEdit.value = bereich;
    isModalEditOpen.value = true;
};
const closeModalEdit = () => isModalEditOpen.value = false;

// Bereich hinzufügen
const addBereich = async (data) => {
    try {
        const response = await axios.post(route('bereich.store'), data);
        localBereiche.value.unshift(response.data.bereich);
        Swal.fire({ title: 'Erfolg!', text: 'Bereich angelegt', icon: 'success', timer: 2000 });
        closeModalCreate();
    } catch (error) {
        Swal.fire({ title: 'Fehler!', text: error.response?.data?.message || 'Fehler beim Erstellen', icon: 'error', timer: 3000 });
    }
};

// Bereich aktualisieren
const updateBereich = (updatedBereich) => {
    const index = localBereiche.value.findIndex(b => b.id === updatedBereich.id);
    if(index !== -1) localBereiche.value[index] = updatedBereich;
    applySearchFilter();
};

// Bereich löschen
const confirmDelete = (bereich) => {
    bereichToDelete.value = { id: bereich.id, name: bereich.name };
    showModalLöschen.value = true;
};
const handleDelete = (bereichId) => {
    localBereiche.value = localBereiche.value.filter(b => b.id !== bereichId);
    showModalLöschen.value = false;
};

// Suchfilter
const applySearchFilter = () => {
    if(search.value) {
        filteredBereiche.value = localBereiche.value.filter(b =>
            b.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredBereiche.value = [...localBereiche.value];
    }
};
watch(search, () => {
    router.get('/bereich', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
});
</script>

<template>
<Head title="Bereichsauswahl" />
<AppLayout>
    <template #header>Bereichsauswahl</template>

    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-3">
        <button @click="openModalCreate" class="btn bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="las la-plus mr-2"></i> Neuer Bereich
        </button>

        <input v-model="search" type="text" placeholder="Suchen ..." class="border p-2 rounded w-1/3" />
    </div>

    <Bereichewaelen :alle_teilnehmer="alle_teilnehmer" :alle_bereiche="projekt.bereiche"
    />
    <!-- Modals -->
    <ModalCreate :visible="isModalCreateOpen" @close="closeModalCreate" @add-bereich="addBereich" />
    <ModalEdit :visible="isModalEditOpen" :toEdit="bereichToEdit" @close="closeModalEdit" @updated="updateBereich" />
    <ModalDestroy v-if="showModalLöschen" :toDelete="bereichToDelete" :seite="'bereich'" @delete="handleDelete" @close="showModalLöschen=false" />
</AppLayout>
</template>

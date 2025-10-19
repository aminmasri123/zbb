

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, defineProps, watch } from 'vue';
import Swal from 'sweetalert2';
import { router, Link, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Gruppe/ModalCreate.vue';
import ModalEdit from '@/Pages/Gruppe/ModalEdit.vue';

let seite = 'gruppe';
let search = ref('');
let gruppeToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let gruppeToEdit = ref(null);

// Props
const props = defineProps({
    gruppen: Object,
    abteilungen: Object
});

// Lokale Liste
let localGruppen = ref([...props.gruppen.data]);
let filteredGruppen = ref([...localGruppen.value]);

// Modals
const openModalCreate = () => { isModalCreateOpen.value = true; };
const closeModalCreate = () => { isModalCreateOpen.value = false; };

const openModalEdit = (gruppe) => {
    gruppeToEdit.value = gruppe;
    isModalEditOpen.value = true;
};
const closeModalEdit = () => { isModalEditOpen.value = false; };

// CRUD
const addGruppe = (gruppe) => {
    localGruppen.value.unshift(gruppe);
    applySearchFilter();
};

const updateGruppe = (updatedGruppe) => {
    const index = localGruppen.value.findIndex(g => g.id === updatedGruppe.id);
    if (index !== -1) {
        localGruppen.value[index] = updatedGruppe;
    }
    applySearchFilter();
};

// Delete
const confirmDelete = (gruppe) => {
    gruppeToDelete.value = { id: gruppe.id, name: gruppe.name };
    showModalLöschen.value = true;
};
const handleDelete = (gruppeId) => {
    localGruppen.value = localGruppen.value.filter(g => g.id !== gruppeId);
    applySearchFilter();
    showModalLöschen.value = false;
};

// Suche
const applySearchFilter = () => {
    if (search.value) {
        filteredGruppen.value = localGruppen.value.filter(g =>
            g.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredGruppen.value = [...localGruppen.value];
    }
};
watch([search], () => {
    router.get('/gruppe', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
});
</script>

<template>
    <Head title="Gruppen" />

    <app-layout>
        <template #header>{{$t('Gruppen')}}</template>

        <!-- Toolbar -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModalCreate" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>
            <input v-model="search" type="text"
                         class="border border-gray-300 text-sm p-2.5 w-full"
                         placeholder="Suchen ..." />
            <Link :href="route('gruppe.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

        <!-- Tabelle -->
        <div class="w-full overflow-x-auto">
            <table class="min-w-[600px] w-full text-sm shadow-sm border-collapse">
                <thead class="text-md text-gray-600 uppercase bg-gray-200 sticky top-0">
                    <tr>
                        <th class="border px-3 py-3 text-left">ID</th>
                        <th class="border px-3 py-3 text-left">Gruppenname</th>
                        <th class="border px-3 py-3 text-left">Abteilung</th>
                        <th class="border px-3 py-3 text-left">Mitglieder</th>
                        <th class="border px-3 py-3 text-center">*</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="gruppe in filteredGruppen" :key="gruppe.id" class="bg-white border hover:bg-gray-50">
                        <td class="border px-6 py-4">{{ gruppe.id }}</td>
                        <td class="border px-6 py-4">{{ gruppe.name }}</td>
                        <td class="border px-6 py-4">{{ gruppe.abteilung?.name }}</td>
                        <td class="border px-6 py-4">
                            <span v-for="mitglied in gruppe.mitglieder" :key="mitglied.id" class="bg-zbb mx-1 p-1 rounded text-white">
                                {{ mitglied.name }}
                            </span>
                        </td>
                        <td class="border px-6 py-4 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <i class="la la-ellipsis-v cursor-pointer"></i>
                                </template>
                                <template #content>
                                    <span
                                        class="flex justify-between cursor-pointer px-6 items-center hover:bg-gray-100"
                                        @click="openModalEdit(gruppe)"
                                    >
                                        Bearbeiten <i class="las la-edit"></i>
                                    </span>
                                    <span
                                        class="flex justify-between cursor-pointer px-6 items-center hover:bg-gray-100"
                                        @click="confirmDelete(gruppe)"
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

        <!-- Modals -->
        <ModalCreate :visible="isModalCreateOpen"
                                 :abteilungen="props.abteilungen"
                                 @close="isModalCreateOpen = false"
                                 @added="(gruppe) => { localGruppen.unshift(gruppe); applySearchFilter(); }"
        />
        <ModalEdit :visible="isModalEditOpen"
                             :toEdit="gruppeToEdit"
                             :abteilungen="props.abteilungen"
                             @close="closeModalEdit"
                             @updated="updateGruppe"/>
        <ModalDestroy v-if="showModalLöschen"
                                    @delete="handleDelete"
                                    @close="showModalLöschen = false"
                                    :seite="seite"
                                    :toDelete="gruppeToDelete"/>
    </app-layout>
</template>


<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Projekt/ModalCreate.vue';
    import ModalEdit from '@/Pages/Projekt/ModalEdit.vue';

    let seite = 'projekt';
    let search = ref('');
    let projektToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let projektToEdit = ref(null);

    // Props
    const props = defineProps({
    projekte: Object,
    abteilungen: Object
    });

    console.log(props.projekte)
    // Lokale Liste
    let localProjekte = ref([...props.projekte.data]);
    let filteredProjekte = ref([...localProjekte.value]);

    // Formatierung für Datum
    const formatDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('de-DE');
    };

    // Modals
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (projekt) => {
    projektToEdit.value = projekt;
    isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addProjekt = (projekt) => {
    localProjekte.value.unshift(projekt);
    applySearchFilter();
    };

    const updateProjekt = (updatedProjekt) => {
    const index = localProjekte.value.findIndex(p => p.id === updatedProjekt.id);
    if (index !== -1) {
        localProjekte.value[index] = updatedProjekt;
    }
    applySearchFilter();
    };


    // Delete
    const confirmDelete = (projekt) => {
    projektToDelete.value = { id: projekt.id, name: projekt.name };
    showModalLöschen.value = true;
    };
    const handleDelete = (projektId) => {
    localProjekte.value = localProjekte.value.filter(p => p.id !== projektId);
    applySearchFilter();
    showModalLöschen.value = false;
    };

    // Suche
    const applySearchFilter = () => {
    if (search.value) {
        filteredProjekte.value = localProjekte.value.filter(p =>
        p.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredProjekte.value = [...localProjekte.value];
    }
    };
    watch([search], () => {
    router.get('/projekt', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
    });
</script>


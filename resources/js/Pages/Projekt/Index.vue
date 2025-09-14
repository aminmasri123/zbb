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

<template>
  <Head title="Projekte" />

  <app-layout>
    <template #header>Projekte</template>

    <!-- Toolbar -->
    <div class="flex justify-around items-center mb-3">
      <div @click="openModalCreate" class="flex items-center">
        <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </div>
      <input v-model="search" type="text"
             class="border border-gray-300 text-sm p-2.5 w-full"
             placeholder="Suchen ..." />
      <Link :href="route('projekt.index')" class="flex items-center">
        <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </Link>
    </div>

    <!-- Tabelle -->
    <div class="relative overflow-x-auto mb-10">
      <table class="w-full text-sm table-auto shadow-sm">
        <thead class="text-md text-gray-600 uppercase bg-gray-200">
          <tr>
            <th class="border px-6 py-3">ID</th>
            <th class="border px-6 py-3">Projekt</th>
            <th class="border px-6 py-3">Kostenstelle</th>
            <th class="border px-6 py-3">Abteilung</th>
            <th class="border px-6 py-3">Antragsdatum</th>
            <th class="border px-6 py-3">Starttermin</th>
            <th class="border px-6 py-3">Anfangsdatum</th>
            <th class="border px-6 py-3">Endtermin</th>
            <th class="border px-6 py-3">Enddatum</th>
            <th class="border px-6 py-3">*</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="projekt in filteredProjekte" :key="projekt.id" class="bg-white border">
            <td class="border px-6 py-4">{{ projekt.id }}</td>
            <td class="border px-6 py-4">{{ projekt.name }}</td>
            <td class="border px-6 py-4">{{ projekt.kostenstelle }}</td>
            <td class="border px-6 py-4">{{ projekt.abteilung?.name }}</td>
            <td class="border px-6 py-4">
              <div v-for="zeit in projekt.projektzeitraume" :key="zeit.id">
                {{ formatDate(zeit.antragsdatum) }}
              </div>
            </td>
            <td class="border px-6 py-4">
              <div v-for="zeit in projekt.projektzeitraume" :key="zeit.id">
                {{ formatDate(zeit.starttermin) }}
              </div>
            </td>
            <td class="border px-6 py-4">
              <div v-for="zeit in projekt.projektzeitraume" :key="zeit.id">
                {{ formatDate(zeit.anfangsdatum) }}
              </div>
            </td>
            <td class="border px-6 py-4">
              <div v-for="zeit in projekt.projektzeitraume" :key="zeit.id">
                {{ formatDate(zeit.endtermin) }}
              </div>
            </td>
            <td class="border px-6 py-4">
              <div v-for="zeit in projekt.projektzeitraume" :key="zeit.id">
                {{ formatDate(zeit.enddatum) }}
              </div>
            </td>
            <td class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span class="flex justify-between cursor-pointer px-6 items-center"
                        @click="openModalEdit(projekt)">
                    Bearbeiten <i class="las la-edit"></i>
                  </span>
                  <span class="flex justify-between cursor-pointer px-6 items-center"
                        @click="confirmDelete(projekt)">
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
                 @added="(projekt) => { localProjekte.unshift(projekt); applySearchFilter(); }"
                 />
    <ModalEdit :visible="isModalEditOpen"
               :toEdit="projektToEdit"
               :abteilungen="props.abteilungen"
               @close="closeModalEdit"
               @updated="updateProjekt"/>
    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="projektToDelete"/>
  </app-layout>
</template>

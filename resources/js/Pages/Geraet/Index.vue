<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, defineProps, watch, nextTick } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Geraet/ModalCreate.vue';
import ModalEdit from '@/Pages/Geraet/ModalEdit.vue';
import Dropzone from "dropzone";
import "dropzone/dist/dropzone.css";
import ModalImport from '@/Components/ModalImport.vue'

Dropzone.autoDiscover = false;
let dropzoneInstance = null;



let search = ref('');
let seite = 'geraet';
let geraetToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let geraetToEdit = ref(null);

const props = defineProps({
    geraete: Array,
    hersteller: Array
});

// Lokale Liste & Filter
let localGeraete = ref([...props.geraete]);
let filteredGeraete = ref([...localGeraete.value]);





/* Dropzone */
const showImportModal = ref(false);
const importGeraet = async () => {
    showImportModal.value = true;
    await nextTick(); // wartet bis DOM gerendert
    initDropzone();
};

const initDropzone = () => {

    const el = document.querySelector("#mydropzone");
    if (!el) return;

    // verhindert doppelte Dropzone
    if (dropzoneInstance) {
        dropzoneInstance.destroy();
        dropzoneInstance = null;
    }

    dropzoneInstance = new Dropzone(el, {
        url: route("teilnehmer.import"),
        method: "post",
        paramName: "file",
        clickable: true,
        maxFilesize: 5,
        acceptedFiles: ".csv,.xlsx,.xls",
        addRemoveLinks: true,

        headers: {
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content")
        },

        dictDefaultMessage: "Datei hier hineinziehen oder klicken",

        success() {
            Swal.fire({
                title: "Import erfolgreich",
                icon: "success"
            });

            showImportModal.value = false;

            router.reload({ only: ["geraets"] });
        },

        error(file, message) {
            Swal.fire({
                title: "Fehler",
                text: message,
                icon: "error"
            });
        }
    });
};












const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('de-DE');
};

// Modals
const openModalCreate = () => { isModalCreateOpen.value = true; };
const closeModalCreate = () => { isModalCreateOpen.value = false; };

const openModalEdit = (geraet) => {
    geraetToEdit.value = geraet;
    isModalEditOpen.value = true;
};
const closeModalEdit = () => { isModalEditOpen.value = false; };

// CRUD
const addGeraet = (geraet) => {
    localGeraete.value.unshift(geraet);
    applySearchFilter();
};

const updateGeraet = (updatedGeraet) => {
    const index = localGeraete.value.findIndex(g => g.id === updatedGeraet.id);
    if (index !== -1) localGeraete.value[index] = updatedGeraet;
    applySearchFilter();
};

// Delete
const confirmDelete = (geraet) => {
    geraetToDelete.value = { id: geraet.id, name: geraet.geraet };
    showModalLöschen.value = true;
};
const handleDelete = (id) => {
    localGeraete.value = localGeraete.value.filter(g => g.id !== id);
    applySearchFilter();
    showModalLöschen.value = false;
};

// Suche
const applySearchFilter = () => {
    if (search.value) {
        filteredGeraete.value = localGeraete.value.filter(g =>
            g.geraet.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredGeraete.value = [...localGeraete.value];
    }
};

watch(search, () => {
    router.get('/geraete', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
});
</script>

<template>
  <Head title="Geräte" />

  <AppLayout>
    <template #header>Geräte</template>
     <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModalCreate" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

             <div @click="importGeraet" class="flex items-center">
                <i class="las la-upload bg-white border border-gray-300  px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="search" class="sr-only">{{$t('Suchen')}}</label>
            <input id="search"v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />


            <Link :href="route('geraet.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

    <!-- Tabelle -->
    <div class="overflow-x-auto shadow rounded-lg">
        <table class="min-w-[1200px] w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-200 text-gray-700 uppercase sticky top-0">
                <tr>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Produkt ID</th>
                    <th class="px-4 py-3 text-left">SN</th>
                    <th class="px-4 py-3 text-left">Gerät</th>
                    <th class="px-4 py-3 text-left">Zustand</th>
                    <th class="px-4 py-3 text-left">Hersteller</th>
                    <th class="px-4 py-3 text-left">Modell</th>
                    <th class="px-4 py-3 text-left">Baujahr</th>
                    <th class="px-4 py-3 text-left">Garantie</th>
                    <th class="px-4 py-3 text-center">*</th>
                </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="geraet in filteredGeraete" :key="geraet.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 ">
                        <span v-if="geraet.verfuegbarkeit" class="text-green-600 font-bold">✔</span>
                        <span v-else class="text-red-600 font-bold">✖</span>
                    </td>
                    <td class="px-4 py-3">{{ geraet.productID }}</td>
                    <td class="px-4 py-3">{{ geraet.sn }}</td>
                    <td class="px-4 py-3">{{ geraet.geraet }}</td>
                    <td class="px-4 py-3">{{ geraet.zustand }}</td>
                    <td class="px-4 py-3">{{ geraet.hersteller }}</td>
                    <td class="px-4 py-3">{{ geraet.modell }}</td>
                    <td class="px-4 py-3">{{ formatDate(geraet.baujahr) }}</td>
                    <td class="px-4 py-3" :class="geraet.garantiefrist >= new Date() ? 'text-green-600' : 'text-red-600'">
                        {{ formatDate(geraet.garantiefrist) }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <Dropdown>
                            <template #trigger>
                                <i class="la la-ellipsis-v cursor-pointer"></i>
                            </template>
                            <template #content>
                                <span class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                      @click="openModalEdit(geraet)">
                                    Bearbeiten <i class="las la-edit"></i>
                                </span>
                                <span class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                      @click="confirmDelete(geraet)">
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
                 :hersteller="props.hersteller"
                 @close="closeModalCreate"
                 @added="addGeraet" />

    <ModalEdit :visible="isModalEditOpen"
               :toEdit="geraetToEdit"
               :hersteller="props.hersteller"
               @close="closeModalEdit"
               @updated="updateGeraet" />

    <ModalDestroy v-if="showModalLöschen"
                :seite="seite"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :toDelete="geraetToDelete"/>



    <ModalImport :show="showImportModal" :seite="seite" @close="showImportModal = false" />


  </AppLayout>
</template>

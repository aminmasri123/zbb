<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, defineProps, watch, nextTick } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Geraet/ModalCreate.vue';
import ModalEdit from '@/Pages/Geraet/ModalEdit.vue';
import Dropzone from "dropzone";
import "dropzone/dist/dropzone.css";
import ModalImport from '@/Components/ModalImport.vue'
import { usePermissions } from '@/utils/permissions';

Dropzone.autoDiscover = false;
let dropzoneInstance = null;



let search = ref('');
let seite = 'geraet';
let geraetToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let geraetToEdit = ref(null);
const { can } = usePermissions();
const canCreateGeraet = computed(() => can('geraet.store'));
const canUpdateGeraet = computed(() => can('geraet.update'));
const canDeleteGeraet = computed(() => can('geraet.destroy') || can('geraet.delete'));
const canImportGeraet = computed(() => can('geraet.import'));
const canManageGeraet = computed(() => canUpdateGeraet.value || canDeleteGeraet.value);

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
    if (!canImportGeraet.value) return;

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
const openModalCreate = () => {
    if (!canCreateGeraet.value) return;
    isModalCreateOpen.value = true;
};
const closeModalCreate = () => { isModalCreateOpen.value = false; };

const openModalEdit = (geraet) => {
    if (!canUpdateGeraet.value) return;
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
    if (!canDeleteGeraet.value) return;
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
        <div class="flex items-stretch mb-3 overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
            <button
                v-if="canCreateGeraet"
                type="button"
                @click="openModalCreate"
                class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Gerät anlegen"
            >
                <i class="la la-plus"></i>
            </button>

             <button
                v-if="canImportGeraet"
                type="button"
                @click="importGeraet"
                class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Geräte importieren"
            >
                <i class="las la-upload"></i>
            </button>

            <label for="search" class="sr-only">{{$t('Suchen')}}</label>
            <input id="search" v-model="search" type="text" class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset" placeholder="Suchen ..." />


            <Link
                :href="route('geraet.index')"
                class="flex w-14 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Aktualisieren"
            >
                <i class="la la-refresh"></i>
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
                    <th v-if="canManageGeraet" class="px-4 py-3 text-center">*</th>
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
                    <td v-if="canManageGeraet" class="px-4 py-3 text-center">
                        <Dropdown>
                            <template #trigger>
                                <i class="la la-ellipsis-v cursor-pointer"></i>
                            </template>
                            <template #content>
                                <span v-if="canUpdateGeraet" class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                      @click="openModalEdit(geraet)">
                                    Bearbeiten <i class="las la-edit"></i>
                                </span>
                                <span v-if="canDeleteGeraet" class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
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
    <ModalCreate v-if="canCreateGeraet" :visible="isModalCreateOpen"
                 :hersteller="props.hersteller"
                 @close="closeModalCreate"
                 @added="addGeraet" />

    <ModalEdit v-if="canUpdateGeraet" :visible="isModalEditOpen"
               :toEdit="geraetToEdit"
               :hersteller="props.hersteller"
               @close="closeModalEdit"
               @updated="updateGeraet" />

    <template v-if="canDeleteGeraet">
    <ModalDestroy v-if="showModalLöschen"
                :seite="seite"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :toDelete="geraetToDelete"/>
    </template>



    <ModalImport v-if="canImportGeraet" :show="showImportModal" :seite="seite" @close="showImportModal = false" />


  </AppLayout>
</template>

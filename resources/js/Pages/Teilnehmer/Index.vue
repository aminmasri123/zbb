<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import ModalCreateTeilnehmer from '@/Pages/Teilnehmer/ModalCreateTeilnehmer.vue';
import ZurGruppeHinzufügen from '@/Components/ZurGruppeHinzufuegen.vue';

import Dropzone from "dropzone";
import "dropzone/dist/dropzone.css";
Dropzone.autoDiscover = false;
let dropzoneInstance = null;


const teilnehmerList = ref([...teilnehmers.data]);

watch(() => teilnehmers.data, (newValue) => {
    teilnehmerList.value = [...newValue];
});
// Suchfeld und Dropdown fuer Standorte
let seite = 'teilnehmer'; // Für die Löschseite
let search = ref(filters?.search ?? '');
let searchStandort = ref('');
let checkBoxListeTeilnehmer = ref(false); //Teilnehmer zur Projekten/Gruppen hinzufügen
let selectedStandort = ref(filters?.standort ?? null);
let isModalOpen = ref(false); // Modal-Zustand
let sortColumn = ref(filters?.sort ?? 'id');  // Spalte zum Sortieren
let sortDirection = ref(filters?.direction ?? 'desc'); // Sortierrichtung ('asc' oder 'desc')

const selected = ref([]);
const groupModal = ref(null);
// Lokale Teilnehmerliste
let teilnehmerToDelete = ref(null); // Speichert den Namen der Teilnehmer, die gelöscht werden sollen
let showModalLöschen = ref(false); // Modal für die Löschung

const { teilnehmers, authProjekte, rollen, gruppen, projekte, standorte, defaultProjekt, filters  } = defineProps({
    pagination: {
        type: Object,
    },
    teilnehmers: { type: Object, default: () => ({ data: [], links: [] }) },
    authProjekte: {
        type: Array,
        default: () => []
    },
    rollen: {
        type: Object,
        default: () => ({})
    },
    gruppen: {
        type: Array,
        default: () => []
    },
    projekte: {
        type: Array,
        default: () => []
    },
    standorte: {
        type: Array,
        default: () => []
    },
     defaultProjekt: { type: Number, default: null },
     filters: {
        type: Object,
        default: () => ({})
     },

});
const showImportModal = ref(false);

const importTeilnehmer = () => {

    showImportModal.value = true;

    setTimeout(() => {
        initDropzone();
    }, 200);

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

        success(file, response) {
            if (response?.error) {
                Swal.fire({
                    title: "Fehler",
                    text: formatImportMessage(response),
                    icon: "error"
                });
                return;
            }

            Swal.fire({
                title: "Import erfolgreich",
                text: response?.message || "Teilnehmer wurden importiert.",
                icon: "success"
            });

            showImportModal.value = false;

            router.reload({ only: ["teilnehmers"] });
        },

        error(file, message) {
            Swal.fire({
                title: "Fehler",
                text: formatImportMessage(message),
                icon: "error"
            });
        }
    });
};

const formatImportMessage = (response) => {
    if (typeof response === 'string') {
        return response;
    }

    const message = response?.message || 'Der Import konnte nicht abgeschlossen werden.';
    const errors = Array.isArray(response?.errors) ? response.errors : [];

    if (errors.length === 0) {
        return message;
    }

    return `${message}\n\n${errors.join('\n')}`;
};
// Löschbestätigung anzeigen und Abteilungsnamen speichern
const confirmDelete = (teilnehmer) => {
    teilnehmerToDelete.value = {
        name: teilnehmer.vorname, // Speichere den Namen der Abteilung
        id: teilnehmer.id      // Speichere die ID der Abteilung
    };
    showModalLöschen.value = true; // Modal anzeigen
};
const deleteTeilnehmer = (id) => {
    // Sofort aus der lokalen Liste entfernen
    const index = teilnehmerList.value.findIndex(t => t.id === id);
    if (index !== -1) {
        teilnehmerList.value.splice(index, 1);
    }

    // Modal schließen
    showModalLöschen.value = false;

    // Optional: Alert
    Swal.fire({
        title: 'Erfolg!',
        text: 'Teilnehmer wurde gelöscht.',
        icon: 'success',
        timer: 2000
    });
};

// Watch fuer Aenderungen in Suche, Standort und Sortierung
watch([search, selectedStandort, sortColumn, sortDirection], () => {
    router.get(route('teilnehmer.index'),
        {
            search: search.value,
            standort: selectedStandort.value,
            sort: sortColumn.value,
            direction: sortDirection.value
        },
        { preserveState: true, replace: true }
    );
});

// Gefilterte Standorte
const filteredStandorte = computed(() => {
    return standorte.filter(standort =>
        standort.name.toLowerCase().includes(searchStandort.value.toLowerCase())
    );
});

const filteredTeilnehmerByProject = computed(() => teilnehmerList.value);


// Projekt auswählen
const selectedCount = computed(() => selected.value.length);
const allVisibleSelected = computed(() =>
    filteredTeilnehmerByProject.value.length > 0
    && filteredTeilnehmerByProject.value.every(teilnehmer => selected.value.includes(teilnehmer.id))
);

const toggleSelectionMode = () => {
    checkBoxListeTeilnehmer.value = !checkBoxListeTeilnehmer.value;

    if (!checkBoxListeTeilnehmer.value) {
        selected.value = [];
    }
};

const toggleSelectAllVisible = () => {
    const visibleIds = filteredTeilnehmerByProject.value.map(teilnehmer => teilnehmer.id);

    if (allVisibleSelected.value) {
        selected.value = selected.value.filter(id => !visibleIds.includes(id));
        return;
    }

    selected.value = [...new Set([...selected.value, ...visibleIds])];
};

const openGroupModal = () => {
    if (selected.value.length === 0) {
        Swal.fire({
            title: 'Keine Auswahl',
            text: 'Bitte markieren Sie zuerst mindestens einen Teilnehmer.',
            icon: 'warning',
            timer: 2500,
        });
        return;
    }

    groupModal.value?.open();
};

const deleteSelectedTeilnehmer = async () => {
    if (selected.value.length === 0) {
        Swal.fire({
            title: 'Keine Auswahl',
            text: 'Bitte markieren Sie zuerst mindestens einen Teilnehmer.',
            icon: 'warning',
            timer: 2500,
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Markierte Teilnehmer löschen?',
        text: `${selected.value.length} Teilnehmer werden dauerhaft geloescht.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const ids = [...selected.value];
        const response = await axios.delete(route('teilnehmer.bulkDestroy'), {
            data: { ids },
        });

        teilnehmerList.value = teilnehmerList.value.filter(teilnehmer => !ids.includes(teilnehmer.id));
        selected.value = [];

        Swal.fire({
            title: 'Erfolg',
            text: response.data.message || 'Die markierten Teilnehmer wurden geloescht.',
            icon: 'success',
            timer: 2500,
        });
    } catch (error) {
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Die markierten Teilnehmer konnten nicht geloescht werden.',
            icon: 'error',
        });
    }
};
const selectStandort = (standortId) => {
    selectedStandort.value = standortId;
};

// Modal öffnen und schließen
const openModal = () => {
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};
// Teilnehmer hinzufügen
const addTeilnehmer = async (formData) => {
        // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
        if (!formData.vorname || !formData.nachname || !formData.geschlecht) {
            Swal.fire({
                title: 'Error!',
                text: 'Bitte füllen Sie alle erforderlichen Felder aus.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        try {
            // Sende die POST-Anfrage an den Server
            //const response = await axios.post(route('teilnehmer.store'), formData);
            const response = await axios.post(route('teilnehmer.store'), formData);

            teilnehmerList.value.unshift(response.data.teilnehmer);

            // Zeige eine Erfolgsnachricht an
            Swal.fire({
                title: 'Erfolg!',
                text: 'Benutzer erfolgreich erstellt!',
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
            });

            // Optional: Formular zurücksetzen und Modal schließen
            closeModal();
        } catch (error) {
            // Fehlerbehandlung hier
            console.error(error);
            Swal.fire({
                title: 'Error!',
                text: error.response.data.message || 'Beim Erstellen des Benutzers ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
        }
    };

// Sortierfunktion aufrufen, wenn ein Spaltenkopf angeklickt wird
const sortByColumn = (column) => {
    if (sortColumn.value === column) {
        // Wenn die gleiche Spalte nochmal geklickt wird, die Richtung umkehren
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        // Neue Spalte -> Richtung auf 'asc' setzen
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
};
</script>

<template>
    <Head title="Teilnehmer" />

    <app-layout>
        <template #header>{{$t('Teilnehmerübersicht')}}</template>


        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <Dropdown align="left">
                <template #trigger>
                    <button class="bg-white border border-gray-300 rounded-l-md px-5 py-2 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500">
                        <i class="la la-ellipsis-v la-lg"></i>
                    </button>
                </template>

                <template #content>
                    <button type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="toggleSelectionMode">
                        {{ checkBoxListeTeilnehmer ? 'Auswahl beenden' : 'Teilnehmer markieren' }}
                        <i class="las la-check-square"></i>
                    </button>
                    <button v-if="checkBoxListeTeilnehmer" type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="toggleSelectAllVisible">
                        {{ allVisibleSelected ? 'Sichtbare abwaehlen' : 'Sichtbare markieren' }}
                        <i class="las la-tasks"></i>
                    </button>
                    <button type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="openGroupModal">
                        In Gruppe hinzufuegen
                        <span class="ml-4 text-xs text-gray-500">{{ selectedCount }}</span>
                    </button>
                    <button type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-red-50 text-left text-red-600" @click="deleteSelectedTeilnehmer">
                        Markierte löschen
                        <span class="ml-4 text-xs">{{ selectedCount }}</span>
                    </button>
                </template>
            </Dropdown>
            <div @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300  px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>


            <div @click="importTeilnehmer" class="flex items-center">
                <i class="las la-upload bg-white border border-gray-300  px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>


            <label for="simple-search" class="sr-only">Search</label>
            <input v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />

            <!-- Dropdown für Projekte -->
            <Dropdown align="right">
                <template #trigger>
                    <button class="inline-flex items-center px-3 py-3 border border-gray-300 text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                        <span class="mr-5">Standorte</span>
                        <span class="transform transition-transform duration-300 menu-arrow"></span>
                    </button>
                </template>

                <template #content>
                    <!-- Projektsuche -->
                    <div class="px-4 py-2" @click.stop>
                        <input v-model="searchStandort" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2" placeholder="Standorte suchen..." />
                    </div>

                    <!-- Gefilterte Projektauswahl -->
                    <DropdownLink @click="selectStandort(null)" href="#">
                        Alle Standorte
                    </DropdownLink>
                    <DropdownLink v-for="standort in filteredStandorte" :key="standort.id" @click="selectStandort(standort.id)" href="#">
                        {{ standort.name }}
                    </DropdownLink>
                </template>
            </Dropdown>
            <Link :href="route('teilnehmer.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

         <ZurGruppeHinzufügen
            ref="groupModal"
            :show-button="false"
            :selected="selected"
            :gruppen="gruppen"
            @submitted="selected = []"
            />

        <!-- Teilnehmer Tabelle -->
        <div class="overflow-x-auto snap-x">
            <div v-if="!$page.props.auth.user.current_team_id" class="flex w-full text-red-500 p-3 bg-white">
                <p >
                    {{ $t('Bitte legen Sie ein Standardprojekt fest.') }}
                </p>
            </div>

                <table class="w-full text-sm text-left text-gray-500 ">
                    <thead class=" text-gray-600 uppercase bg-gray-200">
                        <tr >
                            <th v-if="checkBoxListeTeilnehmer" class="border border-solid border-gray-300 text-center py-3">
                                <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible">
                            </th>
                            <th @click="sortByColumn('id')" scope="col" class="border border-solid border-gray-300 px-6 py-3">
                                {{$t('id')}}
                                <i :class="sortColumn === 'id' && sortDirection === 'asc' ? 'las la-lg la-sort-numeric-down-alt' : 'las la-lg la-sort-numeric-up-alt'"></i>
                            </th>
                            <th @click="sortByColumn('vorname')" scope="col" class="border border-solid border-gray-300 px-6 py-3">
                                {{$t('vorname')}}
                                <i :class="sortColumn === 'vorname' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                            </th>
                            <th @click="sortByColumn('nachname')" scope="col" class="border border-solid border-gray-300 px-6 py-3">
                                {{$t('nachname')}}
                                <i :class="sortColumn === 'nachname' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                            </th>
                        <th @click="sortByColumn('geschlecht')" scope="col" class="border border-solid border-gray-300 px-6 py-3">
                                {{ $t('geschlecht') }}
                                <i :class="sortColumn === 'geschlecht' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                            </th>
                            <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="teilnehmer in filteredTeilnehmerByProject" :key="teilnehmer.id" class="bg-white border-b">
                            <td v-if="checkBoxListeTeilnehmer" class="text-center py-4 border border-solid border-gray-300">
                                <input v-model="selected" :value="teilnehmer.id" type="checkbox">
                            </td>
                            <td class="px-6 py-4 border border-solid border-gray-300"><Link :href="route('teilnehmer.edit', teilnehmer.id)">{{ teilnehmer.id }}</Link> </td>
                            <td class="px-6 py-4 border border-solid border-gray-300">{{ teilnehmer.vorname }}</td>
                            <td class="px-6 py-4 border border-solid border-gray-300">{{ teilnehmer.nachname }}</td>
                            <td class="px-6 py-4 border border-solid border-gray-300">{{ teilnehmer.geschlecht }}</td>

                            <td class="border px-6 py-4 text-center">
                                <!-- Dropdown für Aktion -->
                                <Dropdown>
                                    <template #trigger>
                                        <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                            <span class="cursor-pointer">
                                                <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                            </span>
                                        </button>
                                    </template>

                                    <template #content >
                                        <!-- Gefilterte Projektauswahl -->
                                        <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100 " @click="confirmDelete(teilnehmer)">
                                            {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                        </span>
                                        <Link class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" :href="route('teilnehmer.edit', teilnehmer.id)">
                                        {{ $t('Bearbeiten') }}  <i class="las la-edit"></i>
                                        </Link>
                                    </template>
                                </Dropdown>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <!-- Paginierung -->

            <Pagination :pagination="teilnehmers" />
        </div>


            <ModalCreateTeilnehmer :visible="isModalOpen" :projekte="projekte" :standorte="standorte" :defaultProjekt="defaultProjekt"  @close="closeModal" @add-teilnehmer="addTeilnehmer" />

        <!-- Modal für die Löschung der Abteilung-->

            <ModalDestroy v-if="showModalLöschen"@close="showModalLöschen = false"@delete="deleteTeilnehmer":seite="seite":toDelete="teilnehmerToDelete"/>


            <div v-if="showImportModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                <div class="bg-white p-6 rounded-lg w-1/2">

                    <div class="flex justify-between mb-4">
                        <h2 class="text-lg font-bold">Teilnehmer importieren</h2>
                        <button @click="showImportModal=false">✕</button>
                    </div>

                    <form id="mydropzone" class="dropzone border border-dashed p-6 rounded-lg"></form>

                </div>

            </div>
    </app-layout>
</template>

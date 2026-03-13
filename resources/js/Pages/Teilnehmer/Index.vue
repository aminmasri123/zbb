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
// Suchfeld und Dropdown für Projekte
let seite = 'teilnehmer'; // Für die Löschseite
let search = ref('');
let searchProject = ref('');
let checkBoxListeTeilnehmer = ref(false); //Teilnehmer zur Projekten/Gruppen hinzufügen
let selectedProject = ref(null); // Für das ausgewählte Projekt
let isModalOpen = ref(false); // Modal-Zustand
let sortColumn = ref('');  // Spalte zum Sortieren
let sortDirection = ref('desc'); // Sortierrichtung ('asc' oder 'desc')

const selected = ref([]);
// Lokale Teilnehmerliste
let teilnehmerToDelete = ref(null); // Speichert den Namen der Teilnehmer, die gelöscht werden sollen
let showModalLöschen = ref(false); // Modal für die Löschung

const { teilnehmers, authProjekte, rollen, gruppen, projekte, standorte, defaultProjekt  } = defineProps({
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

        success() {
            Swal.fire({
                title: "Import erfolgreich",
                icon: "success"
            });

            showImportModal.value = false;

            router.reload({ only: ["teilnehmers"] });
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

// Watch für Änderungen in der Suche
watch([search, selectedProject, sortColumn, sortDirection], () => {
    router.get('/teilnehmer',
        {
            search: search.value,
            project: selectedProject.value,
            sort: sortColumn.value,
            direction: sortDirection.value
        },
        { preserveState: true, replace: true }
    );
});

// Watch für das ausgewählte Projekt
watch(selectedProject, value => {
    router.get('/teilnehmer', { search: search.value, project: value }, { preserveState: true });
});

// Gefilterte Projekte
const filteredProjects = computed(() => {
    return projekte.filter(projekt =>
        projekt.name.toLowerCase().includes(searchProject.value.toLowerCase())
    );
});

// Teilnehmer nach Projekt filtern
const filteredTeilnehmerByProject = computed(() => {
  if (!selectedProject.value) {
    return teilnehmerList.value   // jetzt wird deine lokale Liste genutzt
  }
  return teilnehmerList.value.filter(teilnehmer =>
    teilnehmer.projekte?.some(projekt => projekt.name === selectedProject.value)
  )
});


// Projekt auswählen
const selectProject = (projekt) => {
    selectedProject.value = projekt.name;
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
            <div @click="checkBoxListeTeilnehmer = !checkBoxListeTeilnehmer" class="bg-white border border-gray-300 rounded-l-md px-5 py-2 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500">⋮</div>
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
                        <span class="mr-5">Projekte</span>
                        <span class="transform transition-transform duration-300 menu-arrow"></span>
                    </button>
                </template>

                <template #content>
                    <!-- Projektsuche -->
                    <div class="px-4 py-2" @click.stop>
                        <input v-model="searchProject" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2" placeholder="Projekte suchen..." />
                    </div>

                    <!-- Gefilterte Projektauswahl -->
                    <DropdownLink v-for="projekt in filteredProjects" :key="projekt.id" @click="selectProject(projekt)" href="#">
                        {{ projekt.name }}
                    </DropdownLink>
                    <DropdownLink @click="selectProject('refresh')" href="#">
                        <i class="la la-lg la-refresh" aria-hidden="true"></i>
                    </DropdownLink>
                </template>
            </Dropdown>
            <Link :href="route('teilnehmer.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

         <ZurGruppeHinzufügen
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
                            <th v-if="checkBoxListeTeilnehmer" class="border border-solid border-gray-300 text-center py-3">⋮</th>
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
                                <input v-model="selected":value="teilnehmer.id" type="checkbox"></input>
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

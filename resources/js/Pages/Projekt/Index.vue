<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch, computed} from 'vue';
    import Swal from 'sweetalert2';
    import {router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import Modal from '@/Components/ModalForm.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import Select from 'primevue/select';
    import DatePicker from 'primevue/datepicker';

    let seite = 'projekt';
    let search = ref('');
    let projektToDelete = ref(null); // Speichert den Namen der Projekt, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung

     // Definiere die Props direkt
    const props = defineProps({ projekte: Object, abteilungen: Object }); // props wird hier definiert

    // Lokale Kopie der Projekte erstellen
    let localProjekte = ref([...props.projekte.data]);  // Originaldaten
    let filteredProjekte = ref([...localProjekte.value]); // Gefilterte Daten

    const formatDate = (date) => {
    if (!date) return 'Kein Datum verfügbar'; // Überprüfen, ob das Datum vorhanden ist
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0'); // Tag mit führender Null
    const month = String(d.getMonth() + 1).padStart(2, '0'); // Monat (0-basiert)
    const year = d.getFullYear(); // Jahr
    return `${day}.${month}.${year}`; // Format: dd.mm.YYYY
};



     // Funktion, um die Projekte von der Datenbank abzurufen
     const fetchProjekte = async () => {
        try {
            const response = await axios.get(route('projekt.indexAjaxFresh'));
            return response.data.projekte;
        } catch (error) {
            console.error('Fehler beim Abrufen der Projekte:', error);
            return null;
        }
    };


    // Funktion zum Vergleichen und Laden der neuen Daten
    const compareAndReload = async () => {
        const newProjekte = await fetchProjekte();
            if (newProjekte) {
                const localIds = localProjekte.value.map(projekt => projekt.id);
                // Neue Projekte hinzufügen
                newProjekte.data.forEach(newProjekt => {
                    if (!localIds.includes(newProjekt.id)) {
                        localProjekte.value.unshift(newProjekt);
                    }
                });
                // Entferne Projekte, die nicht mehr in der Liste sind
                localProjekte.value = localProjekte.value.filter(localProjekt =>
                    newProjekte.data.some(newProjekt => newProjekt.id === localProjekt.id)
                );
                // Wende die Suchfilterung an, basierend auf der aktuellen Suchanfrage
                applySearchFilter();
            }
    };

    // Setze ein Intervall, um die Daten regelmäßig zu überprüfen
    setInterval(compareAndReload, 5000); // Alle 5 Sekunden vergleichen
    // Funktion, um die Suchergebnisse zu filtern
    const applySearchFilter = () => {
            if (search.value) {
                filteredProjekte.value = localProjekte.value.filter(projekt =>
                    projekt.name.toLowerCase().includes(search.value.toLowerCase())
                );
            } else {
                // Wenn keine Suchanfrage vorliegt, alle Projekte anzeigen
                filteredProjekte.value = [...localProjekte.value];
            }
        };

    // Watch für Änderungen in der Suche
    watch([search], () => {
        // Aktualisiere die URL mit der Suchabfrage
        router.get('/projekt', { search: search.value }, { preserveState: true, replace: true });
        // Führe die Filterung durch
        applySearchFilter();
    });

    // Löschbestätigung anzeigen und Projektsnamen speichern
    const confirmDelete = (projekt) => {
        projektToDelete.value = {
            name: projekt.name, // Speichere den Namen der Projekt
            id: projekt.id      // Speichere die ID der Projekt
        };
        showModalLöschen.value = true; // Modal anzeigen
    };
    // Event-Handler, um die Projekt aus der lokalen Liste zu löschen
    const handleDelete = (projektId) => {
        // Remove the deleted item from localProjekte
        localProjekte.value = localProjekte.value.filter(
            projekt => projekt.id !== projektId
        );
        showModalLöschen.value = false; // Close the delete modal
    };

    let isModalOpen = ref(false); // Modal-Zustand
    // Modal öffnen und schließen
    const openModal = () => {
        isModalOpen.value = true;
    };

    const closeModal = () => {
        isModalOpen.value = false;
        resetForm();
    };
    // Neuen Projekt
    let newProjekt = ref({
        name: '',
        kostenstelle:'',
        abteilung: '',
        antragsdatum: '',
        starttermin: '',
        anfangsdatum: '',
        endtermin: '',
        enddatum: '',
    });
    const resetForm = () => {
        newProjekt.value = {
            name: '',
            kostenstelle:'',
            abteilung: '',
            antragsdatum: '',
            starttermin: '',
            anfangsdatum: '',
            endtermin: '',
            enddatum: '',
        };
    };

// Benutzer hinzufügen

const addProjekt = async () => {
    try {
        // Sende die POST-Anfrage an den Server
        const response = await axios.post(route('projekt.store'), newProjekt.value);

        // Logge die vollständige Antwort des Servers, um zu sehen, was tatsächlich zurückkommt
        console.log('API response:', response.data);
        console.log('API response:', response);

        // Prüfe, ob das erstellte Projekt im response vorhanden ist
        if (!response.data.projekt) {
            throw new Error('Das erstellte Projekt wurde nicht in der Antwort gefunden.');
        }

        // Füge das neu erstellte Projekt zur lokalen Liste hinzu
        //localProjekte.value.unshift(response.data.projekt);

        // Erfolgsnachricht
        Swal.fire({
            title: 'Erfolg!',
            text: 'Projekt erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

        // Filtere die Projekte neu
       // applySearchFilter();

        // Reset des Formulars und Schließen des Modals
        resetForm();
        closeModal();

    } catch (error) {
        console.error('Fehler beim Erstellen des Projekts:', error);
        Swal.fire({
            title: 'Error!',
            text: error.response?.data?.message || 'Beim Erstellen des Projekts ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
    }
};



</script>

<script>

export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

};
</script>

<template>
        <Head title="Projekt" />

    <app-layout>
        <!-- Header Slot -->
        <template #header>{{$t('projekte')}}</template>
        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="simple-search" class="sr-only">Search</label>
            <input v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />


            <Link :href="route('projekt.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>
        <!-- Benutzerausgabe -->
        <div class="relative overflow-x-auto mb-10">
            <table id="table" class="w-full text-sm table-auto mb-5 text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-xs  text-gray-600 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-400">
                    <tr class="font-bold ">
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 w-10 text-center ">ID.</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Projekt')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Kostenstelle')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Bereiche')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Antragsdatum')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Starttermin')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Anfangsdatum')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Endtermin')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Enddatum')}}</th>
                        <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="projekt in filteredProjekte" :key="projekt.id"
                        class="bg-white  border-solid dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="border border-solid border-gray-300 px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white text-center w-6">
                            {{projekt.id}}
                        </th>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <span class="block">{{projekt.name}}</span>
                            <span class="text-xs text-zbb">{{projekt.abteilung.name}}</span>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{projekt.kostenstelle}}
                        </td>

                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <li v-for="bereich in projekt.bereiche" :key="bereich.id" class="text-xs">{{bereich.name}}</li>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <div v-for="projektzeitraum in projekt.projektzeitraume" :key="projektzeitraum.id">
                                <p>{{formatDate(projektzeitraum.antragsdatum)}}</p>
                            </div>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <div v-for="projektzeitraum in projekt.projektzeitraume" :key="projektzeitraum.id">
                                <p>{{formatDate(projektzeitraum.starttermin)}}</p>
                            </div>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <div v-for="projektzeitraum in projekt.projektzeitraume" :key="projektzeitraum.id">
                                <p>{{formatDate(projektzeitraum.anfangsdatum)}}</p>
                            </div>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <div v-for="projektzeitraum in projekt.projektzeitraume" :key="projektzeitraum.id">
                                <p>{{formatDate(projektzeitraum.endtermin)}}</p>
                            </div>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <div v-for="projektzeitraum in projekt.projektzeitraume" :key="projektzeitraum.id">
                                <p>{{formatDate(projektzeitraum.enddatum)}}</p>
                            </div>
                        </td>
                        <td class="w-6 border border-solid border-gray-300 px-6 py-4 text-center m-auto">
                            <!-- Dropdown für Aktion -->
                            <Dropdown >
                                <template #trigger>
                                    <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        <span class="cursor-pointer">
                                            <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                        </span>
                                    </button>
                                </template>

                                <template #content >
                                    <!-- Gefilterte Projektauswahl -->
                                    <span class="flex justify-around cursor-pointer" @click="confirmDelete(projekt)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

         <!-- Modal für neue Projekt -->
         <Modal v-if="isModalOpen" @close="closeModal">
            <template #header>
                <div class="text-center w-full uppercase text-lg font-bold">
                    <h2 class="text-lg font-bold text-gray-500 ">{{ $t('Projekt anlegen') }}</h2>
                </div>
            </template>
            <template #body>
                <form @submit.prevent="addProjekt">
                    <div class="mb-4 w-full mx-1">
                        <FloatLabel variant="on">
                            <InputText id="name" v-model="newProjekt.name" class="w-full" />
                            <label for="name">{{$t('Bezeichnung')}}</label>
                        </FloatLabel>
                    </div>

                    <div class="mb-4 w-full mx-1">
                        <FloatLabel variant="on">
                            <Select v-model="newProjekt.abteilung"  inputId="id" optionValue="id"  :options="abteilungen" optionLabel="name" class="w-full" />
                            <label for="Abteilung">{{$t('Abteilung wählen')}}</label>
                        </FloatLabel>
                    </div>
                    <div class="flex">
                        <FloatLabel variant="on">
                            <InputText id="name" v-model="newProjekt.kostenstelle" class="w-full" />
                            <label for="name">{{$t('Kostenstelle')}}</label>
                        </FloatLabel>
                        <FloatLabel variant="on">
                            <DatePicker v-model="newProjekt.antragsdatum" dateFormat="yy-mm-dd"  inputId="antragsdatum" showIcon iconDisplay="input" />
                            <label for="antragsdatum">{{$t('Antragsdatum')}}</label>
                        </FloatLabel>
                    </div>
                    <div class="flex">
                        <FloatLabel variant="on">
                            <DatePicker v-model="newProjekt.starttermin" dateFormat="yy-mm-dd"  inputId="starttermin" showIcon iconDisplay="input" />
                            <label for="starttermin">{{$t('Starttermin')}}</label>
                        </FloatLabel>
                        <FloatLabel variant="on">
                            <DatePicker v-model="newProjekt.endtermin" dateFormat="yy-mm-dd"  inputId="endtermin" showIcon iconDisplay="input" />
                            <label for="endtermin">{{$t('Endtermin')}}</label>
                        </FloatLabel>
                    </div>
                    <div class="flex">
                        <FloatLabel variant="on">
                            <DatePicker v-model="newProjekt.anfangsdatum" dateFormat="yy-mm-dd"  inputId="anfangsdatum" showIcon iconDisplay="input" />
                            <label for="anfangsdatum">{{$t('Anfangsdatum')}}</label>
                        </FloatLabel>
                        <FloatLabel variant="on">
                            <DatePicker v-model="newProjekt.enddatum" dateFormat="yy-mm-dd"  inputId="enddatum" showIcon iconDisplay="input" />
                            <label for="enddatum">{{$t('Enddatum')}}</label>
                        </FloatLabel>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="w-full flex justify-center">
                    <button @click="addProjekt" class=" mx-2 bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
                    <button @click="closeModal" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
                </div>
            </template>
        </Modal>
        <!-- Modal für die Löschung der Projekt-->
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="projektToDelete"></ModalDestroy>
    </app-layout>
</template>

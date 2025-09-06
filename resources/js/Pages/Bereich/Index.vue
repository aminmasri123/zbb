<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import Modal from '@/Components/ModalForm.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import Textarea from 'primevue/textarea';

    let seite = 'bereich';
    let search = ref('');
    let bereichToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung

     // Definiere die Props direkt
    const props = defineProps({ bereiche: Object, }); // props wird hier definiert
    // Lokale Kopie der Bereiche erstellen

    // Fülle localBereiche mit den Daten aus den Props
    let localBereiche = ref([...props.bereiche.data]);  // Originaldaten
    let filteredBereiche = ref([...localBereiche.value]); // Gefilterte Daten

    // Funktion, um die Bereiche von der Datenbank abzurufen
    const fetchBereiche = async () => {
        try {
            const response = await axios.get(route('bereich.indexAjaxFresh'));
            return response.data.bereiche;
        } catch (error) {
            console.error('Fehler beim Abrufen der Bereiche:', error);
            return null;
        }
    };
    // Funktion zum Vergleichen und Laden der neuen Daten
    const compareAndReload = async () => {
        const newBereiche = await fetchBereiche();

        if (newBereiche) {
            // Überprüfe die aktuellen IDs der lokalen Bereiche
            const localIds = localBereiche.value.map(bereich => bereich.id);

            // Neue Bereiche hinzufügen, die nicht in der lokalen Liste sind
            newBereiche.data.forEach(newBereich => {
                if (!localIds.includes(newBereich.id)) {
                    localBereiche.value.unshift(newBereich); // Füge neue Bereich hinzu
                }
            });

            // Bereiche entfernen, die nicht mehr in der neuen Liste sind
            localBereiche.value = localBereiche.value.filter(localBereich => 
                newBereiche.data.some(newBereich => newBereich.id === localBereich.id)
            );
            applySearchFilter();

        }
    };
    // Setze ein Intervall, um die Daten regelmäßig zu überprüfen
    setInterval(compareAndReload, 5000); // Alle 5 Sekunden vergleichen
 // Funktion, um die Suchergebnisse zu filtern
 const applySearchFilter = () => {
        if (search.value) {
            filteredBereiche.value = localBereiche.value.filter(bereich =>
                bereich.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            // Wenn keine Suchanfrage vorliegt, alle Abteilungen anzeigen
            filteredBereiche.value = [...localBereiche.value];
        }
    };
    watch([search], () => {
        // Aktualisiere die URL mit der Suchabfrage
        router.get('/bereich', { search: search.value }, { preserveState: true, replace: true });
        // Führe die Filterung durch
        applySearchFilter();
    });

// Löschbestätigung anzeigen und Bereichsnamen speichern
const confirmDelete = (bereich) => {
    bereichToDelete.value = {
        name: bereich.name, // Speichere den Namen der Bereich
        id: bereich.id      // Speichere die ID der Bereich
    };
    showModalLöschen.value = true; // Modal anzeigen
};
// Event-Handler, um die Bereich aus der lokalen Liste zu löschen
const handleDelete = (bereichId) => {
    // Remove the deleted item from localBereiche
    localBereiche.value = localBereiche.value.filter(
        bereich => bereich.id !== bereichId
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
    // Neuen Benutzer
        let newBereich = ref({
            name: '',
            beschreibung:'',
        });

    const resetForm = () => {
    newBereich.value = {
        name: '',
        beschreibung: '',
    };
};

// Benutzer hinzufügen
const addBereich = async () => {
    // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
    if (!newBereich.value.name) {
        Swal.fire({
            title: 'Error!',
            text: 'Bitte achten Sie darauf, alle erforderlichen Felder auszufüllen.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    try {
        // Sende die POST-Anfrage an den Server
        const response = await axios.post(route('bereich.store'), newBereich.value);

        // Logge die Antwort des Servers
        console.log(response.data);

        // Zeige eine Erfolgsnachricht an
        Swal.fire({
            title: 'Erfolg!',
            text: 'Bereich erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });
        localBereiche.value.unshift(response.data.bereich);

        // Optional: Formular zurücksetzen und Modal schließen
        resetForm();
        closeModal();
    } catch (error) {
        // Fehlerbehandlung hier
        console.error(error);
        Swal.fire({
            title: 'Error!',
            text: error.response.data.message || 'Beim Erstellen des Bereiches ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
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
        <Head title="Bereiche" />

    <app-layout>
        <!-- Header Slot -->
        <template #header>{{$t('Bereiche')}}</template>

        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="search" class="sr-only">{{$t('Suchen')}}</label>
            <input id="search"v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />


            <Link :href="route('bereich.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>
        <!-- Bereichausgabe -->
        <div class="relative overflow-x-auto mb-10">
            <table id="table" class="w-full text-sm table-auto mb-5 text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-md  text-gray-600 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-400">
                    <tr class="font-bold ">
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 w-10 text-center ">ID.</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Bezeichnung')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Beschreibung')}}</th>
                        <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->

                    </tr>
                </thead>
                <tbody>
                    <tr v-for="bereich in filteredBereiche" :key="bereich.id"
                        class="bg-white  border-solid dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="border border-solid border-gray-300 px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white text-center w-10">
                            {{bereich.id}}
                        </th>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{bereich.name}}
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{bereich.beschreibung}}
                        </td>
                        <td class="w-10 border border-solid border-gray-300 px-6 py-4 text-center m-auto">
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
                                    <span class="flex justify-around cursor-pointer" @click="confirmDelete(bereich)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

         <!-- Modal für neue Bereich -->
         <Modal v-if="isModalOpen" @close="closeModal">
            <template #header>
                <div class="text-center w-full uppercase text-lg font-bold">
                    <h2 class="text-lg font-bold text-gray-500 ">{{ $t('Bereich anlegen') }}</h2>
                </div>
            </template>
            <template #body>
                <form @submit.prevent="addBereich">
                    <div class="mb-4 w-full mx-1">
                        <FloatLabel variant="on">
                            <InputText id="name" v-model="newBereich.name" class="w-full" />
                            <label for="name">{{$t('Bezeichnung')}}</label>
                        </FloatLabel>
                    </div>
                    <div class="mb-4 w-full mx-1">                           
                        <FloatLabel variant="on">
                            <Textarea id="over_label" v-model="newBereich.beschreibung" rows="5"  class="w-full" style="resize: none" />
                            <label for="in_label">{{$t('Beschreibung')}}</label>
                        </FloatLabel>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="w-full flex justify-center">
                    <button @click="addBereich" class=" mx-2 bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
                    <button @click="closeModal" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
                </div>
            </template>
        </Modal>
        <!-- Modal für die Löschung des Bereiches-->
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="bereichToDelete"></ModalDestroy>
    </app-layout>
</template>

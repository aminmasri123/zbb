<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch, computed} from 'vue';
    import Swal from 'sweetalert2';
    import {router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Abteilung/ModalCreate.vue';
    import ModalEdit from '@/Pages/Abteilung/ModalEdit.vue';


    let seite = 'abteilung';
    let search = ref('');
    let abteilungToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let abteilungToEdit = ref(null);

     // Definiere die Props direkt
    const props = defineProps({ abteilungen: Object, users: Object }); // props wird hier definiert


    // Lokale Kopie der Abteilungen erstellen
    let localAbteilungen = ref([...props.abteilungen.data]);  // Originaldaten
    let filteredAbteilungen = ref([...localAbteilungen.value]); // Gefilterte Daten

     // Funktion, um die Abteilungen von der Datenbank abzurufen
     const fetchAbteilungen = async () => {
        try {
            const response = await axios.get(route('abteilung.indexAjaxFresh'));
            return response.data.abteilungen;
        } catch (error) {
            console.error('Fehler beim Abrufen der Abteilungen:', error);
            return null;
        }
    };
        // Funktion zum Vergleichen und Laden der neuen Daten
        const compareAndReload = async () => {
            const newAbteilungen = await fetchAbteilungen();
                if (newAbteilungen) {
                    const localIds = localAbteilungen.value.map(abteilung => abteilung.id);
                    // Neue Abteilungen hinzufügen
                    newAbteilungen.data.forEach(newAbteilung => {
                        if (!localIds.includes(newAbteilung.id)) {
                            localAbteilungen.value.unshift(newAbteilung);
                        }
                    });
                    // Entferne Abteilungen, die nicht mehr in der Liste sind
                    localAbteilungen.value = localAbteilungen.value.filter(localAbteilung =>
                        newAbteilungen.data.some(newAbteilung => newAbteilung.id === localAbteilung.id)
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
            filteredAbteilungen.value = localAbteilungen.value.filter(abteilung =>
                abteilung.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            // Wenn keine Suchanfrage vorliegt, alle Abteilungen anzeigen
            filteredAbteilungen.value = [...localAbteilungen.value];
        }
    };

    // Watch für Änderungen in der Suche
    watch([search], () => {
        // Aktualisiere die URL mit der Suchabfrage
        router.get('/abteilung', { search: search.value }, { preserveState: true, replace: true });
        // Führe die Filterung durch
        applySearchFilter();
    });

// Löschbestätigung anzeigen und Abteilungsnamen speichern
const confirmDelete = (abteilung) => {
    abteilungToDelete.value = {
        name: abteilung.name, // Speichere den Namen der Abteilung
        id: abteilung.id      // Speichere die ID der Abteilung
    };
    showModalLöschen.value = true; // Modal anzeigen
};

let isModalEditOpen = ref(false); // Modal-Zustand

    // Modal öffnen und schließen
    const openModalEdit = (abteilung) => {
        isModalEditOpen.value = true;
        abteilungToEdit.value = abteilung; // direkt übergeben
    };



    const closeModalEdit = () => {
        isModalEditOpen.value = false;
        resetForm(); // setzt aber nur newAbteilung zurück, nicht editAbteilung
    };



// Event-Handler, um die Abteilung aus der lokalen Liste zu löschen
const handleDelete = (abteilungId) => {
    // Remove the deleted item from localAbteilungen
    localAbteilungen.value = localAbteilungen.value.filter(
        abteilung => abteilung.id !== abteilungId
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

    const resetForm = () => {
    newAbteilung.value = {
        name: '',
        abteilungsleiter:'',
        assistenten: [],
    };
};

// Benutzer hinzufügen

const addAbteilung = async () => {

    // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
    if (!newAbteilung.value.name || !newAbteilung.value.abteilungsleiter || !newAbteilung.value.assistenten) {
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
        const response = await axios.post(route('abteilung.store'), newAbteilung.value);

        // Logge die Antwort des Servers
        console.log(response.data);

        // Zeige eine Erfolgsnachricht an
        Swal.fire({
            title: 'Erfolg!',
            text: 'Abteilung erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });
        localAbteilungen.value.unshift(response.data.abteilung);

        // Optional: Formular zurücksetzen und Modal schließen
        resetForm();
        closeModal();
    } catch (error) {
        // Fehlerbehandlung hier
        console.error(error);
        Swal.fire({
            title: 'Error!',
            text: error.response.data.message || 'Beim Erstellen der Abteilung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
    }
};

// Neuen Benutzer
let newAbteilung = ref({
    name: '',
    abteilungsleiter:'',
    assistenten: [],
});

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
        <Head title="Abteilung" />

    <app-layout>
        <!-- Header Slot -->
        <template #header>{{$t('abteilungen')}}</template>

        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="simple-search" class="sr-only">Search</label>
            <input v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />


            <Link :href="route('abteilung.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>
        <!-- Benutzerausgabe -->
        <div class="relative overflow-x-auto mb-10">
            <table id="table" class="w-full text-sm table-auto mb-10 text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-md  text-gray-600 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-400">
                    <tr class="font-bold ">
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 w-10 text-center ">ID.</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">Abteilung</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">Abteilungsleiter</th>
                        <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->

                    </tr>
                </thead>
                <tbody>
                    <tr v-for="abteilung in filteredAbteilungen" :key="abteilung.id"
                        class="bg-white  border-solid dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="border border-solid border-gray-300 px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white text-center w-10">
                            {{abteilung.id}}
                        </th>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{abteilung.name}}
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <p>{{abteilung.user.first_name}} {{abteilung.user.last_name}}</p>
                            <span v-for="abteilungsassistent in abteilung.abteilungsassistente" :key="abteilungsassistent.id" class="text-xs bg-orange-200 rounded p-1 mr-2">
                                {{abteilungsassistent.user.first_name}} {{abteilungsassistent.user.last_name}}
                            </span>
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
                                    <span class="flex justify-between cursor-pointer px-6 items-center" @click="confirmDelete(abteilung)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt "></i>
                                    </span>
                                    <span class="flex justify-between  cursor-pointer px-6 items-center" @click="openModalEdit(abteilung)">
                                        {{ $t('Bearbeiten') }}  <i class="las la-edit  "></i>
                                    </span>

                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ModalCreate :visible="isModalOpen" :users="users"  @close="closeModal" @add-abteilung="addAbteilung" />

         <!-- Modal für die Löschung der Abteilung-->
         <ModalEdit
            :visible="isModalEditOpen"
            :users="users"
            :toEdit="abteilungToEdit"
            @close="closeModalEdit"
            @updated="(updatedAbteilung) => {
                // ersetze Eintrag in localAbteilungen
                const index = localAbteilungen.findIndex(a => a.id === updatedAbteilung.id);
                if (index !== -1) localAbteilungen[index] = updatedAbteilung;
            }"
        />

        <!-- Modal für die Löschung der Abteilung-->
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="abteilungToDelete"></ModalDestroy>
    </app-layout>
</template>

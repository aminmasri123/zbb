<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Bereich/ModalCreate.vue';
    import ModalEdit from '@/Pages/Bereich/ModalEdit.vue';

    let seite = 'bereich';
    let search = ref('');
    let bereichToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let bereichToEdit = ref(null);

     // Definiere die Props direkt
    const props = defineProps({ bereiche: Object, }); // props wird hier definiert
    // Lokale Kopie der Bereiche erstellen


    const openModalCreate = () => {
        isModalCreateOpen.value = true;
    };

    const closeModalCreate = () => {
        isModalCreateOpen.value = false;
    };


    const openModalEdit = (bereich) => {
        bereichToEdit.value = bereich;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };


    const updateBereich = (updatedBereich) => {
    const index = localBereiche.value.findIndex(b => b.id === updatedBereich.id);
        if (index !== -1) {
            localBereiche.value[index] = updatedBereich;
        }
    };


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
const addBereich = async (data) => {
    try {
        const response = await axios.post(route('bereich.store'), data);

        Swal.fire({
            title: 'Erfolg!',
            text: 'Bereich erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

        localBereiche.value.unshift(response.data.bereich);

    } catch (error) {
        console.error(error);
        Swal.fire({
            title: 'Error!',
            text: error.response?.data?.message || 'Beim Erstellen des Bereiches ist ein Fehler aufgetreten.',
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
            <div @click="openModalCreate" class="flex items-center">
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
                                    <span class="flex justify-between cursor-pointer px-6 items-center"  @click="confirmDelete(bereich)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt "></i>
                                    </span>
                                    <span class="flex justify-between cursor-pointer px-6 items-center"  @click="openModalEdit(bereich)">
                                        {{ $t('Bearbeiten') }}  <i class="las la-edit  "></i>
                                    </span>

                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

         <!-- Modal für neue Bereich -->


        <ModalCreate :visible="isModalCreateOpen" @close="closeModalCreate" @add-bereich="addBereich"/>
        <ModalEdit :visible="isModalEditOpen" :toEdit="bereichToEdit" @close="closeModalEdit" @updated="updateBereich"/>
        <!-- Modal für die Löschung des Bereiches-->
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="bereichToDelete"></ModalDestroy>
    </app-layout>
</template>

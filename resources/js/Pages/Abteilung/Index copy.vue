<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { Inertia } from '@inertiajs/inertia';
    import { ref, reactive, defineProps  } from 'vue';
    import Swal from 'sweetalert2';
    import { Link } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownLink from '@/Components/DropdownLink.vue';
    import Modal from '@/Components/ModalForm.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';

    import MultiSelect from 'primevue/multiselect';
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import Select from 'primevue/select';

    let search = ref('');
    let abteilungToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let deleteInput = ref(''); // Speichert den Text des Eingabefelds für die Löschung



  // Definiere die Props direkt
const props = defineProps({ abteilungen: Object, users: Object }); // props wird hier definiert

// Lokale Kopie der Abteilungen erstellen
let localAbteilungen = ref([]); // Initialisiere mit einem leeren Array

// Fülle localAbteilungen mit den Daten aus den Props
localAbteilungen.value = [...props.abteilungen.data]; // Kopiere die Abteilungen in eine reaktive Variable


// Löschbestätigung anzeigen und Abteilungsnamen speichern
const confirmDelete = (abteilung) => {
    abteilungToDelete.value = {
        name: abteilung.name, // Speichere den Namen der Abteilung
        id: abteilung.id      // Speichere die ID der Abteilung
    };
    showModalLöschen.value = true; // Modal anzeigen
};

const deleteItem = () => {
    if (deleteInput.value !== 'delete') {
        Swal.fire({
            title: 'Fehler!',
            text: 'Bitte geben Sie "delete" ein, um fortzufahren.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return; // Stoppe die Funktion, wenn die Eingabe nicht stimmt
    }
    axios.delete(route('abteilung.destroy', { id: abteilungToDelete.value.id }))
        .then(response => {
            // Entferne die gelöschte Abteilung aus der lokalen Kopie
            localAbteilungen.value = localAbteilungen.value.filter(abteilung => abteilung.id !== abteilungToDelete.value.id);
            deleteInput.value = '';

            Swal.fire({
                title: 'Erfolg!',
                text: 'Abteilung erfolgreich gelöscht!',
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
            });
        })
        .catch(error => {
            Swal.fire({
                title: 'Error!',
                text: 'Beim Löschen der Abteilung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
        })
        .finally(() => {
            showModalLöschen.value = false; // Modal schließen
        });
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
        color: '',
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
        <div class="relative overflow-x-auto">
            <table id="table" class="w-full text-sm table-auto  text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-md  text-gray-600 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-400">
                    <tr class="font-bold ">
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 w-10 text-center ">ID.</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">Abteilung</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">Abteilungsleiter</th>
                        <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->

                    </tr>
                </thead>
                <tbody>
                    <tr v-for="abteilung in localAbteilungen" :key="abteilung.id"
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
                                    <span class="flex justify-around cursor-pointer" @click="confirmDelete(abteilung)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>

                                </template>
                            </Dropdown>


                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

         <!-- Modal für neue Abteilung -->
         <Modal v-if="isModalOpen" @close="closeModal">
            <template #header>
                <div class="text-center w-full uppercase text-lg font-bold">
                    <h2 class="text-lg font-bold text-gray-500 ">{{ $t('abteilung anlegen') }}</h2>
                </div>
            </template>
            <template #body>
                <form @submit.prevent="addAbteilung">
                    <div class="mb-4 w-full mx-1">
                        <FloatLabel variant="on">
                            <InputText id="name" v-model="newAbteilung.name" class="w-full" />
                            <label for="name">Bezeichnung</label>
                        </FloatLabel>
                    </div>


                    <div class="mb-4 w-full mx-1">
                        <FloatLabel variant="on">
                            <Select v-model="newAbteilung.abteilungsleiter"  inputId="id" optionValue="id"  :options="users" optionLabel="full_name" class="w-full" />

                            <label for="abteilungsleiter">Abteilungsleitung wählen</label>
                        </FloatLabel>
                    </div>
                    <div class="mb-4 w-full mx-1">
                        <MultiSelect
                            v-model="newAbteilung.assistenten"
                            inputId="id"
                            display="chip"
                            optionLabel="full_name"
                            :options="users"
                            optionValue="id"
                            filter
                            placeholder="Assistenten wählen*"
                            :maxSelectedLabels="3"
                            class="w-full">
                        </MultiSelect>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="w-full flex justify-center">
                    <button @click="addAbteilung" class=" mx-2 bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
                    <button @click="closeModal" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
                </div>
            </template>
        </Modal>


        <!-- Modal für die Löschung der Abteilung-->
        <Modal v-if="showModalLöschen" @close="showModalLöschen = false" >
            <template #header>
                <div class="text-center w-full uppercase text-lg font-bold">
                    <h3>{{ $t('Bestätigung') }}</h3>
                </div>
            </template>
            <template #body>
                <div class="text-center">
                    <p class="mb-4">{{ $t('Sind Sie sicher, dass Sie die Löschung durchführen möchten?') }} : <strong>{{ abteilungToDelete.name }}</strong>?</p>
                    <FloatLabel variant="on">
                        <InputText  v-model="deleteInput"  size="small"  class="w-full" />
                        <label for="abteilungDelete">delete*</label>
                    </FloatLabel>
                    <small id="username-help">Bitte geben Sie "delete" ein, um die Löschung zu bestätigen.</small>
                </div>
            </template>
            <template #footer>
                <div class="w-full flex justify-center">
                    <button @click="deleteItem" class="bg-zbb text-white mx-2 px-4 py-2 rounded">{{ $t('Löschen') }}</button>
                    <button @click="showModalLöschen = false" class="border mx-2 border-zbb text-zbb px-4 py-2 rounded">{{ $t('Abbrechen') }}</button>
                </div>
            </template>
        </Modal>
    </app-layout>
</template>

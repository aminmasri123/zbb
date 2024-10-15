<script setup>
import { ref, watch, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/ModalForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2';
import axios from 'axios';


// Suchfeld und Dropdown für Projekte
let search = ref('');
let searchProject = ref('');
let selectedProject = ref(null); // Für das ausgewählte Projekt
let isModalOpen = ref(false); // Modal-Zustand
let sortColumn = ref('');  // Spalte zum Sortieren
let sortDirection = ref('desc'); // Sortierrichtung ('asc' oder 'desc')

const { users, authProjekte, success, errors } = defineProps({
    users: {
        type: Object,
        default: () => ({ data: [], links: [] })
    },
    authProjekte: {
        type: Array,
        default: () => []
    },
    success: {
        type: String,
        default: ''
    },
    errors: {
        type: Object,
        default: () => ({})
    }

});
    // Zeige die Erfolgsmeldung an, wenn die Komponente geladen wird


// Zeige die Fehlermeldung an, wenn die Komponente geladen wird
// Zeige die Fehler an, wenn die Komponente geladen wird
if (errors && errors.length > 0) {
    Swal.fire({
        title: 'Fehler!',
        text: errors[0].message || 'Ein unbekannter Fehler ist aufgetreten.',
        icon: 'error',
        timer: 3000,
        timerProgressBar: true,
    });
}

if (success) {
    console.log('success');
    Swal.fire({
        title: 'Erfolg!',
        text: 'nachricht von amin masri',
        icon: 'success',
        timer: 3000,
        timerProgressBar: true,
    });
}

// Watch für Änderungen in der Suche
watch([search, selectedProject, sortColumn, sortDirection], () => {
    router.get('/benutzer',
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
    router.get('/benutzer', { search: search.value, project: value }, { preserveState: true });
});

// Gefilterte Projekte
const filteredProjects = computed(() => {
    return authProjekte.filter(projekt =>
        projekt.name.toLowerCase().includes(searchProject.value.toLowerCase())
    );
});

// Benutzer nach Projekt filtern
const filteredUsersByProject = computed(() => {
    if (!selectedProject.value) {
        return users.data; // Keine Filterung, wenn kein Projekt ausgewählt wurde
    }
    return users.data.filter(user => user.projekte.some(projekt => projekt.name === selectedProject.value));
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
    resetForm();
};

const resetForm = () => {
    newUser.value = {
        first_name: '',
        last_name:'',
        email: '',
        password: '',
        password_confirmation: '',
    };
};

// Benutzer hinzufügen

const addUser = async () => {
    // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
    if (!newUser.value.first_name || !newUser.value.last_name || !newUser.value.email || !newUser.value.password || !newUser.value.password_confirmation) {
        Swal.fire({
            title: 'Error!',
            text: 'Bitte füllen Sie alle erforderlichen Felder aus.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    // Überprüfe, ob das Passwort übereinstimmt
    if (newUser.value.password !== newUser.value.password_confirmation) {
        Swal.fire({
            title: 'Error!',
            text: 'Bitte geben Sie ein identisches Kennwort ein.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    try {
        // Sende die POST-Anfrage an den Server
        const response = await axios.post(route('user.store'), newUser.value);

        // Logge die Antwort des Servers
        console.log(response.data);

        // Zeige eine Erfolgsnachricht an
        Swal.fire({
            title: 'Erfolg!',
            text: 'Benutzer erfolgreich erstellt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

        // Optional: Formular zurücksetzen und Modal schließen
        resetForm();
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



// Neuen Benutzer
let newUser = ref({
    first_name: '',
    last_name:'',
    email: '',
    password: '',
    password_confirmation: '',
});


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
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{$t('alle_benutzer')}} {{ success }}    {{ errors }}
            </h2>
        </template>


        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModal" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
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
            <div @click="openModal" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>
        </div>

        <!-- Benutzer Tabelle -->
        <div class="overflow-x-auto snap-x">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class=" text-gray-600 uppercase bg-gray-300">
                    <tr>
                        <th @click="sortByColumn('id')" scope="col" class="px-6 py-3">
                            {{$t('id')}}
                            <i :class="sortColumn === 'id' && sortDirection === 'asc' ? 'las la-lg la-sort-numeric-down-alt' : 'las la-lg la-sort-numeric-up-alt'"></i>
                        </th>
                        <th @click="sortByColumn('first_name')" scope="col" class="px-6 py-3">
                            {{$t('vorname')}}
                            <i :class="sortColumn === 'first_name' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                        </th>
                        <th @click="sortByColumn('last_name')" scope="col" class="px-6 py-3">
                            {{$t('nachname')}}
                            <i :class="sortColumn === 'last_name' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                        </th>
                        <th @click="sortByColumn('email')" scope="col" class="px-6 py-3">
                            {{$t('email')}}
                            <i :class="sortColumn === 'email' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                        </th>
                        <th @click="sortByColumn('email')" scope="col" class="px-6 py-3">
                            {{ $t('projekte') }}
                            <i :class="sortColumn === 'email' && sortDirection === 'asc' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up'"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in filteredUsersByProject" :key="user.id" class="bg-white border-b">
                        <td class="px-6 py-4">{{ user.id }}</td>
                        <td class="px-6 py-4">{{ user.first_name }}</td>
                        <td class="px-6 py-4">{{ user.last_name }}</td>
                        <td class="px-6 py-4">{{ user.email }}</td>
                        <td class="px-6 py-4">
                            <span class="mr-4" v-for="projekt in user.projekte" :key="projekt.id">{{ projekt.name }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginierung -->
            <Pagination :pagination="users" />
        </div>

        <!-- Modal für neuen Benutzer -->
        <Modal v-if="isModalOpen" @close="closeModal">
            <template #header>
                <h2 class="text-lg font-bold text-gray-500 ">Neuen Benutzer hinzufügen</h2>
            </template>
            <template #body>
                <form @submit.prevent="addUser">
                    <div class="mb-4">
                        <label class="block mb-1">Vorname</label>
                        <input v-model="newUser.first_name" type="text" class="border px-2 py-1 w-full" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1">Nachname</label>
                        <input v-model="newUser.last_name" type="text" class="border px-2 py-1 w-full" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1">E-Mail</label>
                        <input v-model="newUser.email" type="email" class="border px-2 py-1 w-full" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1">Passwort</label>
                        <input v-model="newUser.password" type="password" class="border px-2 py-1 w-full" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1">Passwort bestätigen</label>
                        <input v-model="newUser.password_confirmation" type="password" class="border px-2 py-1 w-full" required />
                    </div>
                </form>
            </template>
            <template #footer>
                <button @click="closeModal" class="mr-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
                <button @click="addUser" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
            </template>
        </Modal>
    </app-layout>
</template>

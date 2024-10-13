<script setup>
import { ref, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import  Pagination from '@/Components/Pagination.vue';
import axios from 'axios';
import  Modal from '@/Components/ModalForm.vue';


// Search input state
let search = ref('');
defineProps({
    users: {
        type: Object,
        default: () => ({ data: [], links: [] })
    }
});
// Watch for changes in search and trigger a request
watch(search, value => {
    router.get('/benutzer', { search: value }, { preserveState: true });
});

// Method to handle page navigation
const goToPage = (url) => {
    if (url) {
        router.get(url, { search: search.value }, { preserveState: true });
    }
};

</script>

<script>
    export default {
        // Komponente referenzieren
        components: {
            AppLayout,
            Modal,
        },
        data() {
            return {
                isModalOpen: false,
                newUser: {
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                },
            };
        },

        methods: {
            // Methode für Toggle-Check
            toggleCheck(userId) {
                console.log("User ID: ", userId);  // Konsolenausgabe der Benutzer
                axios.post('/toggleCheck', { userId: userId })
                    .then(response => {
                        console.log("Response: ", response.data);
                        const user = this.users.data.find(u => u.id === userId);
                        if (user) {
                            user.eee = response.data.success;  // Rückgabe vom Server übernehmen
                        }
                    })
                    .catch(error => {
                        console.error("Error: ", error.response ? error.response.data : error.message);
                    });
            },
            openModal() {
        this.isModalOpen = true;
        },
        closeModal() {
        this.isModalOpen = false;
        this.resetForm();
        },
        resetForm() {
        this.newUser = {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        };
        },
        async addUser() {
        try {
            await this.$inertia.post('/users', this.newUser);
            this.closeModal();
        } catch (error) {
            // Fehlerbehandlung hier
            console.error(error);
        }
        },

        }
    };
</script>
<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{$t('alle_benutzer')}}
            </h2>
        </template>
         <!-- Search Input -->
         <div class="flex justify-around items-center mb-3">
            <label for="simple-search" class="sr-only">Search</label>
            <div class="relative ">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <i class="fa fa-search text-gray-500 dark:text-gray-400 pr-1"></i>
                </div>
                <input v-model="search" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Suchen ..."/>
            </div>


            <div @click="openModal"  class="flex items-center"><i class="fa fa-plus bg-orange-500  rounded-md px-5 py-3 text-white hover:text-orange-500 hover:bg-white hover:border hover:border-orange-500"></i></div>

            <modal v-if="isModalOpen" @close="closeModal">
                <template #header>
                        <h2 class="text-lg font-bold">Neuen Benutzer hinzufügen</h2>
                </template>
                <template #body>
                    <form @submit.prevent="addUser">
                    <div class="mb-4">
                        <label class="block mb-1">Vorname</label>
                        <input v-model="newUser.vorname" type="text" class="border px-2 py-1 w-full" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1">Nachname</label>
                        <input v-model="newUser.nachname" type="text" class="border px-2 py-1 w-full" required />
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
                    <button @click="closeModal" class="mr-2 bg-gray-300 px-4 py-2 rounded">Abbrechen</button>
                    <button @click="addUser" class="bg-blue-500 text-white px-4 py-2 rounded">Hinzufügen</button>
                </template>
            </modal>
        </div>

        <!-- User Table -->
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-xs text-gray-700 uppercase bg-gray-300 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">ID</th>
                        <th scope="col" class="px-6 py-3">Vorname</th>
                        <th scope="col" class="px-6 py-3">Nachname</th>
                        <th scope="col" class="px-6 py-3">Email</th>
                        <th scope="col" class="px-6 py-3">2 factor</th>
                        <th scope="col" class="px-6 py-3">Rolle</th>
                        <th scope="col" class="px-6 py-3">Check</th>
                        <th scope="col" class="px-6 py-3">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ user.id }}</th>
                        <td class="px-6 py-4">{{ user.first_name }}</td>
                        <td class="px-6 py-4">{{ user.last_name }}</td>
                        <td class="px-6 py-4">{{ user.email }}</td>
                        <td class="px-6 py-4">
                            <div v-if="user.two_factor_confirmed_at">
                                <i class="fa fa-check text-green-500 text-5xl"></i>
                            </div>
                            <div v-else>
                                <i class="fa fa-times text-red-500" aria-hidden="true"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span v-for="role in user.roles" :key="role.id">{{ role.name }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div @click="toggleCheck(user.id)" class="bg-orange-500 p-2 rounded text-white cursor-pointer">
                                {{ user.eee }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <Link :href="'/benutzer/' + user.id + '/edit'" class="text-orange-500 hover:text-orange-50">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>


            <!-- Pagination Links -->
            <Pagination :pagination="users"/>

        </div>

        <div id="modal" class="fixed inset-0  items-center justify-center bg-black bg-opacity-50 z-50 hidden">
            <div class="bg-white p-8 rounded-lg shadow-lg max-w-lg w-full">
                <h2 class="text-xl font-semibold mb-4">Modal Title</h2>
                <p class="mb-6">This is the content of the modal.</p>
                <div class="flex justify-end">
                    <button id="closeModal" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Close</button>
                </div>
            </div>
        </div>
    </app-layout>
</template>


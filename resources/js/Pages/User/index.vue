<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ref, watch } from 'vue';
import {router} from "@inertiajs/vue3"
let search = ref('');

//lesen von Daten die in seach geschrieben werden
watch(search, value => {
    router.get('/benutzer', { search: value }, {
        preserveState: true,
    });
});
</script>

<script>
export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

    // Props für empfangene Daten
    props: ['users'],

    // Mounted Lifecycle Hook
    mounted() {
        // alle_users aus users setzen (wird aus props geladen)
        console.log(this.users);  // Konsolenausgabe der Benutzer
    },

    methods: {
        // Methode für Toggle-Check
        toggleCheck(userId) {
            console.log("User ID: ", userId);  // Konsolenausgabe der Benutzer
            axios.post('/toggleCheck', { userId: userId })
                .then(response => {
                    console.log(response.data);
                    // Finde den Benutzer in der Liste und aktualisiere den `check` Status
                    const user = this.alle_users.find(u => u.id === userId);
                    if (user) {
                        user.check = response.data.check;  // Rückgabe vom Server übernehmen
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        }
    }
};
</script>

<template>
    <app-layout>
        <!-- Header Slot -->
        <template #header>Alle Users</template>
        <div v-if="users.length === 0">Keine Benutzer gefunden.</div>
        <div v-else>
        <!-- Benutzerausgabe -->
            <div
                class="m-6 p-4 bg-white rounded shadow flex justify-between"
                v-for="user in users"
                :key="user.id">
                <div>
                    <div class="text-2xl">{{ user.first_name }} {{ user.last_name }}</div>
                    <div>{{ user.email }}</div>
                    <!-- Toggle-Check bei Klick -->
                    <div
                        class="bg-orange-500 p-2 rounded text-white cursor-pointer"
                        @click="toggleCheck(user.id)"
                    >
                        {{ user.check }}
                    </div>
                </div>
                <div class="content-center my-auto">
                    <a :href="route('dashboard')" class="bg-orange-500 p-2">---</a>
                </div>
            </div>
        </div>

        <div class="flex items-center max-w-sm mx-auto">
            <label for="simple-search" class="sr-only">Search</label>
            <div class="relative w-full mb-3">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <i class="fa fa-search text-gray-500 dark:text-gray-400 pr-1"></i>
                </div>
                <input v-model="search" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Suchen ..."/>
            </div>
        </div>

        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-xs text-gray-700 uppercase bg-gray-300 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Vorname
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Nachname
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Email
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Check
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr  v-for="user in users" :key="user.id"
                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ user.first_name }}
                        </th>
                        <td class="px-6 py-4">
                            {{ user.last_name }}
                        </td>
                        <td class="px-6 py-4">
                            {{user.email}}
                        </td>
                        <td class="px-6 py-4">
                            {{user.check}}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </app-layout>
</template>

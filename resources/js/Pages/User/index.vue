<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';


export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

    // Props für empfangene Daten
    props: ['users'],

    data() {
        return {
            alle_users: [], // Leeres Array wird initialisiert

        };
    },

    // Mounted Lifecycle Hook
    mounted() {
        // alle_users aus users setzen (wird aus props geladen)
        this.alle_users = this.users;
        console.log(this.alle_users);  // Konsolenausgabe der Benutzer
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

        <!-- Benutzerausgabe -->
        <div
            class="m-6 p-4 bg-white rounded shadow flex justify-between"
            v-for="user in alle_users"
            :key="user.id">
            <div>
                <div class="text-2xl">{{ user.name }}</div>
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

        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-xs text-gray-700 uppercase bg-gray-300 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Benutzername
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
                    <tr  v-for="user in alle_users" :key="user.id"
                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{user.name}}
                        </th>
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

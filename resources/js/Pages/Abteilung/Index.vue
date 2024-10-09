<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>

<script>



export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

    // Props für empfangene Daten
    props: ['alleAbteilungen'],

    data() {
        return {
            alle_abteilungen: [], // Leeres Array wird initialisiert
        };
    },

    // Mounted Lifecycle Hook
    mounted() {
        // alle Abteilungen aus abteilung setzen (wird aus props geladen)
        this.alle_abteilungen = this.alleAbteilungen;
        console.log(this.alle_abteilungen);  // Konsolenausgabe der Benutzer
    },
    computed: {
        // Berechnete Eigenschaft für die Darstellung
        formattedAbteilungen() {
            return this.alle_abteilungen.map(abteilung => ({
                id: abteilung.id,
                name: abteilung.name,
                abteilungsleiter: abteilung.user ? abteilung.user.name : 'Kein Leiter' // Fallback bei fehlendem User
            }));
        }
    },

    methods: {
        // Methode für Toggle-Check

    }
};
</script>

<template>
    <app-layout>
        <!-- Header Slot -->
        <template #header>Alle Abteilungen</template>

        <!-- Benutzerausgabe -->
        <div
            class="m-6 p-4 bg-white rounded shadow flex justify-between"
            v-for="abteilung in alle_abteilungen"
            :key="abteilung.id">
            <div>
                <div class="text-2xl">{{ abteilung.name }}</div>
                <div>{{ abteilung.abteilungsleiter }}</div>

            </div>
            <div class="content-center my-auto">
                <a :href="route('dashboard')" class="bg-orange-500 p-2">---</a>
            </div>
        </div>

        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-xs text-gray-700 uppercase bg-gray-300 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">ID.</th>
                        <th scope="col" class="px-6 py-3">Abteilung</th>
                        <th scope="col" class="px-6 py-3">Abteilungsleiter</th>
                        <th scope="col" class="px-6 py-3">Aktionen</th> <!-- Aktionen hinzufügen -->

                    </tr>
                </thead>
                <tbody>
                    <tr  v-for="abteilung in formattedAbteilungen" :key="abteilung.id"
                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{abteilung.id}}
                        </th>
                        <td class="px-6 py-4">
                            {{abteilung.name}}
                        </td>
                        <td class="px-6 py-4">
                            {{abteilung.abteilungsleiter}}
                        </td>
                        <td class="px-6 py-4">
                            <Link :href="route('dashboard')" class="bg-orange-500 p-2 text-white rounded">Details</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </app-layout>
</template>

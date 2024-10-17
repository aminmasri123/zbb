<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { Link } from '@inertiajs/vue3';


    defineProps({ abteilungen: Object });
</script>




<script>



export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

    // Props für empfangene Daten




};
</script>

<template>
    <app-layout>
        <!-- Header Slot -->
        <template #header>Abteilungen</template>
        <!-- Benutzerausgabe -->
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
                    <tr  v-for="abteilung in abteilungen.data" :key="abteilung.id"
                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{abteilung.id}}
                        </th>
                        <td class="px-6 py-4">
                            {{abteilung.name}}
                        </td>
                        <td class="px-6 py-4">
                            <p>{{abteilung.user.first_name}} {{abteilung.user.last_name}}</p>
                            <span v-for="abteilungsassistent in abteilung.abteilungsassistente" :key="abteilungsassistent.id" class="text-xs bg-orange-200 rounded p-1 mr-2">
                                {{abteilungsassistent.user.first_name}} {{abteilungsassistent.user.last_name}}
                            </span>
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

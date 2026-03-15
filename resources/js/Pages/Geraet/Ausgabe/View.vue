<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, defineProps} from 'vue'

import ApplicationMark from '../../../Components/ApplicationMark.vue'
import { printBlock, pdfBlock } from '@/Utils/printHelper.js'
import AddForm from './AddForm.vue'
const props = defineProps({
    ausgabe: Object,
    nichtAusgegebeneGeraete: Array
})
let isModalAddFormOpen = ref(false)
const openModalAddForm = () => { isModalAddFormOpen.value = true }
const closeModalAddForm= () => { isModalAddFormOpen.value = false }

</script>

<template>

    <Head title="Ausgabeschein" />

    <AppLayout>

        <template #header>
            <div class="flex justify-between items-center">

                <h2 class="text-xl font-bold">Ausgabeschein</h2>

                <div class="flex gap-2">
                    <button class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200" @click="openModalAddForm" ><i class="las la-plus"></i></button>
                    <button class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200" @click="pdfBlock"><i class="las la-file-pdf"></i>PDF</button>
                    <button class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200" @click="printBlock"> <i class="la la-print" aria-hidden="true"></i>Print</button>
                    <button class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200"> <a :href="route('geraet.ausgabe.export.excel', ausgabe.id)" class="btn btn-white"> <i class="las la-file-excel"></i> Excel </a></button>
                </div>
            </div>
        </template>


        <div class="max-w-5xl mx-auto">

            <!-- PRINT AREA -->
             <div class="bg-white shadow rounded-lg p-8">
                <div id="printArea" >

                    <div class="flex justify-end">
                        <ApplicationMark class="w-28" />
                        <!-- <img src="/storage/img/logo.png" class="w-28"> -->
                    </div>

                    <h2 class="text-center text-2xl font-bold mt-6 mb-6">Ausgabeschein</h2>


                    <div class="grid grid-cols-2 mb-8">

                        <div>
                            <p><b>Projekt:</b> {{ ausgabe.projekte.name }}</p>
                            <!-- <p><b>Kostenstelle:</b> {{ ausgabe.projekte.kostenstelle }}</p>
    -->
                        </div>

                        <div class="text-right">
                            <p class="uppercase font-bold">
                                Ausgabeschein #{{ ausgabe.ausgabescheinNr }}
                            </p>

                            <p>
                                {{ new Date().toLocaleDateString('de-DE') }}
                            </p>
                        </div>

                    </div>


                    <p class="text-center mb-6">
                        Hiermit bestätigt der Empfänger den Erhalt des folgenden:
                    </p>


                    <table class="w-full border border-gray-300">

                        <thead class="bg-gray-100">
                            <tr>

                                <th class="p-2 border">Nr</th>
                                <th class="p-2 border">ProduktID</th>
                                <th class="p-2 border">Seriennr</th>
                                <th class="p-2 border">Gerät</th>
                                <th class="p-2 border">Zustand</th>

                            </tr>
                        </thead>

                        <tbody>

                            <tr v-for="(g, index) in ausgabe.geraete" :key="g.id" class="text-center">

                                <td class="border p-2">
                                    {{ index + 1 }}
                                </td>

                                <td class="border p-2">
                                    {{ g.productID }}
                                </td>

                                <td class="border p-2">
                                    {{ g.sn }}
                                </td>

                                <td class="border p-2">
                                    {{ g.geraet }}
                                </td>

                                <td class="border p-2">
                                    {{ g.zustand }}
                                </td>

                            </tr>

                        </tbody>
                    </table>



                    <div class="grid grid-cols-2 mt-10 gap-6">

                        <div>

                            <p><b>Menge:</b> {{ ausgabe.geraete.length }} Gerät(e)</p>

                            <p class="mt-3">
                                <b>Übergabedatum:</b>
                                {{ new Date(ausgabe.ausgabe).toLocaleDateString('de-DE') }}
                            </p>

                            <p class="mt-3">
                                <b>Empfänger:</b>
                                {{ ausgabe.ausleiher.vorname }} {{ ausgabe.ausleiher.nachname }}
                            </p>

                        </div>

                    </div>



                    <div class="grid grid-cols-2 mt-16">

                        <div>

                            <p>{{ new Date().toLocaleDateString('de-DE') }}</p>

                            <div class="border-t w-40 mt-1"></div>
                            <p>Datum</p>

                        </div>

                        <div class="text-right">

                            <div class="border-t w-64 ml-auto mt-6"></div>
                            <p>Unterschrift</p>

                        </div>

                    </div>


                </div>
            </div>
        </div>
        <AddForm :visible="isModalAddFormOpen" :nichtAusgegebeneGeraete="nichtAusgegebeneGeraete" @close="closeModalAddForm"  :ausgabe="ausgabe" />

    </AppLayout>
</template>

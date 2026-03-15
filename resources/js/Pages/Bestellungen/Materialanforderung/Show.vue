<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { defineProps, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import Modal from '@/Components/ModalForm.vue'
const props = defineProps({
    anforderung: Object,
    canConfirmSachlich: Boolean, //Abteilungsleitung
    canConfirmKaufmaenisch: Boolean, //Geschäftsführung und Kaufmännische Leitung
})

// Modal State
const visible = ref(false)

// Aktion: Genehmigen
const genehmigen = () => {
    router.post(route('materialanforderung.sachlich.genehmigen', props.anforderung.id), {}, {
        onSuccess: () => {
            visible.value = false
        }
    })
}
</script>

<template>

    <Head title="Materialanforderung" />

    <AppLayout>

        <template #header>

            <div class="flex justify-between items-center">

                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        Materialanforderung #{{ anforderung.id }}
                    </h2>

                    <p class="text-sm text-gray-500">
                        Übersicht der Bestellung
                    </p>
                </div>

                <span class="px-4 py-1 text-sm rounded-full bg-blue-100 text-blue-700 font-medium">
                    {{ anforderung.status ?? 'Entwurf' }}
                </span>
                     <div>
                        <!-- Button erscheint nur wenn Entwurf + Permission -->
                        <button 
                            v-if="anforderung.status === 'Entwurf' && canConfirmSachlich" 
                            @click="visible = true"
                            class="bg-green-500 text-white px-4 py-2 rounded"
                            >
                            Genehmigen
                        </button>
                         
                 </div>
            </div>
        </template>

        <div class="space-y-8">

            <!-- HEADER CARD -->
            <div class="grid grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                    <p class="text-gray-500 text-sm">Projekt</p>
                    <p class="text-lg font-semibold mt-1">
                        {{ anforderung.projekt }}
                    </p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                    <p class="text-gray-500 text-sm">Besteller</p>
                    <p class="text-lg font-semibold mt-1">
                        {{ anforderung.besteller.vorname }}
                        {{ anforderung.besteller.nachname }}
                    </p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                    <p class="text-gray-500 text-sm">Kostenstelle</p>
                    <p class="text-lg font-semibold mt-1">
                        {{ anforderung.kostenstelle }}
                    </p>
                </div>
            </div>

            <!-- BEMERKUNG -->
            <div class="grid grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border">
                    <h3 class="font-semibold text-gray-800 mb-2">Bemerkung</h3>
                    <p class="text-gray-600 leading-relaxed">{{ anforderung.bemerkungen || 'Keine Bemerkung vorhanden.' }}</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border">
                   <div class="flex gap-8 items-center h-full">
                        <div>
                            <p class="text-sm text-gray-500">Gesamtsumme</p>
                            <p class="text-lg font-semibold text-green-600">{{ anforderung.gesamtpreis }} €</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">inkl. MwSt</p>
                            <p class="text-2xl font-bold text-red-600">{{ anforderung.endsumme }} €</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ARTIKEL CARD -->
            <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">

                <div class="flex justify-between items-center p-6 border-b">

                    <h3 class="text-lg font-semibold text-gray-800">
                        Artikel
                    </h3>
                </div>
                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead class="bg-gray-50 text-gray-500 text-sm">
                            <tr>
                                <th class="px-6 py-4 text-left">Pos</th>
                                <th class="px-6 py-4 text-left">Link</th>
                                <th class="px-6 py-4 text-left">Artikel</th>
                                <th class="px-6 py-4 text-left">Stück</th>
                                <th class="px-6 py-4 text-left">Netto/Stk</th>
                                <th class="px-6 py-4 text-left">MwSt</th>
                                <th class="px-6 py-4 text-left">Netto Gesamt</th>
                                <th class="px-6 py-4 text-left">Brutto Gesamt</th>
                            </tr>
                        </thead>


                        <tbody class="divide-y">

                            <tr v-for="p in anforderung.artikeln" :key="p.id" class="hover:bg-gray-50 transition">

                                <td class="px-6 py-4 font-medium">
                                    {{ p.pos }}
                                </td>
                                <td class="px-6 py-4">
                                    <a v-if="p.link" :title="p.link" :href="p.link" target="_blank"
                                        class="text-blue-600 hover:text-blue-800 hover:underline text-sm">
                                        Produkt ansehen
                                    </a>
                                </td>

                                <td class="px-6 py-4">
                                    {{ p.artikel }}
                                </td>

                                <td class="px-6 py-4">
                                    {{ p.stück }}
                                </td>

                                <td class="px-6 py-4">
                                    {{ p.einzelpreis }} €
                                </td>

                                <td class="px-6 py-4">
                                    {{ p.mwst }} %
                                </td>

                                <td class="px-6 py-4">
                                    {{ (p.einzelpreis * p.stück).toFixed(2) }} €
                                </td>

                                <td class="px-6 py-4 font-semibold text-gray-800">
                                    {{ (p.gesamtpreis * (1 + p.mwst / 100)).toFixed(2) }} €
                                </td>


                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            
    </AppLayout>

<Modal v-if="visible" @close="visible = false">

    <template #header>
        Materialanforderung genehmigen
    </template>

    <template #body>
        <form>
            <h1>Sind Sie sicher, dass Sie diese Bestellung bestätigen möchten?</h1>
        </form>
    </template>

    <template #footer>
        <button @click="genehmigen" class="bg-zbb text-white px-4 py-2 rounded">
            Genehmigen
        </button>

        <button @click="visible = false" class="border px-4 py-2 rounded">
            Abbrechen
        </button>
    </template>

</Modal>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps, watch, computed } from 'vue'
import { router, Link, Head } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import ModalDestroy from '@/Components/ModalDestroyForm.vue'

import ModalCreateRueckgabe from '@/Pages/Geraet/Rueckgabe/ModalCreate.vue'

let showModalCreate = ref(false)

let search = ref('')
let seite = 'geraetrueckgabe'
let ausgabeToDelete = ref(null)
let showModalLöschen = ref(false)
let rueckgabeToDelete = ref(null)
const props = defineProps({
    rueckgaben: Array,
    ausgaben: Array,
    rueckgeber: Array,
    geraete: Array,
})

const openModalCreate = () => {
    showModalCreate.value = true
}

const closeModalCreate = () => {
    showModalCreate.value = false
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('de-DE')
}
const confirmDelete = (rueckgabe) => {
    rueckgabeToDelete.value = {
        id: rueckgabe.id,
        name: rueckgabe.rueckgabebescheinNr
    }
    showModalLöschen.value = true
}

const handleDelete = (id) => {
    router.reload({ only: ['rueckgaben'] })
    showModalLöschen.value = false
}

const filteredRueckgaben = computed(() => {

  if (!search.value) return props.rueckgaben

  return props.rueckgaben.filter(r =>
    r.rueckgabescheinNr.toString().includes(search.value)
  )

})

watch(search, () => {
    router.get('/rueckgaben', { search: search.value }, { preserveState: true, replace: true })
})
</script>

<template>

    <Head title="Rückgaben" />

    <AppLayout>

        <template #header>Rückgaben</template>

        <!-- Suchfeld -->

        <div class="flex justify-around items-center mb-3">

            <div @click="openModalCreate" class="flex items-center">
                <i
                    class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb"></i>
            </div>

            <input v-model="search" type="text" class="border border-gray-300 text-sm block w-full p-2.5"
                placeholder="Rückgabe suchen ..." />

            <Link :href="route('geraet.rueckgabe.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb"></i>
            </Link>

        </div>


        <!-- Tabelle -->

        <div class=" shadow rounded-lg">

            <table class="min-w-[1200px] w-full text-sm divide-y divide-gray-200">

                <thead class="bg-gray-200 text-gray-700 uppercase sticky top-0">

                    <tr>

                        <th class="px-4 py-3 text-left">*</th>

                        <th class="px-4 py-3 text-left">
                            Rückgabeschein
                        </th>

                        <th class="px-4 py-3 text-left">
                            Ausgabeschein
                        </th>

                        <th class="px-4 py-3 text-left">
                            Ausleiher
                        </th>

                        <th class="px-4 py-3 text-left">
                            Projekt
                        </th>

                        <th class="px-4 py-3 text-left">
                            Produkt ID
                        </th>

                        <th class="px-4 py-3 text-left">
                            Rückgabedatum
                        </th>

                        <th class="px-4 py-3 text-center">
                            <i class="las la-cog"></i>
                        </th>

                    </tr>

                </thead>


                <tbody class="bg-white divide-y divide-gray-200">

                    <tr v-for="rueckgabe in filteredRueckgaben" :key="rueckgabe.id" class="hover:bg-gray-50">

                        <td class="px-4 py-3">
                            {{ rueckgabe.id }}
                        </td>


                        <td class="px-4 py-3 font-semibold">
                            {{ rueckgabe.rueckgabescheinNr }}
                        </td>


                        <td class="px-4 py-3">
                            {{ rueckgabe.ausgabe.ausgabescheinNr }}
                        </td>


                        <td class="px-4 py-3">
                            {{ rueckgabe.ausgabe.ausleiher.vorname }}
                            {{ rueckgabe.ausgabe.ausleiher.nachname }}
                        </td>


                        <td class="px-4 py-3">
                            {{ rueckgabe.ausgabe.projekte.name }}
                        </td>

                        <td class="px-4 py-3">

                            <ul class="m-0 p-0 list-none">

                                <li v-for="geraet in rueckgabe.geraete" :key="geraet.id">
                                    {{ geraet.productID }}
                                </li>

                            </ul>

                        </td>


                        <td class="px-4 py-3">
                            {{ formatDate(rueckgabe.rueckgabe) }}
                        </td>


                        <td class="px-4 py-3 text-center">

                            <Dropdown>

                                <template #trigger>
                                    <i class="la la-ellipsis-v cursor-pointer"></i>
                                </template>

                                <template #content>

                                    <span
                                        class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                        Bearbeiten
                                        <i class="las la-edit"></i>
                                    </span>

                                    <span @click="confirmDelete(rueckgabe)"
                                        class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                        Löschen
                                        <i class="las la-trash-alt"></i>
                                    </span>

                                </template>

                            </Dropdown>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </AppLayout>

    <ModalCreateRueckgabe :visible="showModalCreate" :rueckgeber="rueckgeber" :ausgaben="ausgaben" :geraete="geraete"
        :ablageorte="ablageorte" @close="closeModalCreate"/>


        <ModalDestroy
v-if="showModalLöschen"
:seite="seite"
@delete="handleDelete"
@close="showModalLöschen = false"
:toDelete="rueckgabeToDelete"
/>
</template>

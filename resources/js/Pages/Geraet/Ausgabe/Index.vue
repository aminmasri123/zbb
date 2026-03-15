<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps, watch } from 'vue'
import { router, Link, Head } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import ModalDestroy from '@/Components/ModalDestroyForm.vue'


import ModalCreate from '@/Pages/Geraet/Ausgabe/ModalCreate.vue';
import ModalEdit from '@/Pages/Geraet/Ausgabe/ModalEdit.vue';
let seite = 'geraetausgabe';
let search = ref('')
let ausgabeToDelete = ref(null)
let showModalLöschen = ref(false)
let isModalCreateOpen = ref(false)
let isModalEditOpen = ref(false)
let ausgabeToEdit = ref(null)

const props = defineProps({
    ausgaben: Array,
    ausleiher: Array,
    projekte: Array,
    geraete: Array
})

let localAusgaben = ref([...props.ausgaben])
let filteredAusgaben = ref([...localAusgaben.value])

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('de-DE')
}

const openModalCreate = () => { isModalCreateOpen.value = true }
const closeModalCreate = () => { isModalCreateOpen.value = false }

const openModalEdit = (ausgabe) => {
    ausgabeToEdit.value = ausgabe
    isModalEditOpen.value = true
}

const closeModalEdit = () => { isModalEditOpen.value = false }

const confirmDelete = (ausgabe) => {
    ausgabeToDelete.value = {
        id: ausgabe.id,
        name: ausgabe.ausgabescheinNr
    }
    showModalLöschen.value = true
}

const handleDelete = (id) => {
    localAusgaben.value = localAusgaben.value.filter(a => a.id !== id)
    applySearchFilter()
    showModalLöschen.value = false
}

const applySearchFilter = () => {
    if (search.value) {
        filteredAusgaben.value = localAusgaben.value.filter(a =>
            a.ausgabescheinNr.toString().includes(search.value)
        )
    } else {
        filteredAusgaben.value = [...localAusgaben.value]
    }
}

watch(search, () => {
    router.get('/ausgabe', { search: search.value }, { preserveState: true, replace: true })
    applySearchFilter()
})
</script>

<template>

    <Head title="Ausgabe" />

    <AppLayout>

        <template #header>Ausgabe</template>

        <!-- Suchleiste -->

        <div class="flex justify-around items-center mb-3">

            <div @click="openModalCreate" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb"></i>
            </div>

            <input v-model="search" type="text" class="border border-gray-300 text-sm block w-full p-2.5" placeholder="Suchen ..." />

            <Link :href="route('geraet.ausgabe.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb"></i>
            </Link>

        </div>


        <!-- Tabelle -->

        <div class="shadow rounded-lg">

            <table class="min-w-[1200px] w-full text-sm divide-y divide-gray-200">

                <thead class="bg-gray-200 text-gray-700 uppercase">
                    <tr class="text-left">
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Ausgabeschein</th>
                        <th class="px-4 py-3">Ausleiher</th>
                        <th class="px-4 py-3">Projekt</th>
                        <th class="px-4 py-3">Kostenstelle</th>
                        <th class="px-4 py-3">Produkt ID</th>
                        <th class="px-4 py-3">Datum</th>
                        <th class="px-4 py-3 text-center">*</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="ausgabe in filteredAusgaben" :key="ausgabe.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ ausgabe.id }}</td>
                        <td class="px-4 py-3 font-semibold">
                            <Link :href="route('ausgabe.view', ausgabe.id)" target="_blank"> {{ ausgabe.ausgabescheinNr }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ ausgabe.ausleiher.vorname }} {{ ausgabe.ausleiher.nachname }}</td>

                        <td class="px-4 py-3">
                            {{ ausgabe.projekte.name }}
                        </td>

                        <td class="px-4 py-3">
                            <ul class="m-0 p-0 list-none">
                                <li v-for="kostenstelle in ausgabe.projekte.kostenstellen" :key="kostenstelle.id">
                                    {{ kostenstelle.kostenstelle }}
                                </li>
                            </ul>
                        </td>

                        <td class="px-4 py-3">
                            <ul class="m-0 p-0 list-none">
                                <li v-for="geraet in ausgabe.geraete" :key="geraet.id">
                                    {{ geraet.productID }}
                                </li>
                            </ul>
                        </td>

                        <td class="px-4 py-3">
                            {{ formatDate(ausgabe.ausgabe) }}
                        </td>

                        <td class="px-4 py-3 text-center">

                            <Dropdown>

                                <template #trigger>
                                    <i class="la la-ellipsis-v cursor-pointer"></i>
                                </template>

                                <template #content>

                                    <span
                                        class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        @click="openModalEdit(ausgabe)">
                                        Bearbeiten
                                        <i class="las la-edit"></i>
                                    </span>

                                    <span
                                        class="flex justify-between items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        @click="confirmDelete(ausgabe)">
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


        <!-- Modals -->

        <ModalCreate :visible="isModalCreateOpen" :ausleiher="ausleiher" :projekte="projekte" :geraete="geraete"
            @close="closeModalCreate" />

        <ModalEdit :visible="isModalEditOpen" :toEdit="ausgabeToEdit" @close="closeModalEdit" />

        <ModalDestroy v-if="showModalLöschen" :seite="seite" @delete="handleDelete" @close="showModalLöschen = false"
            :toDelete="ausgabeToDelete" />

    </AppLayout>

</template>

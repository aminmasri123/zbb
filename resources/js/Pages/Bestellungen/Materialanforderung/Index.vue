<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps, watch } from 'vue'
import { router, Link, Head } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import ModalDestroy from '@/Components/ModalDestroyForm.vue'
import ModalCreate from './ModalCreate.vue'
import { formatDateTime } from '@/utils/dateFormat.js';

const props = defineProps({
    anforderungen: Array,
    user: Object,
    projekt: Object,
})

let localAnforderungen = ref([...props.anforderungen])
let filteredAnforderungen = ref([...localAnforderungen.value])
let search = ref('')
let showModalCreate = ref(false)
let selectedToDelete = ref(null)
let showModalDelete = ref(false)

const openModalCreate = () => showModalCreate.value = true
const openModalEdit = (item) => {
    router.get(route('materialanforderung.show', item.id), { edit: 1 })
}
const confirmDelete = (item) => { selectedToDelete.value = item; showModalDelete.value = true }
const handleDelete = (id) => {
    localAnforderungen.value = localAnforderungen.value.filter(a => a.id !== id)
    filteredAnforderungen.value = [...localAnforderungen.value]
    showModalDelete.value = false
}

const applySearchFilter = () => {
    filteredAnforderungen.value = search.value
        ? localAnforderungen.value.filter(a => a.projekt.toLowerCase().includes(search.value.toLowerCase()))
        : [...localAnforderungen.value]
}

watch(search, () => {
    router.get(route('materialanforderung.index'), { search: search.value }, { preserveState: true, replace: true })
    applySearchFilter()
})
</script>

<template>

    <Head title="Bestellungen" />
    <AppLayout>
        <template #header>Bestellungen</template>

        <!-- Suchleiste + Add Button -->

        <div class="flex justify-around items-center mb-3">


            <Link :href="route('materialanforderung.create')" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb"></i>
            </Link>


            <input v-model="search" type="text" class="border border-gray-300 text-sm block w-full p-2.5"
                placeholder="Suchen ..." />

            <Link :href="route('materialanforderung.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb"></i>
            </Link>


        </div>

        <!-- Tabelle -->
        <div class="overflow-x-auto shadow rounded-lg">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-200 uppercase text-left">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Projekt</th>
                        <th class="px-4 py-2">Antragsteller</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Kostenstelle</th>
                        <th class="px-4 py-2">Gesamtpreis</th>
                        <th class="px-4 py-2">Endsumme</th>
                        <th class="px-4 py-2">Beantragt am</th>
                        <th class="px-4 py-2 text-center">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in filteredAnforderungen" :key="item.id" class="hover:bg-gray-50">
                        <td class="px-4 py-2"> <Link :href="route('materialanforderung.show', item.id)" target="_blank"> {{ item.id }}</Link></td>
                        <td class="px-4 py-2 font-semibold">{{ item.projekt.name }}</td>
                        <td class="px-4 py-2 font-semibold">{{ item.besteller?.first_name }} {{ item.besteller?.last_name }}</td>
                        <td class="px-4 py-2 font-semibold">{{ item.status }}</td>
                        <td class="px-4 py-2">{{ item.kostenstelle }}</td>
                        <td class="px-4 py-2">{{ item.gesamtpreis }}</td>
                        <td class="px-4 py-2">{{ item.endsumme }}</td>
                        <td class="px-4 py-2">{{ formatDateTime(item.created_at) }}</td>
                        <td class="px-4 py-2 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <i class="la la-ellipsis-v cursor-pointer"></i>
                                </template>
                                <template #content>
                                    <div class="flex flex-col py-1 text-left">
                                        <button
                                            type="button"
                                            class="w-full px-4 py-2 text-left hover:bg-gray-100"
                                            @click="openModalEdit(item)"
                                        >
                                            Bearbeiten
                                        </button>
                                        <button
                                            type="button"
                                            class="w-full px-4 py-2 text-left hover:bg-gray-100"
                                            @click="confirmDelete(item)"
                                        >
                                            Löschen
                                        </button>
                                    </div>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modals -->
        <ModalCreate v-model:visible="showModalCreate" :user="user" :projekt="projekt" />
        <ModalDestroy v-if="showModalDelete" :toDelete="selectedToDelete" @delete="handleDelete"
            @close="showModalDelete = false" />
    </AppLayout>
</template>

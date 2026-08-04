<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref, defineProps, watch } from 'vue'
import { router, Link, Head } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import ModalDestroy from '@/Components/ModalDestroyForm.vue'
import ModalCreate from './ModalCreate.vue'
import { formatDateTime } from '@/utils/dateFormat.js';
import { usePermissions } from '@/utils/permissions';

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
const { can } = usePermissions()
const canCreateMaterialanforderung = computed(() => can('materialanforderung.create'))
const canShowMaterialanforderung = computed(() => can('materialanforderung.show'))
const canUpdateMaterialanforderung = computed(() => can('materialanforderung.update'))
const canDeleteMaterialanforderung = computed(() => can('materialanforderung.destroy'))
const canManageMaterialanforderung = computed(() => canUpdateMaterialanforderung.value || canDeleteMaterialanforderung.value)

const openModalCreate = () => {
    if (!canCreateMaterialanforderung.value) return
    showModalCreate.value = true
}
const openModalEdit = (item) => {
    if (!canUpdateMaterialanforderung.value) return
    router.get(route('materialanforderung.show', item.id), { edit: 1 })
}
const confirmDelete = (item) => {
    if (!canDeleteMaterialanforderung.value) return
    selectedToDelete.value = item
    showModalDelete.value = true
}
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

        <div class="flex items-stretch mb-3 overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">


            <Link
                v-if="canCreateMaterialanforderung"
                :href="route('materialanforderung.create')"
                class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Materialanforderung anlegen"
            >
                <i class="la la-plus"></i>
            </Link>


            <input v-model="search" type="text" class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                placeholder="Suchen ..." />

            <Link
                :href="route('materialanforderung.index')"
                class="flex w-14 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Aktualisieren"
            >
                <i class="la la-refresh"></i>
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
                        <th v-if="canManageMaterialanforderung" class="px-4 py-2 text-center">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in filteredAnforderungen" :key="item.id" class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <Link v-if="canShowMaterialanforderung" :href="route('materialanforderung.show', item.id)" target="_blank"> {{ item.id }}</Link>
                            <span v-else>{{ item.id }}</span>
                        </td>
                        <td class="px-4 py-2 font-semibold">{{ item.projekt.name }}</td>
                        <td class="px-4 py-2 font-semibold">{{ item.besteller?.first_name }} {{ item.besteller?.last_name }}</td>
                        <td class="px-4 py-2 font-semibold">{{ item.status }}</td>
                        <td class="px-4 py-2">{{ item.kostenstelle }}</td>
                        <td class="px-4 py-2">{{ item.gesamtpreis }}</td>
                        <td class="px-4 py-2">{{ item.endsumme }}</td>
                        <td class="px-4 py-2">{{ formatDateTime(item.created_at) }}</td>
                        <td v-if="canManageMaterialanforderung" class="px-4 py-2 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <i class="la la-ellipsis-v cursor-pointer"></i>
                                </template>
                                <template #content>
                                    <div class="flex flex-col py-1 text-left">
                                        <button
                                            v-if="canUpdateMaterialanforderung"
                                            type="button"
                                            class="w-full px-4 py-2 text-left hover:bg-gray-100"
                                            @click="openModalEdit(item)"
                                        >
                                            Bearbeiten
                                        </button>
                                        <button
                                            v-if="canDeleteMaterialanforderung"
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
        <ModalCreate v-if="canCreateMaterialanforderung" v-model:visible="showModalCreate" :user="user" :projekt="projekt" />
        <ModalDestroy v-if="canDeleteMaterialanforderung && showModalDelete" :toDelete="selectedToDelete" @delete="handleDelete"
            @close="showModalDelete = false" />
    </AppLayout>
</template>

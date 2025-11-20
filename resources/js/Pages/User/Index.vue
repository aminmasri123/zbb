<script setup>
import { ref, watch, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Swal from 'sweetalert2';

const { users, authProjekte, rollen } = defineProps({
    users: Object,
    authProjekte: Array,
    rollen: Array
});

// Reactive states
let search = ref('');
let selectedProject = ref(null);
let sortColumn = ref('');
let sortDirection = ref('asc');
let searchProject = ref('');

let userList = ref([...users.data]);

// Auto-update table when pagination updates
watch(() => users.data, (newValue) => {
    userList.value = [...newValue];
});

// TABLE SORTING
const sortByColumn = (column) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
};

// Auto-refresh when filters change
watch([search, selectedProject, sortColumn, sortDirection], () => {
    router.get('/benutzer', {
        search: search.value,
        project: selectedProject.value,
        sort: sortColumn.value,
        direction: sortDirection.value
    }, { preserveState: true, replace: true });
});

// Project filtering
const filteredProjects = computed(() =>
    authProjekte.filter((projekt) =>
        projekt.name.toLowerCase().includes(searchProject.value.toLowerCase())
    )
);

const filteredUsers = computed(() => {
    if (!selectedProject.value) return userList.value;

    return userList.value.filter((user) =>
        user.projekte?.some((p) => p.name === selectedProject.value)
    );
});

// Delete modal
let showModalLöschen = ref(false);
let userToDelete = ref(null);

const confirmDelete = (user) => {
    userToDelete.value = {
        id: user.id,
        name: user.person?.vorname + ' ' + user.person?.nachname
    };
    showModalLöschen.value = true;
};
</script>

<template>
    <Head title="Personal" />

    <AppLayout>
        <template #header>Team</template>

        <!-- Suchzeile -->
        <div class="flex justify-between items-center mb-4">
            <input
                v-model="search"
                class="border border-gray-300 text-gray-900 text-sm p-2.5"
                placeholder="Suchen ..."
            />

            <!-- Projekt dropdown -->
            <Dropdown align="right">
                <template #trigger>
                    <button class="border px-3 py-2 bg-white">Projekte ▾</button>
                </template>

                <template #content>
                    <div class="px-4 py-2">
                        <input
                            v-model="searchProject"
                            class="border w-full p-2 text-sm"
                            placeholder="Projekt suchen..."
                        />
                    </div>

                    <span
                        v-for="projekt in filteredProjects"
                        :key="projekt.id"
                        @click="selectedProject = projekt.name"
                        class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                    >
                        {{ projekt.name }}
                    </span>

                    <span @click="selectedProject = null"
                          class="block px-4 py-2 hover:bg-gray-100 cursor-pointer">
                        Zurücksetzen
                    </span>
                </template>
            </Dropdown>
        </div>

        <!-- Tabelle -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-200 uppercase">
                    <tr>
                        <th @click="sortByColumn('id')" class="px-6 py-3 cursor-pointer">
                            ID
                            <i :class="sortColumn === 'id' && sortDirection === 'asc'
                                ? 'las la-sort-numeric-down'
                                : 'las la-sort-numeric-up'"></i>
                        </th>

                        <th @click="sortByColumn('vorname')" class="px-6 py-3 cursor-pointer">
                            Vorname
                            <i :class="sortColumn === 'vorname' && sortDirection === 'asc'
                                ? 'las la-sort-alpha-down'
                                : 'las la-sort-alpha-up'"></i>
                        </th>

                        <th @click="sortByColumn('nachname')" class="px-6 py-3 cursor-pointer">
                            Nachname
                            <i :class="sortColumn === 'nachname' && sortDirection === 'asc'
                                ? 'las la-sort-alpha-down'
                                : 'las la-sort-alpha-up'"></i>
                        </th>

                        <th @click="sortByColumn('email')" class="px-6 py-3 cursor-pointer">
                            Email
                            <i :class="sortColumn === 'email' && sortDirection === 'asc'
                                ? 'las la-sort-alpha-down'
                                : 'las la-sort-alpha-up'"></i>
                        </th>

                        <th class="px-6 py-3">Titel</th>
                        <th class="px-6 py-3">Projekte</th>
                        <th class="px-6 py-3 text-center">*</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="user in filteredUsers" :key="user.id" class="bg-white border-b">
                        <td class="px-6 py-3">{{ user.id }}</td>
                        <td class="px-6 py-3">{{ user.person?.vorname}}</td>
                        <td class="px-6 py-3">{{ user.person?.nachname }}</td>
                        <td class="px-6 py-3">{{ user.email }}</td>

                        <td class="px-6 py-3">
                            <span
                                v-for="rolle in user.roles"
                                :key="rolle.id"
                                class="px-2 py-1 text-xs rounded"
                                :style="{ backgroundColor: rolle.color }"
                            >
                                {{ rolle.name }}
                            </span>
                        </td>

                        <td class="px-6 py-3">
                            <span v-for="projekt in user.projekte" :key="projekt.id" class="mr-2">
                                {{ projekt.name }}
                            </span>
                        </td>

                        <td class="px-6 py-3 text-center">
                            <Dropdown>
                                <template #trigger>
                                    <button>
                                        <i class="la la-ellipsis-v la-lg"></i>
                                    </button>
                                </template>
                                <template #content>
                                    <span class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                          @click="confirmDelete(user)">
                                        Löschen
                                    </span>
                                    <Link :href="route('user.edit', user.id)" class="block px-4 py-2 hover:bg-gray-100">
                                        Bearbeiten
                                    </Link>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination :pagination="users" />
        </div>

        <ModalDestroy
            v-if="showModalLöschen"
            :toDelete="userToDelete"
            seite="user"
            @close="showModalLöschen = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Swal from 'sweetalert2';
import ModalCreateUser from '@/Pages/User/ModalCreateUser.vue';
import ModalProjektZuweisen from '@/Pages/Personal/ModalProjektZuweisen.vue';

const { users, authProjekte, rollen, alleProjekte, standorte } = defineProps({
    users: Object,
    authProjekte: Array,
    rollen: Array,
    alleProjekte: Array,
    standorte: Array,
});
const page = usePage();
const can = (permission) => (page.props.permissions || []).includes(permission);

// Reactive states
let search = ref('');
let selectedProject = ref(null);
let sortColumn = ref('');
let sortDirection = ref('asc');
let searchProject = ref('');
const showCreateModal = ref(false);
const showProjektZuweisenModal = ref(false);
const userForProjekt = ref(null);

let userList = ref([...users.data]);

const emptyUser = () => ({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    rollen: [],
    projekt_zuweisungen: [
        {
            projekt_id: null,
            standort_ids: [],
        },
    ],
});

let newUser = ref(emptyUser());

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
const openCreateModal = () => {
    newUser.value = emptyUser();
    showCreateModal.value = true;
};

const addUser = () => {
    axios.post(route('user.store'), newUser.value)
        .then((response) => {
            Swal.fire('Gespeichert!', 'Mitarbeiter wurde angelegt.', 'success');
            if (response.data?.user) {
                userList.value = [response.data.user, ...userList.value];
            }
            showCreateModal.value = false;
        })
        .catch((error) => {
            const message = error.response?.data?.message || 'Speichern fehlgeschlagen.';
            Swal.fire('Fehler', message, 'error');
        });
};

const openProjektZuweisen = (user) => {
    userForProjekt.value = user;
    showProjektZuweisenModal.value = true;
};

const handleProjektZuweisungSaved = ({ user_id, zuweisungen }) => {
    const userIndex = userList.value.findIndex((user) => user.person_id === user_id);

    if (userIndex === -1) {
        return;
    }

    const user = userList.value[userIndex];
    const existingProjects = Array.isArray(user.projekte) ? user.projekte : [];
    const existingProjectIds = new Set(existingProjects.map((projekt) => Number(projekt.id)));
    const assignedProjectIds = [...new Set(zuweisungen.map((row) => Number(row.projekt_id)).filter(Boolean))];
    const nextProjects = [...existingProjects];

    for (const projektId of assignedProjectIds) {
        if (!existingProjectIds.has(projektId)) {
            const projekt = alleProjekte.find((item) => Number(item.id) === projektId);

            if (projekt) {
                nextProjects.push(projekt);
                existingProjectIds.add(projektId);
            }
        }
    }

    userList.value[userIndex] = {
        ...user,
        projekte: nextProjects,
    };

    if (userForProjekt.value?.person_id === user_id) {
        userForProjekt.value = userList.value[userIndex];
    }
};

const handleProjektZuweisungRemoved = ({ user_id, projekt_id }) => {
    const userIndex = userList.value.findIndex((user) => user.person_id === user_id);

    if (userIndex === -1) {
        return;
    }

    const user = userList.value[userIndex];
    const nextProjects = (user.projekte || []).filter((projekt) => Number(projekt.id) !== Number(projekt_id));

    userList.value[userIndex] = {
        ...user,
        projekte: nextProjects,
    };

    if (userForProjekt.value?.person_id === user_id) {
        userForProjekt.value = userList.value[userIndex];
    }
};
</script>

<template>
    <Head title="Personal" />

    <AppLayout>
        <template #header>Team</template>

        <!-- Suchzeile -->
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center">
                <button
                    v-if="can('benutzer.store')"
                    type="button"
                    @click="openCreateModal"
                    class="border border-gray-300 bg-white px-4 py-2.5 text-zbb hover:bg-zbb hover:text-white"
                    title="Mitarbeiter anlegen"
                >
                    <i class="la la-plus"></i>
                </button>
                <input
                    v-model="search"
                    class="border border-gray-300 text-gray-900 text-sm p-2.5"
                    placeholder="Suchen ..."
                />
            </div>

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
                                    <span v-if="can('benutzer.destroy')"
                                          class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                          @click="confirmDelete(user)">
                                        Löschen
                                    </span>
                                    <span
                                        v-if="user.person_id && can('benutzer.update')"
                                        class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        @click="openProjektZuweisen(user)"
                                    >
                                        Projekte zuweisen
                                    </span>
                                    <Link v-if="can('benutzer.update')" :href="route('user.edit', user.id)" class="block px-4 py-2 hover:bg-gray-100">
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
        <ModalCreateUser
            :visible="showCreateModal"
            :newUser="newUser"
            :rollen="rollen"
            :projekte="alleProjekte"
            :standorte="standorte"
            @close="showCreateModal = false"
            @add-user="addUser"
        />

        <ModalProjektZuweisen
            :visible="showProjektZuweisenModal"
            :userId="userForProjekt?.person_id"
            :projekte="alleProjekte"
            :standorte="standorte"
            :bestehendeProjekte="userForProjekt?.projekte || []"
            @close="showProjektZuweisenModal = false"
            @saved="handleProjektZuweisungSaved"
            @removed="handleProjektZuweisungRemoved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalDestroyStaff from '@/Components/ModalDestroyStaff.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Swal from 'sweetalert2';
import ModalCreateUser from '@/Pages/User/ModalCreateUser.vue';
import ModalProjektZuweisen from '@/Pages/Personal/ModalProjektZuweisen.vue';
import { usePermissions } from '@/utils/permissions';

const { users, authProjekte, rollen, alleProjekte, standorte } = defineProps({
    users: Object,
    authProjekte: Array,
    rollen: Array,
    alleProjekte: Array,
    standorte: Array,
});
const { can } = usePermissions();

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
    account_setup_method: 'email_invitation',
    password: '',
    password_confirmation: '',
    rollen: [],
    projekt_zuweisungen: [
        {
            projekt_id: null,
            standort_ids: [],
            bereich_ids: [],
            default_bereich_id: null,
            buero_raum_ids: [],
            default_buero_raum_id: null,
            arbeitsbereich_raum_ids: [],
            default_arbeitsbereich_raum_id: null,
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
const showCompleteDeleteModal = ref(false);
const staffToDelete = ref(null);

const confirmDelete = (user) => {
    userToDelete.value = {
        id: user.id,
        name: user.person?.vorname + ' ' + user.person?.nachname
    };
    showModalLöschen.value = true;
};
const confirmCompleteDelete = (user) => {
    staffToDelete.value = {
        id: user.person_id,
        name: `${user.person?.vorname || ''} ${user.person?.nachname || ''}`.trim(),
    };
    showCompleteDeleteModal.value = true;
};

const handleStaffDeleted = ({ personId, message }) => {
    userList.value = userList.value.filter((user) => Number(user.person_id) !== Number(personId));
    Swal.fire('Gelöscht', message, 'success');
};
const openCreateModal = () => {
    newUser.value = emptyUser();
    showCreateModal.value = true;
};

const addUser = () => {
    axios.post(route('user.store'), newUser.value)
        .then((response) => {
            const invitationFailed = response.data?.invitation_sent === false;
            Swal.fire({
                title: invitationFailed ? 'Konto angelegt, E-Mail fehlgeschlagen' : 'Gespeichert!',
                text: response.data?.message || 'Mitarbeiter wurde angelegt.',
                icon: invitationFailed ? 'warning' : 'success',
            });
            showCreateModal.value = false;
            router.reload({ only: ['users'], preserveScroll: true });
        })
        .catch((error) => {
            const message = error.response?.data?.message || 'Speichern fehlgeschlagen.';
            Swal.fire('Fehler', message, 'error');
        });
};

const resendInvitation = async (user) => {
    try {
        const response = await axios.post(route('user.invitation.store', user.id));
        user.invitation_status = response.data.invitation_status;
        user.invitation_expires_at = response.data.invitation_expires_at;
        Swal.fire('Einladung gesendet', response.data.message, 'success');
    } catch (error) {
        Swal.fire('Versand fehlgeschlagen', error.response?.data?.message || 'Die Einladung konnte nicht versendet werden.', 'error');
    }
};

const invitationLabel = (status) => ({
    pending: 'Einladung ausstehend',
    expired: 'Einladung abgelaufen',
    delivery_failed: 'E-Mail nicht versendet',
    accepted: 'Konto aktiviert',
}[status] || '');

const invitationClass = (status) => ({
    pending: 'bg-amber-100 text-amber-800',
    expired: 'bg-gray-200 text-gray-700',
    delivery_failed: 'bg-red-100 text-red-700',
    accepted: 'bg-emerald-100 text-emerald-700',
}[status] || 'bg-gray-100 text-gray-700');

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
                    <tr v-for="user in filteredUsers" :key="user.person_id" class="bg-white border-b">
                        <td class="px-6 py-3">{{ user.display_id || user.person_id }}</td>
                        <td class="px-6 py-3">{{ user.person?.vorname}}</td>
                        <td class="px-6 py-3">{{ user.person?.nachname }}</td>
                        <td class="px-6 py-3">
                            {{ user.email || 'Kein Login-Konto' }}
                            <span
                                v-if="user.invitation_status"
                                class="mt-1 block w-fit rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="invitationClass(user.invitation_status)"
                            >
                                {{ invitationLabel(user.invitation_status) }}
                            </span>
                        </td>

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
                                    <span v-if="user.has_login && can('benutzer.destroy')"
                                          class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                          @click="confirmDelete(user)">
                                        Nur Login-Konto entfernen
                                    </span>
                                    <span
                                        v-if="user.person_id && can('benutzer.destroy')"
                                        class="block cursor-pointer px-4 py-2 text-red-700 hover:bg-red-50"
                                        @click="confirmCompleteDelete(user)"
                                    >
                                        Vollständig löschen
                                    </span>
                                    <span
                                        v-if="user.person_id && can('benutzer.update')"
                                        class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        @click="openProjektZuweisen(user)"
                                    >
                                        Projekte zuweisen
                                    </span>
                                    <span
                                        v-if="['pending', 'expired', 'delivery_failed'].includes(user.invitation_status) && can('benutzer.update')"
                                        class="block cursor-pointer px-4 py-2 hover:bg-gray-100"
                                        @click="resendInvitation(user)"
                                    >
                                        Einladung erneut senden
                                    </span>
                                    <Link v-if="user.has_login && can('benutzer.update')" :href="route('user.edit', user.id)" class="block px-4 py-2 hover:bg-gray-100">
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
        <ModalDestroyStaff
            v-if="showCompleteDeleteModal && staffToDelete"
            :person="staffToDelete"
            @close="showCompleteDeleteModal = false"
            @deleted="handleStaffDeleted"
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

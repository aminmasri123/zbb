<script setup>
import { ref, watch, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Swal from 'sweetalert2';
import ModalProjektZuweisen from "@/Pages/Personal/ModalProjektZuweisen.vue";



const { users, authProjekte, rollen, standorte } = defineProps({
    users: Object,
    authProjekte: Array,
    rollen: Array,
    standorte: Array,
});
console.log(users)
// Reactive states
let search = ref('');
let selectedProject = ref(null);
let sortColumn = ref('');
let sortDirection = ref('asc');
let searchProject = ref('');
const showProjektZuweisenModal = ref(false);
const userForProjekt = ref(null);
let userList = ref([...users.data]);
const openProjektZuweisen = (user) => {
    userForProjekt.value = user;
    showProjektZuweisenModal.value = true;
};

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
    router.get('personal', {
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

const groupProjects = (projekte, standorte) => {
    const grouped = {};

    projekte.forEach(p => {
        const pid = p.id;

        if (!grouped[pid]) {
            grouped[pid] = {
                id: p.id,
                name: p.name,
                standorte: []
            };
        }

        // Standort für dieses Pivot lesen
        const sId = p.pivot_model.standort_id;
        const sObj = standorte.find(s => s.id === sId);

        if (sObj && !grouped[pid].standorte.find(x => x.id === sObj.id)) {
            grouped[pid].standorte.push(sObj);
        }
    });

    return Object.values(grouped);
};





</script>

<template>
    <Head title="Personal" />
    <AppLayout>
        <template #header>{{$t('Personal')}}</template>
        <!-- Tabelle -->
        <div class="">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-200 uppercase">
                    <tr>
                        <th @click="sortByColumn('id')" class="px-6 py-3 cursor-pointer">
                            ID
                            <i :class="sortColumn === 'id' && sortDirection === 'asc'
                                ? 'las la-sort-numeric-down'
                                : 'las la-sort-numeric-up'"></i>
                        </th>
                        <th @click="sortByColumn('nachname')" class="px-6 py-3 cursor-pointer">
                            Nachname
                            <i :class="sortColumn === 'nachname' && sortDirection === 'asc'
                                ? 'las la-sort-alpha-down'
                                : 'las la-sort-alpha-up'"></i>
                        </th>
                        <th @click="sortByColumn('vorname')" class="px-6 py-3 cursor-pointer">
                            Vorname
                            <i :class="sortColumn === 'vorname' && sortDirection === 'asc'
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
                        <td class="px-6 py-3">{{ user?.nachname }}</td>
                        <td class="px-6 py-3">{{ user?.vorname}}</td>
                        <td class="px-6 py-3">{{ user?.user.email }} </td>

                        <td class="px-6 py-3">
                            <span v-for="rolle in user?.user.roles">
                                {{ rolle?.name}}
                            </span>

                        </td>
                        <td class="px-6 py-3 flex flex-wrap text-xs">

                            <div
                                v-for="projekt in groupProjects(user.projekte, user.projekt_standorte)"
                                :key="projekt.id"
                                class="mr-3 my-1 shadow-lg bg-chip text-gray-800 rounded p-2 text-center"
                            >
                                <p class="font-bold bg-zbb text-white rounded mb-1 border">{{ projekt.name }}</p>

                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span
                                        v-for="standort in projekt.standorte"
                                        :key="standort.id"
                                        class="bg-white px-2 py-1 rounded shadow"
                                    >
                                        {{ standort.name }}
                                    </span>
                                </div>
                            </div>

                        </td>
                        <td class="px-6 py-3">
                            <Dropdown>
                                <template #trigger>
                                    <button>
                                        <i class="la la-ellipsis-v la-lg"></i>
                                    </button>
                                </template>
                                <template #content>
                                    <span class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                          @click="confirmDelete(user)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                    <span class="block px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        @click="openProjektZuweisen(user)">
                                        Projekte zuweisen <i class="las la-random"></i>
                                    </span>

                                    <Link :href="route('personal.edit', user.id)" class="block px-4 py-2 hover:bg-gray-100">
                                           {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                    </Link>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination :pagination="users" />
        </div>
        <ModalProjektZuweisen
            :visible="showProjektZuweisenModal"
            :userId="userForProjekt?.id"
            :projekte="authProjekte"
            :standorte="standorte"
            @close="showProjektZuweisenModal = false"
            @saved="router.reload({ only: ['users'] })"
        />


        <ModalDestroy
            v-if="showModalLöschen"
            :toDelete="userToDelete"
            seite="user"
            @close="showModalLöschen = false"
        />
    </AppLayout>
</template>

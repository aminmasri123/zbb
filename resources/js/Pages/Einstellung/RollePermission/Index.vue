<script setup>
    import { ref, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import axios from 'axios';
    import  Modal from '@/Components/ModalForm.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import Swal from 'sweetalert2';
    import { Inertia } from '@inertiajs/inertia';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownLink from '@/Components/DropdownLink.vue';

    // Search input state
    let seite = 'rolle';
    let search = ref('');
    let rolleToDelete = ref(null); // Speichert den Namen der User, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung

    const props = defineProps({
        rollen: {type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
        berechtigungskategorien: { type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
        kategorienDerUser:{ type: Array, default: () => [] },
        alleZugewiesenePermission:{ type:Array, default:()=> []},
        roleId:{ type:Number , default:()=> []},
    });

    // Lokale Kopie der Rollen erstellen
    let localRollen= ref([]); // Initialisiere mit einem leeren Array
    // Fülle localAbteilungen mit den Daten aus den Props
    localRollen.value = [...props.rollen]; // Kopiere die Rollen in eine reaktive Variable

    // Löschbestätigung anzeigen und Abteilungsnamen speichern
    const confirmDelete = (rolle) => {
        rolleToDelete.value = {
            name: rolle.name, // Speichere den Namen der Rolle
            id: rolle.id      // Speichere die ID der Rolle
        };
        showModalLöschen.value = true; // Modal anzeigen
    };
    // Event-Handler, um die Rolle aus der lokalen Liste zu löschen
    const handleDelete = (rolleId) => {
        // Remove the deleted item from localRolle
        localRollen.value = localRollen.value.filter(
            rolle => rolle.id !== rolleId
        );
        showModalLöschen.value = false; // Close the delete modal
    };

    // Watch for changes in search and trigger a request
    watch(search, value => {
        axios.get('/benutzer', { search: value }, { preserveState: true });
    });



</script>

<script>
    export default {
        // Komponente referenzieren
        components: {
            AppLayout,
            Modal,
            Dropdown,
            DropdownLink,

        },
        data() {
            return {
                activeMenu: null,
            };
        },

        methods: {
            toggleListRolle() {
            this.activeMenu = this.activeMenu === 'rolle' ? null : 'rolle';
        },
        isActiveMenu(menu) {
        return this.activeMenu === menu;
         },
            // Methode für Toggle-Check
            toggleCheck(permissionId, roleId, isChecked) {
            const action = isChecked ? 'addPermission' : 'removePermission';

        axios.post('/berechtigungZuweisen', { roleId: roleId, permissionId: permissionId, action: action })
            .then(response => {
                if (response.data.success) {
                    // Finde die richtige Kategorie
                    const kategorie = this.kategorienDerUser.find(k => k.permissions.some(p => p.id === permissionId));
                    if (kategorie) {
                        // Finde die richtige Permission
                        const permission = kategorie.permissions.find(p => p.id === permissionId);

                        if (permission) {
                            Swal.fire({
                                title: 'Erfolg!',
                                text: 'Die Berechtigung wurde erfolgreich zur Rolle hinzugefügt.',
                                icon: 'success',
                                timer: 3000,
                                timerProgressBar: true,
                            });
                            // Setze einen Zustand, z.B. die Checkbox-Statusänderung oder ähnliches
                        }
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Die Berechtigung konnte nicht zugewiesen werden .',
                        icon: 'error',
                        timer: 3000,
                        timerProgressBar: true,
                    });
                    console.error("Fehler: ", response.data.message);
                }
            })
            .catch(error => {
                console.error("Error: ", error.response ? error.response.data : error.message);
            });
    },

            openModal() {
        this.isModalOpen = true;
        },
        closeModal() {
        this.isModalOpen = false;
        this.resetForm();
        },
        resetForm() {
        this.newUser = {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        };
        },
        async addUser() {
        try {
            await this.$inertia.post('/users', this.newUser);
            this.closeModal();
        } catch (error) {
            // Fehlerbehandlung hier
            console.error(error);
        }
        },

        }
    };
</script>
<template>
    <Head title="Rolle-Permission" />
    <app-layout>
        <template #header>{{ $t('rollen-_und_berechtigungsmanagement') }}</template>
        <div class="page-wrapper">
            <!-- Page Content -->
            <div class="content container mx-auto">
                <!-- Mobile Role Dropdown-->
                <ul class=" sm:hidden mb-1 text-center bg-white border rounded-2xl ">
                    <li class="flex flex-col">
                        <a  href="#" @click.prevent="toggleListRolle" class="text-center bg-zbb text-black py-2 hover:bg-orange-400 transition duration-200">
                            <span class="text-white pr-5 ">{{$t('alle_rollen')}}</span>
                            <span :class="{'rotate-180': isActiveMenu('rolle')}" class="hover:rotate-90 transform transition-transform duration-300 menu-arrow"></span>
                        </a>
                        <ul v-show="isActiveMenu('rolle')" class=" mt-2 space-y-2 pb-5 ">
                            <li v-for="rolle in localRollen" :key="rolle.id" class="text-center mx-auto hover:bg-gray-300 ">
                                <Link class="text-gray-400 hover:text-black hover:font-bold transition duration-200"
                                    :class="{'text-zbb font-bold': rolle.id == roleId}"
                                    :href="route('berechtigung.index', { id: rolle.id })">
                                    {{ rolle.name}}
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ul>
                <div class="flex flex-col sm:flex-row">
                    <div class="mb-5 sm:relative sm:w-1/4 ">
                        <div class=" hidden sm:block sm:w-1/5 sm:fixed">
                            <div class="w-full bg-orange-500 py-2 rounded-md text-center">
                                <a href="#" class="text-white">
                                    <i class="fa fa-plus"></i> {{ $t('rolle_anlegen') }}
                                </a>
                            </div>
                            <div class="bg-white mt-5 border">
                                <ul>
                                    <li v-for="rolle in localRollen" :key="rolle.id" class="flex justify-between pr-3  hover:font-bold text-gray-600 ">
                                        <Link class=" hover:bg-gray-200 w-full pl-8  py-2"
                                            :href="route('berechtigung.index', { id: rolle.id })"
                                            :class="{'text-zbb font-bold': rolle.id == roleId}"
                                            >
                                            <div class="cursor-pointer">{{ rolle.name }}</div>
                                        </Link>
                                        <span class="cursor-pointer  py-2">
                                            <!-- Dropdown für Aktion -->
                                            <Dropdown >
                                                <template #trigger>
                                                    <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                        <span class="cursor-pointer">
                                                            <i class="transform transition-transform duration-300  la  la-lg la-ellipsis-v"></i>
                                                        </span>
                                                    </button>
                                                </template>

                                                <template #content >
                                                    <!-- Gefilterte Projektauswahl -->
                                                    <span class="flex justify-around cursor-pointer" @click="confirmDelete(rolle)">
                                                        {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                                    </span>
                                                </template>
                                            </Dropdown>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="sm:w-3/4 sm:pl-3">
                        <div class="overflow-x-auto border">
                            <div class=" min-w-full bg-white">
                                <div class="flex flex-row px-8 py-2">
                                    <div class="basis-1/6">*</div>
                                    <div class="basis-4/6">Bestätigte Berechtigungen</div>
                                    <div class="basis-1/6">Ermächtigen</div>
                                </div>
                                <div>
                                    <div v-for="kategorie in kategorienDerUser" :key="kategorie.id">
                                        <div v-if="kategorie.permissions && kategorie.permissions.length">

                                            <div colspan="3" class="text-center bg-zbb text-white text-2xl py-2 mt-4">
                                                {{ kategorie.name }}
                                            </div>
                                            <div v-for="(permission) in kategorie.permissions" v-if="kategorie.permissions.length" :key="permission.id">
                                                <div class="flex flex-row px-8 py-2">
                                                    <div class="basis-1/6">{{ permission.id}}</div>
                                                    <div class="basis-4/6 ">
                                                        <abbr :title="permission.beschreibung" class=" no-underline">{{ permission.name }}</abbr>
                                                    </div>
                                                    <div class="basis-1/6">
                                                        <div class="flex items-center">
                                                            <!-- Vergleich von permission.name mit zugewiesenePermission.name -->
                                                            <label class="relative cursor-pointer">
                                                                <input type="checkbox"
                                                                    class="sr-only peer"
                                                                    :checked="alleZugewiesenePermission.some(zugewiesenePermission => zugewiesenePermission.name === permission.name)"
                                                                    @change="toggleCheck(permission.id, roleId, $event.target.checked)" />
                                                                    <div class="w-[53px] h-7 flex items-center bg-gray-300 rounded-full text-[9px] peer-checked:text-zbb text-gray-300 font-extrabold after:flex after:items-center after:justify-center peer after:content-['Off'] peer-checked:after:content-['On'] peer-checked:after:translate-x-full after:absolute after:left-[2px] peer-checked:after:border-white after:bg-white after:border after:border-gray-300 after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-zbb">
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Modal für die Löschung der Abteilung-->
        <ModalDestroy v-if="showModalLöschen"  @delete="handleDelete" @close="showModalLöschen = false" :seite="seite" :toDelete="rolleToDelete" >
            <!--<template #header>      </template>
                <template #body>        </template>
                <template #footer>      </template>
            -->
        </ModalDestroy>
    </app-layout>
</template>

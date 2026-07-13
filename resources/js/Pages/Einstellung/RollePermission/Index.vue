<script setup>
    import { computed, ref, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import axios from 'axios';
    import  Modal from '@/Components/ModalForm.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import Swal from 'sweetalert2';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownLink from '@/Components/DropdownLink.vue';
    import ModalCreate from './ModalCreate.vue';
    import { usePermissions } from '@/utils/permissions';
    // Search input state
    let seite = 'rolle';
    let search = ref('');
    let rolleToDelete = ref(null); // Speichert den Namen der User, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let isModalCreateOpen = ref(false);
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };
    const props = defineProps({
        rollen: {type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
        berechtigungskategorien: { type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
        kategorienDerUser:{ type: Array, default: () => [] },
        alleZugewiesenePermission:{ type:Array, default:()=> []},
        roleId:{ type:Number , default:()=> []},
        dataAccess:{ type:Object, default:()=> ({})},
        dataAccessOptions:{ type:Object, default:()=> ({ team: {}, participant: {} })},
    });

    const { canAny } = usePermissions();
    const canManagePermissions = computed(() => canAny(['berechtigung.zuweisen', 'berechtigung.update']));
    const canManageDataAccess = computed(() => canAny(['rolle.data-access.update', 'berechtigung.update']));
    const canCreateRole = computed(() => canAny(['rolle.store', 'berechtigung.store', 'berechtigung.update']));
    const canDeleteRole = computed(() => canAny(['rolle.destroy', 'berechtigung.update']));

    const dataAccessForm = ref({
        team_scope: props.dataAccess?.team_scope || 'none',
        participant_scope: props.dataAccess?.participant_scope || 'none',
    });
    const isSavingDataAccess = ref(false);

    watch(() => props.dataAccess, (value) => {
        dataAccessForm.value = {
            team_scope: value?.team_scope || 'none',
            participant_scope: value?.participant_scope || 'none',
        };
    }, { deep: true });

    const saveDataAccess = async () => {
        isSavingDataAccess.value = true;

        try {
            await axios.put(route('rolle.data-access.update', props.roleId), dataAccessForm.value);
            Swal.fire({
                title: 'Gespeichert!',
                text: 'Der Datenzugriff wurde aktualisiert.',
                icon: 'success',
                timer: 2500,
                timerProgressBar: true,
            });
        } catch (error) {
            Swal.fire({
                title: 'Fehler!',
                text: 'Der Datenzugriff konnte nicht gespeichert werden.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
            console.error(error.response ? error.response.data : error.message);
        } finally {
            isSavingDataAccess.value = false;
        }
    };

    // Lokale Kopie der Rollen erstellen
    let localRollen= ref([]); // Initialisiere mit einem leeren Array
    // Fülle localAbteilungen mit den Daten aus den Props
    localRollen.value = [...props.rollen]; // Kopiere die Rollen in eine reaktive Variable

    const addRolle = (rolle) => {
    localRollen.value.push(rolle);
};
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
                assignedPermissionIds: [],
                savingCategoryIds: [],
            };
        },
        computed: {
            selectedRole() {
                const rollen = Array.isArray(this.rollen) ? this.rollen : [];

                return rollen.find(rolle => Number(rolle.id) === Number(this.roleId)) || null;
            },
            isAdministratorRole() {
                return this.selectedRole?.name === 'Administrator';
            },
        },
        watch: {
            alleZugewiesenePermission: {
                handler() {
                    this.syncAssignedPermissions();
                },
                immediate: true,
                deep: true,
            },
        },

        methods: {
            syncAssignedPermissions() {
                this.assignedPermissionIds = (this.alleZugewiesenePermission || [])
                    .map(permission => Number(permission.id));
            },
            hasPermission(permissionId) {
                return this.assignedPermissionIds.includes(Number(permissionId));
            },
            assignLocalPermissions(permissionIds) {
                const nextIds = new Set(this.assignedPermissionIds);

                permissionIds.forEach(permissionId => nextIds.add(Number(permissionId)));
                this.assignedPermissionIds = Array.from(nextIds);
            },
            revokeLocalPermissions(permissionIds) {
                const removeIds = new Set(permissionIds.map(permissionId => Number(permissionId)));

                this.assignedPermissionIds = this.assignedPermissionIds
                    .filter(permissionId => !removeIds.has(Number(permissionId)));
            },
            categoryPermissionIds(kategorie) {
                return (kategorie.permissions || []).map(permission => Number(permission.id));
            },
            categoryAssignedCount(kategorie) {
                const permissionIds = this.categoryPermissionIds(kategorie);

                return permissionIds.filter(permissionId => this.hasPermission(permissionId)).length;
            },
            categoryAllAssigned(kategorie) {
                const permissionIds = this.categoryPermissionIds(kategorie);

                return permissionIds.length > 0 && permissionIds.every(permissionId => this.hasPermission(permissionId));
            },
            isSavingCategory(kategorieId) {
                return this.savingCategoryIds.includes(Number(kategorieId));
            },
            setCategorySaving(kategorieId, isSaving) {
                const id = Number(kategorieId);

                if (isSaving && !this.savingCategoryIds.includes(id)) {
                    this.savingCategoryIds = [...this.savingCategoryIds, id];
                }

                if (!isSaving) {
                    this.savingCategoryIds = this.savingCategoryIds.filter(value => value !== id);
                }
            },
            toggleListRolle() {
            this.activeMenu = this.activeMenu === 'rolle' ? null : 'rolle';
        },
        isActiveMenu(menu) {
        return this.activeMenu === menu;
         },
            // Methode für Toggle-Check
            toggleCheck(permissionId, roleId, isChecked) {
            const action = isChecked ? 'addPermission' : 'removePermission';
            const previousPermissionIds = [...this.assignedPermissionIds];

            if (!isChecked && this.isAdministratorRole) {
                Swal.fire({
                    title: 'Nicht möglich',
                    text: 'Die Administrator-Rolle muss alle Berechtigungen behalten.',
                    icon: 'info',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            if (isChecked) {
                this.assignLocalPermissions([permissionId]);
            } else {
                this.revokeLocalPermissions([permissionId]);
            }

        axios.post(route('berechtigung.zuweisen'), { roleId: roleId, permissionId: permissionId, action: action })
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
                    this.assignedPermissionIds = previousPermissionIds;
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
                this.assignedPermissionIds = previousPermissionIds;
                console.error("Error: ", error.response ? error.response.data : error.message);
                Swal.fire({
                    title: 'Fehler!',
                    text: 'Die Berechtigung konnte nicht gespeichert werden.',
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true,
                });
            });
    },
            toggleKategorie(kategorie, isChecked) {
                const permissionIds = this.categoryPermissionIds(kategorie);

                if (permissionIds.length === 0 || this.isSavingCategory(kategorie.id)) {
                    return;
                }

                const previousPermissionIds = [...this.assignedPermissionIds];
                const action = isChecked ? 'addCategoryPermissions' : 'removeCategoryPermissions';

                this.setCategorySaving(kategorie.id, true);

                if (isChecked) {
                    this.assignLocalPermissions(permissionIds);
                } else {
                    this.revokeLocalPermissions(permissionIds);
                }

                axios.post(route('berechtigung.kategorie.zuweisen'), {
                    roleId: this.roleId,
                    berechtigungskategorieId: kategorie.id,
                    action,
                })
                    .then(response => {
                        if (response.data.success) {
                            Swal.fire({
                                title: 'Gespeichert!',
                                text: response.data.message,
                                icon: 'success',
                                timer: 2500,
                                timerProgressBar: true,
                            });
                        } else {
                            this.assignedPermissionIds = previousPermissionIds;
                            Swal.fire({
                                title: 'Fehler!',
                                text: response.data.message || 'Die Kategorie konnte nicht aktualisiert werden.',
                                icon: 'error',
                                timer: 3000,
                                timerProgressBar: true,
                            });
                        }
                    })
                    .catch(error => {
                        this.assignedPermissionIds = previousPermissionIds;
                        console.error('Error: ', error.response ? error.response.data : error.message);
                        Swal.fire({
                            title: 'Fehler!',
                            text: error.response?.data?.message || 'Die Kategorie konnte nicht gespeichert werden.',
                            icon: 'error',
                            timer: 3000,
                            timerProgressBar: true,
                        });
                    })
                    .finally(() => {
                        this.setCategorySaving(kategorie.id, false);
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
                    <div class="mb-5 sm:w-1/4 sm:pr-4">
                        <div class="hidden sm:sticky sm:top-6 sm:block">
                            <div v-if="canCreateRole" @click="openModalCreate" class="w-full bg-orange-500 py-2 rounded-md text-center">
                                <a href="#" class="text-white">
                                    <i class="fa fa-plus"></i> {{ $t('rolle_anlegen') }}
                                </a>
                            </div>
                            <div class="mt-5 max-h-[calc(100vh-9rem)] overflow-y-auto overscroll-contain border bg-white">
                                <ul>
                                    <li v-for="rolle in localRollen" :key="rolle.id" class="flex justify-between pr-3  hover:font-bold text-gray-600 ">
                                        <Link class=" hover:bg-gray-200 w-full pl-8  py-2"
                                            :href="route('berechtigung.index', { id: rolle.id })"
                                            :class="{'text-zbb font-bold': rolle.id == roleId}"
                                            >
                                            <div class="cursor-pointer">{{ rolle.name }}</div>
                                        </Link>
                                        <span v-if="canDeleteRole" class="cursor-pointer  py-2">
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
                                <div class="border-b px-8 py-5">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div>
                                            <h2 class="text-lg font-semibold text-gray-800">Datenzugriff</h2>
                                            <p class="mt-1 text-sm text-gray-500">Legt fest, welche Mitarbeiter und Teilnehmer diese Rolle sehen darf.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="!canManageDataAccess || isSavingDataAccess"
                                            @click="saveDataAccess"
                                        >
                                            {{ isSavingDataAccess ? 'Speichern ...' : 'Speichern' }}
                                        </button>
                                    </div>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <label class="block text-sm font-medium text-gray-700">
                                            Team
                                            <select v-model="dataAccessForm.team_scope" class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-400 focus:ring-orange-400">
                                                <option v-for="(label, value) in dataAccessOptions.team" :key="value" :value="value">
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Teilnehmer
                                            <select v-model="dataAccessForm.participant_scope" class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-400 focus:ring-orange-400">
                                                <option v-for="(label, value) in dataAccessOptions.participant" :key="value" :value="value">
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex flex-row px-8 py-2">
                                    <div class="basis-1/6">*</div>
                                    <div class="basis-4/6">Bestätigte Berechtigungen</div>
                                    <div class="basis-1/6">Ermächtigen</div>
                                </div>
                                <div>
                                    <div v-for="kategorie in kategorienDerUser" :key="kategorie.id">
                                        <div v-if="kategorie.permissions && kategorie.permissions.length">

                                            <div colspan="3" class="bg-zbb text-white py-3 mt-4">
                                                <div class="flex flex-col gap-3 px-8 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <div class="text-2xl font-semibold">{{ kategorie.name }}</div>
                                                        <div class="mt-1 text-sm text-white/80">
                                                            {{ categoryAssignedCount(kategorie) }} / {{ kategorie.permissions.length }} Berechtigungen aktiv
                                                        </div>
                                                        <div v-if="isAdministratorRole" class="mt-1 text-xs text-white/70">
                                                            Administrator behält immer alle Berechtigungen.
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            class="rounded bg-white px-3 py-2 text-sm font-semibold text-zbb transition hover:bg-orange-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="!canManagePermissions || isSavingCategory(kategorie.id) || categoryAllAssigned(kategorie)"
                                                            @click="toggleKategorie(kategorie, true)"
                                                        >
                                                            Alle aktivieren
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="rounded border border-white px-3 py-2 text-sm font-semibold text-white transition hover:bg-white hover:text-zbb disabled:cursor-not-allowed disabled:opacity-60"
                                                            :disabled="!canManagePermissions || isAdministratorRole || isSavingCategory(kategorie.id) || categoryAssignedCount(kategorie) === 0"
                                                            @click="toggleKategorie(kategorie, false)"
                                                        >
                                                            Alle deaktivieren
                                                        </button>
                                                    </div>
                                                </div>
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
                                                                    :checked="hasPermission(permission.id)"
                                                                    :disabled="!canManagePermissions || (isAdministratorRole && hasPermission(permission.id))"
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

        <ModalCreate v-if="canCreateRole"
                 :visible="isModalCreateOpen"
                 @close="closeModalCreate"
                 @added="addRolle" />
    </app-layout>
</template>

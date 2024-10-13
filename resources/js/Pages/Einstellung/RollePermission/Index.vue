<script setup>
import { ref, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import  Modal from '@/Components/ModalForm.vue';
import Swal from 'sweetalert2';


// Search input state
let search = ref('');
defineProps({
    rollen: {type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
    berechtigungskategorien: { type: Array, default: () => [] }, // Setzt einen leeren Array als Standardwert
    kategorienDerUser:{ type: Array, default: () => [] },
    alleZugewiesenePermission:{ type:Array, default:()=> []},
    roleId:{ type:Number , default:()=> []},

});
// Watch for changes in search and trigger a request
watch(search, value => {
    router.get('/benutzer', { search: value }, { preserveState: true });
});

// Method to handle page navigation
const goToPage = (url) => {
    if (url) {
        router.get(url, { search: search.value }, { preserveState: true });
    }
};

</script>

<script>
    export default {
        // Komponente referenzieren
        components: {
            AppLayout,
            Modal,
        },
        data() {
            return {
                isModalOpen: false,
                newUser: {
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                },
            };
        },

        methods: {
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
                        console.log("Permission erfolgreich aktualisiert.");

                    }
                }
            } else {
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
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('rollen-_und_berechtigungsmanagement') }}
            </h2>
        </template>
        <div class="page-wrapper">
            <!-- Page Content -->
            <div class="content container mx-auto">
                <div class="flex">
                    <div class="relative w-1/4 ">
                        <div class="w-1/5 fixed">
                            <div class="  w-full bg-orange-500 py-2 rounded-md text-center">
                                <a href="#" class="text-white">
                                    <i class="fa fa-plus"></i> {{ $t('rolle_anlegen') }}
                                </a>
                            </div>
                            <div class="bg-white mt-5 border">
                                <ul>
                                    <li v-for="rolle in rollen" :key="rolle.id" class="py-2 px-8">
                                        <a class="flex justify-between">
                                            <div class="cursor-pointer">{{ rolle.name }}</div>
                                            <span class="cursor-pointer">
                                                <i class="fa fa-ellipsis-v fa-sm"></i>
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="w-3/4 pl-3">
                        <div class="overflow-x-auto border">
                            <div class=" min-w-full bg-white">
                                <div class="flex flex-row px-8 py-2">
                                    <div class="basis-1/6">*</div>
                                    <div class="basis-4/6">Bestätigte Berechtigungen</div>
                                    <div class="basis-1/6">Ermächtigen</div>
                                </div>
                                <div>
                                    <div v-for="kategorie in kategorienDerUser" :key="kategorie.id">
                                        <div colspan="3" class="text-center bg-zbb text-white text-2xl py-2 mt-4">
                                            {{ kategorie.name }}
                                        </div>
                                        <div v-for="(permission, index) in kategorie.permissions" v-if="kategorie.permissions.length" :key="permission.id">
                                            <div class="flex flex-row px-8 py-2">
                                                <div class="basis-1/6">{{ index + 1 }}</div>
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
    </app-layout>
</template>

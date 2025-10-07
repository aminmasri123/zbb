<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalCreate from '@/Pages/Partner/ModalCreate.vue';
    /* import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalEdit from '@/Pages/Partner/ModalEdit.vue'; */

    let seite = 'partner';
    let search = ref('');
    let partnerToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let partnerToEdit = ref(null);

     // Definiere die Props direkt
    const props = defineProps({ partners: Object, }); // props wird hier definiert
    // Lokale Kopie der partner erstellen
    console.log(props.partners)

    const openModalCreate = () => {
        isModalCreateOpen.value = true;
    };

    const closeModalCreate = () => {
        isModalCreateOpen.value = false;
    };


    const openModalEdit = (partner) => {
        partnerToEdit.value = partner;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };


    const updatepartner = (updatedpartner) => {
    const index = localPartners.value.findIndex(b => b.id === updatedPartner.id);
        if (index !== -1) {
            localPartners.value[index] = updatedPartner;
        }
    };


    // Fülle localPartner mit den Daten aus den Props
    let localPartners = ref([...props.partners.data]);  // Originaldaten
    let filteredPartners = ref([...localPartners.value]); // Gefilterte Daten

    // Funktion, um die Partners von der Datenbank abzurufen
   const fetchPartners = async () => {
        try {
            const response = await axios.get(route('partner.indexAjaxFresh'));
            return response.data.partners;
        } catch (error) {
            console.error('Fehler beim Abrufen der Partners:', error);
            return null;
        }
    };
    // Funktion zum Vergleichen und Laden der neuen Daten
    const compareAndReload = async () => {
        const newPartners = await fetchPartners();

        if (newPartners) {
            // Überprüfe die aktuellen IDs der lokalen Partners
            const localIds = localPartners.value.map(partner => partner.id);

            // Neue Partners hinzufügen, die nicht in der lokalen Liste sind
            newPartners.data.forEach(newPartner => {
                if (!localIds.includes(newPartner.id)) {
                    localPartners.value.unshift(newPartner); // Füge neue Partner hinzu
                }
            });

            // Partners entfernen, die nicht mehr in der neuen Liste sind
            localPartners.value = localPartners.value.filter(localPartner =>
                newPartners.data.some(newPartner => newPartner.id === localPartner.id)
            );
            applySearchFilter();

        }
    };
    // Setze ein Intervall, um die Daten regelmäßig zu überprüfen
    setInterval(compareAndReload, 5000); // Alle 5 Sekunden vergleichen
 // Funktion, um die Suchergebnisse zu filtern
 const applySearchFilter = () => {
        if (search.value) {
            filteredPartners.value = localPartners.value.filter(partner =>
                partner.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            // Wenn keine Suchanfrage vorliegt, alle Abteilungen anzeigen
            filteredPartners.value = [...localPartners.value];
        }
    };
    watch([search], () => {
        // Aktualisiere die URL mit der Suchabfrage
        router.get('/organisation/partner', { search: search.value }, { preserveState: true, replace: true });
        // Führe die Filterung durch
        applySearchFilter();
    });

// Löschbestätigung anzeigen und Partnerssnamen speichern
const confirmDelete = (partner) => {
    partnerToDelete.value = {
        name: partner.name, // Speichere den Namen der Partner
        id: partner.id      // Speichere die ID der Partner
    };
    showModalLöschen.value = true; // Modal anzeigen
};
// Event-Handler, um die Partner aus der lokalen Liste zu löschen
const handleDelete = (partnerId) => {
    // Remove the deleted item from localPartners
    localPartners.value = localPartners.value.filter(
        partner => partner.id !== partnerId
    );
    showModalLöschen.value = false; // Close the delete modal
};


    // Neuen Benutzer
        let newPartner = ref({
            name: '',
            beschreibung:'',
        });

    const resetForm = () => {
    newPartner.value = {
        name: '',
        beschreibung: '',
    };
};

// Benutzer hinzufügen
const addPartner = async (data) => {
    try {
        const response = await axios.post(route('partner.store'), data);

        Swal.fire({
            title: 'Erfolg!',
            text: ' erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

        localPartners.value.unshift(response.data.partner);

    } catch (error) {
        console.error(error);
        Swal.fire({
            title: 'Error!',
            text: error.response?.data?.message || 'Beim Erstellen des Partners ist ein Fehler aufgetreten.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
    }
};


</script>

<script>

export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },

};
</script>

<template>
        <Head title="Partner" />

    <app-layout>
        <!-- Header Slot -->
        <template #header>{{$t('Partner')}}</template>

        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModalCreate" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="search" class="sr-only">{{$t('Suchen')}}</label>
            <input id="search"v-model="search" type="text" class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Suchen ..." />


            <Link :href="route('partner.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>
        <!-- Partnerausgabe -->
        <div class="relative overflow-x-auto mb-10">
            <table id="table" class="w-full text-sm table-auto mb-5 text-left rtl:text-right text-gray-500 dark:text-gray-400 shadow-sm">
                <thead class="text-md  text-gray-600 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-400">
                    <tr class="font-bold ">
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 w-10 text-center ">ID.</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Bezeichnung')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Partnerschaftstypen')}}</th>
                        <th scope="col" class="border border-solid border-gray-300 px-6 py-3 ">{{$t('Beschreibung')}}</th>
                        <th scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3 ">*</th> <!-- Aktionen hinzufügen -->

                    </tr>
                </thead>
                <tbody>
                    <tr v-for="partner in filteredPartners" :key="partner.id"
                        class="bg-white  border-solid dark:bg-gray-800 dark:border-gray-700">
                        <th scope="row" class="border border-solid border-gray-300 px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white text-center w-10">
                            {{partner.id}}
                        </th>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{partner.name}}
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            <span class="bg-zbb text-white rounded p-1 mx-1" v-for="partnerschaftstyp in partner.partnerschaftstypens" :key="partnerschaftstyp.id">{{ partnerschaftstyp.bezeichnung}}</span>
                        </td>
                        <td class="border border-solid border-gray-300 px-6 py-4">
                            {{partner.beschreibung}}
                        </td>
                        <td class="w-10 border border-solid border-gray-300 px-6 py-4 text-center m-auto">
                            <!-- Dropdown für Aktion -->
                            <Dropdown >
                                <template #trigger>
                                    <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        <span class="cursor-pointer">
                                            <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                        </span>
                                    </button>
                                </template>

                                <template #content >
                                    <!-- Gefilterte Projektauswahl -->
                                    <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"  @click="confirmDelete(partner)">
                                        {{ $t('Löschen') }} <i class="las la-trash-alt "></i>
                                    </span>
                                    <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"  @click="openModalEdit(partner)">
                                        {{ $t('Bearbeiten') }}  <i class="las la-edit  "></i>
                                    </span>

                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

         <!-- Modal für neue Partner -->


        <ModalCreate :visible="isModalCreateOpen" @close="closeModalCreate" @add-partner="addPartner"/>
        <!-- <ModalEdit :visible="isModalEditOpen" :toEdit="partnerToEdit" @close="closeModalEdit" @updated="updatePartner"/>
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="partnerToDelete"></ModalDestroy>
 -->
    </app-layout>
</template>

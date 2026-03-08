<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalCreate from '@/Pages/Partner/ModalCreate.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalEdit from '@/Pages/Partner/ModalEdit.vue';

    let seite = 'partner';
    let search = ref('');
    let partnerToDelete = ref(null); // Speichert den Namen der Abteilung, die gelöscht werden soll
    let showModalLöschen = ref(false); // Modal für die Löschung
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let partnerToEdit = ref(null);

     // Definiere die Props direkt
    const props = defineProps({
        partners: Object,
        partnerschaftstypen: Array,
        projektName: String,
        kontaktypens: Array,
    });
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
        const newPartner = response.data.partner; // sollte jetzt auch ansprechpartner enthalten

        // Direkt in localPartners einfügen
        localPartners.value.unshift(newPartner);
        applySearchFilter();

        Swal.fire({
            title: 'Erfolg!',
            text: 'Partner erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

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

const updatePartner = async (form) => {
    try {
        //const response = router.put(route('partner.update', partnerToEdit.value.id), form);
        const response = await axios.put(route('partner.update', partnerToEdit.value.id), form);

        Swal.fire("Erfolg!", "Partner aktualisiert!", "success");

        const updated = response.data.partner;

        const index = localPartners.value.findIndex(p => p.id === updated.id);
        if (index !== -1) {
            localPartners.value[index] = { ...updated };
        }

        applySearchFilter(); 

        isModalEditOpen.value = false;

    } catch (error) {
        console.error(error);
        Swal.fire("Fehler", "Update fehlgeschlagen", "error");
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
        <div class="relative  mb-10">
            <table id="table" class="w-full text-sm table-fixed mb-5 text-left border-collapse border border-gray-300 shadow-sm">
    <thead class="text-md text-gray-600 uppercase bg-gray-200">
        <tr>
            <th class="border border-gray-300 px-6 py-3 w-16 text-center">ID</th>
            <th class="border border-gray-300 px-6 py-3 w-48">Bezeichnung</th>
            <th class="border border-gray-300 px-6 py-3 w-1/4">Ansprechpartner</th>
            <th class="border border-gray-300 px-6 py-3 w-1/4">Adresse</th>
            <th class="border border-gray-300 px-6 py-3 w-1/4">Kontakt</th>
            <th class="border border-gray-300 px-6 py-3 w-1/4">Partnerschaftstypen</th>
            <th class="border border-gray-300 px-6 py-3 w-40">Beschreibung</th>
            <th class="border border-gray-300 px-6 py-3 w-12 text-center">*</th>
        </tr>
    </thead>
    <tbody>
        <tr v-for="partner in filteredPartners" :key="partner.id" class="bg-white border-b border-gray-300">
            <td class="align-middle border-r border-gray-300 px-6 py-4 text-center">{{partner.id}}</td>
            <td class="align-middle border-r border-gray-300 px-6 py-4 font-bold">{{partner.name}}</td>

            <td colspan="4" class="p-0 align-top border-r border-gray-300">
                <table class="w-full table-fixed border-hidden border-collapse">
                    <tr v-for="(person, index) in partner.ansprechpartners" :key="person.id" 
                        :class="{'border-b border-gray-300': index !== partner.ansprechpartners.length - 1}">
                        
                        <td class="px-6 py-4 w-1/4 border-r border-gray-300 align-top">
                            {{ person.vorname }} {{ person.nachname }}
                        </td>
                        <td class="px-6 py-4 w-1/4 border-r border-gray-300 align-top">
                            <div v-for="adresse in person.adresses" :key="adresse.id">
                                {{ adresse.strasse }} {{ adresse.hausnummer }}<br>
                                {{ adresse.plz }} {{ adresse.stadt }}
                            </div>
                        </td>
                        <td class="px-6 py-4 w-1/4 border-r border-gray-300 align-top">
                             <div v-for="kontakt in person.kontaktes" :key="kontakt.id">
                                {{ kontakt.kontakttyp?.name }}: {{ kontakt.wert }}
                            </div>
                        </td>
                        <td class="px-6 py-4 w-1/4 align-top">
                            <div class="flex flex-wrap gap-1">
                                <span class="bg-orange-500 text-white rounded px-2 py-0.5 text-[10px] font-bold shadow-sm" 
                                      v-for="typ in person.partner_typ" :key="typ.id">
                                    {{ typ.bezeichnung }}
                                </span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>

            <td class="align-top border-r border-gray-300 px-6 py-4">{{partner.beschreibung}}</td>
            <td class="align-middle py-4 text-center"><i class="la la-ellipsis-v la-lg"></i></td>
        </tr>
    </tbody>
</table>
</div>

         <!-- Modal für neue Partner -->
        <ModalCreate :visible="isModalCreateOpen" :projektName="projektName" :partnerschaftstypen="partnerschaftstypen" @close="closeModalCreate" @add-partner="addPartner"/>
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="partnerToDelete"></ModalDestroy>

        <ModalEdit :visible="isModalEditOpen" :kontaktypens="kontaktypens" :partnerschaftstypen="partnerschaftstypen" :toEdit="partnerToEdit" @close="closeModalEdit" @updated="updatePartner"/>

    </app-layout>
</template>

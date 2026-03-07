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
    import { computed } from 'vue'


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
    });

    const openPartners = ref([])

    const togglePartner = (id) => {
        if (openPartners.value.includes(id)) {
            openPartners.value = openPartners.value.filter(p => p !== id)
        } else {
            openPartners.value.push(id)
        }
    }
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

    const groupedPartners = computed(() => {
    const groups = {}

    filteredPartners.value.forEach(partner => {
        partner.partnerschaftstypens.forEach(type => {

            if (!groups[type.bezeichnung]) {
                groups[type.bezeichnung] = []
            }

            groups[type.bezeichnung].push(partner)

        })
    })

    return groups
});
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
         //const response = router.post(route('partner.store'), data);
        Swal.fire({
            title: 'Erfolg!',
            text: ' erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });

        await compareAndReload();


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

        localPartners.value = localPartners.value.map(p =>
            p.id === updated.id ? updated : p
        );

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
            <div class="space-y-10">

    <div class="space-y-10">

    <div v-for="(partners, category) in groupedPartners" :key="category">

        <!-- Kategorie -->
        <h2 class="text-xl font-bold mb-4 border-b pb-2">
            {{ category }}
        </h2>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Partner Card -->
            <div v-for="partner in partners" :key="partner.id" class="border p-4 rounded mb-3">

                <div @click="togglePartner(partner.id)" class="cursor-pointer flex justify-between">

                    <span class="font-semibold">
                        {{ partner.name }}
                    </span>

                    <span>
                        {{ openPartners.includes(partner.id) ? '▲' : '▼' }}
                    </span>

                </div>

                <div v-if="openPartners.includes(partner.id)" class="mt-3">

                    <div
                        v-for="person in partner.ansprechpartners"
                        :key="person.id"
                        class="text-sm"
                    >
                        {{ person.vorname }} {{ person.nachname }}
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</div>
        </div>

         <!-- Modal für neue Partner -->


        <ModalCreate :visible="isModalCreateOpen" :partnerschaftstypen="partnerschaftstypen" @close="closeModalCreate" @add-partner="addPartner"/>
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="partnerToDelete"></ModalDestroy>

        <ModalEdit :visible="isModalEditOpen" :partnerschaftstypen="partnerschaftstypen" :toEdit="partnerToEdit" @close="closeModalEdit" @updated="updatePartner"/>

    </app-layout>
</template>

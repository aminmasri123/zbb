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
import ModalAnwesenheitslisteBIBB from './BOP/ModalAnwesenheitslisteBIBB.vue';
import ModalAnwesenheitslistePA from './BOP/ModalAnwesenheitslistePA.vue'; // fehlte

// Props
const props = defineProps({
    partners: Object,
    partnerschaftstypen: Array,
    projektName: String,
    kontaktypens: Array,
});

// States
let seite = 'partner';
let search = ref('');
let partnerToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let partnerToEdit = ref(null);
let activeModal = ref(null);
let modalData = ref({ jahr: null, teil: null, klasse: null, partnerId: null });
let localPartners = ref([...props.partners.data]);
let filteredPartners = ref([...localPartners.value]);

// Dropdowns
const openDropdowns = ref({});
const openSubDropdowns = ref({});

// -----------------------------
// Dropdown-Funktionen
// -----------------------------
function toggleDropdown(jahr, teil) {
    const key = `${jahr}-${teil}`;
    openDropdowns.value[key] = !openDropdowns.value[key];
}

function isDropdownOpen(jahr, teil) {
    return openDropdowns.value[`${jahr}-${teil}`] || false;
}

function toggleSubDropdown(jahr, teil) {
    const key = `${jahr}-${teil}`;
    openSubDropdowns.value[key] = !openSubDropdowns.value[key];
}

function isSubDropdownOpen(jahr, teil) {
    return openSubDropdowns.value[`${jahr}-${teil}`] || false;
}

function getKlassen(jahr, teil, partner) {
    return [...new Set(
        partner.schueler
            .filter(s => s.schuljahr === jahr && s.teil === teil)
            .map(s => s.klasse)
    )];
}

// -----------------------------
// Modal-Funktionen
// -----------------------------
function openModal(modalName, { jahr = null, teil = null, klasse = null, partnerId = null } = {}) {
    activeModal.value = modalName;
    modalData.value = { jahr, teil, klasse, partnerId };
}

function closeModal() {
    activeModal.value = null;
    modalData.value = { jahr: null, teil: null, klasse: null, partnerId: null, partner: null };
}

const openModalCreate = () => isModalCreateOpen.value = true;
const closeModalCreate = () => isModalCreateOpen.value = false;
const openModalEdit = (partner) => { partnerToEdit.value = partner; isModalEditOpen.value = true; };
const closeModalEdit = () => isModalEditOpen.value = false;

// -----------------------------
// Partner-Funktionen
// -----------------------------
const updatePartner = (updatedPartner) => {
    const index = localPartners.value.findIndex(b => b.id === updatedPartner.id);
    if (index !== -1) localPartners.value[index] = updatedPartner;
};

// Filter / Suche
const applySearchFilter = () => {
    if (search.value) {
        filteredPartners.value = localPartners.value.filter(partner =>
            partner.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredPartners.value = [...localPartners.value];
    }
};

watch(search, () => {
    router.get('/organisation/partner', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
});

// Fetch / Compare
const fetchPartners = async () => {
    try {
        const response = await axios.get(route('partner.indexAjaxFresh'));
        return response.data.partners;
    } catch (error) {
        console.error('Fehler beim Abrufen der Partners:', error);
        return null;
    }
};

const compareAndReload = async () => {
    const newPartners = await fetchPartners();
    if (newPartners) {
        const localIds = localPartners.value.map(p => p.id);
        newPartners.data.forEach(np => {
            if (!localIds.includes(np.id)) localPartners.value.unshift(np);
        });
        localPartners.value = localPartners.value.filter(lp =>
            newPartners.data.some(np => np.id === lp.id)
        );
        applySearchFilter();
    }
};
setInterval(compareAndReload, 5000);

// -----------------------------
// Delete Partner
// -----------------------------
const confirmDelete = (partner) => {
    partnerToDelete.value = { name: partner.name, id: partner.id };
    showModalLöschen.value = true;
};

const handleDelete = (partnerId) => {
    localPartners.value = localPartners.value.filter(p => p.id !== partnerId);
    showModalLöschen.value = false;
};

// -----------------------------
// Add / Update Partner via API
// -----------------------------
let newPartner = ref({ name: '', beschreibung: '' });
const resetForm = () => newPartner.value = { name: '', beschreibung: '' };

const addPartner = async (data) => {
    try {
        const response = await axios.post(route('partner.store'), data);
        localPartners.value.unshift(response.data.partner);
        applySearchFilter();
        Swal.fire({ title: 'Erfolg!', text: 'Partner erfolgreich angelegt!', icon: 'success', timer: 3000, timerProgressBar: true });
    } catch (error) {
        console.error(error);
        Swal.fire({ title: 'Error!', text: error.response?.data?.message || 'Fehler beim Erstellen des Partners.', icon: 'error', timer: 3000, timerProgressBar: true });
    }
};

const updatePartnerAPI = async (form) => {
    try {
        const response = await axios.put(route('partner.update', partnerToEdit.value.id), form);
        Swal.fire("Erfolg!", "Partner aktualisiert!", "success");
        const updated = response.data.partner;
        const index = localPartners.value.findIndex(p => p.id === updated.id);
        if (index !== -1) localPartners.value[index] = { ...updated };
        applySearchFilter();
        isModalEditOpen.value = false;
    } catch (error) {
        console.error(error);
        Swal.fire("Fehler", "Update fehlgeschlagen", "error");
    }
};
</script>

<template>

    <Head title="Partner" />

    <app-layout>
        <!-- Header Slot -->
        <template #header>{{ $t('Partner') }}</template>

        <!-- Suchfeld -->
        <div class="flex justify-around items-center mb-3">
            <div @click="openModalCreate" class="flex items-center">
                <i
                    class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>

            <label for="search" class="sr-only">{{ $t('Suchen') }}</label>
            <input id="search" v-model="search" type="text"
                class="border border-gray-300 text-gray-900 text-sm  focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                placeholder="Suchen ..." />


            <Link :href="route('partner.index')" class="flex items-center">
                <i
                    class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>
        <!-- Partnerausgabe -->
        <div class="relative  mb-10">
            <table id="table"
                class="w-full text-sm table-fixed mb-5 text-left border-collapse border border-gray-300 shadow-sm">
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
                    <template v-for="partner in filteredPartners" :key="partner.id">

                        <tr v-for="(person, index) in partner.ansprechpartners" :key="person.id"
                            class="bg-white border-b border-gray-300">

                            <!-- ID (nur einmal anzeigen) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="align-middle border-r border-gray-300 px-6 py-4 text-center">
                                {{ partner.id }}
                            </td>

                            <!-- NAME + SCHULJAHR (nur einmal anzeigen) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="align-middle border-r border-gray-300 px-6 py-4 text-sm">

                                <div class="font-bold">{{ partner.name }}</div>

                                <div class="flex gap-3 mt-1" v-if="partner.ansprechpartners.some(p =>
                                    p.partner_typ?.some(t => t.bezeichnung === 'Kooperationsschule')
                                )">

                                    <div v-for="jahr in [...new Set(partner.schueler.map(s => s.schuljahr))]"
                                        :key="jahr">
                                        <div class="font-bold text-xs">{{ jahr }}</div>

                                        <div class="flex gap-1">
                                            <span v-for="teil in [...new Set(
                                                partner.schueler
                                                    .filter(s => s.schuljahr === jahr)
                                                    .map(s => s.teil)
                                            )]" :key="teil" class="text-xs">

                                                <!-- Dropdown -->
                                                <div class="dropdown dropdown-action inline-block relative ">
                                                    <button @click="toggleDropdown(jahr, teil)"
                                                        class="dropdown-toggle py-1 rounded text-xs w-full">
                                                        {{ teil }}
                                                    </button>
                                                    <div v-show="isDropdownOpen(jahr, teil)"
                                                        class="dropdown-menu absolute mt-1  bg-white border rounded text-xs shadow-lg z-50">

                                                        <!-- Links analog Blade -->

                                                        <!-- Bearbeiter -->
                                                        <a @click.prevent="openModal('anwesenheitslisteBoTagbibb', { jahr, teil, partnerId: partner.id})" class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste BO Bibb</a>
                                                        <!-- 🔽 PA mit Subdropdown -->
                                                                <div class="relative">
                                                                    <button @click="toggleSubDropdown(jahr, teil)"
                                                                        class="w-full text-left px-4 py-1 hover:bg-gray-200">
                                                                        Anwesenheitsliste PA ▶
                                                                    </button>

                                                                    <!-- Subdropdown -->
                                                                    <div v-show="isSubDropdownOpen(jahr, teil)"
                                                                        class="absolute left-full top-0 ml-1 bg-white border rounded shadow-lg z-50">

                                                                        <a v-for="klasse in getKlassen(jahr, teil, partner)"
                                                                            :key="klasse"
                                                                            @click.prevent="openModal('anwesenheitslistePATage', { jahr, teil, klasse, partnerId: partner.id })"
                                                                            class="block px-4 py-1 hover:bg-gray-200">

                                                                            {{ klasse }}

                                                                        </a>
                                                                    </div>
                                                                </div>



                                                        <!-- zu erlegigen -->
                                                        <a :href="route('index-anpassung-anwesenheitsdaten', { schulId: '5', schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsdaten</a>

                                                         <a :href="route('export.teilnehmerliste.schule.excel', { schuleId: '5', schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Teilnehmerliste</a>

                                                        <a :href="route('teilnehmer.liste.schule', { schuleId: '5', schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1  hover:bg-gray-200">Bereichsauswahl</a>

                                                        <a :href="route('einteilung.show', { idSchule: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Einteilung</a>

                                                        <a :href="route('alleTeilnehmer.folder.create', { idSchule: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Ordner  anlegen</a>

                                                        <a href="#"
                                                            @click.prevent="openModal('anwesenheitslisteVorBOTage', jahr, teil)"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste BO Vorbereitung</a>



                                                        <a href="#"
                                                            @click.prevent="openModal('anwesenheitslisteBoTag1', jahr, teil)"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste  BO Tag1</a>



                                                        <a :href="route('export.elterneinverstaendniserklaerung.schule', { idSchule: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Vergessene
                                                            Elterneinverständniserklärung</a>

                                                        <a :href="route('export.auswertungsbogenPA.schule.pdf', { schuleId: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertungsbogen
                                                            PA</a>

                                                        <a href="#"
                                                            @click.prevent="openModal('hausordnungTag', jahr, teil)"
                                                            class="block px-4 py-1 hover:bg-gray-200">Hausordnung</a>

                                                        <a :href="route('export.anwesenheitsliste.rechnung', { idSchule: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste
                                                            Rechnung</a>

                                                        <a :href="route('export.zertifikat.schule.pobo', { idSchule: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO</a>

                                                        <a :href="route('export.zertifikat.schule.pobo.pdf', { schuleId: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO PDF</a>

                                                        <a :href="route('export.auswertungBO.schule.pdf', { schulId: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertung
                                                            POBO</a>

                                                        <a :href="route('export.auswertungBO.schule.pdf.tofolder', { schulId: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">BO
                                                            Auswertungen in Ordner generieren</a>

                                                        <a :href="route('export.auswertungPA.schule.pdf.tofolder', { schulId: '5', schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">PA
                                                            Berichte in Ordner generieren</a>

                                                        <a href="#"
                                                            @click.prevent="openModal('auswertungPoboModal', jahr, teil)"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertung POBO Runde</a>

                                                    </div>
                                                </div>

                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </td>

                            <!-- Ansprechpartner -->
                            <td class="px-6 py-4 border-r border-gray-300 align-top">
                                {{ person.vorname }} {{ person.nachname }}
                            </td>

                            <!-- Adresse -->
                            <td class="px-6 py-4 border-r border-gray-300 align-top">
                                <div v-for="adresse in person.adresses" :key="adresse.id">
                                    {{ adresse.strasse }} {{ adresse.hausnummer }}<br>
                                    {{ adresse.plz }} {{ adresse.stadt }}
                                </div>
                            </td>

                            <!-- Kontakt -->
                            <td class="px-6 py-4 border-r border-gray-300 align-top">
                                <div v-for="kontakt in person.kontaktes" :key="kontakt.id">
                                    {{ kontakt.kontakttyp?.name }}: {{ kontakt.wert }}
                                </div>
                            </td>

                            <!-- Partnerschaftstyp -->
                            <td class="px-6 py-4 border-r border-gray-300 align-top">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="typ in person.partner_typ" :key="typ.id"
                                        class="bg-orange-500 text-white rounded px-2 py-0.5 text-[10px] font-bold">
                                        {{ typ.bezeichnung }}
                                    </span>
                                </div>
                            </td>

                            <!-- Beschreibung (nur einmal) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="align-top border-r border-gray-300 px-6 py-4">
                                {{ partner.beschreibung }}
                            </td>

                            <!-- Action (nur einmal) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="align-middle py-4 text-center">
                                <i class="la la-ellipsis-v la-lg"></i>
                            </td>

                        </tr>

                    </template>
                </tbody>
            </table>
        </div>

        <!-- Modal für neue Partner -->
        <ModalCreate :visible="isModalCreateOpen" :projektName="projektName" :partnerschaftstypen="partnerschaftstypen"
            @close="closeModalCreate" @add-partner="addPartner" />
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"
            :toDelete="partnerToDelete"></ModalDestroy>

        <ModalEdit :visible="isModalEditOpen" :kontaktypens="kontaktypens" :partnerschaftstypen="partnerschaftstypen"
            :toEdit="partnerToEdit" @close="closeModalEdit" @updated="updatePartner" />

        <ModalAnwesenheitslisteBIBB v-if="activeModal === 'anwesenheitslisteBoTagbibb'" :visible="true" :partnerId="modalData.partnerId" :schuljahr="modalData.jahr" :teil="modalData.teil" @update:visible="closeModal" @close="closeModal"/>
        <ModalAnwesenheitslistePA v-if="activeModal === 'anwesenheitslistePATage'" :visible="true" :partnerId="modalData.partnerId" :schuljahr="modalData.jahr" :klasse="modalData.klasse" :teil="modalData.teil" @update:visible="closeModal" @close="closeModal"/>
    </app-layout>
</template>

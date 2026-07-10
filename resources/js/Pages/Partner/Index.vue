<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, defineProps, watch } from 'vue';
import Swal from 'sweetalert2';
import { Link, Head } from '@inertiajs/vue3';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';
import ModalCreate from '@/Pages/Partner/ModalCreate.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalEdit from '@/Pages/Partner/ModalEdit.vue';
import ModalAnwesenheitslisteBIBB from './BOP/ModalAnwesenheitslisteBIBBDigital.vue';
import ModalAnwesenheitslistePA from './BOP/ModalAnwesenheitslistePADigital.vue';
import ModalBoTag1 from './BOP/ModalBoTag1.vue'
import ModalHausordnung from './BOP/ModalHausordnung.vue';

// Props
const props = defineProps({
    partners: Object,
    partnerschaftstypen: Array,
    projektName: String,
    kontaktypens: Array,
    anzahlBereiche: Number,
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
let modalData = ref({ jahr: null, teil: null, klasse: null, partnerId: null, klassen: [], teilnehmerCount: 0 });
const normalizePartner = (partner) => ({
    ...partner,
    ansprechpartners: Object.values(
        (partner.ansprechpartners ?? []).reduce((persons, person) => {
            if (!persons[person.id]) {
                persons[person.id] = { ...person };
            }

            return persons;
        }, {})
    ),
});

let localPartners = ref([...props.partners.data].map(normalizePartner));
const selectedNode = ref(null);
// Dropdowns
const openDropdowns = ref({});
let hausordnungForm = ref({
    datum: '',
    sortBy: ''
});
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

const openMenus = ref({});

function toggleMenu(key) {
    openMenus.value = {
        [key]: !openMenus.value[key]
    };
}

function isMenuOpen(key) {
    return openMenus.value[key] || false;
}


function getKlassen(jahr, teil, partner) {
    return [...new Set(
        partner.schueler
            .filter(s => s.schuljahr === jahr && s.teil === teil)
            .map(s => s.klasse)
            .filter(Boolean)
    )];
}

function getSchuelerCount(jahr, teil, partner) {
    return new Set(
        partner.schueler
            .filter(s => s.schuljahr === jahr && s.teil === teil)
            .map(s => s.personen_id ?? s.person_id ?? s.id)
            .filter(Boolean)
    ).size;
}

// -----------------------------
// Modal-Funktionen
// -----------------------------
function openModal(modalName, { jahr = null, teil = null, klasse = null, partnerId = null, klassen = null, teilnehmerCount = 0 } = {}) {
    activeModal.value = modalName;
    modalData.value = { jahr, teil, klasse, partnerId, klassen, teilnehmerCount };
}

function closeModal() {
    activeModal.value = null;
    modalData.value = { jahr: null, teil: null, klasse: null, partnerId: null, partner: null, teilnehmerCount: 0 };
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
    if (index !== -1) localPartners.value[index] = normalizePartner(updatedPartner);
};

// Filter / Suche
const normalizeSearchValue = (value) => String(value ?? '').toLowerCase();

const partnerMatchesSearch = (partner, term) => {
    const personSearchValues = (partner.ansprechpartners ?? []).flatMap(person => [
        person.vorname,
        person.nachname,
        ...(person.adresses ?? []).flatMap(adresse => [
            adresse.strasse,
            adresse.hausnummer,
            adresse.plz,
            adresse.stadt,
            adresse.land,
            adresse.zusatzinfo,
        ]),
        ...(person.kontaktes ?? []).flatMap(kontakt => [
            kontakt.wert,
            kontakt.bemerkung,
            kontakt.kontakttyp?.name,
        ]),
        ...(person.partner_typ ?? []).flatMap(typ => [
            typ.bezeichnung,
            typ.beschreibung,
        ]),
    ]);

    const searchableText = [
        partner.id,
        partner.name,
        partner.beschreibung,
        ...(partner.partnerschaftstypens ?? []).flatMap(typ => [
            typ.bezeichnung,
            typ.beschreibung,
        ]),
        ...personSearchValues,
    ].map(normalizeSearchValue).join(' ');

    return searchableText.includes(term);
};

const filteredPartners = computed(() => {
    const term = normalizeSearchValue(search.value).trim();

    if (!term) {
        return localPartners.value;
    }

    return localPartners.value.filter(partner => partnerMatchesSearch(partner, term));
});

// Fetch / Compare
const fetchPartners = async () => {
    try {
        const response = await axios.get(route('partner.indexAjaxFresh'), {
            params: { search: search.value }
        });
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
            const normalizedPartner = normalizePartner(np);

            if (!localIds.includes(np.id)) {
                localPartners.value.unshift(normalizedPartner);
                return;
            }

            const index = localPartners.value.findIndex(lp => lp.id === np.id);
            if (index !== -1) localPartners.value[index] = normalizedPartner;
        });
        localPartners.value = localPartners.value.filter(lp =>
            newPartners.data.some(np => np.id === lp.id)
        );
    }
};
setInterval(compareAndReload, 5000);

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(compareAndReload, 250);
});

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
// Modal BOTAG 1 Konf
// -----------------------------
const handleBoTag1 = (data) => {
    closeModal()

    if (data.mode === 'klasse') {
        openModal('anwesenheitslisteBoTag1', {
            ...modalData.value,
            klasse: data.klasse
        })
    }

    if (data.mode === 'raum') {
        openModal('anwesenheitslisteBoTag1', {
            ...modalData.value,
            raum: 1
        })
    }

    if (data.mode === 'custom') {
        openModal('anwesenheitslisteBoTag1', {
            ...modalData.value,
            anzahl: data.value
        })
    }
}






// -----------------------------
// Add / Update Partner via API
// -----------------------------
let newPartner = ref({ name: '', beschreibung: '' });
const resetForm = () => newPartner.value = { name: '', beschreibung: '' };

const addPartner = async (data) => {
    try {
        const response = await axios.post(route('partner.store'), data);
        localPartners.value.unshift(normalizePartner(response.data.partner));
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
        if (index !== -1) localPartners.value[index] = normalizePartner(updated);
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

                                                        <!-- Bearbeitet -->

                                                         <button
                                                            type="button"
                                                            @click="openModal('anwesenheitslisteVorbereitungPA', { jahr, teil, klassen: getKlassen(jahr, teil, partner), partnerId: partner.id })"
                                                            class="block w-full px-4 py-1 text-left hover:bg-gray-200"
                                                         >
                                                            Anwesenheitsliste Vorbereitung PA
                                                         </button>

                                                        <!-- 🔽 Anwesenheitsliste BO Bibb -->
                                                        <a @click.prevent="openModal('anwesenheitslisteBoTagbibb', { jahr, teil, partnerId: partner.id })" class="block px-4 py-1 hover:bg-gray-200"> Anwesenheitsliste BO Bibb</a>
                                                        <a
                                                            @click.prevent="openModal('anwesenheitslistePATage', { jahr, teil, klassen: getKlassen(jahr, teil, partner), partnerId: partner.id })"
                                                            class="block px-4 py-1 hover:bg-gray-200"
                                                        >
                                                            Anwesenheitsliste PA
                                                        </a>

                                                        <!-- Rolltag -->
                                                        <a @click.prevent="openModal('boTag1Config', { jahr, teil, klassen: getKlassen(jahr, teil, partner), partnerId: partner.id, teilnehmerCount: getSchuelerCount(jahr, teil, partner) })" class="block px-4 py-1 hover:bg-gray-200"> Rolltag </a>

                                                        <!-- 🔽 Hausordnung -->

                                                        <div class="relative">
                                                            <button
                                                                @click="openModal('hausordnungConfig', { jahr, teil, partnerId: partner.id })"
                                                                class="w-full text-left px-4 py-1 hover:bg-gray-200">
                                                                Hausordnung
                                                            </button>
                                                        </div>

                                                        <!--  Bereichsauswahl -->
                                                        <a :href="route('bereichsauswahl.index', { partnerId: partner.id, schuljahr: jahr, teil: teil })" class="block px-4 py-1  hover:bg-gray-200">Bereichsauswahl</a>
                                                        <a :href="route('export.auswertungsbogenPA.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">Auswertungsbogen PA</a>
                                                        <a :href="route('export.auswertungsbogenPA.roland.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">Auswertungsbogen PA neu Roland</a>
                                                        <a :href="route('export.elterneinverstaendniserklaerung.schule', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">X Elterneinverständniserklärung</a>

                                                        <Link :href="route('einteilung.show', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">Einteilung</Link>


                                                       <!--  <div class="relative">
                                                            <button @click="toggleMenu(`haus-${jahr}-${teil}`)" class="w-full text-left px-4 py-1 hover:bg-gray-200"> Hausordnung ▶ </button>

                                                            <div v-show="isMenuOpen(`haus-${jahr}-${teil}`)" class="absolute left-full w-full top-0 ml-1 bg-white border rounded shadow-lg z-50">
                                                                <a :href="route('hausordnung.export.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil, sortBy: 'klasse' })" class="block px-4 py-1 hover:bg-gray-200 w-full"> Nach Klasse </a>

                                                                <a :href="route('hausordnung.export.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil, sortBy: 'nachname' })" class="block px-4 py-1 hover:bg-gray-200"> Nach Nachname  </a>

                                                            </div>
                                                        </div> -->



                                                        <!-- ⚠️ zu erlegigen -->


                                                        <a href="#">________________________________________</a>
                                                        <a :href="route('index-anpassung-anwesenheitsdaten', { schulId: partner.id, schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsdaten</a>

                                                         <a :href="route('export.teilnehmerliste.schule.excel', { schuleId: partner.id, schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Teilnehmerliste</a>





                                                        <a :href="route('alleTeilnehmer.folder.create', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Ordner  anlegen</a>

                                                       




                                                        <a :href="route('export.anwesenheitsliste.rechnung', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste
                                                            Rechnung</a>

                                                        <a :href="route('export.zertifikat.schule.pobo', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO</a>

                                                        <a :href="route('export.zertifikat.schule.pobo.pdf', { schuleId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO PDF</a>

                                                        <a :href="route('export.auswertungBO.schule.pdf', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertung
                                                            POBO</a>

                                                        <a :href="route('export.auswertungBO.schule.pdf.tofolder', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">BO
                                                            Auswertungen in Ordner generieren</a>

                                                        <a :href="route('export.auswertungPA.schule.pdf.tofolder', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">PA
                                                            Berichte in Ordner generieren</a>

                                                        <a :href="route('auswertungPoboModal', { schuleId: partner.id, schuljahr: jahr, teil })"
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
                                <Dropdown align="right">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100"
                                            aria-label="Partner Aktionen"
                                        >
                                            <i class="la la-ellipsis-v la-lg"></i>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="py-1 text-sm text-gray-700">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2 text-left hover:bg-gray-100"
                                                @click="openModalEdit(partner)"
                                            >
                                                <i class="la la-edit"></i>
                                                <span>Bearbeiten</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-red-600 hover:bg-red-50"
                                                @click="confirmDelete(partner)"
                                            >
                                                <i class="la la-trash"></i>
                                                <span>Löschen</span>
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </td>

                        </tr>

                    </template>
                </tbody>
            </table>
        </div>

        <!-- Modal für neue Partner -->
        <ModalCreate :visible="isModalCreateOpen" :projektName="projektName" :partnerschaftstypen="partnerschaftstypen"  @close="closeModalCreate" @add-partner="addPartner" />
        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="partnerToDelete"></ModalDestroy>

        <ModalEdit :visible="isModalEditOpen" :kontaktypens="kontaktypens" :partnerschaftstypen="partnerschaftstypen" :toEdit="partnerToEdit" @close="closeModalEdit" @updated="updatePartnerAPI" />

        <ModalAnwesenheitslisteBIBB v-if="activeModal === 'anwesenheitslisteBoTagbibb'" :visible="true" :partnerId="modalData.partnerId" :schuljahr="modalData.jahr" :teil="modalData.teil" @update:visible="closeModal" @close="closeModal"/>
        <ModalAnwesenheitslistePA v-if="activeModal === 'anwesenheitslistePATage'" :visible="true" :partnerId="modalData.partnerId" :schuljahr="modalData.jahr" :klasse="modalData.klasse" :klassen="modalData.klassen" :teil="modalData.teil" @update:visible="closeModal" @close="closeModal"/>
        <ModalAnwesenheitslistePA v-if="activeModal === 'anwesenheitslisteVorbereitungPA'" :visible="true" :partnerId="modalData.partnerId" :schuljahr="modalData.jahr" :klasse="modalData.klasse" :klassen="modalData.klassen" :teil="modalData.teil" list-type="pa_preparation" @update:visible="closeModal" @close="closeModal"/>
        <ModalBoTag1 v-if="activeModal === 'boTag1Config'" :visible="true" :anzahlBereiche="props.anzahlBereiche" :jahr="modalData.jahr" :teil="modalData.teil" :klassen="modalData.klassen" :teilnehmerCount="modalData.teilnehmerCount" :partnerId="modalData.partnerId" @close="closeModal" @submit="handleBoTag1" />
        <ModalHausordnung v-if="activeModal === 'hausordnungConfig'" :visible="true" :partnerId="modalData.partnerId" :jahr="modalData.jahr" :teil="modalData.teil" @close="closeModal"/>



    </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, defineProps, watch, onMounted, onBeforeUnmount } from 'vue';
import Swal from 'sweetalert2';
import { Link, Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';
import ModalCreate from '@/Pages/Partner/ModalCreate.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalEdit from '@/Pages/Partner/ModalEdit.vue';
import ModalAnwesenheitslisteBIBB from './BOP/ModalAnwesenheitslisteBIBBDigital.vue';
import ModalAnwesenheitslistePA from './BOP/ModalAnwesenheitslistePADigital.vue';
import ModalBoTag1 from './BOP/ModalBoTag1.vue'
import ModalHausordnung from './BOP/ModalHausordnung.vue';
import ModalUsbStickBrief from './BOP/ModalUsbStickBrief.vue';
import BopRunPlanner from './BOP/BopRunPlanner.vue';
import { usePermissions } from '@/utils/permissions';

// Props
const props = defineProps({
    partners: Object,
    partnerschaftstypen: Array,
    projektName: String,
    kontaktypens: Array,
    anzahlBereiche: Number,
    partnerDokumente: { type: Array, default: () => [] },
});
const { can, canAny } = usePermissions();
const page = usePage();
const canAnySelectionPermission = computed(() => canAny([
    'bereichsauswahl.index',
    'bereichsauswahl.store',
    'bereichsauswahl.update',
    'bereichsauswahl.planning',
]));
const canAnyAssignmentPermission = computed(() => canAny([
    'einteilung.index',
    'einteilung.store',
    'einteilung.update',
    'einteilung.destroy',
    'einteilung.export',
    'einteilung.planning',
]));
const isBopProject = computed(() => String(props.projektName ?? '').toUpperCase().includes('BOP'));
const participantPartsEnabled = computed(() => page.props.currentProjekt?.rules?.participant_parts_enabled !== false);

// States
let seite = 'partner';
let search = ref('');
let partnerToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let partnerToEdit = ref(null);
let activeModal = ref(null);
let modalData = ref({ jahr: null, teil: null, klasse: null, partnerId: null, schoolName: null, schoolYears: [], klassen: [], teilnehmerCount: 0 });
const normalizePartner = (partner) => {
    const ansprechpartners = Object.values(
        (partner.ansprechpartners ?? []).reduce((persons, person) => {
            if (!persons[person.id]) {
                persons[person.id] = { ...person };
            }

            return persons;
        }, {})
    );

    if (ansprechpartners.length === 0) {
        ansprechpartners.push({
            id: `partner-${partner.id}`,
            vorname: '',
            nachname: '',
            adresses: partner.adresses ?? [],
            kontaktes: partner.kontaktes ?? [],
            partner_typ: partner.partnerschaftstypens ?? [],
        });
    }

    return { ...partner, ansprechpartners };
};

let localPartners = ref([...props.partners.data].map(normalizePartner));
watch(
    () => props.partners.data,
    (partners) => {
        localPartners.value = [...(partners ?? [])].map(normalizePartner);
    }
);
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
function dropdownKey(partnerId, jahr, teil) {
    return `${partnerId}-${jahr}-${teil}`;
}

function toggleDropdown(partnerId, jahr, teil) {
    const key = dropdownKey(partnerId, jahr, teil);
    openDropdowns.value = openDropdowns.value[key] ? {} : { [key]: true };
}

function isDropdownOpen(partnerId, jahr, teil) {
    return openDropdowns.value[dropdownKey(partnerId, jahr, teil)] || false;
}

function closeDropdowns() {
    openDropdowns.value = {};
}

onMounted(() => document.addEventListener('click', closeDropdowns));
onBeforeUnmount(() => document.removeEventListener('click', closeDropdowns));

const openMenus = ref({});

function toggleMenu(key) {
    openMenus.value = {
        [key]: !openMenus.value[key]
    };
}

function isMenuOpen(key) {
    return openMenus.value[key] || false;
}

function schoolYearStart(jahr) {
    const value = String(jahr ?? '').trim();
    return value.match(/\d{4}/)?.[0] ?? value.split('/')[0].trim();
}

function sameSchoolYear(jahrA, jahrB) {
    return schoolYearStart(jahrA) === schoolYearStart(jahrB);
}


function getKlassen(jahr, teil, partner) {
    return [...new Set(
        partner.schueler
            .filter(s => sameSchoolYear(s.schuljahr, jahr) && s.teil === teil)
            .map(s => s.klasse)
            .filter(Boolean)
    )];
}

function getSchuelerCount(jahr, teil, partner) {
    return new Set(
        partner.schueler
            .filter(s => sameSchoolYear(s.schuljahr, jahr) && s.teil === teil)
            .map(s => s.personen_id ?? s.person_id ?? s.id)
            .filter(Boolean)
    ).size;
}

function getSchuljahre(partner) {
    return [...new Set(
        [
            ...(partner.schueler ?? []).map(schueler => schueler.schuljahr),
            ...(partner.bop_plans ?? []).map(plan => plan.schuljahr),
        ]
            .filter(jahr => jahr !== null && jahr !== undefined && jahr !== '')
            .map(schoolYearStart)
    )].sort((jahrA, jahrB) => String(jahrB).localeCompare(String(jahrA), 'de', { numeric: true }));
}

function bopYearStatus(partner, jahr) {
    return (partner.bop_plans ?? []).find(plan => sameSchoolYear(plan.schuljahr, jahr))?.status || null;
}

function bopYearClass(partner, jahr) {
    return {
        planning: 'text-red-600',
        confirmed: 'text-gray-950',
        completed: 'text-green-700',
    }[bopYearStatus(partner, jahr)] || 'text-gray-700';
}

function bopYearTitle(partner, jahr) {
    return {
        planning: 'BOP-Planung: In Planung',
        confirmed: 'BOP-Planung: Bestätigt',
        completed: 'BOP-Planung: Abgeschlossen',
    }[bopYearStatus(partner, jahr)] || 'Noch keine BOP-Planung gespeichert';
}

function displaySchoolYear(jahr) {
    return isBopProject.value ? schoolYearStart(jahr) : jahr;
}

function normalizedPart(part) {
    return String(part ?? '').replace(/^Teil\s*/i, '').trim();
}

function getTeile(partner, jahr) {
    if (!participantPartsEnabled.value) return ['1'];
    const actualParts = (partner.schueler ?? [])
        .filter(student => sameSchoolYear(student.schuljahr, jahr))
        .map(student => String(student.teil));
    const plannedParts = (partner.bop_plans ?? [])
        .filter(plan => sameSchoolYear(plan.schuljahr, jahr))
        .flatMap(plan => plan.parts ?? ['1'])
        .map(String);
    const parts = new Map();
    actualParts.forEach(part => parts.set(normalizedPart(part), part));
    plannedParts.forEach(part => {
        const key = normalizedPart(part);
        if (!parts.has(key)) parts.set(key, part);
    });
    return [...parts.values()].sort((partA, partB) => normalizedPart(partA).localeCompare(normalizedPart(partB), 'de', { numeric: true }));
}

function partLabel(part) {
    return `Teil ${normalizedPart(part)}`;
}

function partHasParticipants(partner, jahr, teil) {
    const key = normalizedPart(teil);
    return (partner.schueler ?? []).some(student =>
        sameSchoolYear(student.schuljahr, jahr) && normalizedPart(student.teil) === key
    );
}

async function openPartMenu(partner, jahr, teil) {
    if (!partHasParticipants(partner, jahr, teil)) {
        await Swal.fire({
            title: 'Teilnehmer fehlen',
            text: `Für ${partLabel(teil)} im Schuljahr ${displaySchoolYear(jahr)} wurden noch keine Teilnehmer importiert.`,
            icon: 'info',
            confirmButtonText: 'Schließen',
        });
        return;
    }
    toggleDropdown(partner.id, jahr, teil);
}

function handleBopPlanSaved(payload) {
    const partner = localPartners.value.find(item => Number(item.id) === Number(modalData.value.partnerId));
    const run = payload?.run;
    if (!partner) return;
    if (payload?.reset_mode === 'full') {
        partner.bop_plans = (partner.bop_plans ?? []).filter(plan => !sameSchoolYear(plan.schuljahr, payload.context?.schuljahr));
        return;
    }
    if (!run) return;
    const plans = [...(partner.bop_plans ?? [])].filter(plan => !payload.previous_schuljahr || !sameSchoolYear(plan.schuljahr, payload.previous_schuljahr));
    const index = plans.findIndex(plan => sameSchoolYear(plan.schuljahr, run.schuljahr));
    const summary = { id: run.id, partner_id: run.partner_id, schuljahr: run.schuljahr, status: run.status, parts: run.parts || ['1'], updated_at: run.updated_at };
    if (index >= 0) plans[index] = summary;
    else plans.push(summary);
    partner.bop_plans = plans;
}

async function openBopPlannerForSchool(partner) {
    closeDropdowns();
    const now = new Date();
    const currentStartYear = now.getMonth() >= 7 ? now.getFullYear() : now.getFullYear() - 1;
    const currentYear = String(currentStartYear);

    openModal('bopRunPlanner', {
        jahr: currentYear,
        teil: '_all',
        partnerId: partner.id,
        schoolName: partner.name,
        schoolYears: getSchuljahre(partner),
    });
}

function openBopPlannerForYear(partner, jahr) {
    closeDropdowns();
    openModal('bopRunPlanner', {
        jahr: String(jahr),
        teil: '_all',
        partnerId: partner.id,
        schoolName: partner.name,
        schoolYears: getSchuljahre(partner),
    });
}

// -----------------------------
// Modal-Funktionen
// -----------------------------
function openModal(modalName, { jahr = null, teil = null, klasse = null, partnerId = null, schoolName = null, schoolYears = [], klassen = null, teilnehmerCount = 0 } = {}) {
    activeModal.value = modalName;
    modalData.value = { jahr, teil, klasse, partnerId, schoolName, schoolYears, klassen, teilnehmerCount };
}

async function openPreparationPaModal({ jahr, teil, partner }) {
    closeDropdowns();

    const klassen = getKlassen(jahr, teil, partner)
        .sort((klasseA, klasseB) => String(klasseA).localeCompare(String(klasseB), 'de', { numeric: true }));

    if (klassen.length === 0) {
        await Swal.fire({
            title: 'Keine Klasse gefunden',
            text: 'Für dieses Schuljahr und diesen Teil sind keine Klassen hinterlegt.',
            icon: 'info',
            confirmButtonText: 'Schließen',
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Umfang auswählen',
        text: 'Soll die digitale Anwesenheitsliste Vorbereitung PA für die gesamte Schule oder für eine einzelne Klasse erstellt werden?',
        input: 'select',
        inputOptions: {
            __all__: 'Gesamte Schule / alle Klassen',
            ...Object.fromEntries(klassen.map(klasse => [klasse, `Klasse ${klasse}`])),
        },
        inputPlaceholder: 'Bitte Umfang auswählen',
        showCancelButton: true,
        confirmButtonText: 'Anwesenheitsliste öffnen',
        cancelButtonText: 'Abbrechen',
        inputValidator: value => value ? undefined : 'Bitte gesamte Schule oder eine Klasse auswählen.',
    });

    if (!result.isConfirmed || !result.value) return;

    openModal('anwesenheitslisteVorbereitungPA', {
        jahr,
        teil,
        klasse: result.value === '__all__' ? '' : result.value,
        klassen,
        partnerId: partner.id,
    });
}

function closeModal() {
    activeModal.value = null;
    modalData.value = { jahr: null, teil: null, klasse: null, partnerId: null, schoolName: null, schoolYears: [], klassen: [], teilnehmerCount: 0 };
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
// Der automatische Abgleich ist absichtlich langsam und pausiert in inaktiven Tabs.
// Die alte 5-Sekunden-Abfrage lud dauerhaft sehr viele Relationen und konnte PHP-FPM
// auf dem Produktivserver ueberlasten.
const PARTNER_REFRESH_INTERVAL = 60_000;
let partnerRefreshTimer = null;
let partnerRefreshController = null;
let partnerRefreshRunning = false;
let partnerRefreshActive = false;

const fetchPartners = async () => {
    partnerRefreshController?.abort();
    partnerRefreshController = new AbortController();

    try {
        const response = await axios.get(route('partner.indexAjaxFresh'), {
            params: { search: search.value },
            signal: partnerRefreshController.signal,
        });
        return response.data.partners;
    } catch (error) {
        if (error.code !== 'ERR_CANCELED') {
            console.error('Fehler beim Abrufen der Partners:', error);
        }
        return null;
    }
};

const compareAndReload = async () => {
    if (partnerRefreshRunning || document.hidden) return;

    partnerRefreshRunning = true;
    try {
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
    } finally {
        partnerRefreshRunning = false;
    }
};

const schedulePartnerRefresh = () => {
    clearTimeout(partnerRefreshTimer);
    partnerRefreshTimer = null;

    if (partnerRefreshActive && !document.hidden) {
        partnerRefreshTimer = window.setTimeout(async () => {
            await compareAndReload();
            schedulePartnerRefresh();
        }, PARTNER_REFRESH_INTERVAL);
    }
};

const handlePartnerVisibilityChange = () => {
    if (document.hidden) {
        clearTimeout(partnerRefreshTimer);
        partnerRefreshTimer = null;
        partnerRefreshController?.abort();
        return;
    }

    compareAndReload().finally(schedulePartnerRefresh);
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => compareAndReload().finally(schedulePartnerRefresh), 250);
});

onMounted(() => {
    partnerRefreshActive = true;
    document.addEventListener('visibilitychange', handlePartnerVisibilityChange);
    schedulePartnerRefresh();
});

onBeforeUnmount(() => {
    partnerRefreshActive = false;
    document.removeEventListener('visibilitychange', handlePartnerVisibilityChange);
    clearTimeout(partnerRefreshTimer);
    clearTimeout(searchTimeout);
    partnerRefreshController?.abort();
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
        <div class="mx-auto mb-3 flex w-full max-w-7xl items-stretch overflow-hidden rounded-md border border-gray-300 bg-white shadow-md">
            <button type="button" @click="openModalCreate" class="flex w-14 shrink-0 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white" title="Partner anlegen">
                <i
                    class="la la-plus"></i>
            </button>

            <label for="search" class="sr-only">{{ $t('Suchen') }}</label>
            <input id="search" v-model="search" type="text"
                class="block min-w-0 flex-1 border-0 p-2.5 text-sm text-gray-900 focus:border-orange-500 focus:ring-orange-500"
                placeholder="Suchen ..." />


            <Link :href="route('partner.index')" class="flex w-14 shrink-0 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white" title="Aktualisieren">
                <i
                    class="la la-refresh"></i>
            </Link>
        </div>
        <!-- Partnerausgabe -->
        <div class="relative mx-auto mb-10 w-full max-w-7xl overflow-visible">
            <table id="table"
                class="mb-5 w-full table-auto border-collapse border border-gray-300 text-left text-sm shadow-sm">
                <thead class="text-md text-gray-600 uppercase bg-gray-200">
                    <tr>
                        <th class="hidden w-16 border border-gray-300 px-3 py-3 text-center md:table-cell xl:px-6">ID</th>
                        <th class="w-[46%] border border-gray-300 px-3 py-3 xl:w-48 xl:px-6">Bezeichnung</th>
                        <th class="border border-gray-300 px-3 py-3 xl:w-1/4 xl:px-6">Ansprechpartner <span class="normal-case xl:hidden">/ Details</span></th>
                        <th class="hidden w-1/4 border border-gray-300 px-6 py-3 xl:table-cell">Adresse</th>
                        <th class="hidden w-1/4 border border-gray-300 px-6 py-3 xl:table-cell">Kontakt</th>
                        <th class="hidden w-1/4 border border-gray-300 px-6 py-3 xl:table-cell">Partnerschaftstypen</th>
                        <th class="hidden w-40 border border-gray-300 px-6 py-3 xl:table-cell">Beschreibung</th>
                        <th class="w-12 border border-gray-300 px-2 py-3 text-center">*</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="partner in filteredPartners" :key="partner.id">

                        <tr v-for="(person, index) in partner.ansprechpartners" :key="person.id"
                            class="bg-white border-b border-gray-300">

                            <!-- ID (nur einmal anzeigen) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="hidden align-middle border-r border-gray-300 px-3 py-4 text-center md:table-cell xl:px-6">
                                {{ partner.id }}
                            </td>

                            <!-- NAME + SCHULJAHR (nur einmal anzeigen) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="min-w-0 align-middle border-r border-gray-300 px-3 py-4 text-sm xl:px-6">

                                <button
                                    v-if="isBopProject && can('einteilung.planning')"
                                    type="button"
                                    class="block max-w-full break-words text-left font-bold text-orange-700 hover:underline"
                                    title="BOP-Ablauf dieser Schule planen"
                                    @click="openBopPlannerForSchool(partner)"
                                >
                                    {{ partner.name }}
                                </button>
                                <div v-else class="break-words font-bold">{{ partner.name }}</div>

                                <div class="mt-2 flex flex-wrap gap-3" v-if="partner.ansprechpartners.some(p =>
                                    p.partner_typ?.some(t => t.bezeichnung === 'Kooperationsschule')
                                )">

                                    <div v-for="jahr in getSchuljahre(partner)" :key="jahr">
                                        <button
                                            v-if="isBopProject && can('einteilung.planning')"
                                            type="button"
                                            class="block text-xs font-bold hover:underline"
                                            :class="bopYearClass(partner, jahr)"
                                            :title="bopYearStatus(partner, jahr) ? `${bopYearTitle(partner, jahr)} · anklicken zum Bearbeiten` : 'Noch keine BOP-Planung gespeichert · anklicken zum Nachtragen'"
                                            @click.stop="openBopPlannerForYear(partner, jahr)"
                                        >
                                            {{ displaySchoolYear(jahr) }}
                                        </button>
                                        <div v-else class="font-bold text-xs" :class="isBopProject ? bopYearClass(partner, jahr) : 'text-gray-700'" :title="isBopProject ? bopYearTitle(partner, jahr) : ''">{{ displaySchoolYear(jahr) }}</div>

                                        <div class="flex gap-1">
                                            <span v-for="teil in getTeile(partner, jahr)" :key="teil" class="text-xs">

                                                <!-- Dropdown -->
                                                <div class="dropdown dropdown-action inline-block relative" @click.stop>
                                                    <button @click="openPartMenu(partner, jahr, teil)"
                                                        class="dropdown-toggle py-1 rounded text-xs w-full">
                                                        {{ participantPartsEnabled ? normalizedPart(teil) : 'Aktionen' }}
                                                    </button>
                                                    <div v-show="isDropdownOpen(partner.id, jahr, teil)"
                                                        class="dropdown-menu absolute z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded border bg-white text-xs shadow-lg">

                                                        <!-- Links analog Blade -->

                                                        <!-- Bearbeitet -->

                                                         <button
                                                            v-if="can('anwesenheit.abrechnung')"
                                                            type="button"
                                                            @click="openPreparationPaModal({ jahr, teil, partner })"
                                                            class="block w-full px-4 py-1 text-left hover:bg-gray-200"
                                                         >
                                                            Anwesenheitsliste Vorbereitung PA
                                                         </button>

                                                        <a
                                                            v-if="isBopProject && can('gruppe.bop.export.namensschilder')"
                                                            :href="route('partner.bop.export.namensschilder', { partner: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200"
                                                        >
                                                            Namensschilder
                                                        </a>

                                                        <!-- 🔽 Anwesenheitsliste BO Bibb -->
                                                        <a v-if="can('anwesenheit.abrechnung')" @click.prevent="openModal('anwesenheitslisteBoTagbibb', { jahr, teil, partnerId: partner.id })" class="block px-4 py-1 hover:bg-gray-200"> Anwesenheitsliste BO Bibb</a>
                                                        <a
                                                            v-if="can('anwesenheit.abrechnung')"
                                                            @click.prevent="openModal('anwesenheitslistePATage', { jahr, teil, klassen: getKlassen(jahr, teil, partner), partnerId: partner.id })"
                                                            class="block px-4 py-1 hover:bg-gray-200"
                                                        >
                                                            Anwesenheitsliste PA
                                                        </a>

                                                        <!-- Rolltag -->
                                                        <a v-if="can('anwesenheit.abrechnung')" @click.prevent="openModal('boTag1Config', { jahr, teil, klassen: getKlassen(jahr, teil, partner), partnerId: partner.id, teilnehmerCount: getSchuelerCount(jahr, teil, partner) })" class="block px-4 py-1 hover:bg-gray-200"> Rolltag </a>

                                                        <!-- 🔽 Hausordnung -->

                                                        <div class="relative">
                                                            <button
                                                                v-if="can('dokumente.schule.export')"
                                                                @click="openModal('hausordnungConfig', { jahr, teil, partnerId: partner.id })"
                                                                class="w-full text-left px-4 py-1 hover:bg-gray-200">
                                                                Hausordnung
                                                            </button>
                                                        </div>

                                                        <button
                                                            v-if="isBopProject && can('dokumente.schule.export')"
                                                            type="button"
                                                            class="block w-full px-4 py-1 text-left hover:bg-gray-200"
                                                            @click="openModal('usbStickBrief', { jahr, teil, partnerId: partner.id, schoolName: partner.name })"
                                                        >
                                                            USB-Stick-Brief
                                                        </button>

                                                        <div v-if="partnerDokumente.length" class="my-1 border-t border-gray-200 pt-1">
                                                            <template v-for="dokument in partnerDokumente" :key="dokument.id">
                                                                <a
                                                                    v-for="format in dokument.ausgabeformate"
                                                                    :key="`${dokument.id}-${format}`"
                                                                    :href="route('partner.document.export', { partner: partner.id, dokument: dokument.id, schuljahr: jahr, teil, format })"
                                                                    class="flex items-center justify-between gap-3 px-4 py-1 hover:bg-gray-200"
                                                                >
                                                                    <span>{{ dokument.name }}</span>
                                                                    <span class="text-[10px] font-semibold uppercase text-gray-400">{{ format }}</span>
                                                                </a>
                                                            </template>
                                                        </div>

                                                        <!--  Bereichsauswahl -->
                                                        <a v-if="canAnySelectionPermission" :href="route('bereichsauswahl.index', { partnerId: partner.id, schuljahr: jahr, teil: teil })" class="block px-4 py-1  hover:bg-gray-200">Bereichsauswahl</a>
                                                        <a v-if="can('dokumente.schule.export')" :href="route('export.auswertungsbogenPA.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">Auswertungsbogen PA</a>
                                                        <div v-if="can('dokumente.schule.export')">
                                                            <button
                                                                type="button"
                                                                class="flex w-full items-center justify-between px-4 py-1 text-left hover:bg-gray-200"
                                                                @click.stop="toggleMenu(`roland-${partner.id}-${jahr}-${teil}`)"
                                                            >
                                                                <span>Auswertungsbogen PA neu Roland</span>
                                                                <span>{{ isMenuOpen(`roland-${partner.id}-${jahr}-${teil}`) ? '▼' : '▶' }}</span>
                                                            </button>
                                                            <div v-show="isMenuOpen(`roland-${partner.id}-${jahr}-${teil}`)" class="border-l-2 border-gray-200 pl-3">
                                                                <a
                                                                    v-for="klasse in getKlassen(jahr, teil, partner)"
                                                                    :key="`roland-${partner.id}-${jahr}-${teil}-${klasse}`"
                                                                    :href="route('export.auswertungsbogenPA.roland.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil, klasse })"
                                                                    class="block px-4 py-1 hover:bg-gray-200"
                                                                >
                                                                    Klasse {{ klasse }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <a v-if="can('dokumente.ansprechpartner.manage')" :href="route('export.elterneinverstaendniserklaerung.schule', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">X Elterneinverständniserklärung</a>

                                                        <Link v-if="canAnyAssignmentPermission" :href="route('einteilung.show', { partnerId: partner.id, schuljahr: jahr, teil })" class="block px-4 py-1 hover:bg-gray-200">Einteilung</Link>


                                                       <!--  <div class="relative">
                                                            <button @click="toggleMenu(`haus-${jahr}-${teil}`)" class="w-full text-left px-4 py-1 hover:bg-gray-200"> Hausordnung ▶ </button>

                                                            <div v-show="isMenuOpen(`haus-${jahr}-${teil}`)" class="absolute left-full w-full top-0 ml-1 bg-white border rounded shadow-lg z-50">
                                                                <a :href="route('hausordnung.export.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil, sortBy: 'klasse' })" class="block px-4 py-1 hover:bg-gray-200 w-full"> Nach Klasse </a>

                                                                <a :href="route('hausordnung.export.schule.pdf', { partnerId: partner.id, schuljahr: jahr, teil, sortBy: 'nachname' })" class="block px-4 py-1 hover:bg-gray-200"> Nach Nachname  </a>

                                                            </div>
                                                        </div> -->



                                                        <!-- ⚠️ zu erlegigen -->


                                                        <a href="#">________________________________________</a>
                                                        <a v-if="can('anwesenheit.abrechnung')" :href="route('index-anpassung-anwesenheitsdaten', { schulId: partner.id, schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsdaten</a>

                                                         <a v-if="can('teilnehmer.liste.export')" :href="route('export.teilnehmerliste.schule.excel', { schuleId: partner.id, schuljahr: jahr, teil: teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Teilnehmerliste</a>





                                                        <a v-if="can('dokumente.ansprechpartner.manage')" :href="route('alleTeilnehmer.folder.create', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Ordner  anlegen</a>

                                                       




                                                        <a v-if="can('anwesenheit.abrechnung')" :href="route('export.anwesenheitsliste.rechnung', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Anwesenheitsliste
                                                            Rechnung</a>

                                                        <a v-if="can('dokumente.schule.export')" :href="route('export.zertifikat.schule.pobo', { idSchule: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO</a>

                                                        <a v-if="can('dokumente.schule.export')" :href="route('export.zertifikat.schule.pobo.pdf', { schuleId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Zertifikat
                                                            POBO PDF</a>

                                                        <a v-if="can('dokumente.schule.export')" :href="route('export.auswertungBO.schule.pdf', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertung
                                                            POBO</a>

                                                        <a v-if="can('dokumente.ansprechpartner.manage')" :href="route('export.auswertungBO.schule.pdf.tofolder', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">BO
                                                            Auswertungen in Ordner generieren</a>

                                                        <a v-if="can('dokumente.ansprechpartner.manage')" :href="route('export.auswertungPA.schule.pdf.tofolder', { schulId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">PA
                                                            Berichte in Ordner generieren</a>

                                                        <a v-if="can('dokumente.schule.export')" :href="route('auswertungPoboModal', { schuleId: partner.id, schuljahr: jahr, teil })"
                                                            class="block px-4 py-1 hover:bg-gray-200">Auswertung POBO Runde</a>

                                                    </div>
                                                </div>

                                            </span>
                                        </div>
                                    </div>

                                </div>

                                <p v-if="partner.beschreibung" class="mt-3 break-words text-xs text-gray-500 xl:hidden">
                                    {{ partner.beschreibung }}
                                </p>
                            </td>

                            <!-- Ansprechpartner -->
                            <td class="min-w-0 border-r border-gray-300 px-3 py-4 align-top xl:px-6">
                                <div class="break-words font-medium text-gray-800">{{ person.vorname }} {{ person.nachname }}</div>

                                <div class="mt-2 space-y-2 text-xs text-gray-500 xl:hidden">
                                    <div v-for="adresse in person.adresses" :key="`compact-address-${adresse.id}`" class="flex items-start gap-1">
                                        <i class="la la-map-marker mt-0.5 shrink-0 text-zbb/70"></i>
                                        <span class="min-w-0 break-words">{{ adresse.strasse }} {{ adresse.hausnummer }}, {{ adresse.plz }} {{ adresse.stadt }}</span>
                                    </div>
                                    <div v-for="kontakt in person.kontaktes" :key="`compact-contact-${kontakt.id}`" class="flex items-start gap-1">
                                        <i class="la la-address-book mt-0.5 shrink-0 text-zbb/70"></i>
                                        <span class="min-w-0 break-all">{{ kontakt.kontakttyp?.name }}: {{ kontakt.wert }}</span>
                                    </div>
                                    <div v-if="person.partner_typ?.length" class="flex flex-wrap gap-1">
                                        <span v-for="typ in person.partner_typ" :key="`compact-type-${typ.id}`" class="rounded bg-orange-500 px-2 py-0.5 text-[10px] font-bold text-white">
                                            {{ typ.bezeichnung }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Adresse -->
                            <td class="hidden border-r border-gray-300 px-6 py-4 align-top xl:table-cell">
                                <div v-for="adresse in person.adresses" :key="adresse.id">
                                    {{ adresse.strasse }} {{ adresse.hausnummer }}<br>
                                    {{ adresse.plz }} {{ adresse.stadt }}
                                </div>
                            </td>

                            <!-- Kontakt -->
                            <td class="hidden border-r border-gray-300 px-6 py-4 align-top xl:table-cell">
                                <div v-for="kontakt in person.kontaktes" :key="kontakt.id">
                                    {{ kontakt.kontakttyp?.name }}: {{ kontakt.wert }}
                                </div>
                            </td>

                            <!-- Partnerschaftstyp -->
                            <td class="hidden border-r border-gray-300 px-6 py-4 align-top xl:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="typ in person.partner_typ" :key="typ.id"
                                        class="bg-orange-500 text-white rounded px-2 py-0.5 text-[10px] font-bold">
                                        {{ typ.bezeichnung }}
                                    </span>
                                </div>
                            </td>

                            <!-- Beschreibung (nur einmal) -->
                            <td v-if="index === 0" :rowspan="partner.ansprechpartners.length"
                                class="hidden border-r border-gray-300 px-6 py-4 align-top xl:table-cell">
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
        <ModalUsbStickBrief v-if="activeModal === 'usbStickBrief'" :partner-id="modalData.partnerId" :schuljahr="modalData.jahr" :school-name="modalData.schoolName" @close="closeModal" />
        <BopRunPlanner
            v-if="activeModal === 'bopRunPlanner'"
            :visible="true"
            :partner-id="modalData.partnerId"
            :schuljahr="modalData.jahr"
            :teil="modalData.teil"
            :school-name="modalData.schoolName"
            :school-years="modalData.schoolYears"
            @saved="handleBopPlanSaved"
            @close="closeModal"
        />



    </app-layout>
</template>

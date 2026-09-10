<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { applyUploadCsrf } from '@/utils/uploadCsrf.mjs';
import { redirectAfterSessionExpiry } from '@/keepAlive';
import ModalCreateTeilnehmer from '@/Pages/Teilnehmer/ModalCreateTeilnehmer.vue';
import ZurGruppeHinzufügen from '@/Components/ZurGruppeHinzufuegen.vue';
import { usePermissions } from '@/utils/permissions';

import Dropzone from "dropzone";
import "dropzone/dist/dropzone.css";
Dropzone.autoDiscover = false;
let dropzoneInstance = null;


const teilnehmerList = ref([...teilnehmers.data]);

watch(() => teilnehmers.data, (newValue) => {
    teilnehmerList.value = [...newValue];
});
// Suchfeld und Dropdown fuer Standorte
let seite = 'teilnehmer'; // Für die Löschseite
let search = ref(filters?.search ?? '');
let searchStandort = ref('');
let checkBoxListeTeilnehmer = ref(false); //Teilnehmer zur Projekten/Gruppen hinzufügen
let selectedStandort = ref(filters?.standort ?? null);
let selectedSchool = ref(filters?.schule ?? null);
let selectedInstructor = ref(filters?.anleiter ?? null);
let selectedArea = ref(filters?.bereich ?? null);
let isModalOpen = ref(false); // Modal-Zustand
let sortColumn = ref(filters?.sort ?? 'id');  // Spalte zum Sortieren
let sortDirection = ref(filters?.direction ?? 'desc'); // Sortierrichtung ('asc' oder 'desc')
let selectedPeriod = ref(filters?.period ?? new Date().toISOString().slice(0, 7));
let attentionFilter = ref('all');

const selected = ref([]);
const groupModal = ref(null);
// Lokale Teilnehmerliste
let teilnehmerToDelete = ref(null); // Speichert den Namen der Teilnehmer, die gelöscht werden sollen
let showModalLöschen = ref(false); // Modal für die Löschung

const { teilnehmers, authProjekte, rollen, gruppen, projekte, standorte, anleiter, bereiche, canUseAdvancedGroupFilters, defaultProjekt, filters, overviewPeriods, overviewStats, participantOverviewColumns, participantOverviewColumnDefinitions, participantOverviewShowMetrics, participantSchools  } = defineProps({
    pagination: {
        type: Object,
    },
    teilnehmers: { type: Object, default: () => ({ data: [], links: [] }) },
    authProjekte: {
        type: Array,
        default: () => []
    },
    rollen: {
        type: Object,
        default: () => ({})
    },
    gruppen: {
        type: Array,
        default: () => []
    },
    projekte: {
        type: Array,
        default: () => []
    },
    standorte: {
        type: Array,
        default: () => []
    },
    anleiter: { type: Array, default: () => [] },
    bereiche: { type: Array, default: () => [] },
    canUseAdvancedGroupFilters: { type: Boolean, default: false },
     defaultProjekt: { type: Number, default: null },
     overviewPeriods: { type: Array, default: () => [] },
     overviewStats: { type: Object, default: () => ({}) },
     participantOverviewColumns: { type: Array, default: () => [] },
     participantOverviewColumnDefinitions: { type: Array, default: () => [] },
     participantOverviewShowMetrics: { type: Boolean, default: true },
     participantSchools: { type: Array, default: () => [] },
     filters: {
        type: Object,
        default: () => ({})
     },

});
const { can } = usePermissions();
const showImportModal = ref(false);
const importProfile = ref('auto');
const importPreview = ref(null);
const importFile = ref(null);
const importSaving = ref(false);
const importUploading = ref(false);
const importContextLoading = ref(false);
const importContext = ref(null);
const importLocationId = ref(null);
const importBusy = computed(() => importSaving.value || importUploading.value || importContextLoading.value);
watch(importLocationId, () => { importPreview.value = null; });
const importDecisions = ref({});
const importReviews = ref([]);
const activeImportReview = ref(null);
const importResult = ref(null);
const selectedImportCount = computed(() => Object.values(importDecisions.value).filter(choice => ['new','reuse','separate'].includes(choice.action)).length);
const setImportPreview = (response, file) => {
    importPreview.value = response;
    importFile.value = file;
    importResult.value = null;
    importDecisions.value = Object.fromEntries(response.rows.map(row => [row.line, { action: row.match.status === 'new' ? 'new' : 'defer', person_id: null, identity_checked: false }]));
};
const loadImportReviews = async () => {
    try { importReviews.value = (await axios.get(route('teilnehmer.import.reviews'))).data.reviews; }
    catch (error) { Swal.fire('Fehler', formatImportMessage(error.response?.data), 'error'); }
};
const loadImportContext = async () => {
    importContextLoading.value = true;
    importContext.value = null;
    try { importContext.value = (await axios.get(route('teilnehmer.import.context'))).data; }
    catch (error) { Swal.fire('Import nicht verfügbar', formatImportMessage(error.response?.data), 'error'); }
    finally { importContextLoading.value = false; }
};
const appendImportContext = (payload) => {
    payload.append('standort_id', importLocationId.value);
    payload.append('project_id', importContext.value.project.id);
};
const previewParticipantImport = async () => {
    if (importBusy.value || !importFile.value || !importLocationId.value || !importContext.value) return;
    importSaving.value = true;
    importPreview.value = null;
    try {
        const payload = new FormData();
        payload.append('file', importFile.value);
        payload.append('preview', '1');
        payload.append('import_profile', importProfile.value);
        appendImportContext(payload);
        if (activeImportReview.value) payload.append('review_id', activeImportReview.value);
        const { data } = await axios.post(route('teilnehmer.import'), payload);
        setImportPreview(data, importFile.value);
    } catch (error) { Swal.fire('Prüfung nicht möglich', formatImportMessage(error.response?.data), 'error'); }
    finally { importSaving.value = false; }
};
const resumeImportReview = async (review) => {
    if (importBusy.value || !importContext.value) return;
    importSaving.value = true;
    importPreview.value = null;
    importFile.value = null;
    activeImportReview.value = null;
    try {
        const { data: saved } = await axios.get(route('teilnehmer.import.reviews.resume', review.id));
        if (saved.standort_id && importContext.value.locations.some(location => location.id === saved.standort_id)) {
            importLocationId.value = saved.standort_id;
        } else {
            importLocationId.value = null;
        }
        importFile.value = new File([saved.csv], 'zurueckgestellte-teilnehmer.csv', { type: 'text/csv' });
        importProfile.value = saved.profile;
        activeImportReview.value = saved.id;
    } catch (error) { Swal.fire('Prüfung nicht möglich', formatImportMessage(error.response?.data), 'error'); }
    finally { importSaving.value = false; }
    if (importLocationId.value) await previewParticipantImport();
};
const confirmParticipantImport = async () => {
    if (!importPreview.value || !importFile.value || importBusy.value || !importLocationId.value) return;
    importSaving.value = true;
    const data = new FormData();
    data.append('file', importFile.value);
    data.append('import_profile', importPreview.value.profile);
    data.append('confirmation', importPreview.value.confirmation);
    data.append('decisions', JSON.stringify(importDecisions.value));
    appendImportContext(data);
    if (activeImportReview.value) data.append('review_id', activeImportReview.value);
    try {
        const { data: response } = await axios.post(route('teilnehmer.import'), data);
        if (response.error) throw { response: { data: response } };
        importResult.value = response;
        activeImportReview.value = null;
        importPreview.value = null;
        importFile.value = null;
        Swal.fire('Import erfolgreich', response.message, 'success');
        await loadImportReviews();
        router.reload({ only: ['teilnehmers'] });
    } catch (error) {
        Swal.fire('Import nicht durchgeführt', formatImportMessage(error.response?.data), 'error');
        importPreview.value = null;
    } finally { importSaving.value = false; }
};
const parentalConsentSaving = ref(new Set());
const canCreateParticipant = computed(() => can('teilnehmer.store'));
const canImportParticipant = computed(() => can('teilnehmer.import') || can('teilnehmer.store'));
const canUpdateParticipant = computed(() => can('teilnehmer.update'));
const canDeleteParticipant = computed(() => can('teilnehmer.destroy'));
const canBulkDeleteParticipant = computed(() => can('teilnehmer.bulkDestroy') || can('teilnehmer.destroy'));
const canInviteToPortal = computed(() => can('teilnehmer.portal.invite'));
const canAssignParticipantToGroup = computed(() => can('gruppeHasTeilnehmer.store'));
const canUseSelectionActions = computed(() => canAssignParticipantToGroup.value || canBulkDeleteParticipant.value || canUpdateParticipant.value);
const canManageParticipantRows = computed(() => canUpdateParticipant.value || canDeleteParticipant.value || canInviteToPortal.value);

const inviteToPortal = async (teilnehmer) => {
    if (!canInviteToPortal.value || !teilnehmer?.overview?.participation_id || teilnehmer?.overview?.portal_account_exists) return;

    const savedEmail = teilnehmer.overview.participant_email || '';
    const invitationEmail = teilnehmer.overview.portal_invitation_email || '';
    const result = await Swal.fire({
        title: invitationEmail ? 'Portal-Einladung erneut senden' : 'Zum Portal einladen',
        html: !savedEmail && !invitationEmail
            ? '<div class="rounded border border-amber-200 bg-amber-50 p-3 text-left text-sm text-amber-800">Beim Teilnehmer ist keine E-Mail-Adresse gespeichert. Sie können die Adresse hier nur für die Einladung eintragen oder den Vorgang abbrechen und die E-Mail im Teilnehmerprofil speichern.</div>'
            : undefined,
        input: 'email',
        inputLabel: 'E-Mail-Adresse des Teilnehmers',
        inputValue: invitationEmail || savedEmail,
        inputPlaceholder: 'name@beispiel.de',
        showCancelButton: true,
        confirmButtonText: invitationEmail ? 'Einladung erneut senden' : 'Einladung senden',
        cancelButtonText: 'Abbrechen',
        inputValidator: (value) => !value ? 'Bitte eine E-Mail-Adresse angeben.' : undefined,
    });

    if (!result.isConfirmed) return;

    try {
        const { data } = await axios.post(route('teilnehmer.portal.invite', teilnehmer.overview.participation_id), { email: result.value });
        await Swal.fire('Einladung gesendet', data?.message || 'Die Portal-Einladung wurde per E-Mail gesendet.', 'success');
        router.reload({ only: ['teilnehmers'] });
    } catch (error) {
        await Swal.fire('Fehler', error.response?.data?.message || 'Die Einladung konnte nicht erstellt werden.', 'error');
    }
};

const importTeilnehmer = () => {
    if (!canImportParticipant.value) return;

    importPreview.value = null;
    importFile.value = null;
    activeImportReview.value = null;
    importResult.value = null;
    importLocationId.value = null;
    showImportModal.value = true;
    loadImportContext();
    loadImportReviews();

    setTimeout(() => {
        initDropzone();
    }, 200);

};

const initDropzone = () => {

    const el = document.querySelector("#mydropzone");
    if (!el) return;

    // verhindert doppelte Dropzone
    if (dropzoneInstance) {
        dropzoneInstance.destroy();
        dropzoneInstance = null;
    }

    dropzoneInstance = new Dropzone(el, {
        url: route("teilnehmer.import"),
        method: "post",
        paramName: "file",
        clickable: true,
        accept(file, done) {
            if (importBusy.value) done('Bitte den laufenden Vorgang abwarten.');
            else if (!importContext.value || !importLocationId.value) done('Bitte zuerst einen zugewiesenen Standort auswählen.');
            else done();
        },
        maxFilesize: 5,
        maxFiles: 1,
        params: () => ({ preview: '1', import_profile: importProfile.value, standort_id: importLocationId.value, project_id: importContext.value?.project.id }),
        acceptedFiles: ".csv,.xlsx,.xls",
        addRemoveLinks: true,

        headers: { Accept: 'application/json' },
        sending(file, xhr) {
            importUploading.value = true;
            applyUploadCsrf(xhr);
        },
        complete(file) {
            importUploading.value = false;
            file.previewElement?.classList.add('dz-complete');
        },

        dictDefaultMessage: "Datei hier hineinziehen oder klicken",

        success(file, response) {
            if (response?.error) {
                Swal.fire({
                    title: "Fehler",
                    text: formatImportMessage(response),
                    icon: "error"
                });
                return;
            }

            activeImportReview.value = null;
            setImportPreview(response, file);
        },

        removedfile(file) {
            file.previewElement?.remove();
            importFile.value = null;
            importPreview.value = null;
        },

        error(file, message, xhr) {
            if ([401, 419].includes(xhr?.status)) {
                redirectAfterSessionExpiry();
                return;
            }
            Swal.fire({
                title: "Fehler",
                text: formatImportMessage(message),
                icon: "error"
            });
        }
    });
};

const formatImportMessage = (response) => {
    if (typeof response === 'string') {
        return response;
    }

    const message = response?.message || 'Der Import konnte nicht abgeschlossen werden.';
    const errors = Array.isArray(response?.errors) ? response.errors : [];

    if (errors.length === 0) {
        return message;
    }

    return `${message}\n\n${errors.join('\n')}`;
};
// Löschbestätigung anzeigen und Abteilungsnamen speichern
const confirmDelete = (teilnehmer) => {
    if (!canDeleteParticipant.value) return;

    teilnehmerToDelete.value = {
        name: teilnehmer.vorname, // Speichere den Namen der Abteilung
        id: teilnehmer.id      // Speichere die ID der Abteilung
    };
    showModalLöschen.value = true; // Modal anzeigen
};
const deleteTeilnehmer = (id) => {
    // Sofort aus der lokalen Liste entfernen
    const index = teilnehmerList.value.findIndex(t => t.id === id);
    if (index !== -1) {
        teilnehmerList.value.splice(index, 1);
    }

    // Modal schließen
    showModalLöschen.value = false;

    // Optional: Alert
    Swal.fire({
        title: 'Erfolg!',
        text: 'Teilnehmer wurde gelöscht.',
        icon: 'success',
        timer: 2000
    });
};

// Watch fuer Aenderungen in Suche, Standort und Sortierung
watch([search, selectedStandort, selectedSchool, selectedInstructor, selectedArea, sortColumn, sortDirection, selectedPeriod], () => {
    router.get(route('teilnehmer.index'),
        {
            search: search.value,
            standort: selectedStandort.value,
            schule: selectedSchool.value,
            anleiter: selectedInstructor.value,
            bereich: selectedArea.value,
            sort: sortColumn.value,
            direction: sortDirection.value
            ,period: selectedPeriod.value
        },
        { preserveState: true, replace: true }
    );
});

// Gefilterte Standorte
const filteredStandorte = computed(() => {
    return standorte.filter(standort =>
        standort.name.toLowerCase().includes(searchStandort.value.toLowerCase())
    );
});

const filteredTeilnehmerByProject = computed(() => teilnehmerList.value.filter((participant) => {
    if (attentionFilter.value === 'overdue') return (participant.overview?.overdue_tasks || 0) > 0;
    if (attentionFilter.value === 'unexcused') return (participant.overview?.period?.unexcused_days || 0) > 0;
    if (attentionFilter.value === 'negative_balance') return (participant.overview?.period?.balance_minutes || 0) < 0;
    if (attentionFilter.value === 'measure_follow_up') return (participant.overview?.overdue_measure_follow_ups || 0) > 0;
    return true;
}));

const fallbackOverviewColumns = [
    'id',
    'first_name',
    'last_name',
    'participation',
    'group_supervisor',
    'period_balance',
    'total_balance',
    'absences',
    'tasks',
    'measures',
    'gender',
];

const overviewColumnDefinitionMap = computed(() => new Map(
    participantOverviewColumnDefinitions.map((column) => [column.key, column])
));

const visibleOverviewColumns = computed(() => {
    const configured = participantOverviewColumns?.length
        ? participantOverviewColumns
        : fallbackOverviewColumns;

    return configured
        .map((key) => overviewColumnDefinitionMap.value.get(key))
        .filter(Boolean);
});

const hasOverviewColumn = (key) => visibleOverviewColumns.value.some((column) => column.key === key);
const showOverviewMetrics = computed(() => Boolean(participantOverviewShowMetrics));
const usesPeriodFilter = computed(() => (
    showOverviewMetrics.value
    || hasOverviewColumn('period_balance')
    || hasOverviewColumn('absences')
));

const formatPeriod = (period) => {
    if (!period) return 'Monat';
    const [year, month] = period.split('-').map(Number);
    return new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' })
        .format(new Date(year, month - 1, 1));
};

const formatOverviewMinutes = (minutes) => {
    if (minutes === null || minutes === undefined) return '–';
    const sign = minutes < 0 ? '-' : minutes > 0 ? '+' : '';
    const absolute = Math.abs(minutes);
    return `${sign}${String(Math.floor(absolute / 60)).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}`;
};

const balanceClass = (minutes) => {
    if (minutes < 0) return 'text-red-600';
    if (minutes > 0) return 'text-green-600';
    return 'text-gray-700';
};

const participationStatusLabel = (status) => ({
    angefragt: 'Angefragt',
    angemeldet: 'Angemeldet',
    aufgenommen: 'Aufgenommen',
    aktiv: 'Aktiv',
    pausiert: 'Pausiert',
    abgeschlossen: 'Abgeschlossen',
    abgebrochen: 'Abgebrochen',
}[status] || status || '–');


const overviewColumnLabel = (column) => {
    if (column.key === 'period_balance') return formatPeriod(selectedPeriod.value);
    if (column.key === 'parental_consent') return 'EEE';

    return column.label;
};

const overviewColumnTitle = (column) => {
    if (column.key === 'parental_consent') {
        return 'Elterneinverständniserklärung';
    }

    return column.label;
};

const sortKeyForOverviewColumn = (column) => column.sortable || null;

const sortByOverviewColumn = (column) => {
    const sortKey = sortKeyForOverviewColumn(column);

    if (sortKey) {
        sortByColumn(sortKey);
    }
};

const overviewSortIconClass = (column) => {
    const sortKey = sortKeyForOverviewColumn(column);

    if (!sortKey) {
        return '';
    }

    const sortType = sortKey === 'id' ? 'numeric' : 'alpha';
    const direction = sortColumn.value === sortKey && sortDirection.value === 'asc' ? 'down' : 'up';

    if (sortType === 'numeric') {
        return direction === 'down' ? 'las la-lg la-sort-numeric-down-alt' : 'las la-lg la-sort-numeric-up-alt';
    }

    return direction === 'down' ? 'las la-lg la-sort-alpha-down' : 'las la-lg la-sort-alpha-up';
};

const overviewHeaderClass = (column) => [
    'border border-solid border-gray-300 px-4 py-3',
    sortKeyForOverviewColumn(column) ? 'cursor-pointer select-none' : '',
];

const overviewCellClass = (column) => {
    const base = 'border border-solid border-gray-300 px-4 py-4';
    const widths = {
        id: 'px-6',
        first_name: 'px-6 min-w-32',
        last_name: 'px-6 min-w-32',
        participation: 'min-w-36',
        group_supervisor: 'min-w-44',
        period_balance: 'min-w-36',
        total_balance: 'min-w-36',
        absences: 'min-w-36',
        tasks: 'min-w-32',
        measures: 'min-w-36',
        school: 'min-w-52',
        visited_areas: 'min-w-80',
        parental_consent: 'min-w-20 text-center',
    };

    return `${base} ${widths[column.key] || ''}`;
};

const joinOrDash = (values) => {
    return Array.isArray(values) && values.length ? values.join(', ') : '-';
};

const schoolContextLabel = (school) => joinOrDash(school?.contexts || []);

const parentalConsentStatusText = (value) => {
    if (value === null || value === undefined) {
        return 'Elterneinverständniserklärung nicht verfügbar';
    }

    return value
        ? 'Elterneinverständniserklärung eingegangen'
        : 'Elterneinverständniserklärung fehlt';
};

const parentalConsentClass = (value) => {
    if (value === null || value === undefined) {
        return 'text-gray-400';
    }

    return value ? 'text-green-600' : 'text-red-600';
};

const parentalConsentIconClass = (value) => {
    if (value === null || value === undefined) {
        return 'las la-minus';
    }

    return value ? 'las la-check' : 'las la-times';
};

const canUpdateParentalConsent = () => can('teilnehmer.elterneinverstaendnis.update');
const isParentalConsentSaving = (participantId) => parentalConsentSaving.value.has(participantId);
const setParentalConsentSaving = (participantId, saving) => {
    const next = new Set(parentalConsentSaving.value);

    if (saving) {
        next.add(participantId);
    } else {
        next.delete(participantId);
    }

    parentalConsentSaving.value = next;
};

const canToggleParentalConsent = (participant) => {
    const value = participant.overview?.school?.parental_consent_received;

    return canUpdateParentalConsent()
        && value !== null
        && value !== undefined
        && !isParentalConsentSaving(participant.id);
};

const toggleParentalConsent = async (participant) => {
    if (!canToggleParentalConsent(participant)) {
        return;
    }

    const received = participant.overview?.school?.parental_consent_received !== true;
    setParentalConsentSaving(participant.id, true);

    try {
        const response = await axios.patch(route('teilnehmer.elterneinverstaendnis.update', participant.id), {
            received,
        });

        participant.overview = participant.overview || {};
        participant.overview.school = {
            ...(participant.overview.school || {}),
            ...(response.data.school || {}),
        };

        Swal.fire({
            title: 'Gespeichert',
            text: response.data.message || 'Elterneinverstaendnis wurde aktualisiert.',
            icon: 'success',
            timer: 1200,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Elterneinverstaendnis konnte nicht aktualisiert werden.',
            icon: 'error',
        });
    } finally {
        setParentalConsentSaving(participant.id, false);
    }
};

// Projekt auswählen
const selectedCount = computed(() => selected.value.length);
const selectionKey = (id) => String(id);
const selectedKeySet = computed(() => new Set(selected.value.map(selectionKey)));
const visibleParticipantIds = computed(() => filteredTeilnehmerByProject.value.map(teilnehmer => teilnehmer.id));
const allVisibleSelected = computed(() =>
    visibleParticipantIds.value.length > 0
    && visibleParticipantIds.value.every(id => selectedKeySet.value.has(selectionKey(id)))
);
const someVisibleSelected = computed(() =>
    visibleParticipantIds.value.some(id => selectedKeySet.value.has(selectionKey(id)))
);
const isParticipantSelected = (id) => selectedKeySet.value.has(selectionKey(id));

const toggleSelectionMode = () => {
    if (!canUseSelectionActions.value) return;

    checkBoxListeTeilnehmer.value = !checkBoxListeTeilnehmer.value;

    if (!checkBoxListeTeilnehmer.value) {
        selected.value = [];
    }
};

const setParticipantSelected = (id, checked) => {
    const key = selectionKey(id);

    if (checked) {
        if (!selectedKeySet.value.has(key)) {
            selected.value = [...selected.value, id];
        }
        return;
    }

    selected.value = selected.value.filter(selectedId => selectionKey(selectedId) !== key);
};

const toggleSelectAllVisible = (event = null) => {
    const shouldSelect = event?.currentTarget?.type === 'checkbox'
        ? event.currentTarget.checked
        : !allVisibleSelected.value;
    const visibleKeys = new Set(visibleParticipantIds.value.map(selectionKey));
    const remainingIds = selected.value.filter(id => !visibleKeys.has(selectionKey(id)));

    selected.value = shouldSelect
        ? [...remainingIds, ...visibleParticipantIds.value]
        : remainingIds;
};

const openGroupModal = () => {
    if (!canAssignParticipantToGroup.value) return;

    if (selected.value.length === 0) {
        Swal.fire({
            title: 'Keine Auswahl',
            text: 'Bitte markieren Sie zuerst mindestens einen Teilnehmer.',
            icon: 'warning',
            timer: 2500,
        });
        return;
    }

    groupModal.value?.open();
};

const deleteSelectedTeilnehmer = async () => {
    if (!canBulkDeleteParticipant.value) return;

    if (selected.value.length === 0) {
        Swal.fire({
            title: 'Keine Auswahl',
            text: 'Bitte markieren Sie zuerst mindestens einen Teilnehmer.',
            icon: 'warning',
            timer: 2500,
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Markierte Teilnehmer löschen?',
        text: `${selected.value.length} Teilnehmer werden dauerhaft geloescht.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const ids = [...selected.value];
        const response = await axios.delete(route('teilnehmer.bulkDestroy'), {
            data: { ids },
        });

        teilnehmerList.value = teilnehmerList.value.filter(teilnehmer => !ids.includes(teilnehmer.id));
        selected.value = [];

        Swal.fire({
            title: 'Erfolg',
            text: response.data.message || 'Die markierten Teilnehmer wurden geloescht.',
            icon: 'success',
            timer: 2500,
        });
    } catch (error) {
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Die markierten Teilnehmer konnten nicht geloescht werden.',
            icon: 'error',
        });
    }
};

const swapSelectedParticipantNames = async () => {
    if (!canUpdateParticipant.value) return;

    if (selected.value.length === 0) {
        await Swal.fire({
            title: 'Keine Auswahl',
            text: 'Bitte markieren Sie zuerst mindestens einen Teilnehmer.',
            icon: 'warning',
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Vorname und Nachname tauschen?',
        html: `Bei <strong>${selected.value.length}</strong> markierten Teilnehmern werden Vorname und Nachname vertauscht. Andere Teilnehmerdaten bleiben unverändert.<br><br>Zur Bestätigung <strong>tauschen</strong> eingeben.`,
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'tauschen',
        showCancelButton: true,
        confirmButtonText: 'Namen tauschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#ea580c',
        inputValidator: (value) => String(value || '').trim().toLowerCase() === 'tauschen'
            ? undefined
            : 'Bitte tauschen eingeben.',
    });

    if (!result.isConfirmed) return;

    try {
        const response = await axios.patch(route('teilnehmer.names.swap'), {
            ids: [...selected.value],
        });
        const changed = new Map((response.data.participants || []).map((participant) => [participant.id, participant]));

        teilnehmerList.value = teilnehmerList.value.map((participant) => (
            changed.has(participant.id)
                ? { ...participant, ...changed.get(participant.id) }
                : participant
        ));
        selected.value = [];

        await Swal.fire({
            title: 'Gespeichert',
            text: response.data.message || 'Vorname und Nachname wurden getauscht.',
            icon: 'success',
        });
    } catch (error) {
        await Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Die Namen konnten nicht getauscht werden.',
            icon: 'error',
        });
    }
};
const selectStandort = (standortId) => {
    selectedStandort.value = standortId;
};

// Modal öffnen und schließen
const openModal = () => {
    if (!canCreateParticipant.value) return;

    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};
// Teilnehmer hinzufügen
const addTeilnehmer = async (formData) => {
        if (!canCreateParticipant.value) return;

        // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
        if (!formData.vorname || !formData.nachname || !formData.geschlecht) {
            Swal.fire({
                title: 'Error!',
                text: 'Bitte füllen Sie alle erforderlichen Felder aus.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        try {
            // Sende die POST-Anfrage an den Server
            //const response = await axios.post(route('teilnehmer.store'), formData);
            const response = await axios.post(route('teilnehmer.store'), formData);

            // Zeige eine Erfolgsnachricht an
            Swal.fire({
                title: 'Erfolg!',
                text: 'Benutzer erfolgreich erstellt!',
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
            });

            // Optional: Formular zurücksetzen und Modal schließen
            closeModal();
            router.reload({
                only: ['teilnehmers', 'overviewStats'],
                preserveScroll: true,
                preserveState: true,
            });
        } catch (error) {
            // Fehlerbehandlung hier
            console.error(error);
            Swal.fire({
                title: 'Error!',
                text: error.response.data.message || 'Beim Erstellen des Benutzers ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
        }
    };

// Sortierfunktion aufrufen, wenn ein Spaltenkopf angeklickt wird
const sortByColumn = (column) => {
    if (sortColumn.value === column) {
        // Wenn die gleiche Spalte nochmal geklickt wird, die Richtung umkehren
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        // Neue Spalte -> Richtung auf 'asc' setzen
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
};
</script>

<template>
    <Head title="Teilnehmer" />

    <app-layout>
        <template #header>{{$t('Teilnehmerübersicht')}}</template>

        <section v-if="showOverviewMetrics" class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Kennzahlen der gefilterten Projektteilnehmer">
            <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="attentionFilter === 'all' ? 'border-zbb ring-1 ring-zbb' : 'border-gray-200'" @click="attentionFilter = 'all'"><p class="text-xs font-semibold uppercase text-gray-500">Teilnehmer</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ overviewStats.participants ?? 0 }}</p><p class="text-xs text-gray-500">im aktuellen Projektfilter</p></button>
            <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="attentionFilter === 'overdue' ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'" @click="attentionFilter = attentionFilter === 'overdue' ? 'all' : 'overdue'"><p class="text-xs font-semibold uppercase text-red-600">Überfällige Aufgaben</p><p class="mt-1 text-2xl font-bold text-red-700">{{ overviewStats.with_overdue_tasks ?? 0 }}</p><p class="text-xs text-gray-500">{{ overviewStats.overdue_tasks ?? 0 }} Aufgaben betroffen</p></button>
            <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="attentionFilter === 'unexcused' ? 'border-orange-500 ring-1 ring-orange-500' : 'border-gray-200'" @click="attentionFilter = attentionFilter === 'unexcused' ? 'all' : 'unexcused'"><p class="text-xs font-semibold uppercase text-orange-600">Unentschuldigte Fehlzeit</p><p class="mt-1 text-2xl font-bold text-orange-700">{{ overviewStats.with_unexcused_absence ?? 0 }}</p><p class="text-xs text-gray-500">im gewählten Monat</p></button>
            <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="attentionFilter === 'negative_balance' ? 'border-rose-500 ring-1 ring-rose-500' : 'border-gray-200'" @click="attentionFilter = attentionFilter === 'negative_balance' ? 'all' : 'negative_balance'"><p class="text-xs font-semibold uppercase text-rose-600">Negativer Saldo</p><p class="mt-1 text-2xl font-bold text-rose-700">{{ overviewStats.with_negative_balance ?? 0 }}</p><p class="text-xs text-gray-500">im gewählten Monat</p></button>
            <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="attentionFilter === 'measure_follow_up' ? 'border-violet-500 ring-1 ring-violet-500' : 'border-gray-200'" @click="attentionFilter = attentionFilter === 'measure_follow_up' ? 'all' : 'measure_follow_up'"><p class="text-xs font-semibold uppercase text-violet-600">Praktikum nachfassen</p><p class="mt-1 text-2xl font-bold text-violet-700">{{ overviewStats.with_overdue_measure_follow_up ?? 0 }}</p><p class="text-xs text-gray-500">{{ overviewStats.active_measures ?? 0 }} aktive Maßnahmen</p></button>
            <div class="rounded-xl border border-gray-200 bg-slate-900 p-4 text-white shadow-sm"><p class="text-xs font-semibold uppercase text-slate-300">Monatssaldo gesamt</p><p class="mt-1 text-2xl font-bold">{{ formatOverviewMinutes(overviewStats.period_balance_minutes) }}</p><p class="text-xs text-slate-300">{{ overviewStats.open_tasks ?? 0 }} offene Aufgaben</p></div>
        </section>


        <!-- Suchfeld -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <Dropdown v-if="canUseSelectionActions" align="left">
                <template #trigger>
                    <button class="bg-white border border-gray-300 rounded-l-md px-5 py-2 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500">
                        <i class="la la-ellipsis-v la-lg"></i>
                    </button>
                </template>

                <template #content>
                    <button type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="toggleSelectionMode">
                        {{ checkBoxListeTeilnehmer ? 'Auswahl beenden' : 'Teilnehmer markieren' }}
                        <i class="las la-check-square"></i>
                    </button>
                    <button v-if="checkBoxListeTeilnehmer" type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="toggleSelectAllVisible">
                        {{ allVisibleSelected ? 'Sichtbare abwählen' : 'Sichtbare markieren' }}
                        <i class="las la-tasks"></i>
                    </button>
                    <button v-if="canAssignParticipantToGroup" type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-gray-100 text-left" @click="openGroupModal">
                        In Gruppe hinzufügen
                        <span class="ml-4 text-xs text-gray-500">{{ selectedCount }}</span>
                    </button>
                    <button v-if="canUpdateParticipant" type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-orange-50 text-left text-orange-700" @click="swapSelectedParticipantNames">
                        Vor-/Nachname tauschen
                        <span class="ml-4 text-xs">{{ selectedCount }}</span>
                    </button>
                    <button v-if="canBulkDeleteParticipant" type="button" class="flex w-full justify-between cursor-pointer py-2 px-6 items-center hover:bg-red-50 text-left text-red-600" @click="deleteSelectedTeilnehmer">
                        Markierte löschen
                        <span class="ml-4 text-xs">{{ selectedCount }}</span>
                    </button>
                </template>
            </Dropdown>
            <div v-if="canCreateParticipant" @click="openModal" class="flex items-center">
                <i
                    class="la la-plus bg-white border border-gray-300 px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"
                    :class="!canUseSelectionActions ? 'rounded-l-md' : ''"
                ></i>
            </div>


            <a v-if="can('teilnehmer.import')" :href="route('teilnehmer.import.template')" class="border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-zbb hover:bg-zbb hover:text-white" title="CSV-Importvorlage für das aktive Projekt herunterladen">
                <i class="las la-download" aria-hidden="true"></i> Importvorlage
            </a>
            <div v-if="canImportParticipant" @click="importTeilnehmer" class="flex items-center">
                <i
                    class="las la-upload bg-white border border-gray-300 px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"
                    :class="!canUseSelectionActions && !canCreateParticipant ? 'rounded-l-md' : ''"
                ></i>
            </div>

            <Link
                v-if="$page.props.currentProjekt?.features?.internship_management !== false"
                :href="route('internships.index')"
                class="border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-zbb hover:border-orange-500 hover:bg-zbb hover:text-white"
            >
                Praktikant:innen
            </Link>

            <Link
                v-if="$page.props.currentProjekt?.features?.participant_portal === true && can('teilnehmer.portal.overview')"
                :href="route('teilnehmer.portal-users.index')"
                class="border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-zbb hover:border-orange-500 hover:bg-zbb hover:text-white"
            >
                <i class="las la-users mr-2"></i>
                Portal-Nutzer
            </Link>


            <label for="simple-search" class="sr-only">Search</label>
            <input
                v-model="search"
                type="text"
                class="block min-w-60 flex-1 border border-gray-300 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                :class="!canUseSelectionActions && !canCreateParticipant && !canImportParticipant ? 'rounded-l-md' : ''"
                placeholder="Suchen ..."
            />

            <select
                v-if="usesPeriodFilter"
                v-model="selectedPeriod"
                class="border border-gray-300 text-gray-700 text-sm focus:ring-zbb focus:border-zbb p-2.5"
                aria-label="Auswertungsmonat"
            >
                <option v-if="!overviewPeriods.includes(selectedPeriod)" :value="selectedPeriod">
                    {{ formatPeriod(selectedPeriod) }}
                </option>
                <option v-for="period in overviewPeriods" :key="period" :value="period">
                    {{ formatPeriod(period) }}
                </option>
            </select>

            <!-- Standortfilter innerhalb des aktiven Projekts -->
            <Dropdown align="right">
                <template #trigger>
                    <button class="inline-flex items-center px-3 py-3 border border-gray-300 text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                        <span class="mr-5">Standorte</span>
                        <span class="transform transition-transform duration-300 menu-arrow"></span>
                    </button>
                </template>

                <template #content>
                    <!-- Standortsuche -->
                    <div class="px-4 py-2" @click.stop>
                        <input v-model="searchStandort" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2" placeholder="Standorte suchen..." />
                    </div>

                    <!-- Gefilterte Standortauswahl -->
                    <DropdownLink @click="selectStandort(null)" href="#">
                        Alle Standorte
                    </DropdownLink>
                    <DropdownLink v-for="standort in filteredStandorte" :key="standort.id" @click="selectStandort(standort.id)" href="#">
                        {{ standort.name }}
                    </DropdownLink>
                </template>
            </Dropdown>
            <select
                v-model="selectedSchool"
                class="border border-gray-300 bg-white p-2.5 text-sm text-gray-700 focus:border-zbb focus:ring-zbb"
                aria-label="Schule oder Partner filtern"
            >
                <option :value="null">Alle Schulen / Partner</option>
                <option v-for="school in participantSchools" :key="school.id" :value="school.id">
                    {{ school.name }}
                </option>
            </select>
            <select
                v-if="canUseAdvancedGroupFilters"
                v-model="selectedInstructor"
                class="border border-gray-300 bg-white p-2.5 text-sm text-gray-700 focus:border-zbb focus:ring-zbb"
                aria-label="Anleiter filtern"
            >
                <option :value="null">Alle Anleiter</option>
                <option v-for="instructor in anleiter" :key="instructor.id" :value="instructor.id">
                    {{ instructor.nachname }}, {{ instructor.vorname }}
                </option>
            </select>
            <select
                v-if="canUseAdvancedGroupFilters"
                v-model="selectedArea"
                class="border border-gray-300 bg-white p-2.5 text-sm text-gray-700 focus:border-zbb focus:ring-zbb"
                aria-label="Bereich filtern"
            >
                <option :value="null">Alle Bereiche</option>
                <option v-for="area in bereiche" :key="area.id" :value="area.id">
                    {{ area.name }}
                </option>
            </select>
            <Link :href="route('teilnehmer.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

        <ZurGruppeHinzufügen
            v-if="canAssignParticipantToGroup"
            ref="groupModal"
            :show-button="false"
            :selected="selected"
            :gruppen="gruppen"
            @submitted="selected = []"
            />

        <div
            v-if="checkBoxListeTeilnehmer && canUseSelectionActions"
            class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm"
            role="status"
        >
            <span class="font-medium text-blue-900">
                {{ selectedCount }} Teilnehmer markiert
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-md border border-blue-300 bg-white px-3 py-1.5 font-medium text-blue-800 hover:bg-blue-100"
                    @click="toggleSelectAllVisible"
                >
                    {{ allVisibleSelected ? 'Sichtbare abwählen' : `Alle sichtbaren markieren (${visibleParticipantIds.length})` }}
                </button>
                <button
                    v-if="selectedCount > 0"
                    type="button"
                    class="rounded-md px-3 py-1.5 font-medium text-gray-700 hover:bg-white"
                    @click="selected = []"
                >
                    Auswahl aufheben
                </button>
            </div>
        </div>

        <!-- Teilnehmer Tabelle -->
        <div class="overflow-x-auto snap-x">
            <div v-if="!$page.props.auth.user.current_team_id" class="flex w-full text-red-500 p-3 bg-white">
                <p >
                    {{ $t('Bitte legen Sie ein Standardprojekt fest.') }}
                </p>
            </div>

                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-gray-600 uppercase bg-gray-200">
                        <tr>
                            <th v-if="checkBoxListeTeilnehmer && canUseSelectionActions" class="border border-solid border-gray-300 text-center py-3">
                                <button
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="allVisibleSelected ? 'true' : (someVisibleSelected ? 'mixed' : 'false')"
                                    aria-label="Alle sichtbaren Teilnehmer markieren"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded border-2 transition"
                                    :class="allVisibleSelected || someVisibleSelected
                                        ? 'border-zbb bg-zbb text-white'
                                        : 'border-gray-400 bg-white text-transparent hover:border-zbb'"
                                    @click="toggleSelectAllVisible"
                                >
                                    <i v-if="allVisibleSelected" class="las la-check text-base" aria-hidden="true"></i>
                                    <i v-else-if="someVisibleSelected" class="las la-minus text-base" aria-hidden="true"></i>
                                </button>
                            </th>
                            <th
                                v-for="column in visibleOverviewColumns"
                                :key="column.key"
                                scope="col"
                                :class="overviewHeaderClass(column)"
                                :title="overviewColumnTitle(column)"
                                @click="sortByOverviewColumn(column)"
                            >
                                {{ overviewColumnLabel(column) }}
                                <i v-if="sortKeyForOverviewColumn(column)" :class="overviewSortIconClass(column)"></i>
                            </th>
                            <th v-if="canManageParticipantRows" scope="col" class="border w-10 border-solid border-gray-300 text-center px-6 py-3">*</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="teilnehmer in filteredTeilnehmerByProject" :key="teilnehmer.id" class="bg-white border-b">
                            <td v-if="checkBoxListeTeilnehmer && canUseSelectionActions" class="text-center py-4 border border-solid border-gray-300">
                                <button
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="isParticipantSelected(teilnehmer.id) ? 'true' : 'false'"
                                    :aria-label="`${teilnehmer.vorname} ${teilnehmer.nachname} markieren`"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded border-2 transition"
                                    :class="isParticipantSelected(teilnehmer.id)
                                        ? 'border-zbb bg-zbb text-white'
                                        : 'border-gray-400 bg-white text-transparent hover:border-zbb'"
                                    @click="setParticipantSelected(teilnehmer.id, !isParticipantSelected(teilnehmer.id))"
                                >
                                    <i v-if="isParticipantSelected(teilnehmer.id)" class="las la-check text-base" aria-hidden="true"></i>
                                </button>
                            </td>
                            <td
                                v-for="column in visibleOverviewColumns"
                                :key="`${teilnehmer.id}-${column.key}`"
                                :class="overviewCellClass(column)"
                            >
                                <template v-if="column.key === 'id'">
                                    <Link v-if="canUpdateParticipant" :href="route('teilnehmer.edit', teilnehmer.id)">{{ teilnehmer.id }}</Link>
                                    <span v-else>{{ teilnehmer.id }}</span>
                                </template>
                                <template v-else-if="column.key === 'first_name'">
                                    {{ teilnehmer.vorname }}
                                </template>
                                <template v-else-if="column.key === 'last_name'">
                                    {{ teilnehmer.nachname }}
                                </template>
                                <template v-else-if="column.key === 'gender'">
                                    {{ teilnehmer.geschlecht || '-' }}
                                </template>
                                <template v-else-if="column.key === 'parental_consent'">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-lg font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                        :class="[parentalConsentClass(teilnehmer.overview?.school?.parental_consent_received), canToggleParentalConsent(teilnehmer) ? 'hover:bg-gray-100' : '']"
                                        :disabled="!canToggleParentalConsent(teilnehmer)"
                                        :title="parentalConsentStatusText(teilnehmer.overview?.school?.parental_consent_received)"
                                        :aria-label="parentalConsentStatusText(teilnehmer.overview?.school?.parental_consent_received)"
                                        @click="toggleParentalConsent(teilnehmer)"
                                    >
                                        <i v-if="isParentalConsentSaving(teilnehmer.id)" class="las la-spinner la-spin"></i>
                                        <i v-else :class="parentalConsentIconClass(teilnehmer.overview?.school?.parental_consent_received)"></i>
                                    </button>
                                </template>
                                <template v-else-if="column.key === 'school_class'">
                                    {{ joinOrDash(teilnehmer.overview?.school?.classes || []) }}
                                </template>
                                <template v-else-if="column.key === 'school'">
                                    <p class="font-medium text-gray-700">{{ joinOrDash(teilnehmer.overview?.school?.schools || []) }}</p>
                                    <p class="mt-1 text-xs text-zbb">{{ schoolContextLabel(teilnehmer.overview?.school) }}</p>
                                </template>
                                <template v-else-if="column.key === 'visited_areas'">
                                    <ul v-if="teilnehmer.overview?.school?.visited_areas?.length" class="list-disc space-y-1 pl-4 text-gray-700">
                                        <li v-for="area in teilnehmer.overview.school.visited_areas" :key="area">{{ area }}</li>
                                    </ul>
                                    <span v-else>-</span>
                                </template>
                                <template v-else-if="column.key === 'participation'">
                                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                                        {{ participationStatusLabel(teilnehmer.overview?.participation_status) }}
                                    </span>
                                    <p class="mt-1 text-xs text-gray-500">{{ teilnehmer.overview?.location || 'Kein Standort' }}</p>
                                </template>
                                <template v-else-if="column.key === 'group_supervisor'">
                                    <p class="font-medium text-gray-700">{{ teilnehmer.overview?.groups?.join(', ') || 'Keine Gruppe' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ teilnehmer.overview?.supervisor || 'Keine Betreuung' }}</p>
                                </template>
                                <template v-else-if="column.key === 'period_balance'">
                                    <p class="font-mono font-semibold" :class="balanceClass(teilnehmer.overview?.period?.balance_minutes)">
                                        {{ formatOverviewMinutes(teilnehmer.overview?.period?.balance_minutes) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Quote: {{ teilnehmer.overview?.period?.attendance_rate ?? '-' }} %
                                    </p>
                                </template>
                                <template v-else-if="column.key === 'total_balance'">
                                    <p class="font-mono font-semibold" :class="balanceClass(teilnehmer.overview?.total?.balance_minutes)">
                                        {{ formatOverviewMinutes(teilnehmer.overview?.total?.balance_minutes) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Quote: {{ teilnehmer.overview?.total?.attendance_rate ?? '-' }} %
                                    </p>
                                </template>
                                <template v-else-if="column.key === 'absences'">
                                    <p class="font-semibold text-gray-700">{{ teilnehmer.overview?.period?.absence_days ?? 0 }} Tage</p>
                                    <p class="text-xs text-red-600">
                                        davon {{ teilnehmer.overview?.period?.unexcused_days ?? 0 }} unentschuldigt
                                    </p>
                                </template>
                                <template v-else-if="column.key === 'tasks'">
                                    <p class="font-semibold" :class="teilnehmer.overview?.overdue_tasks ? 'text-red-600' : 'text-gray-700'">
                                        {{ teilnehmer.overview?.open_tasks ?? 0 }} offen
                                    </p>
                                    <p v-if="teilnehmer.overview?.overdue_tasks" class="text-xs font-medium text-red-600">
                                        {{ teilnehmer.overview.overdue_tasks }} ueberfaellig
                                    </p>
                                    <p v-else-if="teilnehmer.overview?.next_due_at" class="text-xs text-gray-500">
                                        Naechste: {{ new Date(teilnehmer.overview.next_due_at).toLocaleDateString('de-DE') }}
                                    </p>
                                </template>
                                <template v-else-if="column.key === 'measures'">
                                    <p class="font-semibold" :class="teilnehmer.overview?.overdue_measure_follow_ups ? 'text-red-600' : 'text-gray-700'">
                                        {{ teilnehmer.overview?.active_measures ?? 0 }} aktiv
                                    </p>
                                    <p v-if="teilnehmer.overview?.overdue_measure_follow_ups" class="text-xs text-red-600">
                                        {{ teilnehmer.overview.overdue_measure_follow_ups }} Nachverfolgung ueberfaellig
                                    </p>
                                    <p v-else-if="teilnehmer.overview?.next_measure_follow_up_at" class="text-xs text-gray-500">
                                        Naechste: {{ new Date(teilnehmer.overview.next_measure_follow_up_at).toLocaleDateString('de-DE') }}
                                    </p>
                                </template>
                                <template v-else>
                                    -
                                </template>
                            </td>
                            <td v-if="canManageParticipantRows" class="border px-6 py-4 text-center">
                                <Dropdown>
                                    <template #trigger>
                                        <button class="items-center text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                            <span class="cursor-pointer">
                                                <i class="transform transition-transform duration-300 la la-ellipsis-v la-lg"></i>
                                            </span>
                                        </button>
                                    </template>

                                    <template #content>
                                        <span
                                            v-if="$page.props.enabledModules?.participant_portal && $page.props.currentProjekt?.features?.participant_portal === true && canInviteToPortal && teilnehmer.overview?.participation_id && !teilnehmer.overview?.portal_account_exists"
                                            class="flex cursor-pointer items-center justify-between gap-4 px-6 py-1 hover:bg-gray-100"
                                            @click="inviteToPortal(teilnehmer)"
                                        >
                                            {{ teilnehmer.overview?.portal_invitation_email ? 'Einladung erneut senden' : 'Einladung senden' }}
                                            <i class="las la-paper-plane"></i>
                                        </span>
                                        <span v-if="canDeleteParticipant" class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" @click="confirmDelete(teilnehmer)">
                                            {{ $t('Loeschen') }} <i class="las la-trash-alt"></i>
                                        </span>
                                        <Link v-if="canUpdateParticipant" class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" :href="route('teilnehmer.edit', teilnehmer.id)">
                                            {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                        </Link>
                                    </template>
                                </Dropdown>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <!-- Paginierung -->

            <Pagination :pagination="teilnehmers" />
        </div>


            <ModalCreateTeilnehmer
                v-if="canCreateParticipant"
                :visible="isModalOpen"
                :active-project="$page.props.currentProjekt"
                :standorte="standorte"
                :schools="participantSchools"
                :defaultProjekt="defaultProjekt"
                @close="closeModal"
                @add-teilnehmer="addTeilnehmer"
            />

        <!-- Modal für die Löschung der Abteilung-->

            <ModalDestroy v-if="canDeleteParticipant && showModalLöschen"@close="showModalLöschen = false"@delete="deleteTeilnehmer":seite="seite":toDelete="teilnehmerToDelete"/>


            <div v-if="canImportParticipant && showImportModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                <div class="bg-white p-6 rounded-lg w-[95vw] max-w-6xl max-h-[90vh] overflow-y-auto">

                    <div class="flex justify-between mb-4">
                        <h2 class="text-lg font-bold">Teilnehmer importieren</h2>
                        <button :disabled="importBusy" @click="showImportModal=false">✕</button>
                    </div>

                    <p v-if="can('teilnehmer.import')" class="mb-3 text-sm">
                        <a :href="route('teilnehmer.import.template')" class="underline font-medium">Importvorlage für das aktive Projekt herunterladen</a><br>
                        Eine Person pro Zeile. Datum: TT.MM.JJJJ; Geschlecht: m, w oder d. PLZ und Telefonnummern als Text eingeben, damit führende Nullen erhalten bleiben. ID-Spalten beziehen sich auf die IDs im Programm. Leere optionale Felder sind erlaubt.
                    </p>
                    <p class="text-sm mb-3">Die Datei wird zuerst geprüft. Gespeichert wird erst nach Ihrer Bestätigung. Bestehende Personen werden nicht überschrieben.</p>
                    <p v-if="importContextLoading" class="mb-3 text-sm" role="status">Projekt und zugewiesene Standorte werden geladen…</p>
                    <div v-else-if="importContext" class="grid gap-4 sm:grid-cols-2 rounded border bg-slate-50 p-4 mb-4">
                        <div>
                            <p class="font-semibold">Aktives Projekt</p>
                            <p>{{ importContext.project.name }}</p>
                            <p class="text-sm text-slate-600">Wird automatisch für alle Teilnehmer übernommen.</p>
                        </div>
                        <div>
                            <label for="participant-import-location" class="block font-semibold mb-1">Standort <span class="text-red-600">*</span></label>
                            <select id="participant-import-location" v-model="importLocationId" required :disabled="importBusy || !importContext.locations.length" class="w-full border rounded p-2" aria-describedby="participant-import-location-help">
                                <option :value="null" disabled>Bitte Standort auswählen</option>
                                <option v-for="location in importContext.locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                            </select>
                            <p id="participant-import-location-help" class="text-sm mt-1" :class="!importContext.locations.length ? 'text-red-700' : 'text-slate-600'">
                                {{ importContext.locations.length ? 'Pflichtauswahl für alle Zeilen. Es werden nur Ihre Standorte im aktiven Projekt angezeigt.' : 'Ihnen ist im aktiven Projekt kein Standort zugewiesen. Bitte lassen Sie die Projektzuordnung durch die Administration ergänzen.' }}
                            </p>
                        </div>
                        <p class="text-sm sm:col-span-2">Projekt und Standort werden hier festgelegt. Vorhandene Projekt_ID- und Standort_ID-Spalten in älteren Dateien werden nicht zur Zuordnung verwendet.</p>
                    </div>
                    <label class="block mb-3">Importprofil
                        <select v-model="importProfile" :disabled="importBusy" @change="importPreview=null" class="ml-2 border rounded p-2">
                            <option value="auto">Automatisch erkennen</option><option value="standard">Standard</option><option value="bop">BOP</option><option value="bvb_reha">BVB Reha / BA</option>
                        </select>
                    </label>
                    <p v-if="!importLocationId" class="text-sm mb-2">Bitte wählen Sie zuerst einen Standort, bevor Sie eine Datei prüfen.</p>
                    <form id="mydropzone" :inert="importBusy || !importLocationId || !importContext" :class="{ 'opacity-50': !importLocationId }" class="dropzone border border-dashed p-6 rounded-lg"></form>
                    <button :disabled="importBusy" class="mt-2 underline text-sm" @click="dropzoneInstance?.removeAllFiles(true); importFile=null; importPreview=null; activeImportReview=null">Andere Datei prüfen</button>
                    <button v-if="importFile && !importPreview" :disabled="importBusy || !importLocationId || !importContext" class="ml-4 mt-2 underline text-sm disabled:opacity-50" @click="previewParticipantImport">Vorschau erneut prüfen</button>
                    <p v-if="importResult" class="mt-3 p-3 rounded bg-green-50">{{ importResult.message }}</p>
                    <section v-if="importReviews.length" class="my-4 border rounded p-3">
                        <h3 class="font-bold">Zurückgestellte Importe</h3>
                        <p class="text-sm">Nur für Sie im aktiven Projekt sichtbar. Temporäre Importkopien werden nach 30 Tagen entfernt; vorhandene Teilnehmer bleiben erhalten.</p>
                        <div v-for="review in importReviews" :key="review.id" class="flex justify-between gap-3 py-2 border-t">
                            <span>{{ review.count }} offene Zeilen · verfügbar bis {{ new Date(review.expires_at).toLocaleDateString('de-DE') }}</span>
                            <button :disabled="importBusy || !importContext" class="underline" @click="resumeImportReview(review)">Weiter prüfen</button>
                        </div>
                    </section>
                    <section v-if="importPreview" class="mt-4">
                        <h3 class="font-bold">Vorschau: {{ importPreview.new_count }} neue Teilnehmer · {{ importPreview.count - importPreview.new_count }} mögliche Treffer → {{ importPreview.project }}</h3>
                        <p class="text-sm font-semibold">Standort: {{ importPreview.location.name }} · gilt für alle übernommenen Teilnehmer</p>
                        <p class="text-sm">Profil: {{ importPreview.profile }} · Angaben bitte vor dem Import kontrollieren.</p>
                        <details class="my-2"><summary>Erkannte Spaltenzuordnung</summary><ul><li v-for="column in importPreview.mapping" :key="column.source">{{ column.source }} → {{ column.target }}</li></ul></details>
                        <p class="my-2 text-sm">Neue Teilnehmer können bereits importiert werden. Unklare Treffer bleiben zurückgestellt. „Dieselbe Person“ legt eine neue Projektzuordnung an und verwendet die vorhandenen Stammdaten. Abweichende Namen, Adressen und Kontaktdaten aus der Datei werden nicht automatisch übernommen. Frühere Berichte bleiben im ursprünglichen Projekt.</p>
                        <div v-for="row in importPreview.rows.filter(item => item.match.status !== 'new')" :key="row.line" class="border rounded p-3 mb-3 bg-amber-50">
                            <h4 class="font-semibold mb-2">Identität prüfen · Zeile {{ row.line }}</h4>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="bg-white rounded p-2">
                                    <p class="font-semibold text-sm mb-1">Importdatei</p>
                                    <p>{{ row.values[0] }} {{ row.values[1] }}</p>
                                    <p class="text-sm">Geburtsdatum: {{ row.values[3] || 'nicht angegeben' }}</p>
                                    <p class="text-sm">Zielprojekt: {{ importPreview.project }}</p>
                                </div>
                                <div>
                            <p v-if="row.match.status === 'restricted'" class="text-sm">Möglicher Bestandstreffer. Ihre Berechtigung reicht nicht für die Identitätsprüfung; bitte zur Klärung zurückstellen.</p>
                            <p v-if="row.match.status === 'file_duplicate'" class="text-sm">Eine gleichlautende Person steht bereits weiter oben in dieser Datei. Bitte die doppelte Zeile prüfen.</p>
                            <div v-for="candidate in row.match.candidates" :key="candidate.id" class="bg-white rounded p-2 mb-2">
                                <p class="font-semibold text-sm mb-1">Vorhandener Teilnehmer</p>
                                <p>{{ candidate.name }} · {{ candidate.birthdate || 'Geburtsdatum nicht hinterlegt' }}</p>
                                <p class="text-sm">Für Sie sichtbare Projekte: {{ candidate.projects.join(', ') }}</p>
                                <p v-if="!candidate.exact_birthdate" class="text-sm text-amber-800">Geburtsdatum stimmt nicht vollständig überein oder fehlt. Identität sorgfältig prüfen.</p>
                                <p v-if="candidate.reason" class="text-sm">{{ candidate.reason }}</p>
                            </div>
                                </div>
                            </div>
                            <select v-model="importDecisions[row.line].action" :disabled="importBusy" @change="importDecisions[row.line].identity_checked=false; importDecisions[row.line].person_id=null" class="border rounded p-2 mt-2 max-w-full">
                                <option value="defer">Noch unklar – zur Prüfung behalten</option>
                                <option value="skip">Diese Zeile überspringen</option>
                                <option v-if="row.match.candidates.some(candidate => candidate.can_reuse)" value="reuse">Dieselbe Person – dem aktiven Projekt zuordnen</option>
                                <option v-if="row.match.status === 'match'" value="separate">Andere Person – getrennt neu anlegen</option>
                            </select>
                            <select v-if="importDecisions[row.line].action === 'reuse'" v-model="importDecisions[row.line].person_id" :disabled="importBusy" class="border rounded p-2 block mt-2 max-w-full">
                                <option :value="null" disabled>Vorhandene Person auswählen</option>
                                <option v-for="candidate in row.match.candidates.filter(item => item.can_reuse)" :key="candidate.id" :value="candidate.id">{{ candidate.name }} ({{ candidate.birthdate || 'ohne Geburtsdatum' }}) · {{ candidate.projects.join(', ') }}</option>
                            </select>
                            <label v-if="['reuse','separate'].includes(importDecisions[row.line].action)" class="block text-sm mt-2"><input type="checkbox" v-model="importDecisions[row.line].identity_checked" :disabled="importBusy"> Ich habe die Identität fachlich geprüft und bestätige diese Zuordnung. Dies ist keine Freigabe früherer Berichte.</label>
                        </div>
                        <details class="my-2"><summary>Alle Importdaten prüfen ({{ importPreview.count }} Zeilen)</summary>
                            <div class="overflow-auto max-h-80 border rounded"><table class="text-sm whitespace-nowrap w-full">
                                <thead class="sticky top-0 bg-slate-100"><tr><th class="p-2">Zeile</th><th v-for="field in importPreview.fields" :key="field" class="p-2 text-left">{{ field }}</th></tr></thead>
                                <tbody><tr v-for="row in importPreview.rows" :key="row.line" class="border-t"><td class="p-2">{{ row.line }}</td><td v-for="(value,index) in row.values" :key="index" class="p-2">{{ value ?? '–' }}</td></tr></tbody>
                            </table></div>
                        </details>
                        <button :disabled="importBusy || !importLocationId" class="mt-4 bg-zbb text-white rounded px-4 py-2 disabled:opacity-50" @click="confirmParticipantImport">{{ importSaving ? 'Wird verarbeitet…' : `${selectedImportCount} Teilnehmer übernehmen und offene Zeilen zurückstellen` }}</button>
                    </section>

                </div>

            </div>
    </app-layout>
</template>

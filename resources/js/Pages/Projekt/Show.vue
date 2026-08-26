<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import MultiSelect from 'primevue/multiselect';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/utils/permissions';

const props = defineProps({
    projekt: Object,
    fehlendeMitarbeiter: Array,
    alleBereiche: Array,
    alleStandorte: Array,
    anwesenheitsstatuten: Array,
});

const { can } = usePermissions();
const canUpdateProjekt = computed(() => can('projekt.update'));
const canManagePotenzialanalyse = computed(() => can('potenzialanalyse.manage'));
const canConfigurePotenzialanalyse = computed(() => canUpdateProjekt.value && canManagePotenzialanalyse.value);
const canConfigureProjectFeature = (featureKey) =>
    featureKey === 'potential_analysis'
        ? canConfigurePotenzialanalyse.value
        : canUpdateProjekt.value;
const administrationTabs = computed(() => [
    { key: 'overview', label: 'Übersicht' },
    ...(canUpdateProjekt.value ? [{ key: 'areas', label: 'Bereiche' }] : []),
    { key: 'participants', label: 'Teilnehmerprofil' },
    { key: 'features', label: 'Funktionen & Regeln' },
    ...(canUpdateProjekt.value ? [{ key: 'luv', label: 'LuV & KI' }] : []),
    ...(canManagePotenzialanalyse.value ? [{ key: 'potential_analysis', label: 'Potenzialanalyse' }] : []),
    { key: 'staff', label: 'Mitarbeiter' },
]);
const activeAdministrationTab = ref('overview');
const areaAssignmentSaving = ref(false);
const selectedAreaIds = ref((props.projekt.bereiche || []).map((bereich) => bereich.id));

const resetAreaAssignment = () => {
    selectedAreaIds.value = (props.projekt.bereiche || []).map((bereich) => bereich.id);
};

const saveAreaAssignment = async () => {
    areaAssignmentSaving.value = true;

    try {
        const response = await axios.put(route('projekt.bereiche.update', props.projekt.id), {
            bereiche: selectedAreaIds.value,
        });

        props.projekt.bereiche = response.data.bereiche;
        selectedAreaIds.value = response.data.bereiche.map((bereich) => bereich.id);
        Swal.fire('Gespeichert!', 'Die Bereiche wurden dem Projekt zugeordnet.', 'success');
    } catch (error) {
        const message = Object.values(error.response?.data?.errors || {}).flat()[0];
        Swal.fire('Fehler', message || 'Die Projektbereiche konnten nicht gespeichert werden.', 'error');
    } finally {
        areaAssignmentSaving.value = false;
    }
};

const projectFeatures = reactive({ ...(props.projekt.features || {}) });
const featureSaving = ref(false);
const featureErrors = ref({});
const paTage = ref(props.projekt.potenzialanalyse_tage || null);
const projectRules = reactive({ ...(props.projekt.rules || {}) });
const ruleSaving = ref(false);
const ruleErrors = ref({});
const intakeChecklistItems = ref(JSON.parse(JSON.stringify(props.projekt.intake_checklist_items || [])));
const intakeChecklistSaving = ref(false);
const completionChecklistItems = ref(JSON.parse(JSON.stringify(props.projekt.completion_checklist_items || [])));
const completionChecklistSaving = ref(false);
const portalFeatures = reactive({ ...(props.projekt.portal_features || {}) });
const portalFeaturesSaving = ref(false);
const participantProfile = reactive(JSON.parse(JSON.stringify(props.projekt.participant_profile || {
    enabled_tabs: [],
    tab_order: [],
})));
const participantProfileSaving = ref(false);
const participantProfileDefinitions = props.projekt.participant_profile_tab_definitions || [];
const participantProfileDefinitionMap = new Map(participantProfileDefinitions.map((item) => [item.key, item]));
const orderedParticipantProfileDefinitions = computed(() => (participantProfile.tab_order || [])
    .map((key) => participantProfileDefinitionMap.get(key))
    .filter(Boolean));
const participantProfileEnabled = (key) => participantProfile.enabled_tabs?.includes(key);
const toggleParticipantProfileTab = (key, enabled) => {
    const definition = participantProfileDefinitionMap.get(key);
    if (definition?.required) return;
    const selected = new Set(participantProfile.enabled_tabs || []);
    enabled ? selected.add(key) : selected.delete(key);
    selected.add('stammdaten');
    participantProfile.enabled_tabs = participantProfile.tab_order.filter((tabKey) => selected.has(tabKey));
};
const moveParticipantProfileTab = (key, direction) => {
    const order = [...participantProfile.tab_order];
    const index = order.indexOf(key);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= order.length) return;
    [order[index], order[target]] = [order[target], order[index]];
    participantProfile.tab_order = order;
    const enabled = new Set(participantProfile.enabled_tabs || []);
    participantProfile.enabled_tabs = order.filter((tabKey) => enabled.has(tabKey));
};
const applyParticipantProfilePreset = (preset) => {
    const allKeys = participantProfile.tab_order;
    const compact = ['stammdaten', 'adresse', 'kontaktdaten', 'projektverlauf', 'aufnahme', 'anwesenheit', 'notizen', 'exportieren'];
    const bop = ['stammdaten', 'adresse', 'kontaktdaten', 'projektverlauf', 'aufnahme', 'anwesenheit', 'schule_beruf', 'briefe', 'notizen', 'praktika', 'luv', 'exportieren'];
    const selection = preset === 'all' ? allKeys : (preset === 'bop' ? bop : compact);
    participantProfile.enabled_tabs = allKeys.filter((key) => selection.includes(key));
};
const saveParticipantProfile = async () => {
    participantProfileSaving.value = true;
    try {
        const response = await axios.put(route('projekt.participant-profile.update', props.projekt.id), {
            enabled_tabs: participantProfile.enabled_tabs,
            tab_order: participantProfile.tab_order,
        });
        Object.assign(participantProfile, response.data.participant_profile);
        props.projekt.participant_profile = response.data.participant_profile;
        Swal.fire('Gespeichert!', 'Die Teilnehmerseite wurde für dieses Projekt konfiguriert.', 'success');
    } catch (error) {
        const message = Object.values(error.response?.data?.errors || {}).flat()[0];
        Swal.fire('Fehler', message || 'Die Teilnehmerprofil-Konfiguration konnte nicht gespeichert werden.', 'error');
    } finally {
        participantProfileSaving.value = false;
    }
};
const portalFeatureDefinitions = [
    { key: 'profile', label: 'Profil', description: 'Berufliches Profil selbst vervollständigen' },
    { key: 'attendance_self_service', label: 'Eigene Anwesenheit', description: 'Freigegebene Anwesenheitsdaten einsehen' },
    { key: 'tasks_and_appointments', label: 'Aufgaben und Termine', description: 'Ausdrücklich freigegebene Aufgaben anzeigen' },
    { key: 'job_search', label: 'Jobsuche', description: 'Stellen der BA-Suche finden und merken' },
    { key: 'application_management', label: 'Bewerbungen', description: 'Bewerbungsstatus und nächste Schritte verwalten' },
    { key: 'learning', label: 'Kurse und Lernen', description: 'Kurse, Lektionen und Fortschritt' },
    { key: 'messaging', label: 'Nachrichten', description: 'Kommunikation mit zuständigen Mitarbeitenden' },
    { key: 'consents_and_approvals', label: 'Einwilligungen', description: 'Versionierte Zustimmungen und Widerrufe verwalten' },
];
const featureDefinitions = [
    { key: 'participant_management', label: 'Teilnehmerverwaltung', description: 'Teilnehmerlisten, Stammdaten und Projektteilnahmen' },
    { key: 'group_management', label: 'Gruppen und Bereiche', description: 'Gruppenbildung und Zuordnung von Teilnehmern' },
    { key: 'attendance_management', label: 'Anwesenheit', description: 'Anwesenheiten innerhalb dieses Projekts erfassen' },
    { key: 'internship_management', label: 'Praktika', description: 'Praktikums- und Bildungsmaßnahmen verwalten' },
    { key: 'completion_management', label: 'Abschlüsse', description: 'Abschlüsse der Projektteilnehmer verwalten' },
    { key: 'portal_users_overview', label: 'Portal-Nutzerübersicht', description: 'Zeigt im Teilnehmerbereich die Übersicht „Portal-Nutzer“ an.' },
    { key: 'classbook_management', label: 'Klassenbuch', description: 'Projektbezogene Klassenbücher und Wochenberichte' },
    { key: 'potential_analysis', label: 'Potenzialanalyse', description: 'PA-Übungen, Kriterien und Bewertungen' },
];
const participationStatuses = [
    { value: 'angefragt', label: 'Angefragt' },
    { value: 'angemeldet', label: 'Angemeldet (Bestand)' },
    { value: 'aufgenommen', label: 'Aufgenommen' },
    { value: 'aktiv', label: 'Aktiv' },
    { value: 'pausiert', label: 'Pausiert' },
    { value: 'abgeschlossen', label: 'Abgeschlossen' },
    { value: 'abgebrochen', label: 'Abgebrochen' },
];
const participantOverviewColumnDefinitions = props.projekt.participant_overview_column_definitions || [];
const defaultParticipantOverviewColumns = [
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
const bopParticipantOverviewColumns = [
    'id',
    'parental_consent',
    'first_name',
    'last_name',
    'gender',
    'school_class',
    'school',
    'visited_areas',
];

if (!Array.isArray(projectRules.participant_overview_columns) || !projectRules.participant_overview_columns.length) {
    projectRules.participant_overview_columns = [...defaultParticipantOverviewColumns];
}

if (projectRules.participant_overview_show_metrics === undefined) {
    projectRules.participant_overview_show_metrics = true;
}

const orderedParticipantOverviewColumns = (selectedKeys) => {
    const selected = new Set(selectedKeys);
    return participantOverviewColumnDefinitions
        .map((column) => column.key)
        .filter((key) => selected.has(key));
};

const isParticipantOverviewColumnActive = (key) => {
    return projectRules.participant_overview_columns?.includes(key);
};

const toggleParticipantOverviewColumn = (key, enabled) => {
    const selected = new Set(projectRules.participant_overview_columns || []);

    if (enabled) {
        selected.add(key);
    } else if (selected.size > 1) {
        selected.delete(key);
    }

    projectRules.participant_overview_columns = orderedParticipantOverviewColumns([...selected]);
};

const applyParticipantOverviewPreset = (columns, showMetrics) => {
    projectRules.participant_overview_columns = orderedParticipantOverviewColumns(columns);
    projectRules.participant_overview_show_metrics = showMetrics;
};

watch(() => projectFeatures.participant_management, (enabled) => {
    if (!enabled) {
        projectFeatures.group_management = false;
        projectFeatures.attendance_management = false;
        projectFeatures.internship_management = false;
        projectFeatures.completion_management = false;
        projectFeatures.classbook_management = false;
        projectFeatures.potential_analysis = false;
    }
});

watch(() => projectFeatures.group_management, (enabled) => {
    if (!enabled) {
        projectFeatures.classbook_management = false;
        projectFeatures.potential_analysis = false;
    }
});

const saveFeatures = async () => {
    if (!canManagePotenzialanalyse.value) {
        projectFeatures.potential_analysis = Boolean(props.projekt.potenzialanalyse_aktiv);
        paTage.value = props.projekt.potenzialanalyse_tage || null;
    }

    featureSaving.value = true;
    featureErrors.value = {};

    try {
        const response = await axios.put(route('projekt.features.update', props.projekt.id), {
            features: projectFeatures,
            potenzialanalyse_tage: paTage.value,
        });
        Object.assign(projectFeatures, response.data.features);
        props.projekt.features = response.data.features;
        props.projekt.klassenbuch_aktiv = response.data.features.classbook_management;
        props.projekt.potenzialanalyse_aktiv = response.data.features.potential_analysis;
        props.projekt.potenzialanalyse_tage = response.data.potenzialanalyse_tage;
        Swal.fire('Gespeichert!', 'Die Projektfunktionen wurden aktualisiert.', 'success');
    } catch (error) {
        featureErrors.value = error.response?.data?.errors || {};
        Swal.fire('Fehler', 'Die Projektfunktionen konnten nicht gespeichert werden.', 'error');
    } finally {
        featureSaving.value = false;
    }
};

const saveRules = async () => {
    ruleSaving.value = true;
    ruleErrors.value = {};

    try {
        const response = await axios.put(route('projekt.rules.update', props.projekt.id), {
            rules: projectRules,
        });
        Object.assign(projectRules, response.data.rules);
        props.projekt.rules = response.data.rules;
        Swal.fire('Gespeichert!', 'Die Projektregeln wurden aktualisiert.', 'success');
    } catch (error) {
        ruleErrors.value = error.response?.data?.errors || {};
        Swal.fire('Fehler', 'Die Projektregeln konnten nicht gespeichert werden.', 'error');
    } finally {
        ruleSaving.value = false;
    }
};

const addIntakeChecklistItem = () => {
    intakeChecklistItems.value.push({
        id: null,
        label: '',
        description: '',
        required: false,
        sort_order: intakeChecklistItems.value.length,
    });
};

const removeIntakeChecklistItem = (index) => {
    intakeChecklistItems.value.splice(index, 1);
    intakeChecklistItems.value.forEach((item, itemIndex) => { item.sort_order = itemIndex; });
};

const saveIntakeChecklist = async () => {
    if (intakeChecklistItems.value.some((item) => !item.label?.trim())) {
        Swal.fire('Fehler', 'Jeder Checklistenpunkt benötigt eine Bezeichnung.', 'error');
        return;
    }

    intakeChecklistSaving.value = true;
    try {
        const response = await axios.put(route('projekt.intake-checklist.update', props.projekt.id), {
            items: intakeChecklistItems.value.map((item, index) => ({
                id: item.id || null,
                label: item.label.trim(),
                description: item.description?.trim() || null,
                required: Boolean(item.required),
                sort_order: index,
            })),
        });
        intakeChecklistItems.value = JSON.parse(JSON.stringify(response.data.items || []));
        Swal.fire('Gespeichert!', response.data.message, 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die Aufnahmecheckliste konnte nicht gespeichert werden.', 'error');
    } finally {
        intakeChecklistSaving.value = false;
    }
};

const addCompletionChecklistItem = () => completionChecklistItems.value.push({ id: null, label: '', description: '', required: false, sort_order: completionChecklistItems.value.length });
const removeCompletionChecklistItem = (index) => {
    completionChecklistItems.value.splice(index, 1);
    completionChecklistItems.value.forEach((item, itemIndex) => { item.sort_order = itemIndex; });
};
const saveCompletionChecklist = async () => {
    if (completionChecklistItems.value.some((item) => !item.label?.trim())) return Swal.fire('Fehler', 'Jeder Checklistenpunkt benötigt eine Bezeichnung.', 'error');
    completionChecklistSaving.value = true;
    try {
        const response = await axios.put(route('projekt.completion-checklist.update', props.projekt.id), { items: completionChecklistItems.value.map((item, index) => ({ id: item.id || null, label: item.label.trim(), description: item.description?.trim() || null, required: Boolean(item.required), sort_order: index })) });
        completionChecklistItems.value = JSON.parse(JSON.stringify(response.data.items || []));
        Swal.fire('Gespeichert!', response.data.message, 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die Abschlusscheckliste konnte nicht gespeichert werden.', 'error');
    } finally { completionChecklistSaving.value = false; }
};

const savePortalFeatures = async () => {
    portalFeaturesSaving.value = true;
    try {
        const response = await axios.put(route('projekt.portal-features.update', props.projekt.id), { features: portalFeatures });
        Object.assign(portalFeatures, response.data.features);
        Swal.fire('Gespeichert!', response.data.message, 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die Portal-Funktionen konnten nicht gespeichert werden.', 'error');
    } finally {
        portalFeaturesSaving.value = false;
    }
};

const selectedStandorte = reactive({});
const projektMitarbeiter = ref([...(props.projekt.mitarbeiter || [])]);
const fehlendeMitarbeiterListe = ref([...(props.fehlendeMitarbeiter || [])]);
const paLegacyKompetenzen = [
    ['feinmotorik', 'Feinmotorik'],
    ['grobmotorik', 'Grobmotorik'],
    ['wahrnehmung_symmetrie', 'Wahrnehmung und Symmetrie'],
    ['analyse_problemloesefaehigkeit', 'Analyse- und Problemlösefähigkeit'],
    ['arbeitsplanung', 'Arbeitsplanung'],
    ['motivation_leistungsbereitschaft', 'Motivation und Leistungsbereitschaft'],
    ['durchhaltevermoegen', 'Durchhaltevermögen'],
    ['sorgfalt', 'Sorgfalt und Genauigkeit'],
    ['kommunikation', 'Kommunikation'],
    ['teamfaehigkeit', 'Teamfähigkeit'],
    ['umgangsformen', 'Umgangsformen'],
].map(([key, label]) => ({ key, label }));
const paProfil = props.projekt.potenzialanalyse_profil || null;
const paKompetenzen = (paProfil?.kompetenzen?.length ? paProfil.kompetenzen : paLegacyKompetenzen)
    .map((item) => ({
        key: item.key,
        label: item.label,
        category: item.category || item.kategorie_label || '',
        categoryCode: item.category_code || item.kategorie_code || '',
    }));
const paProfilBearbeitbar = computed(() => !paProfil || paProfil.status === 'entwurf');
const paProfilForm = reactive({
    name: `${props.projekt.name} Potenzialanalyse`,
    vorlage: 'hamet_eplus',
});
const paBerichtConfigForm = reactive({
    titel: paProfil?.bericht_config?.darstellung?.titel || 'Auswertung der Potenzialanalyse',
    untertitel: paProfil?.bericht_config?.darstellung?.untertitel || '',
    uebungsergebnisse_anzeigen: paProfil?.bericht_config?.darstellung?.uebungsergebnisse_anzeigen ?? true,
    selbsteinschaetzung_anzeigen: paProfil?.bericht_config?.darstellung?.selbsteinschaetzung_anzeigen ?? true,
    staerkenprofil_anzeigen: paProfil?.bericht_config?.darstellung?.staerkenprofil_anzeigen ?? true,
    logo_anzeigen: paProfil?.bericht_config?.darstellung?.logo_anzeigen
        ?? Boolean(paProfil?.bericht_config?.darstellung?.logo_url),
});
const paBerichtLogoInput = ref(null);
const paBerichtLogoFile = ref(null);
const paBerichtLogoPreview = ref(paProfil?.bericht_config?.darstellung?.logo_url || '');
const paBerichtLogoEntfernen = ref(false);
const paKompetenzForm = reactive({
    key: '',
    label: '',
    kategorie: 'persoenlich',
    kategorie_label: 'Persönliche Kompetenzen',
    kategorie_code: 'PP',
    beschreibung: '',
    selbsteinschaetzung_text: '',
    bewertungsbeschreibungen: [
        'Kaum erkennbar; umfassende Unterstützung ist erforderlich.',
        'Teilweise erkennbar; häufige Unterstützung ist erforderlich.',
        'Überwiegend angemessen; gelegentliche Unterstützung ist erforderlich.',
        'Sicher, selbstständig und wiederholt erkennbar.',
        'Besonders sicher, selbstständig und auch in anspruchsvollen Situationen erkennbar.',
    ],
    sort_order: 0,
    aktiv: true,
});
const paKategorieOptionen = [
    { value: 'persoenlich', label: 'Persönliche Kompetenzen', code: 'PP' },
    { value: 'praktisch', label: 'Praktische Kompetenzen', code: 'PR' },
    { value: 'methodisch', label: 'Methodische Kompetenzen', code: 'MP' },
    { value: 'sozial', label: 'Soziale Kompetenzen', code: 'SP' },
];
const setPaKategorie = () => {
    const category = paKategorieOptionen.find((item) => item.value === paKompetenzForm.kategorie);
    if (!category) return;
    paKompetenzForm.kategorie_label = category.label;
    paKompetenzForm.kategorie_code = category.code;
};
const paKompetenzEntwuerfe = reactive(Object.fromEntries((paProfil?.kompetenzen || []).map((competency) => [
    competency.id,
    {
        key: competency.key,
        label: competency.label,
        kategorie: competency.kategorie,
        kategorie_label: competency.kategorie_label,
        kategorie_code: competency.kategorie_code,
        beschreibung: competency.beschreibung || '',
        selbsteinschaetzung_text: competency.selbsteinschaetzung_text || '',
        bewertungsbeschreibungen: competency.bewertungsbeschreibungen || [
            'Kaum erkennbar; umfassende Unterstützung ist erforderlich.',
            'Teilweise erkennbar; häufige Unterstützung ist erforderlich.',
            'Überwiegend angemessen; gelegentliche Unterstützung ist erforderlich.',
            'Sicher, selbstständig und wiederholt erkennbar.',
            'Besonders sicher, selbstständig und auch in anspruchsvollen Situationen erkennbar.',
        ],
        sort_order: competency.sort_order || 0,
        aktiv: competency.aktiv ?? true,
    },
])));
const paReportStyles = [
    { value: 'staerkenorientiert', label: 'Stärkenorientiert' },
    { value: 'ausfuehrlich', label: 'Ausführlich' },
    { value: 'kompakt', label: 'Kompakt' },
    { value: 'sachlich', label: 'Sachlich und wertschätzend' },
];
const paZuordnungen = (entries = []) => paKompetenzen.map((kompetenz) => {
    const found = entries.find((entry) => entry.merkmal === kompetenz.key);
    return {
        merkmal: kompetenz.key,
        label: kompetenz.label,
        aktiv: Boolean(found && (found.aktiv ?? true)),
        gewichtung: Number(found?.gewichtung ?? 100),
    };
});
const paZeitstufen = [
    { key: 'stufe_5_bis', label: 'Stufe 5 bis' },
    { key: 'stufe_4_bis', label: 'Stufe 4 bis' },
    { key: 'stufe_3_bis', label: 'Stufe 3 bis' },
    { key: 'stufe_2_bis', label: 'Stufe 2 bis' },
];
const formatPaZeitgrenze = (seconds) => {
    if (seconds === null || seconds === undefined || seconds === '') return '';
    const value = Number(seconds);
    if (!Number.isFinite(value) || value <= 0) return '';
    return `${Math.floor(value / 60)}:${String(Math.floor(value % 60)).padStart(2, '0')}`;
};
const parsePaZeitgrenze = (value) => {
    const match = String(value ?? '').trim().match(/^(\d+):([0-5]\d)$/);
    return match ? (Number(match[1]) * 60) + Number(match[2]) : null;
};
const paZeitgrenzenAusConfig = (config = {}) => Object.fromEntries(paZeitstufen.map(({ key }) => [
    key,
    formatPaZeitgrenze(config?.zeitgrenzen?.[key]),
]));
const normalizePaUebung = (uebung) => ({
    ...uebung,
    auswertung_hervorheben: Boolean(uebung.auswertung_hervorheben),
    im_bericht_anzeigen: uebung.im_bericht_anzeigen !== false,
    ergebnis_typ: uebung.ergebnis_typ || 'punkte',
    berechnungsregel: uebung.berechnungsregel || 'direkte_punkte',
    zeit_erfassen: uebung.berechnungsregel === 'zeit'
        || Boolean(uebung.zeit_erfassen ?? (uebung.berechnungsregel === 'direkte_punkte')),
    fehler_abzug: Number(uebung.fehler_abzug ?? 1),
    berechnungs_config: uebung.berechnungs_config || {},
    zeitgrenzen: paZeitgrenzenAusConfig(uebung.berechnungs_config || {}),
    mindestwert: Number(uebung.mindestwert ?? 0),
    kompetenzen: paZuordnungen(uebung.kompetenz_zuordnungen || uebung.kompetenzen || []),
});
const paUebungen = ref((props.projekt.potenzialanalyse_uebungen || []).map(normalizePaUebung));
const paUebungForm = reactive({
    name: '',
    tag: null,
    beschreibung: '',
    hoechstwert: null,
    auswertbar: false,
    auswertung_hervorheben: false,
    im_bericht_anzeigen: true,
    ergebnis_typ: 'punkte',
    berechnungsregel: 'direkte_punkte',
    zeit_erfassen: false,
    fehler_abzug: 1,
    berechnungs_config: {},
    zeitgrenzen: paZeitgrenzenAusConfig(),
    mindestwert: 0,
    kompetenzen: paZuordnungen(),
    sort_order: 0,
    aktiv: true,
});
const paKriteriumForms = reactive({});
const savingPa = ref(false);
const defaultPaConfig = {
    thresholds: { rating_2_from: 20, rating_3_from: 40, rating_4_from: 60, rating_5_from: 80 },
    source_weights: { exercises: 100, coach: 0, self: 0 },
    report_style: 'staerkenorientiert',
};
const paAuswertungConfig = reactive(JSON.parse(JSON.stringify({
    ...defaultPaConfig,
    ...(props.projekt.potenzialanalyse_auswertung_config || {}),
    thresholds: { ...defaultPaConfig.thresholds, ...(props.projekt.potenzialanalyse_auswertung_config?.thresholds || {}) },
    source_weights: { ...defaultPaConfig.source_weights },
})));

const paAktiv = computed(() => Boolean(props.projekt.potenzialanalyse_aktiv));
const paMatrixEntry = (uebung, merkmal) => uebung.kompetenzen.find((entry) => entry.merkmal === merkmal);
const paMatrixCellValue = (uebung, merkmal) => {
    const entry = paMatrixEntry(uebung, merkmal);
    return entry?.aktiv ? entry.gewichtung : '';
};
const updatePaMatrixCell = (uebung, merkmal, value) => {
    const entry = paMatrixEntry(uebung, merkmal);
    if (!entry) return;

    const normalized = String(value).replace(',', '.').trim();
    const weight = normalized === '' ? 0 : Number(normalized);
    if (!Number.isFinite(weight)) return;

    entry.aktiv = weight > 0;
    entry.gewichtung = weight > 0 ? Math.min(100, weight) : 0;
};
const paMatrixTotals = computed(() => Object.fromEntries(paKompetenzen.map((kompetenz) => [
    kompetenz.key,
    paUebungen.value
        .filter((uebung) => uebung.aktiv)
        .reduce((sum, uebung) => {
            const entry = paMatrixEntry(uebung, kompetenz.key);
            return sum + (entry?.aktiv ? Number(entry.gewichtung || 0) : 0);
        }, 0),
])));
const paMatrixInvalidCompetencies = computed(() => paKompetenzen.filter((kompetenz) => {
    const total = paMatrixTotals.value[kompetenz.key];
    return total > 0 && Math.abs(total - 100) > 0.01;
}));
const paMatrixTotalClass = (merkmal) => {
    const total = paMatrixTotals.value[merkmal];
    if (total === 0) return 'bg-gray-100 text-gray-500';
    return Math.abs(total - 100) <= 0.01
        ? 'bg-green-100 text-green-700'
        : 'bg-red-100 text-red-700';
};

const standortById = computed(() => {
    return new Map((props.alleStandorte || []).map((standort) => [standort.id, standort]));
});

const zugewieseneMitarbeiter = computed(() => {
    const grouped = new Map();

    for (const person of projektMitarbeiter.value) {
        if (!grouped.has(person.id)) {
            grouped.set(person.id, {
                ...person,
                standorte: [],
            });
        }

        const standortId = person.pivot?.standort_id;
        const standort = standortById.value.get(standortId);

        if (standort && !grouped.get(person.id).standorte.some((item) => item.id === standort.id)) {
            grouped.get(person.id).standorte.push(standort);
        }
    }

    return Array.from(grouped.values()).sort((a, b) => {
        return `${a.nachname} ${a.vorname}`.localeCompare(`${b.nachname} ${b.vorname}`);
    });
});

const formatDate = (date) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('de-DE');
};

const roleNames = (person) => {
    return person.user?.roles?.map((role) => role.name).join(', ') || '-';
};

const resetUebungForm = () => {
    paUebungForm.name = '';
    paUebungForm.tag = null;
    paUebungForm.beschreibung = '';
    paUebungForm.hoechstwert = null;
    paUebungForm.auswertbar = false;
    paUebungForm.auswertung_hervorheben = false;
    paUebungForm.im_bericht_anzeigen = true;
    paUebungForm.ergebnis_typ = 'punkte';
    paUebungForm.berechnungsregel = 'direkte_punkte';
    paUebungForm.zeit_erfassen = false;
    paUebungForm.fehler_abzug = 1;
    paUebungForm.berechnungs_config = {};
    paUebungForm.zeitgrenzen = paZeitgrenzenAusConfig();
    paUebungForm.mindestwert = 0;
    paUebungForm.kompetenzen = paZuordnungen();
    paUebungForm.sort_order = 0;
    paUebungForm.aktiv = true;
};

const kriteriumForm = (uebungId) => {
    if (!paKriteriumForms[uebungId]) {
        paKriteriumForms[uebungId] = {
            name: '',
            beschreibung: '',
            skala_min: 1,
            skala_max: 5,
            sort_order: 0,
            aktiv: true,
        };
    }

    return paKriteriumForms[uebungId];
};

const resetKriteriumForm = (uebungId) => {
    paKriteriumForms[uebungId] = {
        name: '',
        beschreibung: '',
        skala_min: 1,
        skala_max: 5,
        sort_order: 0,
        aktiv: true,
    };
};

const updatePaUebungen = (uebungen) => {
    paUebungen.value = JSON.parse(JSON.stringify((uebungen || []).map(normalizePaUebung)));
};

const paPayload = (item) => ({
    name: item.name,
    tag: item.tag || null,
    beschreibung: item.beschreibung || null,
    hoechstwert: item.hoechstwert || null,
    auswertbar: Boolean(item.auswertbar),
    auswertung_hervorheben: Boolean(item.auswertung_hervorheben),
    im_bericht_anzeigen: item.im_bericht_anzeigen !== false,
    ergebnis_typ: item.ergebnis_typ || 'punkte',
    berechnungsregel: item.berechnungsregel || 'direkte_punkte',
    zeit_erfassen: item.berechnungsregel === 'zeit' || Boolean(item.zeit_erfassen),
    fehler_abzug: Number(item.fehler_abzug ?? 1),
    berechnungs_config: item.berechnungsregel === 'zeit'
        ? {
            ...(item.berechnungs_config || {}),
            zeitgrenzen: Object.fromEntries(paZeitstufen.map(({ key }) => [key, parsePaZeitgrenze(item.zeitgrenzen?.[key])])),
        }
        : (item.berechnungs_config || null),
    mindestwert: Number(item.mindestwert ?? 0),
    kompetenzen: (item.kompetenzen || [])
        .filter((entry) => entry.aktiv)
        .map((entry) => ({ merkmal: entry.merkmal, gewichtung: Number(entry.gewichtung || 100), aktiv: true })),
    sort_order: item.sort_order || 0,
    aktiv: Boolean(item.aktiv),
});

const reloadAfterPaChange = () => window.location.reload();

const createPaProfil = async () => {
    savingPa.value = true;
    try {
        const response = await axios.post(
            route('potenzialanalyse.projekt.profile.store', props.projekt.id),
            JSON.parse(JSON.stringify(paProfilForm)),
        );
        await Swal.fire('Profil angelegt', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Fehler', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Das Profil konnte nicht angelegt werden.'), 'error');
    } finally {
        savingPa.value = false;
    }
};

const publishPaProfil = async () => {
    if (!paProfil) return;
    savingPa.value = true;
    try {
        const response = await axios.post(route('potenzialanalyse.profile.publish', paProfil.id));
        await Swal.fire('Profil veröffentlicht', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Profil noch nicht vollständig', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Das Profil konnte nicht veröffentlicht werden.'), 'warning');
    } finally {
        savingPa.value = false;
    }
};

const resetPaProfil = async () => {
    if (!paProfil || !paProfilBearbeitbar.value) return;

    const confirmation = await Swal.fire({
        title: 'Profilentwurf zurücksetzen?',
        text: 'Der unveröffentlichte Entwurf mit seinen Kompetenzen, Übungen und Gewichtungen wird verworfen. Danach können Sie die Vorlage erneut auswählen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Entwurf verwerfen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#b91c1c',
    });
    if (!confirmation.isConfirmed) return;

    savingPa.value = true;
    try {
        const response = await axios.delete(route('potenzialanalyse.profile.destroy', paProfil.id));
        await Swal.fire('Zurückgesetzt', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Zurücksetzen nicht möglich', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Der Profilentwurf konnte nicht verworfen werden.'), 'error');
    } finally {
        savingPa.value = false;
    }
};

const savePaBerichtConfig = async () => {
    if (!paProfil || !paProfilBearbeitbar.value) return;
    savingPa.value = true;
    try {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('titel', paBerichtConfigForm.titel);
        formData.append('untertitel', paBerichtConfigForm.untertitel || '');
        formData.append('uebungsergebnisse_anzeigen', paBerichtConfigForm.uebungsergebnisse_anzeigen ? '1' : '0');
        formData.append('selbsteinschaetzung_anzeigen', paBerichtConfigForm.selbsteinschaetzung_anzeigen ? '1' : '0');
        formData.append('staerkenprofil_anzeigen', paBerichtConfigForm.staerkenprofil_anzeigen ? '1' : '0');
        formData.append('logo_anzeigen', paBerichtConfigForm.logo_anzeigen ? '1' : '0');
        formData.append('logo_entfernen', paBerichtLogoEntfernen.value ? '1' : '0');
        if (paBerichtLogoFile.value) formData.append('logo', paBerichtLogoFile.value);

        const response = await axios.post(
            route('potenzialanalyse.profile.bericht-config.update', paProfil.id),
            formData,
        );
        const display = response.data.profil?.bericht_config?.darstellung || {};
        paBerichtLogoPreview.value = display.logo_url || '';
        paBerichtConfigForm.logo_anzeigen = display.logo_anzeigen ?? false;
        paBerichtLogoFile.value = null;
        paBerichtLogoEntfernen.value = false;
        if (paBerichtLogoInput.value) paBerichtLogoInput.value.value = '';
        Swal.fire('Gespeichert', response.data.message, 'success');
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Fehler', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Die Berichtsdarstellung konnte nicht gespeichert werden.'), 'error');
    } finally {
        savingPa.value = false;
    }
};

const selectPaBerichtLogo = (event) => {
    const file = event.target.files?.[0] || null;
    paBerichtLogoFile.value = file;
    paBerichtLogoEntfernen.value = false;
    if (!file) return;

    paBerichtConfigForm.logo_anzeigen = true;
    const reader = new FileReader();
    reader.onload = () => { paBerichtLogoPreview.value = reader.result; };
    reader.readAsDataURL(file);
};

const removePaBerichtLogo = () => {
    paBerichtLogoFile.value = null;
    paBerichtLogoPreview.value = '';
    paBerichtLogoEntfernen.value = true;
    paBerichtConfigForm.logo_anzeigen = false;
    if (paBerichtLogoInput.value) paBerichtLogoInput.value.value = '';
};

const createPaProfilVersion = async () => {
    if (!paProfil) return;
    savingPa.value = true;
    try {
        const response = await axios.post(route('potenzialanalyse.profile.versions.store', paProfil.id));
        await Swal.fire('Neue Version', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die neue Version konnte nicht angelegt werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const storePaKompetenz = async () => {
    if (!paProfil || !paProfilBearbeitbar.value) return;
    setPaKategorie();
    savingPa.value = true;
    try {
        const response = await axios.post(
            route('potenzialanalyse.profile.kompetenzen.store', paProfil.id),
            JSON.parse(JSON.stringify(paKompetenzForm)),
        );
        await Swal.fire('Kompetenz angelegt', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Fehler', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Die Kompetenz konnte nicht angelegt werden.'), 'error');
    } finally {
        savingPa.value = false;
    }
};

const updatePaKompetenz = async (competency) => {
    const draft = paKompetenzEntwuerfe[competency.id];
    if (!draft || !paProfilBearbeitbar.value) return;
    const category = paKategorieOptionen.find((item) => item.value === draft.kategorie);
    if (category) {
        draft.kategorie_label = category.label;
        draft.kategorie_code = category.code;
    }

    savingPa.value = true;
    try {
        const response = await axios.put(
            route('potenzialanalyse.profile.kompetenzen.update', competency.id),
            JSON.parse(JSON.stringify(draft)),
        );
        await Swal.fire('Gespeichert', response.data.message, 'success');
        reloadAfterPaChange();
    } catch (error) {
        const errors = error.response?.data?.errors;
        Swal.fire('Fehler', errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Die Kompetenz konnte nicht aktualisiert werden.'), 'error');
    } finally {
        savingPa.value = false;
    }
};

const destroyPaKompetenz = async (competency) => {
    if (!paProfilBearbeitbar.value) return;
    const confirmation = await Swal.fire({
        title: 'Kompetenz entfernen?',
        text: `${competency.label} wird auch aus der Gewichtungsmatrix entfernt.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Entfernen',
        cancelButtonText: 'Abbrechen',
    });
    if (!confirmation.isConfirmed) return;

    savingPa.value = true;
    try {
        await axios.delete(route('potenzialanalyse.profile.kompetenzen.destroy', competency.id));
        reloadAfterPaChange();
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die Kompetenz konnte nicht entfernt werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const savePaAuswertungConfig = async () => {
    if (!canManagePotenzialanalyse.value) return;
    savingPa.value = true;
    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.auswertung-config.update', props.projekt.id),
            JSON.parse(JSON.stringify(paAuswertungConfig)),
        );
        Object.assign(paAuswertungConfig, response.data.config);
        Swal.fire('Gespeichert', response.data.message || 'Auswertungseinstellungen wurden gespeichert.', 'success');
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors ? Object.values(errors).flat()[0] : error.response?.data?.message;
        Swal.fire('Fehler', message || 'Auswertungseinstellungen konnten nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const savePaGewichtungsmatrix = async () => {
    if (!canManagePotenzialanalyse.value || !paUebungen.value.length) return;

    if (paMatrixInvalidCompetencies.value.length) {
        Swal.fire(
            'Gewichtung prüfen',
            `Diese Kompetenzspalten ergeben noch nicht 100 %: ${paMatrixInvalidCompetencies.value.map((item) => item.label).join(', ')}.`,
            'warning',
        );
        return;
    }

    savingPa.value = true;
    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.gewichtungsmatrix.update', props.projekt.id),
            {
                uebungen: paUebungen.value.map((uebung) => ({
                    id: uebung.id,
                    kompetenzen: uebung.kompetenzen
                        .filter((entry) => entry.aktiv && Number(entry.gewichtung) > 0)
                        .map((entry) => ({
                            merkmal: entry.merkmal,
                            gewichtung: Number(entry.gewichtung),
                        })),
                })),
            },
        );
        updatePaUebungen(response.data.uebungen);
        Swal.fire('Gespeichert', response.data.message || 'Gewichtungsmatrix wurde gespeichert.', 'success');
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors ? Object.values(errors).flat()[0] : error.response?.data?.message;
        Swal.fire('Fehler', message || 'Gewichtungsmatrix konnte nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const kriteriumPayload = (item) => ({
    name: item.name,
    beschreibung: item.beschreibung || null,
    skala_min: item.skala_min || 1,
    skala_max: item.skala_max || 5,
    sort_order: item.sort_order || 0,
    aktiv: Boolean(item.aktiv),
});

const storeUebung = async () => {
    if (!canManagePotenzialanalyse.value) return;

    if (!paUebungForm.name) {
        Swal.fire('Fehler', 'Bitte einen Namen fuer die Uebung eintragen.', 'error');
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.post(
            route('potenzialanalyse.projekt.uebungen.store', props.projekt.id),
            paPayload(paUebungForm)
        );

        updatePaUebungen(response.data.uebungen);
        resetUebungForm();
        Swal.fire('Gespeichert', response.data.message || 'Uebung wurde gespeichert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const updateUebung = async (uebung) => {
    if (!canManagePotenzialanalyse.value) return;

    savingPa.value = true;

    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.uebungen.update', uebung.id),
            paPayload(uebung)
        );

        updatePaUebungen(response.data.uebungen);
        Swal.fire('Gespeichert', response.data.message || 'Uebung wurde aktualisiert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht aktualisiert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const destroyUebung = async (uebung) => {
    if (!canManagePotenzialanalyse.value) return;

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Uebung loeschen?',
        text: 'Erfasste Punkte und Zeiten zu dieser Uebung werden ebenfalls geloescht.',
        showCancelButton: true,
        confirmButtonText: 'Loeschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.delete(route('potenzialanalyse.projekt.uebungen.destroy', uebung.id));
        updatePaUebungen(response.data.uebungen);
        Swal.fire('Geloescht', response.data.message || 'Uebung wurde geloescht.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Uebung konnte nicht geloescht werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const storeKriterium = async (uebung) => {
    if (!canManagePotenzialanalyse.value) return;

    const form = kriteriumForm(uebung.id);
    if (!form.name) {
        Swal.fire('Fehler', 'Bitte einen Namen fuer das Kriterium eintragen.', 'error');
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.post(
            route('potenzialanalyse.projekt.kriterien.store', uebung.id),
            kriteriumPayload(form)
        );

        updatePaUebungen(response.data.uebungen);
        resetKriteriumForm(uebung.id);
        Swal.fire('Gespeichert', response.data.message || 'Kriterium wurde gespeichert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht gespeichert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const updateKriterium = async (kriterium) => {
    if (!canManagePotenzialanalyse.value) return;

    savingPa.value = true;

    try {
        const response = await axios.put(
            route('potenzialanalyse.projekt.kriterien.update', kriterium.id),
            kriteriumPayload(kriterium)
        );

        updatePaUebungen(response.data.uebungen);
        Swal.fire('Gespeichert', response.data.message || 'Kriterium wurde aktualisiert.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht aktualisiert werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const destroyKriterium = async (kriterium) => {
    if (!canManagePotenzialanalyse.value) return;

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Kriterium loeschen?',
        text: 'Erfasste Bewertungen zu diesem Kriterium werden ebenfalls geloescht.',
        showCancelButton: true,
        confirmButtonText: 'Loeschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });

    if (!result.isConfirmed) {
        return;
    }

    savingPa.value = true;

    try {
        const response = await axios.delete(route('potenzialanalyse.projekt.kriterien.destroy', kriterium.id));
        updatePaUebungen(response.data.uebungen);
        Swal.fire('Geloescht', response.data.message || 'Kriterium wurde geloescht.', 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Kriterium konnte nicht geloescht werden.', 'error');
    } finally {
        savingPa.value = false;
    }
};

const addMitarbeiter = (person) => {
    const standortIds = selectedStandorte[person.id] || [];

    if (!standortIds.length) {
        Swal.fire('Fehler', 'Bitte mindestens einen Standort auswahlen.', 'error');
        return;
    }

    axios.post(route('projekthaspersonen.store'), {
        user_id: person.id,
        zuweisungen: [
            {
                projekt_id: props.projekt.id,
                standort_id: standortIds,
            },
        ],
    })
        .then(() => {
            for (const standortId of standortIds) {
                projektMitarbeiter.value.push({
                    ...person,
                    pivot: {
                        ...(person.pivot || {}),
                        standort_id: standortId,
                        status: 'aktiv',
                    },
                });
            }

            fehlendeMitarbeiterListe.value = fehlendeMitarbeiterListe.value.filter((item) => item.id !== person.id);
            selectedStandorte[person.id] = [];
            Swal.fire('Gespeichert!', 'Mitarbeiter wurde dem Projekt zugewiesen.', 'success');
        })
        .catch(() => {
            Swal.fire('Fehler', 'Zuweisung konnte nicht gespeichert werden.', 'error');
        });
};

const cloneLuvSections = (sections) => JSON.parse(JSON.stringify(
    Array.isArray(sections) && sections.length ? sections : (props.projekt.luv_default_sections || [])
));
const luvTemplates = ref([...(props.projekt.luv_templates || [])]);
const activeLuvTemplate = computed(() => luvTemplates.value.find((template) => template.is_active) || null);
const luvTemplateFile = ref(null);
const luvTemplateSaving = ref(false);
const luvTemplateActivating = ref(null);
const luvTemplateForm = reactive({
    name: activeLuvTemplate.value?.name || 'LuV ' + props.projekt.name,
    ai_instructions: activeLuvTemplate.value?.ai_instructions || '',
    sections: cloneLuvSections(activeLuvTemplate.value?.sections),
});

const editLuvTemplateVersion = (template) => {
    luvTemplateForm.name = template.name;
    luvTemplateForm.ai_instructions = template.ai_instructions || '';
    luvTemplateForm.sections = cloneLuvSections(template.sections);
};

const addLuvSection = () => {
    if (luvTemplateForm.sections.length >= 6) return;
    luvTemplateForm.sections.push({
        key: `abschnitt_${Date.now()}`,
        heading: 'Neuer Abschnitt',
        instruction: 'Fasse ausschließlich die belegten Informationen zu diesem Abschnitt zusammen.',
        required: false,
    });
};

const removeLuvSection = (index) => {
    if (luvTemplateForm.sections.length > 1) luvTemplateForm.sections.splice(index, 1);
};

const moveLuvSection = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= luvTemplateForm.sections.length) return;
    [luvTemplateForm.sections[index], luvTemplateForm.sections[target]] = [
        luvTemplateForm.sections[target],
        luvTemplateForm.sections[index],
    ];
};

const saveLuvTemplate = async () => {
    luvTemplateSaving.value = true;
    try {
        const data = new FormData();
        data.append('name', luvTemplateForm.name);
        data.append('ai_instructions', luvTemplateForm.ai_instructions || '');
        data.append('sections', JSON.stringify(luvTemplateForm.sections));
        if (luvTemplateFile.value) data.append('template', luvTemplateFile.value);

        const response = await axios.post(route('projekt.luv-templates.store', props.projekt.id), data);
        luvTemplates.value = response.data.templates;
        luvTemplateFile.value = null;
        Swal.fire('Aktiviert', response.data.message, 'success');
    } catch (error) {
        const message = Object.values(error.response?.data?.errors || {}).flat()[0];
        Swal.fire('Fehler', message || 'Die LuV-Vorlagenversion konnte nicht gespeichert werden.', 'error');
    } finally {
        luvTemplateSaving.value = false;
    }
};

const activateLuvTemplate = async (template) => {
    luvTemplateActivating.value = template.id;
    try {
        const response = await axios.put(route('projekt.luv-templates.activate', [props.projekt.id, template.id]));
        luvTemplates.value = response.data.templates;
        editLuvTemplateVersion(luvTemplates.value.find((item) => item.is_active));
        Swal.fire('Aktiviert', response.data.message, 'success');
    } catch (error) {
        Swal.fire('Fehler', error.response?.data?.message || 'Die Version konnte nicht aktiviert werden.', 'error');
    } finally {
        luvTemplateActivating.value = null;
    }
};

const formatLuvTemplateDate = (value) => value
    ? new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '-';
</script>

<template>
    <Head :title="`Projekt ${projekt.name}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>{{ projekt.name }}</span>
                <Link :href="route('projekt.index')" class="text-sm text-zbb hover:underline">
                    Zuruck zur Projektliste
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <nav class="flex gap-2 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 shadow-sm" aria-label="Projektverwaltung">
                <button
                    v-for="tab in administrationTabs"
                    :key="tab.key"
                    type="button"
                    class="shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition"
                    :class="activeAdministrationTab === tab.key ? 'bg-zbb text-white shadow-sm' : 'text-gray-600 hover:bg-zbbTrp hover:text-zbb'"
                    @click="activeAdministrationTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <section v-if="activeAdministrationTab === 'overview'" class="bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-6">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Projekt</p>
                        <p class="font-semibold">{{ projekt.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Abteilung</p>
                        <p class="font-semibold">{{ projekt.abteilung?.name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Bereiche</p>
                        <p class="font-semibold">{{ projekt.bereiche?.map((bereich) => bereich.code || bereich.name).join(', ') || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Kostenstellen</p>
                        <p class="font-semibold">{{ projekt.kostenstellen?.map((kostenstelle) => kostenstelle.kostenstelle).join(', ') || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Potenzialanalyse</p>
                        <p class="font-semibold">
                            {{ projekt.potenzialanalyse_aktiv ? `Ja (${projekt.potenzialanalyse_tage || '?'} Tage)` : 'Nein' }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'areas' && canUpdateProjekt" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800">Projektbereiche</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Wählen Sie alle Bereiche aus, die diesem Projekt zugeordnet werden sollen. Bereiche können über das Kreuz im Auswahlfeld entfernt werden.
                    </p>
                </div>

                <MultiSelect
                    v-model="selectedAreaIds"
                    :options="alleBereiche || []"
                    optionLabel="name"
                    optionValue="id"
                    display="chip"
                    filter
                    placeholder="Bereiche auswählen"
                    class="w-full"
                >
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <span>{{ option.name }}</span>
                            <span v-if="option.code" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                {{ option.code }}
                            </span>
                        </div>
                    </template>
                </MultiSelect>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="areaAssignmentSaving"
                        @click="resetAreaAssignment"
                    >
                        Zurücksetzen
                    </button>
                    <button
                        type="button"
                        class="rounded bg-zbb px-4 py-2 text-sm font-medium text-white hover:bg-zbb/90 disabled:opacity-50"
                        :disabled="areaAssignmentSaving"
                        @click="saveAreaAssignment"
                    >
                        {{ areaAssignmentSaving ? 'Speichert …' : 'Bereiche speichern' }}
                    </button>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'participants' && projectFeatures.participant_management" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Bereiche im Teilnehmerprofil</h2>
                        <p class="mt-1 max-w-3xl text-sm text-gray-500">
                            Legen Sie fest, welche Tabs Mitarbeiter bei Teilnehmern dieses Projekts sehen. Das Ausblenden entfernt keine gespeicherten Daten. Projektfunktionen und Benutzerberechtigungen gelten zusätzlich.
                        </p>
                    </div>
                    <div v-if="canUpdateProjekt" class="flex flex-wrap gap-2">
                        <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700" @click="applyParticipantProfilePreset('compact')">Kompakt</button>
                        <button type="button" class="rounded border border-zbb px-3 py-2 text-sm text-zbb" @click="applyParticipantProfilePreset('bop')">BOP-Vorschlag</button>
                        <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700" @click="applyParticipantProfilePreset('all')">Alle Bereiche</button>
                        <button type="button" class="rounded bg-zbb px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="participantProfileSaving" @click="saveParticipantProfile">
                            {{ participantProfileSaving ? 'Speichert …' : 'Profilansicht speichern' }}
                        </button>
                    </div>
                </div>

                <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    <strong>{{ participantProfile.enabled_tabs?.length || 0 }} Bereiche aktiv.</strong>
                    „Stammdaten“ ist ein Pflichtbereich. Mit den Pfeilen bestimmen Sie die Reihenfolge auf der Teilnehmerseite.
                </div>

                <div class="grid gap-3 xl:grid-cols-2">
                    <div
                        v-for="item in orderedParticipantProfileDefinitions"
                        :key="item.key"
                        class="flex items-start gap-3 rounded-lg border p-3 transition"
                        :class="participantProfileEnabled(item.key) ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'"
                    >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                    :checked="participantProfileEnabled(item.key)"
                                    :disabled="!canUpdateProjekt || item.required"
                                    @change="toggleParticipantProfileTab(item.key, $event.target.checked)"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-gray-800">{{ item.label }}</span>
                                        <span class="rounded bg-white/80 px-2 py-0.5 text-[11px] text-gray-500">{{ item.group }}</span>
                                        <span v-if="item.required" class="rounded bg-zbbTrp px-2 py-0.5 text-[11px] font-medium text-zbb">Pflicht</span>
                                        <span v-if="item.feature || item.portal_feature || item.portal_feature_any" class="rounded bg-gray-200 px-2 py-0.5 text-[11px] text-gray-600">funktionsabhängig</span>
                                    </div>
                                    <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ item.description }}</p>
                                </div>
                                <div v-if="canUpdateProjekt" class="flex shrink-0 flex-col gap-1">
                                    <button type="button" class="rounded border bg-white px-2 py-1 text-xs text-gray-600 disabled:opacity-30" :disabled="participantProfile.tab_order.indexOf(item.key) === 0" title="Nach oben" @click="moveParticipantProfileTab(item.key, -1)">↑</button>
                                    <button type="button" class="rounded border bg-white px-2 py-1 text-xs text-gray-600 disabled:opacity-30" :disabled="participantProfile.tab_order.indexOf(item.key) === participantProfile.tab_order.length - 1" title="Nach unten" @click="moveParticipantProfileTab(item.key, 1)">↓</button>
                                </div>
                    </div>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'participants' && projectFeatures.participant_management" class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Teilnehmerportal-Funktionen</h2>
                        <p class="mt-1 text-sm text-gray-500">Diese Freigaben gelten nur für Teilnehmer dieses Projekts. Zusätzlich muss das globale Modul „Teilnehmerportal“ aktiv sein.</p>
                    </div>
                    <button v-if="can('projekt.update')" type="button" class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="portalFeaturesSaving" @click="savePortalFeatures">
                        {{ portalFeaturesSaving ? 'Speichert …' : 'Portal-Funktionen speichern' }}
                    </button>
                    <Link v-if="portalFeatures.learning && can('projekt.update')" :href="route('projekt.courses.index', projekt.id)" class="rounded border border-zbb px-4 py-2 text-sm text-zbb">Kurse verwalten</Link>
                    <Link v-if="portalFeatures.consents_and_approvals && can('projekt.update')" :href="route('projekt.consents.index', projekt.id)" class="rounded border border-zbb px-4 py-2 text-sm text-zbb">Einwilligungen verwalten</Link>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label v-for="feature in portalFeatureDefinitions" :key="feature.key" class="flex items-start gap-3 rounded border p-4" :class="portalFeatures[feature.key] ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50'">
                        <input v-model="portalFeatures[feature.key]" type="checkbox" class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb" :disabled="!can('projekt.update')" />
                        <span><span class="block font-semibold text-gray-800">{{ feature.label }}</span><span class="mt-1 block text-xs text-gray-500">{{ feature.description }}</span></span>
                    </label>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'participants' && projectFeatures.completion_management" class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold">Abschlusscheckliste</h2><p class="mt-1 text-sm text-gray-500">Pflichtpunkte müssen erledigt sein, bevor ein Teilnahmeabschluss freigegeben werden kann. Entfernte Punkte werden historienerhaltend deaktiviert.</p></div><div v-if="can('projekt.update')" class="flex gap-2"><button type="button" class="rounded border border-zbb px-4 py-2 text-sm text-zbb" @click="addCompletionChecklistItem">Punkt hinzufügen</button><button type="button" class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="completionChecklistSaving" @click="saveCompletionChecklist">{{ completionChecklistSaving ? 'Speichert …' : 'Checkliste speichern' }}</button></div></div>
                <div class="space-y-3"><div v-for="(item, index) in completionChecklistItems" :key="item.id || `completion-new-${index}`" class="grid gap-3 rounded border border-gray-200 p-4 md:grid-cols-[60px_1fr_1fr_130px_auto]"><input v-model.number="item.sort_order" type="number" min="0" class="rounded border-gray-300 text-sm" disabled /><input v-model="item.label" maxlength="150" placeholder="Bezeichnung, z. B. Abschlussgespräch geführt" class="rounded border-gray-300 text-sm" :disabled="!can('projekt.update')" /><input v-model="item.description" maxlength="500" placeholder="Optionale Erläuterung" class="rounded border-gray-300 text-sm" :disabled="!can('projekt.update')" /><label class="flex items-center gap-2 text-sm text-gray-600"><input v-model="item.required" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" :disabled="!can('projekt.update')" />Pflichtpunkt</label><button v-if="can('projekt.update')" type="button" class="text-sm text-red-600" @click="removeCompletionChecklistItem(index)">Entfernen</button></div><p v-if="!completionChecklistItems.length" class="rounded border border-dashed p-5 text-center text-sm text-gray-500">Noch keine Abschlussprüfpunkte konfiguriert.</p></div>
            </section>

            <section v-if="activeAdministrationTab === 'participants' && projectFeatures.participant_management" class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Aufnahmecheckliste</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Diese neutralen Prüfpunkte gelten für jede neue Teilnahme in {{ projekt.name }}. Entfernte Punkte werden nur deaktiviert; vorhandene Bearbeitungsstände bleiben erhalten.
                        </p>
                    </div>
                    <div v-if="can('projekt.update')" class="flex gap-2">
                        <button type="button" class="rounded border border-zbb px-4 py-2 text-sm text-zbb" @click="addIntakeChecklistItem">
                            Punkt hinzufügen
                        </button>
                        <button type="button" class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="intakeChecklistSaving" @click="saveIntakeChecklist">
                            {{ intakeChecklistSaving ? 'Speichert …' : 'Checkliste speichern' }}
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="(item, index) in intakeChecklistItems" :key="item.id || `new-${index}`" class="grid gap-3 rounded border border-gray-200 p-4 md:grid-cols-[60px_1fr_1fr_130px_auto]">
                        <input v-model.number="item.sort_order" type="number" min="0" class="rounded border-gray-300 text-sm" disabled />
                        <input v-model="item.label" maxlength="150" placeholder="Bezeichnung, z. B. Stammdaten geprüft" class="rounded border-gray-300 text-sm" :disabled="!can('projekt.update')" />
                        <input v-model="item.description" maxlength="500" placeholder="Optionale sachliche Erläuterung" class="rounded border-gray-300 text-sm" :disabled="!can('projekt.update')" />
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="item.required" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" :disabled="!can('projekt.update')" />
                            Pflichtpunkt
                        </label>
                        <button v-if="can('projekt.update')" type="button" class="text-sm text-red-600" @click="removeIntakeChecklistItem(index)">Entfernen</button>
                    </div>
                    <p v-if="!intakeChecklistItems.length" class="rounded border border-dashed p-5 text-center text-sm text-gray-500">Noch keine Aufnahmeprüfpunkte konfiguriert.</p>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'participants' && projectFeatures.participant_management" class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Teilnehmeruebersicht</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Legt fest, welche Kennzahlen und Spalten in der Teilnehmeruebersicht dieses Projekts sichtbar sind.
                        </p>
                    </div>
                    <div v-if="can('projekt.update')" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded border border-zbb px-4 py-2 text-sm text-zbb"
                            @click="applyParticipantOverviewPreset(bopParticipantOverviewColumns, false)"
                        >
                            BOP-Ansicht
                        </button>
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700"
                            @click="applyParticipantOverviewPreset(defaultParticipantOverviewColumns, true)"
                        >
                            Standardansicht
                        </button>
                        <button
                            type="button"
                            class="rounded bg-zbb px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            :disabled="ruleSaving"
                            @click="saveRules"
                        >
                            {{ ruleSaving ? 'Speichert ...' : 'Ansicht speichern' }}
                        </button>
                    </div>
                </div>

                <label class="mb-4 flex items-start gap-3 rounded border border-gray-200 p-4 text-sm text-gray-600">
                    <input
                        v-model="projectRules.participant_overview_show_metrics"
                        type="checkbox"
                        class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                        :disabled="!can('projekt.update')"
                    />
                    <span>
                        <span class="block font-semibold text-gray-800">Kennzahlen oben anzeigen</span>
                        <span class="mt-1 block text-xs text-gray-500">Teilnehmer, Aufgaben, Fehlzeiten, Saldo und Massnahmen als Karten einblenden.</span>
                    </span>
                </label>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="column in participantOverviewColumnDefinitions"
                        :key="column.key"
                        class="flex items-start gap-3 rounded border p-4"
                        :class="isParticipantOverviewColumnActive(column.key) ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50'"
                    >
                        <input
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                            :checked="isParticipantOverviewColumnActive(column.key)"
                            :disabled="!can('projekt.update')"
                            @change="toggleParticipantOverviewColumn(column.key, $event.target.checked)"
                        />
                        <span>
                            <span class="block font-semibold text-gray-800">{{ column.label }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ column.description }}</span>
                        </span>
                    </label>
                </div>
                <p class="mt-3 text-xs text-gray-500">Mindestens eine Spalte bleibt aktiv.</p>
            </section>

            <section v-if="activeAdministrationTab === 'features'" class="bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Funktionen und Regeln</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Diese Einstellungen gelten nur für {{ projekt.name }}. Projektzuweisung, Rolle und Berechtigungen werden zusätzlich geprüft.
                        </p>
                    </div>
                    <button
                        v-if="can('projekt.update')"
                        type="button"
                        class="rounded bg-zbb px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="featureSaving"
                        @click="saveFeatures"
                    >
                        {{ featureSaving ? 'Speichert …' : 'Funktionen speichern' }}
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="feature in featureDefinitions"
                        :key="feature.key"
                        class="flex items-start gap-3 rounded border border-gray-200 p-4"
                        :class="projectFeatures[feature.key] ? 'bg-green-50' : 'bg-gray-50'"
                    >
                        <input
                            v-model="projectFeatures[feature.key]"
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                            :disabled="!canConfigureProjectFeature(feature.key)"
                        />
                        <span>
                            <span class="block font-semibold text-gray-800">{{ feature.label }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ feature.description }}</span>
                        </span>
                    </label>
                </div>

                <label v-if="projectFeatures.potential_analysis" class="mt-4 block max-w-xs text-sm text-gray-600">
                    Anzahl der PA-Tage
                    <input v-model.number="paTage" type="number" min="1" max="60" class="mt-1 w-full rounded border-gray-300" :disabled="!canConfigurePotenzialanalyse" />
                    <span v-if="featureErrors.potenzialanalyse_tage" class="mt-1 block text-xs text-red-600">
                        {{ featureErrors.potenzialanalyse_tage[0] }}
                    </span>
                </label>

                <div class="mt-6 border-t border-gray-200 pt-5">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-800">Projektregeln</h3>
                            <p class="mt-1 text-sm text-gray-500">Diese Werte werden bei Gruppenzuordnung und Anwesenheitsanlage serverseitig geprüft.</p>
                        </div>
                        <button
                            v-if="can('projekt.update')"
                            type="button"
                            class="rounded bg-zbb px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            :disabled="ruleSaving"
                            @click="saveRules"
                        >
                            {{ ruleSaving ? 'Speichert …' : 'Regeln speichern' }}
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="text-sm text-gray-600">
                            Maximale Teilnehmer pro Gruppe
                            <input
                                v-model.number="projectRules.max_group_participants"
                                type="number"
                                min="1"
                                max="999"
                                placeholder="Unbegrenzt"
                                class="mt-1 w-full rounded border-gray-300"
                                :disabled="!projectFeatures.group_management || !can('projekt.update')"
                            />
                            <span class="mt-1 block text-xs text-gray-400">Leer bedeutet keine zusätzliche Begrenzung.</span>
                        </label>

                        <label class="text-sm text-gray-600">
                            Standard-Anwesenheitsstatus
                            <select
                                v-model="projectRules.attendance_default_status"
                                class="mt-1 w-full rounded border-gray-300"
                                :disabled="!projectFeatures.attendance_management || !can('projekt.update')"
                            >
                                <option v-for="status in anwesenheitsstatuten" :key="status.id" :value="status.status">
                                    {{ status.status }}{{ status.abkuerzung ? ` (${status.abkuerzung})` : '' }}
                                </option>
                            </select>
                        </label>

                        <label class="flex items-start gap-3 rounded border border-gray-200 p-4 text-sm text-gray-600">
                            <input
                                v-model="projectRules.attendance_skip_weekends"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                :disabled="!projectFeatures.attendance_management || !can('projekt.update')"
                            />
                            <span>
                                <span class="block font-semibold text-gray-800">Wochenenden überspringen</span>
                                <span class="mt-1 block text-xs text-gray-500">Samstag und Sonntag erzeugen keine Anwesenheitstage.</span>
                            </span>
                        </label>
                    </div>
                    <div class="mt-4 grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-3">
                        <label class="flex items-start gap-3 rounded border border-gray-200 p-4 text-sm text-gray-600">
                            <input
                                v-model="projectRules.participant_birthdate_required"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            />
                            <span>
                                <span class="block font-semibold text-gray-800">Geburtsdatum verpflichtend</span>
                                <span class="mt-1 block text-xs text-gray-500">Gilt bei manueller Anlage, Bearbeitung und Excel-Import.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded border border-gray-200 p-4 text-sm text-gray-600">
                            <input
                                v-model="projectRules.participant_address_enabled"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            />
                            <span>
                                <span class="block font-semibold text-gray-800">Adresse bei Neuanlage erfassen</span>
                                <span class="mt-1 block text-xs text-gray-500">Zeigt die Adressfelder im Modal an und speichert sie direkt beim Teilnehmer.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded border border-gray-200 p-4 text-sm text-gray-600">
                            <input
                                v-model="projectRules.participant_parts_enabled"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            />
                            <span>
                                <span class="block font-semibold text-gray-800">Teilnehmer nach Teilabschnitt erfassen</span>
                                <span class="mt-1 block text-xs text-gray-500">Fragt bei jedem neuen Schüler, ob er an Teil 1, Teil 2 usw. teilnimmt.</span>
                            </span>
                        </label>
                        <label class="text-sm text-gray-600">
                            Mindestalter
                            <input
                                v-model.number="projectRules.participant_min_age"
                                type="number"
                                min="0"
                                max="120"
                                placeholder="Keine Vorgabe"
                                class="mt-1 w-full rounded border-gray-300"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            />
                        </label>
                        <label class="text-sm text-gray-600">
                            Höchstalter
                            <input
                                v-model.number="projectRules.participant_max_age"
                                type="number"
                                min="0"
                                max="120"
                                placeholder="Keine Vorgabe"
                                class="mt-1 w-full rounded border-gray-300"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            />
                        </label>
                        <label class="text-sm text-gray-600">
                            Status bei neuer Projektteilnahme
                            <select
                                v-model="projectRules.participation_initial_status"
                                class="mt-1 w-full rounded border-gray-300"
                                :disabled="!projectFeatures.participant_management || !can('projekt.update')"
                            >
                                <option v-for="status in participationStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <p v-if="Object.keys(ruleErrors).length" class="mt-3 text-sm text-red-600">Bitte die markierten Regelwerte prüfen.</p>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'luv' && canUpdateProjekt" class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Projektbezogene LuV- und KI-Vorlage</h2>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                                Reihenfolge, Überschriften und Schreibregeln gelten nur für {{ projekt.name }}. Beim Speichern entsteht immer eine neue, sofort aktive Version; ältere Versionen bleiben wiederherstellbar.
                            </p>
                        </div>
                        <span v-if="activeLuvTemplate" class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Aktiv: Version {{ activeLuvTemplate.version }}</span>
                        <span v-else class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Globale Standardvorlage aktiv</span>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">
                            Name der neuen Version
                            <input v-model="luvTemplateForm.name" type="text" maxlength="120" class="mt-1 w-full rounded border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Word-Vorlage (DOCX, optional)
                            <input type="file" accept=".docx" class="mt-1 block w-full rounded border border-gray-300 p-2 text-sm" @change="luvTemplateFile = $event.target.files?.[0] || null" />
                            <span class="mt-1 block text-xs font-normal text-gray-500">Ohne neue Datei wird die Datei der aktiven Version weiterverwendet. Maximal 10 MB.</span>
                        </label>
                    </div>

                    <label class="mt-5 block text-sm font-medium text-gray-700">
                        Zusätzliche Schreibregeln für die KI
                        <textarea v-model="luvTemplateForm.ai_instructions" rows="4" maxlength="4000" class="mt-1 w-full rounded border-gray-300" placeholder="Beispiel: Formell, wertschätzend und in kurzen Sätzen schreiben. Keine Diagnosen formulieren." />
                        <span class="mt-1 block text-xs font-normal text-gray-500">Diese Regeln ändern keine Sicherheits-, Quellen- oder Berechtigungsprüfung.</span>
                    </label>

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-800">Abschnitte und Reihenfolge</h3>
                            <p class="text-xs text-gray-500">Die lokale KI verarbeitet höchstens sechs kompakte Abschnitte.</p>
                        </div>
                        <button type="button" class="rounded border border-zbb px-3 py-2 text-sm text-zbb disabled:opacity-50" :disabled="luvTemplateForm.sections.length >= 6" @click="addLuvSection">Abschnitt hinzufügen</button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div v-for="(section, index) in luvTemplateForm.sections" :key="section.key" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="min-w-[240px] flex-1 space-y-3">
                                    <input v-model="section.heading" type="text" maxlength="120" class="w-full rounded border-gray-300 font-semibold" placeholder="Überschrift" />
                                    <textarea v-model="section.instruction" rows="2" maxlength="800" class="w-full rounded border-gray-300 text-sm" placeholder="Was soll die KI in diesem Abschnitt schreiben?" />
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                        <input v-model="section.required" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                        Pflichtabschnitt
                                    </label>
                                </div>
                                <div class="flex gap-1">
                                    <button type="button" class="rounded border bg-white px-2 py-1 text-sm disabled:opacity-30" :disabled="index === 0" title="Nach oben" @click="moveLuvSection(index, -1)">↑</button>
                                    <button type="button" class="rounded border bg-white px-2 py-1 text-sm disabled:opacity-30" :disabled="index === luvTemplateForm.sections.length - 1" title="Nach unten" @click="moveLuvSection(index, 1)">↓</button>
                                    <button type="button" class="rounded border border-red-200 bg-white px-2 py-1 text-sm text-red-600 disabled:opacity-30" :disabled="luvTemplateForm.sections.length === 1" title="Entfernen" @click="removeLuvSection(index)">×</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <details class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                        <summary class="cursor-pointer font-semibold">Erlaubte Word-Platzhalter anzeigen</summary>
                        <p class="mt-2 text-xs">Platzhalter in Word werden zum Beispiel als <code>${vorname}</code> geschrieben. Nicht unterstützte Platzhalter werden beim Upload abgewiesen.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <code v-for="placeholder in projekt.luv_supported_placeholders" :key="placeholder" class="rounded bg-white px-2 py-1 text-xs">{{ '${' + placeholder + '}' }}</code>
                        </div>
                    </details>

                    <div class="mt-5 flex justify-end">
                        <button type="button" class="rounded bg-zbb px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="luvTemplateSaving" @click="saveLuvTemplate">
                            {{ luvTemplateSaving ? 'Speichert …' : 'Als neue Version speichern und aktivieren' }}
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold">Versionsverlauf</h2>
                    <p class="mt-1 text-sm text-gray-500">Eine frühere Version kann jederzeit wieder aktiviert werden. Vorlagendateien liegen geschützt außerhalb des öffentlichen Webverzeichnisses.</p>
                    <div v-if="luvTemplates.length" class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th class="px-3 py-2">Version</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Word-Datei</th><th class="px-3 py-2">Erstellt</th><th class="px-3 py-2 text-right">Aktion</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="template in luvTemplates" :key="template.id">
                                    <td class="px-3 py-3"><span class="font-semibold">v{{ template.version }}</span><span v-if="template.is_active" class="ml-2 rounded bg-green-100 px-2 py-0.5 text-xs text-green-700">aktiv</span></td>
                                    <td class="px-3 py-3">{{ template.name }}</td>
                                    <td class="px-3 py-3">
                                        <a v-if="template.has_file" :href="route('projekt.luv-templates.download', [projekt.id, template.id])" class="text-zbb hover:underline">{{ template.original_filename || 'DOCX herunterladen' }}</a>
                                        <span v-else class="text-gray-400">globale Vorlage</span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-500">{{ formatLuvTemplateDate(template.created_at) }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-xs text-gray-700" @click="editLuvTemplateVersion(template)">Als Basis laden</button>
                                            <button v-if="!template.is_active" type="button" class="rounded border border-zbb px-3 py-1.5 text-xs text-zbb disabled:opacity-50" :disabled="luvTemplateActivating === template.id" @click="activateLuvTemplate(template)">Aktivieren</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Noch keine projektspezifische Version vorhanden. Bis zur ersten Speicherung wird die globale Standardvorlage verwendet.</div>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'potential_analysis' && canManagePotenzialanalyse" class="bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Potenzialanalyse</h2>
                        <p class="text-sm text-gray-500">
                            {{ paAktiv ? `${projekt.potenzialanalyse_tage || '?'} Tage` : 'Nicht aktiv' }}
                        </p>
                    </div>
                    <span
                        class="rounded px-2 py-1 text-xs font-medium"
                        :class="paAktiv ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ paAktiv ? 'Aktiv' : 'Aus' }}
                    </span>
                </div>

                <div v-if="paAktiv" class="space-y-5">
                    <div class="rounded border border-indigo-200 bg-indigo-50 p-4">
                        <div v-if="paProfil" class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold text-indigo-950">{{ paProfil.name }} · Version {{ paProfil.version }}</h3>
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="paProfil.status === 'entwurf' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'">
                                            {{ paProfil.status === 'entwurf' ? 'Entwurf' : 'Veröffentlicht' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-indigo-800">
                                        Kategorien, Kompetenzen, Übungen, Berechnungsregeln und Gewichtungen stammen aus dieser Projektversion.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button v-if="paProfilBearbeitbar" type="button" class="rounded bg-indigo-700 px-3 py-2 text-sm text-white disabled:opacity-50" :disabled="savingPa" @click="publishPaProfil">Profil veröffentlichen</button>
                                    <button v-if="paProfilBearbeitbar" type="button" class="rounded border border-red-300 bg-white px-3 py-2 text-sm text-red-700 disabled:opacity-50" :disabled="savingPa" @click="resetPaProfil">Entwurf zurücksetzen</button>
                                    <button v-else type="button" class="rounded border border-indigo-300 bg-white px-3 py-2 text-sm text-indigo-800 disabled:opacity-50" :disabled="savingPa" @click="createPaProfilVersion">Neue Version bearbeiten</button>
                                </div>
                            </div>

                            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                                <div v-for="competency in paProfil.kompetenzen" :key="competency.id" class="rounded border border-indigo-100 bg-white p-3">
                                    <div v-if="!paProfilBearbeitbar">
                                        <div>
                                            <p class="text-xs font-semibold uppercase text-indigo-500">{{ competency.kategorie_code }} · {{ competency.kategorie_label }}</p>
                                            <p class="mt-1 text-sm font-semibold text-gray-800">{{ competency.label }}</p>
                                            <p v-if="competency.selbsteinschaetzung_text" class="mt-1 text-xs text-gray-500">{{ competency.selbsteinschaetzung_text }}</p>
                                        </div>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <input v-model="paKompetenzEntwuerfe[competency.id].label" class="w-full rounded border-gray-300 text-sm font-semibold" />
                                        <select v-model="paKompetenzEntwuerfe[competency.id].kategorie" class="w-full rounded border-gray-300 text-xs">
                                            <option v-for="category in paKategorieOptionen" :key="category.value" :value="category.value">{{ category.label }}</option>
                                        </select>
                                        <textarea v-model="paKompetenzEntwuerfe[competency.id].selbsteinschaetzung_text" rows="2" class="w-full rounded border-gray-300 text-xs" placeholder="Text für Selbsteinschätzung"></textarea>
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" class="text-xs font-semibold text-indigo-700 hover:underline" @click="updatePaKompetenz(competency)">Speichern</button>
                                            <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyPaKompetenz(competency)">Entfernen</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="paProfilBearbeitbar" class="rounded border border-indigo-100 bg-white p-3">
                                <h4 class="text-sm font-semibold text-gray-800">Freie Kompetenz hinzufügen</h4>
                                <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                    <label class="text-xs text-gray-600">Technischer Schlüssel
                                        <input v-model="paKompetenzForm.key" placeholder="z. B. lernbereitschaft" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                    <label class="text-xs text-gray-600">Bezeichnung
                                        <input v-model="paKompetenzForm.label" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                    <label class="text-xs text-gray-600">Kategorie
                                        <select v-model="paKompetenzForm.kategorie" class="mt-1 w-full rounded border-gray-300 text-sm" @change="setPaKategorie">
                                            <option v-for="category in paKategorieOptionen" :key="category.value" :value="category.value">{{ category.label }}</option>
                                        </select>
                                    </label>
                                    <label class="text-xs text-gray-600 xl:col-span-2">Text für Selbsteinschätzung
                                        <input v-model="paKompetenzForm.selbsteinschaetzung_text" placeholder="Ich kann ..." class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="rounded bg-indigo-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="savingPa || !paKompetenzForm.key || !paKompetenzForm.label" @click="storePaKompetenz">Kompetenz hinzufügen</button>
                                </div>
                            </div>

                            <div v-if="paProfilBearbeitbar" class="rounded border border-indigo-100 bg-white p-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Projektbezogene Berichtsdarstellung</h4>
                                        <p class="mt-1 text-xs text-gray-500">Die Einstellung wird mit der Profilversion veröffentlicht.</p>
                                    </div>
                                    <button type="button" class="rounded bg-indigo-700 px-3 py-2 text-sm text-white disabled:opacity-50" :disabled="savingPa" @click="savePaBerichtConfig">Berichtseinstellungen speichern</button>
                                </div>
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <label class="text-xs text-gray-600">Titel
                                        <input v-model="paBerichtConfigForm.titel" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                    <label class="text-xs text-gray-600">Untertitel
                                        <input v-model="paBerichtConfigForm.untertitel" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                </div>
                                <div class="mt-3 rounded border border-gray-200 bg-gray-50 p-3">
                                    <div class="flex flex-wrap items-center gap-4">
                                        <div class="flex h-24 w-32 items-center justify-center rounded border border-gray-200 bg-white p-2">
                                            <img v-if="paBerichtLogoPreview" :src="paBerichtLogoPreview" alt="Vorschau Berichtslogo" class="max-h-20 max-w-28 object-contain" />
                                            <span v-else class="text-xs text-gray-400">Kein Logo</span>
                                        </div>
                                        <div class="min-w-[240px] flex-1 space-y-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800">Logo im Potenzialanalyse-Bericht</p>
                                                <p class="text-xs text-gray-500">PNG, JPG oder WebP, maximal 2 MB. Die Einstellung gilt nur für dieses Profil.</p>
                                            </div>
                                            <input ref="paBerichtLogoInput" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-indigo-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-800" @change="selectPaBerichtLogo" />
                                            <div class="flex flex-wrap items-center gap-4">
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input v-model="paBerichtConfigForm.logo_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb" />
                                                    Logo anzeigen
                                                </label>
                                                <button v-if="paBerichtLogoPreview || paBerichtLogoFile" type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="removePaBerichtLogo">Logo entfernen</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-700">
                                    <label class="flex items-center gap-2"><input v-model="paBerichtConfigForm.uebungsergebnisse_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb" /> Übungsergebnisse anzeigen</label>
                                    <label class="flex items-center gap-2"><input v-model="paBerichtConfigForm.selbsteinschaetzung_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb" /> Selbsteinschätzung anzeigen</label>
                                    <label class="flex items-center gap-2"><input v-model="paBerichtConfigForm.staerkenprofil_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb" /> Stärkenprofil anzeigen</label>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-3">
                            <div>
                                <h3 class="font-semibold text-indigo-950">Skalierbares Projektprofil anlegen</h3>
                                <p class="mt-1 text-xs text-indigo-800">Beginnen Sie leer oder kopieren Sie die bearbeitbare hamet-e+-Startvorlage. Bestehende BOP-Projekte bleiben ohne Auswahl unverändert.</p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-[1fr_260px_auto]">
                                <input v-model="paProfilForm.name" class="rounded border-indigo-200 text-sm" placeholder="Profilname" />
                                <select v-model="paProfilForm.vorlage" class="rounded border-indigo-200 text-sm">
                                    <option value="leer">Leeres Profil</option>
                                    <option value="hamet_eplus">hamet e+ als Startvorlage</option>
                                </select>
                                <button type="button" class="rounded bg-indigo-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="savingPa" @click="createPaProfil">Profil anlegen</button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded border border-blue-200 bg-blue-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-blue-900">Berechnung und Berichtstext</h3>
                                <p class="mt-1 text-xs text-blue-700">Die Werte erzeugen einen prüfbaren Vorschlag. Sie ersetzen nicht die fachliche Einschätzung.</p>
                            </div>
                            <button type="button" class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-60" :disabled="savingPa || !paProfilBearbeitbar" @click="savePaAuswertungConfig">Einstellungen speichern</button>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Grenzen der Stufen (Prozent)</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label v-for="stufe in [2, 3, 4, 5]" :key="stufe" class="text-xs text-gray-600">
                                        Stufe {{ stufe }} ab
                                        <input v-model.number="paAuswertungConfig.thresholds[`rating_${stufe}_from`]" type="number" min="0" max="100" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                    </label>
                                </div>
                            </div>
                            <div class="rounded border border-green-200 bg-green-50 p-3">
                                <p class="text-xs font-semibold uppercase text-green-800">Berechnungsgrundlage</p>
                                <p class="mt-2 text-xs leading-relaxed text-green-700">
                                    Prozentwert und Stufe werden ausschließlich aus den ausgefüllten Übungen berechnet.
                                    Nicht gemachte Übungen zählen nicht als null. Anleiter- und Selbsteinschätzung bleiben
                                    als zusätzliche Dokumentation erhalten, verändern das Ergebnis aber nicht.
                                </p>
                            </div>
                            <label class="text-xs text-gray-600">Standard-Berichtsstil
                                <select v-model="paAuswertungConfig.report_style" class="mt-1 w-full rounded border-gray-300 text-sm">
                                    <option v-for="stil in paReportStyles" :key="stil.value" :value="stil.value">{{ stil.label }}</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="rounded border border-gray-200 bg-white">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 p-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">Gewichtungsmatrix</h3>
                                <p class="mt-1 text-xs text-gray-500">
                                    Tragen Sie ein, zu wie viel Prozent eine Übung in die jeweilige Kompetenz einfließt. Leere Felder werden nicht gewertet.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="savingPa || !paUebungen.length || !paProfilBearbeitbar"
                                @click="savePaGewichtungsmatrix"
                            >
                                {{ savingPa ? 'Speichert …' : 'Matrix speichern' }}
                            </button>
                        </div>

                        <div v-if="paUebungen.length" class="overflow-x-auto">
                            <table class="min-w-max border-separate border-spacing-0 text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-xs text-gray-600">
                                        <th class="sticky left-0 z-20 min-w-56 border-b border-r border-gray-200 bg-gray-50 px-3 py-3 text-left font-semibold">
                                            Übung
                                        </th>
                                        <th
                                            v-for="kompetenz in paKompetenzen"
                                            :key="kompetenz.key"
                                            class="min-w-36 border-b border-r border-gray-200 px-3 py-3 text-center font-semibold"
                                        >
                                            {{ kompetenz.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="uebung in paUebungen"
                                        :key="uebung.id"
                                        :class="uebung.aktiv ? 'bg-white' : 'bg-gray-50 text-gray-400'"
                                    >
                                        <th
                                            class="sticky left-0 z-10 border-b border-r border-gray-200 px-3 py-2 text-left font-medium"
                                            :class="uebung.aktiv ? 'bg-white text-gray-800' : 'bg-gray-50 text-gray-400'"
                                        >
                                            <span class="block" :class="uebung.auswertung_hervorheben ? 'font-bold' : ''">{{ uebung.name }}</span>
                                            <span class="mt-0.5 block text-xs font-normal text-gray-400">
                                                {{ uebung.tag ? `Tag ${uebung.tag}` : 'Ohne PA-Tag' }}{{ uebung.aktiv ? '' : ' · Inaktiv' }}
                                            </span>
                                        </th>
                                        <td
                                            v-for="kompetenz in paKompetenzen"
                                            :key="kompetenz.key"
                                            class="border-b border-r border-gray-200 p-2 text-center"
                                        >
                                            <div class="relative mx-auto w-24">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    :value="paMatrixCellValue(uebung, kompetenz.key)"
                                                    :disabled="!uebung.aktiv || savingPa || !paProfilBearbeitbar"
                                                    placeholder="–"
                                                    class="w-full rounded border-gray-300 py-1.5 pr-7 text-right text-sm disabled:bg-gray-100"
                                                    :aria-label="`${uebung.name}: ${kompetenz.label}`"
                                                    @input="updatePaMatrixCell(uebung, kompetenz.key, $event.target.value)"
                                                />
                                                <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="font-semibold">
                                        <th class="sticky left-0 z-20 border-r border-gray-200 bg-gray-100 px-3 py-3 text-left text-gray-700">
                                            Summe
                                        </th>
                                        <td
                                            v-for="kompetenz in paKompetenzen"
                                            :key="kompetenz.key"
                                            class="border-r border-gray-200 px-3 py-3 text-center"
                                            :class="paMatrixTotalClass(kompetenz.key)"
                                        >
                                            {{ Number(paMatrixTotals[kompetenz.key].toFixed(2)) }} %
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div v-else class="p-6 text-center text-sm text-gray-500">
                            Legen Sie zuerst mindestens eine Übung an, um die Gewichtungsmatrix zu bearbeiten.
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>100 % – vollständig</span>
                            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>Abweichung – bitte prüfen</span>
                            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-gray-400"></span>0 % – Kompetenz wird nicht verwendet</span>
                        </div>
                    </div>

                    <div class="rounded border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Übung anlegen</h3>
                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                            <label class="text-sm text-gray-600">
                                Name
                                <input v-model="paUebungForm.name" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                PA-Tag
                                <input
                                    v-model.number="paUebungForm.tag"
                                    type="number"
                                    min="1"
                                    :max="projekt.potenzialanalyse_tage || 60"
                                    class="mt-1 w-full rounded border-gray-300 text-sm"
                                />
                            </label>
                            <label class="text-sm text-gray-600">Ergebnistyp
                                <select v-model="paUebungForm.ergebnis_typ" :disabled="paUebungForm.berechnungsregel === 'zeit'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100">
                                    <option value="punkte">Punkte</option><option value="prozent">Prozent</option><option value="skala">Skala</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-600">Berechnungsregel
                                <select v-model="paUebungForm.berechnungsregel" class="mt-1 w-full rounded border-gray-300 text-sm">
                                    <option value="direkte_punkte">Direkte Punkte</option>
                                    <option value="fehler_abzug">Maximalpunkte minus Fehler</option>
                                    <option value="zeit">Zeit / Grenzwerte</option>
                                    <option value="beobachtung">Nur Beobachtung</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-600">Abzug je Fehler
                                <input v-model.number="paUebungForm.fehler_abzug" type="number" min="0" step="0.01" :disabled="paUebungForm.berechnungsregel !== 'fehler_abzug'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">Mindestwert
                                <input v-model.number="paUebungForm.mindestwert" type="number" min="0" :disabled="paUebungForm.berechnungsregel === 'zeit'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">Höchstwert
                                <input v-model.number="paUebungForm.hoechstwert" type="number" min="1" :disabled="paUebungForm.ergebnis_typ === 'prozent' || paUebungForm.berechnungsregel === 'zeit'" :placeholder="paUebungForm.berechnungsregel === 'zeit' ? 'automatisch 5' : (paUebungForm.ergebnis_typ === 'prozent' ? '100' : '')" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">
                                Reihenfolge
                                <input v-model.number="paUebungForm.sort_order" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                        </div>
                        <label class="mt-3 block text-sm text-gray-600">
                            Beschreibung
                            <textarea v-model="paUebungForm.beschreibung" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        </label>
                        <div v-if="paUebungForm.berechnungsregel === 'zeit'" class="mt-3 rounded border border-blue-100 bg-blue-50 p-3">
                            <p class="text-sm font-semibold text-blue-800">Individuelle Zeitgrenzen</p>
                            <p class="mt-1 text-xs text-blue-700">Format Minuten:Sekunden, zum Beispiel 3:30. Längere Zeiten als die Grenze für Stufe 2 ergeben Stufe 1.</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label v-for="stufe in paZeitstufen" :key="stufe.key" class="text-xs text-gray-600">
                                    {{ stufe.label }}
                                    <input v-model="paUebungForm.zeitgrenzen[stufe.key]" type="text" inputmode="numeric" placeholder="0:00" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                </label>
                            </div>
                        </div>
                        <p class="mt-3 rounded border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                            Die Kompetenz-Zuordnung erfolgt nach dem Anlegen zentral in der Gewichtungsmatrix.
                        </p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="paUebungForm.auswertbar" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Auswertbar
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="paUebungForm.auswertung_hervorheben" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Pflichtauswertung
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="paUebungForm.im_bericht_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Im Bericht anzeigen
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input
                                    type="checkbox"
                                    :checked="paUebungForm.berechnungsregel === 'zeit' || paUebungForm.zeit_erfassen"
                                    :disabled="paUebungForm.berechnungsregel === 'zeit'"
                                    class="rounded border-gray-300 text-zbb focus:ring-zbb disabled:bg-gray-100"
                                    @change="paUebungForm.zeit_erfassen = $event.target.checked"
                                />
                                Zeit erfassen
                            </label>
                            <button
                                type="button"
                                class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-60"
                                :disabled="savingPa || !paProfilBearbeitbar"
                                @click="storeUebung"
                            >
                                Übung speichern
                            </button>
                        </div>
                    </div>

                    <div v-if="!paUebungen.length" class="rounded border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
                        Noch keine Übungen angelegt.
                    </div>

                    <div
                        v-for="uebung in paUebungen"
                        :key="uebung.id"
                        class="rounded border border-gray-200 p-4"
                    >
                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                            <label class="text-sm text-gray-600">
                                Uebung
                                <input v-model="uebung.name" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="text-sm text-gray-600">
                                PA-Tag
                                <input
                                    v-model.number="uebung.tag"
                                    type="number"
                                    min="1"
                                    :max="projekt.potenzialanalyse_tage || 60"
                                    class="mt-1 w-full rounded border-gray-300 text-sm"
                                />
                            </label>
                            <label class="text-sm text-gray-600">Ergebnistyp
                                <select v-model="uebung.ergebnis_typ" :disabled="uebung.berechnungsregel === 'zeit'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100">
                                    <option value="punkte">Punkte</option><option value="prozent">Prozent</option><option value="skala">Skala</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-600">Berechnungsregel
                                <select v-model="uebung.berechnungsregel" class="mt-1 w-full rounded border-gray-300 text-sm">
                                    <option value="direkte_punkte">Direkte Punkte</option>
                                    <option value="fehler_abzug">Maximalpunkte minus Fehler</option>
                                    <option value="zeit">Zeit / Grenzwerte</option>
                                    <option value="beobachtung">Nur Beobachtung</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-600">Abzug je Fehler
                                <input v-model.number="uebung.fehler_abzug" type="number" min="0" step="0.01" :disabled="uebung.berechnungsregel !== 'fehler_abzug'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">Mindestwert
                                <input v-model.number="uebung.mindestwert" type="number" min="0" :disabled="uebung.berechnungsregel === 'zeit'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">Höchstwert
                                <input v-model.number="uebung.hoechstwert" type="number" min="1" :disabled="uebung.ergebnis_typ === 'prozent' || uebung.berechnungsregel === 'zeit'" class="mt-1 w-full rounded border-gray-300 text-sm disabled:bg-gray-100" />
                            </label>
                            <label class="text-sm text-gray-600">
                                Reihenfolge
                                <input v-model.number="uebung.sort_order" type="number" min="0" class="mt-1 w-full rounded border-gray-300 text-sm" />
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.auswertbar" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Auswertbar
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.auswertung_hervorheben" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Pflichtauswertung
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.im_bericht_anzeigen" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Im Bericht anzeigen
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input
                                    type="checkbox"
                                    :checked="uebung.berechnungsregel === 'zeit' || uebung.zeit_erfassen"
                                    :disabled="uebung.berechnungsregel === 'zeit'"
                                    class="rounded border-gray-300 text-zbb focus:ring-zbb disabled:bg-gray-100"
                                    @change="uebung.zeit_erfassen = $event.target.checked"
                                />
                                Zeit erfassen
                            </label>
                            <label class="mt-6 flex items-center gap-2 text-sm text-gray-600">
                                <input v-model="uebung.aktiv" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                                Aktiv
                            </label>
                        </div>
                        <label class="mt-3 block text-sm text-gray-600">
                            Beschreibung
                            <textarea v-model="uebung.beschreibung" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        </label>
                        <div v-if="uebung.berechnungsregel === 'zeit'" class="mt-3 rounded border border-blue-100 bg-blue-50 p-3">
                            <p class="text-sm font-semibold text-blue-800">Individuelle Zeitgrenzen</p>
                            <p class="mt-1 text-xs text-blue-700">Format Minuten:Sekunden. Die Grenzen gelten ausschließlich für diese Übung.</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label v-for="stufe in paZeitstufen" :key="`${uebung.id}-${stufe.key}`" class="text-xs text-gray-600">
                                    {{ stufe.label }}
                                    <input v-model="uebung.zeitgrenzen[stufe.key]" type="text" inputmode="numeric" placeholder="0:00" class="mt-1 w-full rounded border-gray-300 text-sm" />
                                </label>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">
                            Kompetenz-Zuordnungen und Gewichtungen werden zentral in der Matrix oben gepflegt.
                        </p>
                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="rounded border border-zbb px-3 py-2 text-sm text-zbb disabled:opacity-60"
                                :disabled="savingPa || !paProfilBearbeitbar"
                                @click="updateUebung(uebung)"
                            >
                                Übung aktualisieren
                            </button>
                            <button
                                type="button"
                                class="rounded border border-red-200 px-3 py-2 text-sm text-red-600 disabled:opacity-60"
                                :disabled="savingPa || !paProfilBearbeitbar"
                                @click="destroyUebung(uebung)"
                            >
                                Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'overview'" class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Zeitraume</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Antrag</th>
                                <th class="px-3 py-2">Starttermin</th>
                                <th class="px-3 py-2">Anfang</th>
                                <th class="px-3 py-2">Endtermin</th>
                                <th class="px-3 py-2">Ende</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="zeitraum in projekt.zeitraume" :key="zeitraum.id" class="border-b">
                                <td class="px-3 py-2">{{ formatDate(zeitraum.antragsdatum) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.starttermin) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.anfangsdatum) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.endtermin) }}</td>
                                <td class="px-3 py-2">{{ formatDate(zeitraum.enddatum) }}</td>
                            </tr>
                            <tr v-if="!projekt.zeitraume?.length">
                                <td colspan="5" class="px-3 py-3 text-gray-500">Keine Zeitraume hinterlegt.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'staff'" class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Mitarbeiter im Projekt</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">E-Mail</th>
                                <th class="px-3 py-2">Rollen</th>
                                <th class="px-3 py-2">Standorte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="person in zugewieseneMitarbeiter" :key="person.id" class="border-b">
                                <td class="px-3 py-2 font-medium">{{ person.vorname }} {{ person.nachname }}</td>
                                <td class="px-3 py-2">{{ person.user?.email || '-' }}</td>
                                <td class="px-3 py-2">{{ roleNames(person) }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        v-for="standort in person.standorte"
                                        :key="standort.id"
                                        class="mr-1 inline-block rounded bg-gray-100 px-2 py-1 text-xs"
                                    >
                                        {{ standort.name }}
                                    </span>
                                    <span v-if="!person.standorte.length">-</span>
                                </td>
                            </tr>
                            <tr v-if="!zugewieseneMitarbeiter.length">
                                <td colspan="4" class="px-3 py-3 text-gray-500">Noch keine Mitarbeiter zugewiesen.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="activeAdministrationTab === 'staff'" class="bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">Fehlende Mitarbeiter hinzufugen</h2>
                <div class="space-y-3">
                    <div
                        v-for="person in fehlendeMitarbeiterListe"
                        :key="person.id"
                        class="grid gap-3 border-b pb-3 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <div>
                            <p class="font-medium">{{ person.vorname }} {{ person.nachname }}</p>
                            <p class="text-sm text-gray-500">{{ person.user?.email || '-' }}</p>
                        </div>
                        <MultiSelect
                            v-model="selectedStandorte[person.id]"
                            :options="alleStandorte"
                            optionLabel="name"
                            optionValue="id"
                            display="chip"
                            filter
                            placeholder="Standorte auswahlen"
                            class="w-full"
                        />
                        <button
                            type="button"
                            @click="addMitarbeiter(person)"
                            class="self-start rounded bg-zbb px-4 py-2 text-sm text-white"
                        >
                            Hinzufugen
                        </button>
                    </div>
                    <p v-if="!fehlendeMitarbeiterListe.length" class="text-sm text-gray-500">
                        Alle aktiven Mitarbeiter sind diesem Projekt bereits zugewiesen.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

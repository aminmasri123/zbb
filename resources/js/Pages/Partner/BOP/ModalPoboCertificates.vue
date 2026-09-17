<script setup>
import Modal from '@/Components/ModalForm.vue';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import { usePermissions } from '@/utils/permissions';

const props = defineProps({
    partnerId: Number,
    schuljahr: [String, Number],
    teil: [String, Number],
    schoolName: String,
});
const emit = defineEmits(['close']);

const loading = ref(true);
const error = ref('');
const preview = ref(null);
const period = reactive({ from: '', to: '' });
const calibrationOpen = ref(false);
const calibrationSaving = ref(false);
const calibrationMessage = ref('');
const calibrationError = ref('');
const calibration = reactive({
    horizontal_offset_mm: 0,
    vertical_offset_mm: 0,
    row_spacing_offset_mm: 0,
    cross_font_size_pt: 13,
});
const { can } = usePermissions();
const canConfigurePrint = computed(() => can('dokumente.update'));
const areaLabels = [
    'Hauswirtschaft',
    'Metalltechnik',
    'Holztechnik',
    'IT-Mediengestaltung',
    'Verkauf',
    'Friseure/Kosmetik/Körperpflege',
    'Elektro',
    'Farbe und Raumgestaltung',
];

const periodIsValid = computed(() => period.from && period.to && period.to >= period.from);
const canExport = computed(() => periodIsValid.value && (preview.value?.eligible_participants ?? 0) > 0);
const markerPreviewGridStyle = computed(() => ({
    rowGap: `${Math.max(0, 1 + (Number(calibration.row_spacing_offset_mm) || 0) * 3)}px`,
}));
const markerPreviewCrossStyle = computed(() => ({
    transform: `translate(${Number(calibration.horizontal_offset_mm) || 0}mm, ${Number(calibration.vertical_offset_mm) || 0}mm)`,
    fontSize: `${Number(calibration.cross_font_size_pt) || 13}pt`,
}));

function applyPrintSettings(settings = {}) {
    calibration.horizontal_offset_mm = Number(settings.horizontal_offset_mm ?? 0);
    calibration.vertical_offset_mm = Number(settings.vertical_offset_mm ?? 0);
    calibration.row_spacing_offset_mm = Number(settings.row_spacing_offset_mm ?? 0);
    calibration.cross_font_size_pt = Number(settings.cross_font_size_pt ?? 13);
}

async function loadPreview() {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get(route('export.zertifikat.schule.pobo.preview', {
            idSchule: props.partnerId,
            schuljahr: props.schuljahr,
            teil: props.teil,
        }));
        preview.value = response.data;
        period.from = response.data.period?.from ?? '';
        period.to = response.data.period?.to ?? '';
        applyPrintSettings(response.data.print_settings);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Die Zertifikatsübersicht konnte nicht geladen werden.';
    } finally {
        loading.value = false;
    }
}

async function saveCalibration() {
    calibrationSaving.value = true;
    calibrationMessage.value = '';
    calibrationError.value = '';
    try {
        const response = await axios.put(route('export.zertifikat.schule.pobo.settings.update'), calibration);
        applyPrintSettings(response.data.print_settings);
        calibrationMessage.value = response.data.message || 'Druckposition gespeichert.';
    } catch (exception) {
        calibrationError.value = exception.response?.data?.message
            || Object.values(exception.response?.data?.errors || {})[0]?.[0]
            || 'Die Druckposition konnte nicht gespeichert werden.';
    } finally {
        calibrationSaving.value = false;
    }
}

function resetCalibration() {
    applyPrintSettings();
    saveCalibration();
}

onMounted(loadPreview);

function exportCertificates(format) {
    if (!canExport.value) return;
    const routeName = format === 'pdf'
        ? 'export.zertifikat.schule.pobo.pdf'
        : 'export.zertifikat.schule.pobo';
    const routeParameters = format === 'pdf'
        ? { schuleId: props.partnerId, schuljahr: props.schuljahr, teil: props.teil }
        : { idSchule: props.partnerId, schuljahr: props.schuljahr, teil: props.teil };
    const url = new URL(route(routeName, routeParameters), window.location.origin);
    url.searchParams.set('von', period.from);
    url.searchParams.set('bis', period.to);

    const link = document.createElement('a');
    link.href = url.toString();
    document.body.appendChild(link);
    link.click();
    link.remove();
    emit('close');
}
</script>

<template>
    <Modal scrollable-layout wide @close="emit('close')">
        <template #header>
            <div>
                <h2 class="text-lg font-bold text-gray-900">POBO-Zertifikate prüfen und exportieren</h2>
                <p class="mt-1 text-sm text-gray-600">{{ schoolName }} · Schuljahr {{ schuljahr }} · {{ teil }}</p>
            </div>
        </template>

        <template #body>
            <div v-if="loading" class="flex min-h-48 items-center justify-center text-sm text-gray-600">
                <i class="la la-spinner la-spin mr-2 text-xl text-zbb"></i>
                Zeitraum und Teilnehmer werden geladen …
            </div>

            <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>{{ error }}</p>
                <button type="button" class="mt-3 rounded border border-red-300 bg-white px-3 py-1.5 font-semibold" @click="loadPreview">Erneut laden</button>
            </div>

            <div v-else class="space-y-5">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-start gap-3">
                        <i class="la la-calendar-check mt-0.5 text-xl text-blue-700"></i>
                        <div>
                            <p class="font-semibold text-blue-950">Ein gemeinsamer Zeitraum für alle Zertifikate</p>
                            <p class="mt-1 text-sm text-blue-900">Vorschlag: {{ preview.period.source_label }}. Sie können beide Daten vor dem Export ändern.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="pobo-certificate-from" class="mb-1 block text-sm font-semibold text-gray-700">Von · Rolltag</label>
                        <input id="pobo-certificate-from" v-model="period.from" type="date" :max="period.to || undefined" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label for="pobo-certificate-to" class="mb-1 block text-sm font-semibold text-gray-700">Bis · letzter Werkstatttag</label>
                        <input id="pobo-certificate-to" v-model="period.to" type="date" :min="period.from || undefined" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                </div>
                <p v-if="!periodIsValid" class="text-sm font-medium text-red-600">Bitte einen gültigen Zeitraum auswählen. Das Bis-Datum darf nicht vor dem Von-Datum liegen.</p>

                <div v-if="canConfigurePrint" class="rounded-lg border border-gray-200 bg-gray-50">
                    <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left" @click="calibrationOpen = !calibrationOpen">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Druckposition kalibrieren</span>
                            <span class="mt-0.5 block text-xs text-gray-600">Gilt zentral für Word und PDF.</span>
                        </span>
                        <i class="la text-lg" :class="calibrationOpen ? 'la-angle-up' : 'la-angle-down'"></i>
                    </button>
                    <div v-if="calibrationOpen" class="border-t border-gray-200 p-4">
                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.8fr)]">
                            <div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="text-sm font-medium text-gray-700">
                                        Horizontal in mm
                                        <input v-model.number="calibration.horizontal_offset_mm" type="number" min="-20" max="20" step="0.5" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                        <span class="mt-1 block text-xs font-normal text-gray-500">Plus = nach rechts, Minus = nach links</span>
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">
                                        Vertikal in mm
                                        <input v-model.number="calibration.vertical_offset_mm" type="number" min="-20" max="20" step="0.5" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                        <span class="mt-1 block text-xs font-normal text-gray-500">Plus = nach unten, Minus = nach oben</span>
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">
                                        Zusätzlicher Zeilenabstand in mm
                                        <input v-model.number="calibration.row_spacing_offset_mm" type="number" min="0" max="5" step="0.25" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">
                                        Kreuzgröße in Punkt
                                        <input v-model.number="calibration.cross_font_size_pt" type="number" min="8" max="20" step="0.5" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                    </label>
                                </div>
                                <p class="mt-3 text-xs text-gray-600">Am besten zuerst ein einzelnes Word-Dokument auf Normalpapier testen und über das Originalformular halten. Im Druckdialog muss die Skalierung 100 % betragen.</p>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button type="button" :disabled="calibrationSaving" class="rounded bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="saveCalibration">
                                        {{ calibrationSaving ? 'Speichert …' : 'Kalibrierung speichern' }}
                                    </button>
                                    <button type="button" :disabled="calibrationSaving" class="rounded border px-4 py-2 text-sm" @click="resetCalibration">Standardwerte</button>
                                </div>
                                <p v-if="calibrationMessage" class="mt-2 text-sm font-medium text-green-700">{{ calibrationMessage }}</p>
                                <p v-if="calibrationError" class="mt-2 text-sm font-medium text-red-700">{{ calibrationError }}</p>
                            </div>
                            <div class="overflow-hidden rounded-lg border bg-white p-4">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Schematische Vorschau</p>
                                <div class="grid text-xs text-gray-700" :style="markerPreviewGridStyle">
                                    <div v-for="label in areaLabels" :key="label" class="grid grid-cols-[1fr_24px] items-center gap-2 border-b border-dotted border-gray-300 py-1 last:border-0">
                                        <span>{{ label }}</span>
                                        <span class="flex h-5 w-5 items-center justify-center bg-gray-200">
                                            <span class="font-bold leading-none text-blue-700" :style="markerPreviewCrossStyle">X</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border bg-gray-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Teilnehmer</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ preview.total_participants }}</p>
                    </div>
                    <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-green-700">Werden exportiert</p>
                        <p class="mt-1 text-2xl font-bold text-green-800">{{ preview.eligible_participants }}</p>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-amber-700">Noch nicht ausreichend</p>
                        <p class="mt-1 text-2xl font-bold text-amber-800">{{ preview.excluded_participants }}</p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Übersicht der zu erstellenden Dokumente</h3>
                    <div v-if="preview.participants.length" class="max-h-64 overflow-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2">Teilnehmer</th>
                                    <th class="px-3 py-2">Klasse</th>
                                    <th class="px-3 py-2">Anwesenheit</th>
                                    <th class="px-3 py-2">Dokument</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="participant in preview.participants" :key="participant.id">
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ participant.name }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ participant.class || '–' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ participant.attendance_days }} Tage</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full bg-zbb/10 px-2 py-1 text-xs font-semibold text-zbb">{{ participant.document_label }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Aktuell erfüllt noch kein Teilnehmer die erforderlichen Anwesenheitstage.</p>
                </div>
            </div>
        </template>

        <template #footer>
            <button type="button" class="rounded border px-5 py-2 text-sm" @click="emit('close')">Abbrechen</button>
            <button type="button" class="rounded border border-zbb px-5 py-2 text-sm font-semibold text-zbb disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading || !canExport" @click="exportCertificates('word')">
                <i class="la la-file-word mr-1"></i> Word exportieren
            </button>
            <button type="button" class="rounded bg-zbb px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading || !canExport" @click="exportCertificates('pdf')">
                <i class="la la-file-pdf mr-1"></i> PDF exportieren
            </button>
        </template>
    </Modal>
</template>

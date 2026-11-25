<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    packages: { type: Array, required: true },
    canEdit: Boolean,
    canViewDocuments: Boolean,
    initialEditId: String,
});
const emit = defineEmits(['close', 'updated']);
const dialog = ref(null);
const schools = ref([]);
const selected = ref(null);
const editing = ref(null);
const schoolId = ref('');
const year = ref('');
const part = ref('');
const sort = ref('klasse');
const date = ref('');
const plannedDates = ref([]);
const loadingPlanning = ref(false);
const planningReady = ref(false);
const needsDate = ref(false);
const hasPlannedGroups = ref(false);
const participantCount = ref(0);
const participantCountsByDate = ref({});
const rolltagMode = ref('classes');
const roomCount = ref(1);
const roomNames = ref(['Raum 1']);
const roomCapacities = ref(['']);
let planningRequest = 0;
const busy = ref(false);
const loadingSchools = ref(false);
const error = ref('');
const documentToAdd = ref('');
const form = ref({ name: '', documents: [] });
const activePackage = computed(() => props.packages.find(item => item.id === selected.value));
const editPackage = computed(() => props.packages.find(item => item.id === editing.value));
const school = computed(() => schools.value.find(item => String(item.id) === schoolId.value));
const years = computed(() => Object.keys(school.value?.years ?? {}).sort().reverse());
const parts = computed(() => [...(school.value?.years?.[year.value] ?? [])]
    .sort((a, b) => a.localeCompare(b, 'de', { numeric: true })));
const allDates = computed(() => selected.value === 'vorbereitung-pa');
const isRolltag = computed(() => selected.value === 'rolltag');
const selectedParticipantCount = computed(() => participantCountsByDate.value[date.value] ?? participantCount.value);
const roomCapacity = computed(() => roomCapacities.value.reduce((sum, value) => sum + (Number(value) || 0), 0));
const missingRoomCapacity = computed(() => Math.max(selectedParticipantCount.value - roomCapacity.value, 0));
const roomsValid = computed(() => {
    if (!isRolltag.value || hasPlannedGroups.value || rolltagMode.value !== 'rooms') return true;
    const names = roomNames.value.map(name => name.trim().toLowerCase());
    return roomCount.value >= 1 && roomCount.value <= 50
        && names.length === roomCount.value && names.every(Boolean) && new Set(names).size === names.length
        && roomCapacities.value.length === roomCount.value
        && roomCapacities.value.every(capacity => Number.isInteger(Number(capacity)) && Number(capacity) > 0)
        && missingRoomCapacity.value === 0;
});

onMounted(() => {
    dialog.value.showModal();
    const initial = props.packages.find(item => item.id === props.initialEditId);
    if (props.canEdit && initial) beginEdit(initial);
});
watch(schoolId, () => {
    year.value = years.value.length === 1 ? years.value[0] : '';
    part.value = parts.value.length === 1 ? parts.value[0] : '';
});
watch(year, () => { part.value = parts.value.length === 1 ? parts.value[0] : ''; });
watch([selected, schoolId, year, part], async () => {
    const sequence = ++planningRequest;
    date.value = '';
    plannedDates.value = [];
    hasPlannedGroups.value = false;
    participantCount.value = 0;
    participantCountsByDate.value = {};
    rolltagMode.value = 'classes';
    roomCount.value = 1;
    roomNames.value = ['Raum 1'];
    roomCapacities.value = [''];
    planningReady.value = false;
    loadingPlanning.value = false;
    error.value = '';
    if (!selected.value || !schoolId.value || !year.value || !part.value) return;
    loadingPlanning.value = true;
    try {
        const { data } = await axios.post(route('partner.bop.packages.download', {
            partner: schoolId.value, package: selected.value,
        }), { schuljahr: year.value, teil: part.value, preview: true });
        if (sequence !== planningRequest) return;
        plannedDates.value = data.dates;
        needsDate.value = data.needsDate;
        hasPlannedGroups.value = Boolean(data.hasPlannedGroups);
        participantCount.value = Number(data.participantCount) || 0;
        participantCountsByDate.value = data.participantCountsByDate || {};
        if (data.dates.length === 1 || allDates.value) date.value = data.dates[0] ?? '';
        planningReady.value = true;
    } catch {
        if (sequence === planningRequest) error.value = 'Die Planung konnte nicht geprüft werden. Bitte die Auswahl erneut treffen.';
    } finally {
        if (sequence === planningRequest) loadingPlanning.value = false;
    }
});

watch(roomCount, value => {
    const count = Math.min(50, Math.max(1, Math.floor(Number(value)) || 1));
    roomNames.value = Array.from({ length: count }, (_, index) => roomNames.value[index] ?? `Raum ${index + 1}`);
    roomCapacities.value = Array.from({ length: count }, (_, index) => roomCapacities.value[index] ?? '');
});

async function configure(item) {
    selected.value = item.id;
    error.value = '';
    if (schools.value.length) return;
    loadingSchools.value = true;
    try {
        const { data } = await axios.get(route('partner.bop.packages.schools'));
        schools.value = data.schools;
    } catch {
        error.value = 'Die Schulen konnten nicht geladen werden. Bitte versuche es erneut.';
    } finally {
        loadingSchools.value = false;
    }
}

function beginEdit(item) {
    documentToAdd.value = '';
    editing.value = item.id;
    form.value = { name: item.name, documents: [...item.documents] };
    error.value = '';
}

function back() {
    selected.value = null;
    editing.value = null;
    error.value = '';
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.put(route('partner.bop.packages.update', { package: editing.value }), form.value);
        emit('updated', data.packages);
        if (props.initialEditId) dialog.value.close();
        else back();
    } catch (exception) {
        error.value = Object.values(exception.response?.data?.errors ?? {}).flat()[0]
            ?? 'Die Änderungen konnten nicht gespeichert werden.';
    } finally {
        busy.value = false;
    }
}

async function download() {
    if (!activePackage.value || !school.value || !year.value || !part.value || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const payload = { schuljahr: year.value, teil: part.value, sortierung: sort.value, termin: needsDate.value ? date.value : null };
        if (isRolltag.value && !hasPlannedGroups.value) {
            payload.rolltag_mode = rolltagMode.value;
            if (rolltagMode.value === 'rooms') {
                payload.rooms = roomNames.value.map((name, index) => ({
                    name: name.trim(), capacity: Number(roomCapacities.value[index]),
                }));
            }
        }
        const response = await axios.post(route('partner.bop.packages.download', {
            partner: schoolId.value,
            package: selected.value,
        }), payload, {
            responseType: 'blob',
            headers: { Accept: 'application/json, application/zip' },
        });

        if (!response.data.type.includes('zip')) throw new Error('Expected a ZIP export');
        const filename = response.headers['content-disposition']?.match(/filename="?([^";]+)"?/i)?.[1]
            ?? 'BOP-Dokumentenpaket.zip';
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        dialog.value.close();
    } catch (exception) {
        const body = exception.response?.data;
        let message;
        if (body instanceof Blob && body.type.includes('json') && [403, 404, 422].includes(exception.response.status)) {
            const details = await body.text().then(JSON.parse).catch(() => null);
            message = Object.values(details?.errors ?? {}).flat()[0] ?? details?.message;
        }
        error.value = message ?? 'Das Paket konnte nicht erstellt werden. Bitte versuche es erneut.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <dialog ref="dialog" aria-labelledby="bop-packages-title" class="w-[calc(100%-2rem)] max-w-2xl rounded-xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/50" @close="emit('close')" @cancel="busy && $event.preventDefault()">
        <header class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 sm:px-6">
            <div>
                <h2 id="bop-packages-title" class="text-lg font-semibold">{{ editPackage ? 'Paket bearbeiten' : (activePackage?.name ?? 'BOP-Dokumentenpakete') }}</h2>
                <p v-if="activePackage" class="mt-1 text-sm text-gray-600">{{ activePackage.documents.join(' · ') }}</p>
            </div>
            <button type="button" autofocus aria-label="Schließen" class="rounded p-1 text-gray-600 hover:bg-gray-100 disabled:opacity-50" :disabled="busy" @click="dialog.close()">
                <i class="la la-times text-xl" aria-hidden="true"></i>
            </button>
        </header>

        <ul v-if="!activePackage && !editPackage" class="space-y-3 p-5 sm:px-6">
            <li v-for="item in packages" :key="item.id" class="rounded-lg border border-gray-200 p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <button type="button" class="text-left text-base font-semibold text-zbb hover:underline" @click="configure(item)">{{ item.name }}</button>
                    <button v-if="canEdit" type="button" :aria-label="'Paket bearbeiten: ' + item.name" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50" @click="beginEdit(item)">Bearbeiten</button>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ item.documents.join(' · ') }}</p>
                <p v-if="item.unavailableReason" class="mt-2 text-sm text-amber-900">{{ item.unavailableReason }}</p>
            </li>
        </ul>

        <form v-else @submit.prevent="editPackage ? save() : download()">
            <fieldset v-if="editPackage" :disabled="busy" class="space-y-4 p-5 sm:px-6">
                <label class="block text-sm font-medium">
                    Paketname
                    <input v-model.trim="form.name" required maxlength="120" class="mt-1 block w-full rounded-md border-gray-300">
                </label>
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-medium">Enthaltene Dokumente</legend>
                    <label v-for="document in [...new Set([...editPackage.documents, ...form.documents])]" :key="document" class="flex items-start gap-2 text-sm">
                        <input v-model="form.documents" type="checkbox" :value="document" class="mt-0.5 rounded border-gray-300 text-zbb">
                        {{ document }}
                    </label>
                </fieldset>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="min-w-0 flex-1 text-sm font-medium">
                        Dokument hinzufügen
                        <select v-model="documentToAdd" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">Vorlage wählen</option>
                            <option v-for="document in editPackage.documentOptions.filter(item => !form.documents.includes(item))" :key="document" :value="document">
                                {{ document === 'Namensschilder' ? 'Namensschilder BOP' : document }}
                            </option>
                        </select>
                    </label>
                    <button type="button" :disabled="!documentToAdd" class="rounded-md border border-zbb px-3 py-2 text-sm font-medium text-zbb disabled:opacity-40"
                        @click="form.documents.push(documentToAdd); documentToAdd = ''">Hinzufügen</button>
                </div>
                <a v-if="canViewDocuments" :href="route('dokumente.index')" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-zbb underline underline-offset-2">
                    Dokumente öffnen
                    <i class="la la-external-link" aria-hidden="true"></i>
                    <span class="sr-only">(öffnet in einem neuen Tab)</span>
                </a>
            </fieldset>

            <fieldset v-else :disabled="busy || loadingSchools" class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">
                <label class="block text-sm font-medium sm:col-span-2">
                    Schule
                    <select v-model="schoolId" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="" disabled>{{ loadingSchools ? 'Schulen werden geladen …' : 'Schule wählen' }}</option>
                        <option v-for="item in schools" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Schuljahr
                    <select v-model="year" required :disabled="!school" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-50">
                        <option value="" disabled>Schuljahr wählen</option>
                        <option v-for="item in years" :key="item" :value="item">{{ item }}</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Teil
                    <select v-model="part" required :disabled="!year" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-50">
                        <option value="" disabled>Teil wählen</option>
                        <option v-for="item in parts" :key="item" :value="item">Teil {{ item.replace(/^Teil\s*/i, '') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Sortierung
                    <select v-model="sort" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="klasse">Nach Klasse</option>
                        <option value="nachname">Nach Nachname</option>
                    </select>
                </label>
                <div v-if="isRolltag && planningReady" class="space-y-3 rounded-lg border border-gray-200 p-4 sm:col-span-2">
                    <template v-if="hasPlannedGroups">
                        <p class="text-sm font-semibold text-gray-800">Geplante Gruppen werden verwendet</p>
                        <p class="text-xs text-gray-600">Das Paket enthält eine Anwesenheitsliste je geplanter Gruppe sowie eine Einteilungsübersicht.</p>
                    </template>
                    <template v-else>
                        <p class="text-sm font-semibold text-gray-800">Aufteilung der Rolltag-Anwesenheitslisten</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="rounded border p-3 text-sm" :class="rolltagMode === 'classes' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
                                <input v-model="rolltagMode" type="radio" value="classes" class="mr-2 text-zbb">
                                Eine Liste je Klasse
                            </label>
                            <label class="rounded border p-3 text-sm" :class="rolltagMode === 'rooms' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
                                <input v-model="rolltagMode" type="radio" value="rooms" class="mr-2 text-zbb">
                                Nach Räumen aufteilen
                            </label>
                        </div>
                        <div v-if="rolltagMode === 'rooms'" class="space-y-3">
                            <label class="block text-sm font-medium">
                                Anzahl Räume
                                <input v-model.number="roomCount" type="number" min="1" max="50" class="mt-1 block w-full rounded-md border-gray-300">
                            </label>
                            <div v-for="(_, index) in roomNames" :key="index" class="grid gap-2 sm:grid-cols-2">
                                <input v-model="roomNames[index]" required maxlength="120" class="rounded-md border-gray-300" :placeholder="`Raum ${index + 1}`">
                                <input v-model.number="roomCapacities[index]" required type="number" min="1" class="rounded-md border-gray-300" placeholder="Kapazität">
                            </div>
                            <div class="rounded bg-gray-50 p-3 text-sm">
                                <p>Teilnehmende: <strong>{{ selectedParticipantCount }}</strong> · Plätze: <strong>{{ roomCapacity }}</strong></p>
                                <p v-if="missingRoomCapacity" class="mt-1 text-red-700">Es fehlen {{ missingRoomCapacity }} Plätze.</p>
                                <p v-else class="mt-1 text-green-700">Die Kapazitäten reichen aus.</p>
                            </div>
                        </div>
                    </template>
                </div>
                <p v-if="loadingPlanning" class="text-sm text-gray-600 sm:col-span-2">Planung wird geprüft …</p>
                <p v-if="planningReady && plannedDates.length" class="text-sm text-gray-600 sm:col-span-2">Termine aus der BOP-Planung: {{ plannedDates.map(day => day.split('-').reverse().join('.')).join(', ') }}.</p>
                <label v-if="planningReady && needsDate && !plannedDates.length" class="block text-sm font-medium sm:col-span-2">
                    Kein Termin in der Planung – bitte Datum angeben
                    <input v-model="date" required type="date" class="mt-1 block w-full rounded-md border-gray-300">
                    <span class="mt-1 block text-xs text-gray-500">Gilt nur für diesen Export. Die Planung wird nicht geändert.</span>
                </label>
                <label v-if="planningReady && needsDate && plannedDates.length > 1 && !allDates" class="block text-sm font-medium">
                    Geplanten Termin wählen
                    <select v-model="date" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="" disabled>Termin wählen</option>
                        <option v-for="day in plannedDates" :key="day" :value="day">{{ day.split('-').reverse().join('.') }}</option>
                    </select>
                </label>
                <p v-if="planningReady && allDates && plannedDates.length" class="text-sm text-gray-600 sm:col-span-2">Die PA-Anwesenheitsliste verwendet die aktuelle Planung und ihre Klassenzuordnungen. Jeder Termin erhält ein eigenes Excel-Blatt.</p>
                <p v-if="!loadingSchools && !schools.length && !error" class="text-sm text-gray-600 sm:col-span-2">Im aktiven Projekt sind keine Schulen mit Teilnehmenden für den Export verfügbar.</p>
                <p v-if="activePackage.unavailableReason" class="text-sm text-amber-900 sm:col-span-2">{{ activePackage.unavailableReason }}</p>
            </fieldset>

            <p v-if="error" role="alert" class="border-t border-red-100 bg-red-50 px-5 py-3 text-sm text-red-800 sm:px-6">{{ error }}</p>
            <footer class="flex flex-wrap justify-between gap-2 border-t border-gray-200 px-5 py-4 sm:px-6">
                <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-50" :disabled="busy" @click="initialEditId ? dialog.close() : back()">{{ initialEditId ? 'Abbrechen' : 'Zur Paketliste' }}</button>
                <button v-if="editPackage" type="submit" class="rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !form.name || !form.documents.length">{{ busy ? 'Wird gespeichert …' : 'Speichern' }}</button>
                <button v-else type="submit" class="rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="busy || loadingSchools || loadingPlanning || !planningReady || !school || !year || !part || Boolean(activePackage.unavailableReason) || (needsDate && !date) || !roomsValid">
                    {{ busy ? 'Paket wird erstellt …' : 'ZIP herunterladen' }}
                </button>
            </footer>
        </form>
    </dialog>
</template>

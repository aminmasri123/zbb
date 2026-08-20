<template>
    <section class="mx-auto mt-6 max-w-6xl">
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-zbb">PA-Unterschriften</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Alle Unterschriftstage dieses Teilnehmers im aktiven Projekt – einschließlich älterer Versionen.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-zbb px-4 py-2 text-sm font-semibold text-zbb hover:bg-zbb/5 disabled:opacity-50"
                    :disabled="loading"
                    @click="loadSubjects"
                >
                    {{ loading ? 'Wird geladen …' : 'Aktualisieren' }}
                </button>
            </div>

            <div v-if="subjects.length" class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Unterschriftstage</p>
                    <p class="mt-1 text-2xl font-semibold text-zbb">{{ subjects.length }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Aktuell vorhanden</p>
                    <p class="mt-1 text-2xl font-semibold text-green-700">{{ currentSignatureCount }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Gespeicherte Versionen</p>
                    <p class="mt-1 text-2xl font-semibold text-zbb">{{ totalVersionCount }}</p>
                </div>
            </div>

            <div v-if="subjects.length" class="mt-5">
                <input
                    v-model="search"
                    type="search"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-zbb focus:ring-zbb"
                    placeholder="Nach Tag, Datum, Schule, Klasse oder Schuljahr suchen"
                >
            </div>

            <div v-if="loading && !subjects.length" class="py-12 text-center text-sm text-gray-500">
                Unterschriften werden gesucht …
            </div>

            <div v-else-if="loadError" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ loadError }}
            </div>

            <div v-else-if="!filteredSubjects.length" class="mt-5 rounded-xl bg-gray-50 py-12 text-center">
                <p class="font-medium text-gray-700">Keine PA-Unterschriften gefunden.</p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ search ? 'Für diesen Suchbegriff gibt es keinen Treffer.' : 'Für diesen Teilnehmer ist im aktiven Projekt noch keine Unterschrift gespeichert.' }}
                </p>
            </div>

            <div v-else class="mt-5 overflow-hidden rounded-xl border">
                <div
                    v-for="subject in filteredSubjects"
                    :key="`${subject.partner_id}:${subject.list_type}:${subject.signature_key}`"
                    class="grid gap-3 border-b p-4 last:border-b-0 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] md:items-center"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-gray-900">{{ subject.day_label || dayTypeLabel(subject.day_type) }}</p>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="subject.has_current_signature ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ subject.has_current_signature ? 'Unterschrift vorhanden' : 'Aktuell entfernt' }}
                            </span>
                        </div>
                        <p class="mt-1 text-lg font-semibold text-zbb">{{ formatDate(subject.signed_for_date) }}</p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ subject.partner_name }}
                            <span v-if="subject.class_name"> · Klasse {{ subject.class_name }}</span>
                        </p>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>{{ listTypeLabel(subject.list_type) }}</p>
                        <p>Schuljahr {{ subject.schuljahr }} · Teil {{ subject.teil }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ subject.version_count }} {{ subject.version_count === 1 ? 'Version' : 'Versionen' }}
                            · letzter Stand {{ formatDateTime(subject.updated_at) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-zbb/90"
                        @click="openHistory(subject)"
                    >
                        Alle Versionen
                    </button>
                </div>
            </div>
        </div>

        <div v-if="historyOpen" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4" @click.self="closeHistory">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b bg-white px-6 py-5">
                    <div>
                        <h3 class="text-lg font-semibold text-zbb">Alle Unterschriftsversionen</h3>
                        <p v-if="selectedSubject" class="mt-1 text-sm text-gray-500">
                            {{ selectedSubject.day_label || dayTypeLabel(selectedSubject.day_type) }} · {{ formatDate(selectedSubject.signed_for_date) }}
                        </p>
                    </div>
                    <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-700" @click="closeHistory">×</button>
                </div>

                <div class="p-6">
                    <p v-if="historyLoading" class="py-12 text-center text-sm text-gray-500">Versionen werden geladen …</p>
                    <p v-else-if="historyError" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ historyError }}</p>
                    <div v-else class="space-y-4">
                        <article v-for="version in versions" :key="version.id" class="rounded-xl border p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold">Version {{ version.version }}</p>
                                        <span v-if="version.is_current" class="rounded-full bg-zbb/10 px-2.5 py-1 text-xs font-medium text-zbb">Aktueller Stand</span>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">{{ actionLabel(version.action) }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ formatDateTime(version.created_at) }}
                                        <span v-if="version.actor_name"> · {{ version.actor_name }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Unterschrift für {{ formatDate(version.signed_for_date) }}
                                        <span v-if="version.class_name"> · Klasse {{ version.class_name }}</span>
                                    </p>
                                </div>
                            </div>
                            <div v-if="version.signature" class="mt-4 rounded-lg border bg-gray-50 p-3">
                                <img :src="version.signature" alt="Gespeicherte Unterschrift" class="mx-auto max-h-40 max-w-full object-contain">
                            </div>
                            <p v-else class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">
                                In dieser Version ist keine Unterschrift hinterlegt.
                            </p>
                        </article>
                        <p v-if="!versions.length" class="py-10 text-center text-sm text-gray-500">Keine Versionen vorhanden.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    participantId: { type: Number, required: true },
});

const subjects = ref([]);
const loading = ref(false);
const loadError = ref('');
const search = ref('');
const historyOpen = ref(false);
const historyLoading = ref(false);
const historyError = ref('');
const selectedSubject = ref(null);
const versions = ref([]);

const currentSignatureCount = computed(() => subjects.value.filter((subject) => subject.has_current_signature).length);
const totalVersionCount = computed(() => subjects.value.reduce((sum, subject) => sum + Number(subject.version_count || 0), 0));
const filteredSubjects = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase('de');
    if (!needle) return subjects.value;

    return subjects.value.filter((subject) => [
        subject.day_label,
        subject.signed_for_date,
        formatDate(subject.signed_for_date),
        subject.partner_name,
        subject.class_name,
        subject.schuljahr,
        subject.teil,
        listTypeLabel(subject.list_type),
    ].filter(Boolean).join(' ').toLocaleLowerCase('de').includes(needle));
});

const formatDate = (value) => {
    if (!value) return 'Datum nicht ermittelt';
    const [year, month, day] = String(value).slice(0, 10).split('-');
    return year && month && day ? `${day}.${month}.${year}` : value;
};

const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('de-DE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
    : 'unbekannt';

const listTypeLabel = (type) => type === 'pa_preparation' ? 'Vorbereitung PA' : 'Potenzialanalyse';
const dayTypeLabel = (type) => type === 'preparation' ? 'Vorbereitung PA' : (type === 'feedback' ? 'Feedbackgespräch' : 'PA-Tag');
const actionLabel = (action) => ({
    captured: 'Erfasst',
    replaced: 'Ersetzt',
    restored: 'Wiederhergestellt',
    deleted: 'Entfernt',
    imported: 'Bestehende Unterschrift übernommen',
}[action] || action);

const loadSubjects = async () => {
    loading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get(route('teilnehmer.pa-signatures.index', props.participantId));
        subjects.value = response.data.subjects || [];
    } catch (error) {
        loadError.value = error.response?.data?.message || 'Die PA-Unterschriften konnten nicht geladen werden.';
    } finally {
        loading.value = false;
    }
};

const historyPayload = (subject) => ({
    schuleId: subject.partner_id,
    schuljahr: subject.schuljahr,
    teil: subject.teil,
    listType: subject.list_type,
    exportMode: subject.export_mode || 'alle',
    klasse: subject.klasse || undefined,
    signatureKey: subject.signature_key,
});

const openHistory = async (subject) => {
    selectedSubject.value = subject;
    versions.value = [];
    historyError.value = '';
    historyLoading.value = true;
    historyOpen.value = true;
    try {
        const response = await axios.post(route('anwesenheitsliste.PA.digital.signature.history'), historyPayload(subject));
        versions.value = response.data.versions || [];
    } catch (error) {
        historyError.value = error.response?.data?.message || 'Der Versionsverlauf konnte nicht geladen werden.';
    } finally {
        historyLoading.value = false;
    }
};

const closeHistory = () => {
    historyOpen.value = false;
    selectedSubject.value = null;
    versions.value = [];
};

onMounted(loadSubjects);
</script>

<template>
    <section class="mb-6 rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <span class="rounded-full bg-indigo-600 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white">KI</span>
                    <h3 class="text-base font-semibold text-gray-900">LuV-Entwurf vorbereiten</h3>
                </div>
                <p class="max-w-2xl text-sm text-gray-600">
                    Erstellt einen belegpflichtigen Entwurf. Es wird nichts automatisch gespeichert, freigegeben oder versendet.
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="open"
            >
                ✨ KI-Entwurf erstellen
            </button>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" @click.self="close">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <header class="sticky top-0 z-10 flex items-start justify-between border-b bg-white px-6 py-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-amber-800">Entwurf</span>
                            <h2 class="text-xl font-bold text-gray-900">KI-gestützter LuV-Entwurf</h2>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Teilnehmer-ID {{ participantId }} · menschliche Prüfung erforderlich</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Dialog schließen" :disabled="loading" @click="close">×</button>
                </header>

                <div class="space-y-6 p-6">
                    <form v-if="!draft && !isWorking" class="space-y-5" @submit.prevent="generate">
                        <div>
                            <span class="mb-2 block text-sm font-semibold text-gray-700">Welche LuV soll erstellt werden?</span>
                            <div class="grid grid-cols-3 gap-2 rounded-lg bg-gray-100 p-1">
                                <button
                                    v-for="option in luvTypes"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-md px-3 py-2 text-sm font-semibold transition"
                                    :class="form.luv_type === option.value ? 'bg-white text-indigo-700 shadow' : 'text-gray-600 hover:text-gray-900'"
                                    @click="selectLuvType(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-gray-700">Berichtszeitraum von</span>
                                <input v-model="form.from_date" type="date" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-gray-700">Berichtszeitraum bis</span>
                                <input v-model="form.until_date" type="date" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-gray-700">Arbeitsauftrag</span>
                            <textarea
                                v-model="form.request"
                                required
                                maxlength="4000"
                                rows="5"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Zum Beispiel: Erstelle einen sachlichen LuV-Entwurf und kennzeichne fehlende Informationen deutlich."
                            />
                            <span class="mt-1 block text-right text-xs text-gray-400">{{ form.request.length }}/4000</span>
                        </label>

                        <div v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                            {{ errorMessage }}
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            Der erzeugte Text ist ausschließlich eine Arbeitshilfe. Fakten ohne freigegebene Quelle werden als „Daten fehlen“ markiert.
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading" @click="close">Abbrechen</button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60" :disabled="loading">
                                {{ loading ? 'KI verarbeitet den Entwurf …' : 'Entwurf erzeugen' }}
                            </button>
                        </div>
                    </form>

                    <section v-if="isWorking && !draft" class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="h-4 w-4 animate-pulse rounded-full bg-indigo-500"></div>
                            <p class="text-sm font-semibold text-indigo-900">KI verarbeitet den Entwurf...</p>
                        </div>

                        <p class="text-sm text-indigo-800">
                            {{ runStatus.status_label || 'Warte auf KI-Verarbeitung…' }}
                        </p>

                        <div class="h-3 w-full overflow-hidden rounded-full bg-indigo-200">
                            <div
                                class="h-3 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-700 transition-all duration-300"
                                :style="{ width: `${Math.min(100, Math.max(0, Number(runStatus.progress_percent) || 0))}%` }"
                            ></div>
                        </div>

                        <div class="grid gap-2 text-xs text-indigo-900 sm:grid-cols-3">
                            <span><strong>Stand:</strong> {{ runStatus.status || 'Wartet' }}</span>
                            <span><strong>Laufzeit:</strong> {{ formatDuration(displayedElapsedSeconds) }}</span>
                            <span v-if="runStatus.estimated_remaining_seconds !== null"><strong>Noch ca.:</strong> {{ formatDuration(runStatus.estimated_remaining_seconds) }}</span>
                        </div>

                        <div v-if="runStatus.queue_warning" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                            {{ runStatus.queue_warning }}
                        </div>

                        <div v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                            {{ errorMessage }}
                        </div>

                        <p v-if="runId" class="text-xs text-gray-500">Lauf-ID: {{ runId }}</p>
                    </section>

                    <div v-else-if="draft" class="space-y-5">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <strong>Nicht freigegeben:</strong> Dieser Entwurf wurde weder als LuV gespeichert noch versendet.
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ reportTypeLabel(draft.report_type) }}</p>
                            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ draft.title }}</h3>
                            <p class="mt-1 text-xs text-gray-400">Lauf-ID: {{ runId }}</p>
                        </div>

                        <section v-for="(section, sectionIndex) in draft.sections" :key="`${section.heading}-${sectionIndex}`" class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <h4 class="mb-4 text-lg font-semibold text-gray-900">{{ section.heading }}</h4>
                            <div v-if="section.claims.length" class="space-y-3">
                                <article v-for="claim in section.claims" :key="claim.claim_id" class="rounded-lg border bg-white p-4">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span v-if="claim.status === 'supported'" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Belegt</span>
                                        <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Daten fehlen</span>
                                    </div>
                                    <p class="whitespace-pre-line text-sm leading-6 text-gray-800">{{ claim.text }}</p>
                                    <p v-if="claim.source_ids.length" class="mt-2 text-xs text-gray-500">Quellen: {{ claim.source_ids.join(', ') }}</p>
                                </article>
                            </div>
                            <p v-else class="text-sm italic text-gray-500">Dieser Abschnitt enthält keine Aussagen.</p>
                        </section>

                            <div v-if="draft.warnings.length" class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                            <h4 class="mb-2 text-sm font-semibold text-orange-900">Hinweise</h4>
                            <ul class="list-disc space-y-1 pl-5 text-sm text-orange-800">
                                <li v-for="warning in draft.warnings" :key="warning">{{ warning }}</li>
                            </ul>
                        </div>

                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="reset">Neuen Entwurf erstellen</button>
                            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60" :disabled="adopting" @click="adoptDraft">
                                {{ adopting ? 'Wird übernommen …' : 'Als LuV-Entwurf übernehmen' }}
                            </button>
                            <button type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" @click="close">Schließen</button>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <p>{{ errorMessage }}</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

const props = defineProps({
    participantId: { type: Number, required: true },
});
const emit = defineEmits(['adopted']);
const luvTypes = [
    { value: 'Start', label: 'Start-LuV', reportType: 'luv' },
    { value: 'Verlauf', label: 'Verlauf-LuV', reportType: 'interim' },
    { value: 'Abschluss', label: 'Abschluss-LuV', reportType: 'final' },
];

const today = new Date();
const isoDate = (date) => date.toISOString().slice(0, 10);
const startOfYear = new Date(Date.UTC(today.getUTCFullYear(), 0, 1));

const visible = ref(false);
const loading = ref(false);
const adopting = ref(false);
const errorMessage = ref('');
const draft = ref(null);
const runId = ref('');
const clientElapsedSeconds = ref(0);
const runStatus = reactive({
    status: '',
    status_label: '',
    progress_percent: 0,
    elapsed_seconds: 0,
    estimated_remaining_seconds: null,
    queue_warning: null,
});
let pollTimer = null;
let elapsedTimer = null;

const form = reactive({
    luv_type: 'Start',
    from_date: isoDate(startOfYear),
    until_date: isoDate(today),
    request: 'Erstelle einen sachlichen Start-LuV-Entwurf. Verwende ausschließlich belegte Informationen und kennzeichne fehlende Daten deutlich.',
});

const selectLuvType = (type) => {
    form.luv_type = type;
    form.request = `Erstelle einen sachlichen ${type}-LuV-Entwurf. Verwende ausschließlich belegte Informationen, keine Diagnosen, und kennzeichne fehlende Daten deutlich.`;
};

const isWorking = computed(() => runStatus.status === 'queued' || runStatus.status === 'running');
const displayedElapsedSeconds = computed(() => Math.max(
    Number(runStatus.elapsed_seconds) || 0,
    clientElapsedSeconds.value,
));

const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) {
        return '—';
    }

    const safeSeconds = Math.max(0, Number(seconds));
    if (!Number.isFinite(safeSeconds)) {
        return '—';
    }

    const rounded = Math.round(safeSeconds);
    const minutes = Math.floor(rounded / 60);
    const secs = rounded % 60;

    if (minutes > 0) {
        return `${minutes} min ${secs.toString().padStart(2, '0')} s`;
    }

    return `${secs} s`;
};

const stopPolling = () => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    if (elapsedTimer !== null) {
        clearInterval(elapsedTimer);
        elapsedTimer = null;
    }
};

const pollStatus = async () => {
    if (!runId.value) {
        return;
    }

    try {
        const statusResponse = await axios.get(route('ai.reports.status', { run: runId.value }));
        const payload = statusResponse.data || {};

        runStatus.status = payload.status || '';
        runStatus.status_label = payload.status_label || '';
        runStatus.progress_percent = Number(payload.progress_percent) || 0;
        runStatus.elapsed_seconds = payload.elapsed_seconds ?? null;
        runStatus.estimated_remaining_seconds = payload.estimated_remaining_seconds ?? null;
        runStatus.queue_warning = payload.queue_warning ?? null;
        clientElapsedSeconds.value = Math.max(clientElapsedSeconds.value, Number(payload.elapsed_seconds) || 0);

        if (payload.status === 'completed') {
            draft.value = payload.report || null;
            loading.value = false;
            stopPolling();
            return;
        }

        if (payload.status === 'failed') {
            loading.value = false;
            errorMessage.value = payload.error_message || 'Der KI-Dienst konnte die Anfrage nicht verarbeiten.';
            stopPolling();
            return;
        }
    } catch (error) {
        if (error.response?.status === 404) {
            loading.value = false;
            errorMessage.value = 'Dieser KI-Lauf wurde nicht gefunden.';
            stopPolling();
            return;
        }

        if (error.response?.status === 429) {
            errorMessage.value = 'Zu viele KI-Anfragen. Bitte warte kurz und versuche es erneut.';
            return;
        }

        errorMessage.value = 'Der Status der KI-Verarbeitung konnte nicht abgefragt werden.';
        return;
    }
};

const startPolling = () => {
    stopPolling();
    pollTimer = window.setInterval(pollStatus, 2500);
    elapsedTimer = window.setInterval(() => {
        clientElapsedSeconds.value += 1;
    }, 1000);
};

const open = () => {
    visible.value = true;
    errorMessage.value = '';
};

const close = () => {
    if (!loading.value) {
        visible.value = false;
        stopPolling();
    }
};

const reset = () => {
    draft.value = null;
    runId.value = '';
    errorMessage.value = '';
    runStatus.status = '';
    runStatus.status_label = '';
    runStatus.progress_percent = 0;
    runStatus.elapsed_seconds = 0;
    runStatus.estimated_remaining_seconds = null;
    runStatus.queue_warning = null;
    clientElapsedSeconds.value = 0;
};

const reportTypeLabel = (type) => ({ luv: 'LuV-Bericht', interim: 'Zwischenbericht', final: 'Abschlussbericht' }[type] || type);

const adoptDraft = async () => {
    if (!runId.value || adopting.value) return;
    adopting.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(route('ai.reports.adopt', { run: runId.value }));
        emit('adopted', response.data.luv);
        await Swal.fire({ icon: 'success', title: 'LuV-Entwurf übernommen', text: response.data.message, timer: 2500, showConfirmButton: false });
        visible.value = false;
        reset();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Der KI-Entwurf konnte nicht übernommen werden.';
    } finally {
        adopting.value = false;
    }
};

const generate = async () => {
    loading.value = true;
    draft.value = null;
    errorMessage.value = '';
    stopPolling();
    runStatus.status = 'queued';
    runStatus.status_label = 'Warte auf KI-Verarbeitung';
    runStatus.progress_percent = 0;
    runStatus.elapsed_seconds = 0;
    runStatus.estimated_remaining_seconds = null;
    runStatus.queue_warning = null;
    clientElapsedSeconds.value = 0;

    try {
        const response = await axios.post(route('ai.reports.draft'), {
            participant_id: props.participantId,
            report_type: luvTypes.find((option) => option.value === form.luv_type)?.reportType || 'luv',
            luv_type: form.luv_type,
            from_date: form.from_date,
            until_date: form.until_date,
            request: form.request,
        });

        runId.value = response.data.run_id || '';
        await pollStatus();
        if (isWorking.value) {
            startPolling();
        }
    } catch (error) {
        const status = error.response?.status;
        const validationErrors = error.response?.data?.errors;

        if (validationErrors) {
            errorMessage.value = Object.values(validationErrors).flat().join(' ');
        } else if (status === 403 || status === 404) {
            errorMessage.value = 'Für diesen Teilnehmer oder das aktive Projekt fehlt die KI-Berechtigung.';
        } else if (status === 429) {
            errorMessage.value = 'Zu viele KI-Anfragen. Bitte warte eine Minute und versuche es erneut.';
        } else if (status === 503) {
            errorMessage.value = 'Der lokale KI-Dienst ist derzeit nicht erreichbar. Bitte prüfe den SSH-Tunnel und Ollama.';
        } else {
            errorMessage.value = error.response?.data?.message || 'Der KI-Entwurf konnte nicht erstellt werden.';
        }

        loading.value = false;
        stopPolling();
    }
};

onBeforeUnmount(() => {
    stopPolling();
});
</script>

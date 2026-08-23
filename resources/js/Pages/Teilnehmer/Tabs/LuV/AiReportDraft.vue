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
                    <button type="button" class="rounded-lg p-2 text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Dialog schließen" @click="close">×</button>
                </header>

                <div class="space-y-6 p-6">
                    <form v-if="!draft" class="space-y-5" @submit.prevent="generate">
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
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="loading" @click="close">Abbrechen</button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60" :disabled="loading">
                                {{ loading ? 'KI verarbeitet den Entwurf …' : 'Entwurf erzeugen' }}
                            </button>
                        </div>
                    </form>

                    <div v-else class="space-y-5">
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
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="reset">Neuen Entwurf erstellen</button>
                            <button type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" @click="close">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import axios from 'axios';
import { reactive, ref } from 'vue';

const props = defineProps({
    participantId: { type: Number, required: true },
});

const today = new Date();
const isoDate = (date) => date.toISOString().slice(0, 10);
const startOfYear = new Date(Date.UTC(today.getUTCFullYear(), 0, 1));

const visible = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const draft = ref(null);
const runId = ref('');
const form = reactive({
    from_date: isoDate(startOfYear),
    until_date: isoDate(today),
    request: 'Erstelle einen sachlichen LuV-Entwurf. Verwende ausschließlich belegte Informationen und kennzeichne fehlende Daten deutlich.',
});

const open = () => {
    visible.value = true;
    errorMessage.value = '';
};

const close = () => {
    if (!loading.value) visible.value = false;
};

const reset = () => {
    draft.value = null;
    runId.value = '';
    errorMessage.value = '';
};

const reportTypeLabel = (type) => ({ luv: 'LuV-Bericht', interim: 'Zwischenbericht', final: 'Abschlussbericht' }[type] || type);

const generate = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(route('ai.reports.draft'), {
            participant_id: props.participantId,
            report_type: 'luv',
            from_date: form.from_date,
            until_date: form.until_date,
            request: form.request,
        });
        draft.value = response.data.report;
        runId.value = response.data.run_id;
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
    } finally {
        loading.value = false;
    }
};
</script>

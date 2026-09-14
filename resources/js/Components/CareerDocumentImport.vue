<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ previewUrl: String, confirmUrl: String, templates: Array, participantName: String });
const emit = defineEmits(['close', 'imported']);
const dialog = ref(null);
const file = ref(null);
const originalUrl = ref('');
const draft = ref(null);
const sourceText = ref('');
const warnings = ref([]);
const busy = ref(false);
const saving = ref(false);
const error = ref('');
const reviewed = ref(false);
const type = ref('resume');
const fieldErrors = reactive({});
let requestController;
const hasContent = computed(() => draft.value && [draft.value.content.body, draft.value.content.summary, ...(draft.value.content.entries || []).flatMap(e => [e.title, e.description, e.subtitle])].some(value => value?.trim()));
const commonFields = [ ['full_name', 'Name', 255], ['headline', 'Überschrift / Position', 255], ['contact', 'Kontaktdaten', 1000] ];
const entryFields = [ ['section', 'Abschnitt', 100], ['period', 'Zeitraum', 100], ['title', 'Position / Abschluss', 255], ['subtitle', 'Arbeitgeber / Schule', 255] ];

onMounted(() => dialog.value.showModal());
onBeforeUnmount(() => { requestController?.abort(); releaseUrl(); });
function releaseUrl() { if (originalUrl.value) URL.revokeObjectURL(originalUrl.value); originalUrl.value = ''; }
function clearErrors() { error.value = ''; Object.keys(fieldErrors).forEach(key => delete fieldErrors[key]); }
function showError(e) {
    Object.assign(fieldErrors, e.response?.data?.errors || {});
    error.value = Object.values(fieldErrors).flat().join(' ') || e.response?.data?.message || 'Der Import ist gerade nicht möglich. Bitte erneut versuchen.';
}
function chooseFile(event) {
    releaseUrl(); clearErrors(); draft.value = null; reviewed.value = false;
    file.value = event.target.files?.[0] || null;
    if (!file.value) return;
    if (!/\.pdf$/i.test(file.value.name) || file.value.size > 10 * 1024 * 1024) {
        error.value = 'Bitte eine PDF-Datei mit höchstens 10 MB auswählen.'; file.value = null; return;
    }
    originalUrl.value = URL.createObjectURL(file.value);
}
async function preview() {
    if (!file.value || busy.value) return;
    clearErrors(); busy.value = true; requestController = new AbortController();
    const data = new FormData(); data.append('file', file.value); data.append('type', type.value);
    try {
        const result = await axios.post(props.previewUrl, data, { signal: requestController.signal });
        draft.value = result.data.document; warnings.value = result.data.warnings; sourceText.value = result.data.source_text;
        reviewed.value = false;
        await nextTick(); dialog.value.querySelector('[data-review-heading]')?.focus();
    } catch (e) { if (!axios.isCancel(e)) showError(e); }
    finally { busy.value = false; }
}
async function confirmImport() {
    if (saving.value || !reviewed.value || !hasContent.value) return;
    clearErrors(); saving.value = true;
    try {
        const result = await axios.post(props.confirmUrl, { ...draft.value, reviewed: true });
        emit('imported', result.data.document);
    } catch (e) { showError(e); }
    finally { saving.value = false; }
}
function close() { if (!saving.value) { requestController?.abort(); emit('close'); } }
function back() { draft.value = null; file.value = null; reviewed.value = false; sourceText.value = ''; warnings.value = []; releaseUrl(); clearErrors(); }
function addEntry() { draft.value.content.entries.push({ section: 'Berufserfahrung', period: '', title: '', subtitle: '', description: '' }); }
</script>

<template>
    <dialog ref="dialog" class="import-dialog max-h-[94dvh] w-[min(1440px,96vw)] max-w-none rounded-2xl border border-[var(--border)] bg-[var(--card)] p-0 text-[var(--primary)] shadow-2xl open:flex open:flex-col" aria-labelledby="import-title" @cancel.prevent="close">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-[var(--border)] p-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--secondary)]">{{ draft ? '2 · Prüfen und übernehmen' : '1 · PDF auswählen' }}</p>
                <h2 id="import-title" class="mt-1 text-xl font-bold">{{ draft ? 'Import-Vorschau' : 'Vorhandenes PDF importieren' }}</h2>
                <p class="mt-1 text-sm text-[var(--secondary)]">Für {{ participantName }} · Erst nach Ihrer Bestätigung wird ein neues Dokument gespeichert.</p>
            </div>
            <button type="button" :disabled="saving" class="rounded-lg border px-3 py-2 disabled:opacity-50" aria-label="Import schließen" @click="close">✕</button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            <div v-if="!draft" class="mx-auto max-w-xl space-y-5 py-6">
                <label class="block font-semibold">Dokumentart<select v-model="type" :disabled="busy" class="mt-2 w-full rounded-lg border-[var(--border)] bg-[var(--inputBg)]"><option value="resume">Lebenslauf</option><option value="cover_letter">Anschreiben</option></select></label>
                <label class="block rounded-xl border-2 border-dashed border-[var(--border)] p-6 font-semibold">PDF-Datei auswählen<input type="file" accept=".pdf,application/pdf" :disabled="busy" class="mt-3 block w-full text-sm" @change="chooseFile"><span class="mt-3 block text-sm font-normal text-[var(--secondary)]">Maximal 10 MB und 20 Seiten. Die Inhalte werden in eine bearbeitbare Studiovorlage übernommen.</span></label>
                <p class="text-sm text-[var(--secondary)]">Bei eingescannten Seiten können Sie die Angaben in der Vorschau manuell ergänzen.</p>
            </div>
            <div v-else class="grid min-w-0 gap-6 lg:grid-cols-2">
                <section class="min-w-0 lg:sticky lg:top-0 lg:self-start">
                    <div class="mb-2 flex items-center justify-between gap-2"><h3 class="font-bold">Original-PDF</h3><a :href="originalUrl" target="_blank" rel="noopener" class="text-sm underline">Separat öffnen</a></div>
                    <iframe :src="originalUrl" title="Original-PDF zur Prüfung des Imports" class="h-[45vh] w-full rounded-lg border border-[var(--border)] bg-white lg:h-[59vh]"></iframe>
                    <p class="mt-2 text-xs text-[var(--secondary)]">Falls die PDF-Anzeige nicht verfügbar ist, nutzen Sie „Separat öffnen“.</p>
                    <details v-if="sourceText" class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Vollständigen erkannten Text anzeigen</summary><pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg border p-3 font-sans">{{ sourceText }}</pre></details>
                </section>
                <section class="min-w-0 space-y-4">
                    <h3 tabindex="-1" data-review-heading class="font-bold">Erkannte Inhalte prüfen und korrigieren</h3>
                    <div class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"><p v-for="warning in warnings" :key="warning">{{ warning }}</p><p>Gelb markierte Felder sind noch ungeprüft. Fehlende Angaben können Sie ergänzen.</p></div>
                    <fieldset :disabled="saving" class="space-y-4 disabled:opacity-70">
                        <label class="block text-sm font-semibold">Vorlagenname<input v-model="draft.title" maxlength="255" required class="import-field" placeholder="z. B. Mein Basis-Lebenslauf"></label>
                        <label class="block text-sm font-semibold">Design<select v-model="draft.template_key" class="import-field"><option v-for="template in templates" :key="template.key" :value="template.key">{{ template.name }}</option></select></label>
                        <label v-for="[key, label, limit] in commonFields" :key="key" class="block text-sm font-semibold">{{ label }}<textarea v-if="key === 'contact'" v-model="draft.content[key]" :maxlength="limit" rows="3" class="import-field" :class="{ 'needs-review': !reviewed }"></textarea><input v-else v-model="draft.content[key]" :maxlength="limit" class="import-field" :class="{ 'needs-review': !reviewed }"></label>
                        <template v-if="draft.type === 'cover_letter'">
                            <label class="block text-sm font-semibold">Empfänger / Briefkopf prüfen<textarea v-model="draft.content.recipient" maxlength="1000" rows="5" class="import-field" :class="{ 'needs-review': !reviewed }"></textarea><span class="font-normal text-[var(--secondary)]">Absenderangaben gegebenenfalls nach „Kontaktdaten“ verschieben.</span></label>
                            <label class="block text-sm font-semibold">Betreff<input v-model="draft.content.subject" maxlength="255" class="import-field" :class="{ 'needs-review': !reviewed }"></label>
                            <label class="block text-sm font-semibold">Anschreiben<textarea v-model="draft.content.body" maxlength="15000" rows="18" class="import-field" :class="{ 'needs-review': !reviewed }"></textarea></label>
                        </template>
                        <template v-else>
                            <label class="block text-sm font-semibold">Kurzprofil<textarea v-model="draft.content.summary" maxlength="5000" rows="3" class="import-field" :class="{ 'needs-review': !reviewed }"></textarea></label>
                            <article v-for="(entry, index) in draft.content.entries" :key="index" class="space-y-3 rounded-xl border border-[var(--border)] p-3">
                                <div class="flex justify-between gap-2"><h4 class="font-semibold">Eintrag {{ index + 1 }}</h4><button type="button" class="text-sm text-red-600" @click="draft.content.entries.splice(index, 1)">Entfernen</button></div>
                                <div class="grid gap-3 sm:grid-cols-2"><label v-for="[key, label, limit] in entryFields" :key="key" class="block text-sm font-semibold">{{ label }}<input v-model="entry[key]" :maxlength="limit" class="import-field" :class="{ 'needs-review': !reviewed }"></label></div>
                                <label class="block text-sm font-semibold">Beschreibung<textarea v-model="entry.description" maxlength="5000" rows="4" class="import-field" :class="{ 'needs-review': !reviewed }"></textarea></label>
                            </article>
                            <button type="button" :disabled="draft.content.entries.length >= 100" class="rounded-lg border px-3 py-2 text-sm" @click="addEntry">+ Eintrag ergänzen</button>
                        </template>
                    </fieldset>
                </section>
            </div>
        </div>
        <footer class="shrink-0 space-y-3 border-t border-[var(--border)] p-5">
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ error }}</p>
            <label v-if="draft" class="flex items-start gap-3 text-sm"><input v-model="reviewed" type="checkbox" :disabled="saving" class="mt-0.5 rounded">Ich habe die Inhalte mit dem Original verglichen und die Angaben geprüft.</label>
            <p v-if="draft && !hasContent" class="text-sm text-[var(--secondary)]">Bitte zuerst den Anschreibentext oder mindestens einen Lebenslaufeintrag ergänzen.</p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" :disabled="saving" class="rounded-lg border border-[var(--border)] px-4 py-2 disabled:opacity-50" @click="close">Abbrechen</button>
                <div class="flex flex-wrap gap-3"><button v-if="draft" type="button" :disabled="saving" class="rounded-lg border px-4 py-2 disabled:opacity-50" @click="back">Andere Datei wählen</button><button v-if="!draft" type="button" :disabled="!file || busy" class="rounded-lg bg-[var(--buttonPrimary)] px-4 py-2 font-semibold text-[var(--buttonTextPrimary)] disabled:opacity-50" @click="preview">{{ busy ? 'PDF wird gelesen …' : 'Import-Vorschau anzeigen' }}</button><button v-else type="button" :disabled="saving || !reviewed || !draft.title.trim() || !hasContent" class="rounded-lg bg-[var(--buttonPrimary)] px-4 py-2 font-semibold text-[var(--buttonTextPrimary)] disabled:opacity-50" @click="confirmImport">{{ saving ? 'Wird übernommen …' : 'Als bearbeitbare Vorlage übernehmen' }}</button></div>
            </div>
        </footer>
    </dialog>
</template>

<style scoped>
.import-dialog::backdrop { background: rgb(15 23 42 / 65%); }
.import-field { display: block; width: 100%; margin-top: .35rem; border: 1px solid var(--border); border-radius: .5rem; background: var(--inputBg); font-weight: 400; }
.needs-review { border-color: #d97706; }
</style>

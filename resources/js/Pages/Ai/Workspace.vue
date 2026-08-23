<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ runs: Array, retentionDays: { type: Number, default: 7 }, knowledgeLabel: String, modelLabel: String })
const history = ref(props.runs || [])
const active = ref(history.value[0] || null)
const form = reactive({ task: 'chat', instruction: '', documents: [], image: null })
const loading = ref(false)
const error = ref('')
const tasks = [['chat', 'Textchat', 'Fragen stellen und Texte entwerfen'], ['summarize', 'PDF zusammenfassen', 'Mit Seitenquellen'], ['compare', 'Dokumente vergleichen', 'Zwei PDFs gegenüberstellen'], ['image_analysis', 'Bild analysieren', 'Inhalt eines Bildes beschreiben']]
const needsPdf = computed(() => ['summarize', 'compare'].includes(form.task))
watch(() => form.task, task => {
    if (task !== 'image_analysis') form.image = null
    if (!['summarize', 'compare'].includes(task)) form.documents = []
})

async function generate() {
    loading.value = true; error.value = ''
    const body = new FormData(); body.append('task', form.task); body.append('instruction', form.instruction)
    form.documents.forEach((file, index) => body.append(`documents[${index}]`, file))
    if (form.task === 'image_analysis' && form.image) body.append('image', form.image)
    try { const response = await axios.post(route('ai.workspace.generate'), body); history.value.unshift(response.data.run); active.value = response.data.run }
    catch (exception) { error.value = exception.response?.data?.message || Object.values(exception.response?.data?.errors || {})[0]?.[0] || 'Die KI-Anfrage ist fehlgeschlagen.' }
    finally { loading.value = false }
}
function selectDocuments(event) { form.documents = Array.from(event.target.files) }
async function deleteRun(run) {
    if (!window.confirm(`Verlauf „${run.title || run.instruction}“ endgültig löschen?`)) return
    try { await axios.delete(route('ai.workspace.destroy', run.id)); history.value = history.value.filter(item => item.id !== run.id); if (active.value?.id === run.id) active.value = history.value[0] || null }
    catch (exception) { error.value = exception.response?.data?.message || 'Der Verlauf konnte nicht gelöscht werden.' }
}
async function deleteAllRuns() {
    if (!history.value.length || !window.confirm('Alle eigenen KI-Verläufe endgültig löschen?')) return
    try { await axios.delete(route('ai.workspace.destroy-all')); history.value = []; active.value = null }
    catch (exception) { error.value = exception.response?.data?.message || 'Die Verläufe konnten nicht gelöscht werden.' }
}
</script>

<template>
    <Head title="KI-Arbeitsbereich" />
    <AppLayout title="KI-Arbeitsbereich">
        <template #header>KI-Arbeitsbereich</template>
        <div class="min-h-screen bg-[var(--bg)] py-2 text-[var(--primary)]">
            <div class="mx-auto max-w-7xl px-4">
                <header class="mb-6 rounded-lg border border-[var(--border)] bg-[var(--card)] px-6 py-5 shadow-sm">
                    <p class="text-sm font-bold uppercase tracking-widest text-[var(--buttonPrimary)]">Lokal & geschützt</p>
                    <h1 class="text-3xl font-black">KI-Arbeitsbereich</h1>
                    <p class="mt-1 text-[var(--secondary)]">Chat, PDF-Zusammenfassung, Dokumentvergleich und Bildanalyse direkt in ZBB.</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-amber-100 px-3 py-1.5 text-amber-900">⚠ {{ knowledgeLabel }}</span>
                        <span class="rounded-full bg-[var(--muted)] px-3 py-1.5 text-[var(--secondary)]">{{ modelLabel }}</span>
                        <span class="rounded-full bg-red-50 px-3 py-1.5 text-red-700">Verläufe werden nach {{ retentionDays }} Tagen automatisch gelöscht</span>
                    </div>
                    <p class="mt-3 text-xs text-[var(--secondary)]">Aktuelle politische, rechtliche oder amtliche Angaben dürfen nur mit einer geprüften Quelle verwendet werden.</p>
                </header>
                <div class="grid gap-6 xl:grid-cols-[250px_1fr]">
                    <aside class="space-y-2">
                        <button v-for="item in tasks" :key="item[0]" class="w-full rounded-2xl border p-4 text-left transition" :class="form.task === item[0] ? 'border-[var(--borderHover)] bg-[var(--surfaceTint)] ring-2 ring-[var(--border)]' : 'border-[var(--border)] bg-[var(--card)] hover:border-[var(--borderHover)]'" @click="form.task = item[0]"><b class="block">{{ item[1] }}</b><span class="text-xs text-[var(--secondary)]">{{ item[2] }}</span></button>
                        <section v-if="history.length" class="pt-5">
                            <div class="flex items-center justify-between gap-2 px-2"><p class="text-xs font-bold uppercase text-[var(--secondary)]">Letzte Ergebnisse</p><button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="deleteAllRuns">Alle löschen</button></div>
                            <div v-for="run in history" :key="run.id" class="mt-2 flex items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--card)] shadow-sm"><button type="button" class="min-w-0 flex-1 truncate p-3 text-left text-sm" @click="active = run">{{ run.title || run.instruction }}</button><button type="button" class="shrink-0 px-3 py-2 text-lg text-red-600" title="Verlauf löschen" aria-label="Verlauf löschen" @click="deleteRun(run)">×</button></div>
                        </section>
                    </aside>
                    <main class="space-y-5">
                        <section class="rounded-3xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                            <label class="block font-bold">Was soll die KI tun?<textarea v-model="form.instruction" rows="5" maxlength="8000" class="mt-2 w-full rounded-2xl border-[var(--border)] bg-[var(--card)] text-[var(--primary)]" :placeholder="form.task === 'chat' ? 'Schreibe oder frage etwas …' : 'Beschreiben Sie das gewünschte Ergebnis …'"></textarea></label>
                            <label v-if="needsPdf" class="mt-4 block cursor-pointer rounded-2xl border-2 border-dashed border-[var(--borderHover)] bg-[var(--surfaceTint)] p-6 text-center"><input type="file" accept="application/pdf,.pdf" :multiple="form.task === 'compare'" class="sr-only" @change="selectDocuments"><b>{{ form.documents.map(file => file.name).join(', ') || (form.task === 'compare' ? 'Zwei PDF-Dateien auswählen' : 'PDF-Datei auswählen') }}</b><span class="mt-1 block text-xs text-[var(--secondary)]">Maximal 10 MB je Datei, höchstens 50 Seiten</span></label>
                            <label v-if="form.task === 'image_analysis'" class="mt-4 block cursor-pointer rounded-2xl border-2 border-dashed border-[var(--borderHover)] bg-[var(--surfaceTint)] p-6 text-center"><input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="form.image = $event.target.files[0]"><b>{{ form.image?.name || 'Bild auswählen' }}</b><span class="mt-1 block text-xs text-[var(--secondary)]">PNG, JPG oder WebP bis 5 MB</span></label>
                            <p v-if="error" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                            <button :disabled="loading || !form.instruction.trim()" class="mt-4 rounded-xl bg-[var(--buttonPrimary)] px-6 py-3 font-bold text-[var(--buttonTextPrimary)] disabled:opacity-50" @click="generate">{{ loading ? 'KI arbeitet …' : '✦ Erstellen' }}</button>
                        </section>
                        <section v-if="active" class="rounded-3xl border border-[var(--border)] bg-[var(--card)] p-7 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase text-[var(--buttonPrimary)]">KI-Ergebnis · bitte fachlich prüfen</p><h2 class="mt-1 text-2xl font-black">{{ active.title }}</h2></div><div class="flex gap-2"><a :href="route('ai.workspace.export', { run: active.id, format: 'docx' })" class="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-bold">DOCX</a><a :href="route('ai.workspace.export', { run: active.id, format: 'pdf' })" class="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-bold">PDF</a></div></div>
                            <div class="mt-6 whitespace-pre-wrap leading-7">{{ active.content }}</div>
                            <div v-if="active.citations?.length" class="mt-6 rounded-2xl bg-[var(--muted)] p-4"><b class="text-sm">Quellen</b><div class="mt-2 flex flex-wrap gap-2"><span v-for="citation in active.citations" :key="`${citation.source_id}-${citation.page}`" class="rounded-full bg-[var(--card)] px-3 py-1 text-xs ring-1 ring-[var(--border)]">{{ citation.source_id }}<template v-if="citation.page"> · S. {{ citation.page }}</template></span></div></div>
                        </section>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

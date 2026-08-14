<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    feedbackItems: { type: Array, default: () => [] },
    canManage: Boolean,
    assignees: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    contextPage: { type: String, default: '' },
    requestedFeedbackId: { type: Number, default: null },
});

const page = usePage();
const items = ref([...props.feedbackItems]);
const activeView = ref(props.canManage ? 'all' : 'mine');
const search = ref('');
const statusFilter = ref('');
const typeFilter = ref('');
const priorityFilter = ref('');
const areaFilter = ref('');
const createOpen = ref(false);
const detailOpen = ref(false);
const selected = ref(null);
const saving = ref(false);
const selectedFiles = ref([]);
const commentBody = ref('');
const commentInternal = ref(false);
const savingComment = ref(false);

const emptyForm = () => ({
    type: 'suggestion',
    title: '',
    description: '',
    expected_result: '',
    area: '',
    priority: 'normal',
    page_url: props.contextPage || '',
});

const form = ref(emptyForm());
const management = ref({
    status: 'new',
    priority: 'normal',
    assigned_to_user_id: null,
    release_version: '',
    status_note: '',
});

const optionLabel = (group, value) => props.options[group]?.find((entry) => entry.value === value)?.label || value || '–';
const userName = (user) => user?.name || [user?.first_name, user?.last_name].filter(Boolean).join(' ') || user?.username || user?.email || 'Unbekannt';
const ownUserId = computed(() => page.props.auth?.user?.id);

const visibleItems = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase('de');

    return items.value.filter((item) => {
        if (activeView.value === 'mine' && item.user_id !== ownUserId.value) return false;
        if (statusFilter.value && item.status !== statusFilter.value) return false;
        if (typeFilter.value && item.type !== typeFilter.value) return false;
        if (priorityFilter.value && item.priority !== priorityFilter.value) return false;
        if (areaFilter.value && item.area !== areaFilter.value) return false;

        if (!needle) return true;

        return [item.reference, item.title, item.description, item.area, userName(item.user)]
            .filter(Boolean)
            .some((value) => String(value).toLocaleLowerCase('de').includes(needle));
    });
});

const metrics = computed(() => {
    const source = activeView.value === 'mine'
        ? items.value.filter((item) => item.user_id === ownUserId.value)
        : items.value;

    return [
        { label: 'Gesamt', value: source.length, icon: 'la-inbox', tone: 'slate' },
        { label: 'Neu', value: source.filter((item) => item.status === 'new').length, icon: 'la-sparkles', tone: 'blue' },
        { label: 'In Bearbeitung', value: source.filter((item) => ['review', 'planned', 'in_progress', 'testing'].includes(item.status)).length, icon: 'la-tools', tone: 'amber' },
        { label: 'Veröffentlicht', value: source.filter((item) => item.status === 'released').length, icon: 'la-check-circle', tone: 'green' },
    ];
});

const statusClass = (status) => ({
    new: 'bg-blue-50 text-blue-700 ring-blue-200',
    review: 'bg-violet-50 text-violet-700 ring-violet-200',
    planned: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    in_progress: 'bg-amber-50 text-amber-700 ring-amber-200',
    testing: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
    released: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
    duplicate: 'bg-slate-100 text-slate-600 ring-slate-200',
}[status] || 'bg-slate-100 text-slate-600 ring-slate-200');

const priorityClass = (priority) => ({
    low: 'text-slate-500',
    normal: 'text-blue-600',
    high: 'text-orange-600',
    critical: 'font-bold text-red-600',
}[priority] || 'text-slate-500');

function formatDate(value) {
    if (!value) return '–';
    return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function formatSize(bytes) {
    if (!bytes) return '0 KB';
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function openCreate() {
    form.value = emptyForm();
    selectedFiles.value = [];
    createOpen.value = true;
}

function handleFiles(event) {
    selectedFiles.value = Array.from(event.target.files || []).slice(0, 5);
}

function replaceItem(updated) {
    const index = items.value.findIndex((item) => item.id === updated.id);
    if (index === -1) items.value.unshift(updated);
    else items.value.splice(index, 1, updated);
    selected.value = updated;
}

async function submitFeedback() {
    saving.value = true;

    try {
        const data = new FormData();
        Object.entries(form.value).forEach(([key, value]) => data.append(key, value ?? ''));
        selectedFiles.value.forEach((file) => data.append('attachments[]', file));

        const response = await axios.post(route('program-feedback.store'), data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        replaceItem(response.data.feedback);
        createOpen.value = false;

        await Swal.fire({
            icon: 'success',
            title: 'Vielen Dank!',
            text: response.data.message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#f97316',
        });
    } catch (error) {
        Swal.fire('Meldung nicht gespeichert', firstError(error, 'Bitte prüfen Sie Ihre Angaben.'), 'error');
    } finally {
        saving.value = false;
    }
}

function openDetail(item) {
    selected.value = item;
    management.value = {
        status: item.status,
        priority: item.priority,
        assigned_to_user_id: item.assigned_to_user_id,
        release_version: item.release_version || '',
        status_note: '',
    };
    commentBody.value = '';
    commentInternal.value = false;
    detailOpen.value = true;
}

async function updateFeedback() {
    if (!props.canManage || !selected.value) return;
    saving.value = true;

    try {
        const response = await axios.put(route('program-feedback.update', selected.value.id), management.value);
        replaceItem(response.data.feedback);
        management.value.status_note = '';
        Swal.fire({ icon: 'success', title: 'Gespeichert', text: response.data.message, timer: 1500, showConfirmButton: false });
    } catch (error) {
        Swal.fire('Fehler', firstError(error, 'Die Meldung konnte nicht aktualisiert werden.'), 'error');
    } finally {
        saving.value = false;
    }
}

async function addComment() {
    if (!selected.value || !commentBody.value.trim()) return;
    savingComment.value = true;

    try {
        const response = await axios.post(route('program-feedback.comments.store', selected.value.id), {
            body: commentBody.value,
            is_internal: props.canManage && commentInternal.value,
        });
        replaceItem(response.data.feedback);
        commentBody.value = '';
        commentInternal.value = false;
    } catch (error) {
        Swal.fire('Fehler', firstError(error, 'Die Rückmeldung konnte nicht gespeichert werden.'), 'error');
    } finally {
        savingComment.value = false;
    }
}

async function deleteFeedback() {
    if (!props.canManage || !selected.value) return;
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Meldung löschen?',
        text: 'Kommentare, Verlauf und Anhänge werden ebenfalls gelöscht.',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    });
    if (!result.isConfirmed) return;

    try {
        await axios.delete(route('program-feedback.destroy', selected.value.id));
        items.value = items.value.filter((item) => item.id !== selected.value.id);
        detailOpen.value = false;
        Swal.fire({ icon: 'success', title: 'Gelöscht', timer: 1200, showConfirmButton: false });
    } catch (error) {
        Swal.fire('Fehler', firstError(error, 'Die Meldung konnte nicht gelöscht werden.'), 'error');
    }
}

function firstError(error, fallback) {
    const errors = error.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0] || fallback;
    return error.response?.data?.message || fallback;
}

onMounted(() => {
    if (props.requestedFeedbackId) {
        const requested = items.value.find((item) => item.id === props.requestedFeedbackId);
        if (requested) openDetail(requested);
    }
});
</script>

<template>
    <Head title="Programm-Feedback" />

    <AppLayout>
        <template #header>Programm-Feedback</template>

        <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--card)] shadow-sm">
                <div class="flex flex-col gap-5 p-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex max-w-3xl gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-zbb ring-1 ring-orange-100">
                            <i class="las la-lightbulb text-2xl" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-zbb">Gemeinsam besser</p>
                            <h1 class="mt-1 text-2xl font-semibold text-[var(--primary)]">Ideen und Programmfehler zentral melden</h1>
                            <p class="mt-2 text-sm leading-6 text-[var(--secondary)]">
                                Teilen Sie Verbesserungsvorschläge oder melden Sie eine Funktion, die nicht wie erwartet arbeitet. Den Bearbeitungsstand können Sie jederzeit hier verfolgen.
                            </p>
                        </div>
                    </div>
                    <button type="button" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-zbb px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600" @click="openCreate">
                        <i class="las la-plus text-lg" aria-hidden="true"></i>
                        Neue Meldung
                    </button>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in metrics" :key="metric.label" class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-[var(--secondary)]">{{ metric.label }}</p>
                            <p class="mt-1 text-2xl font-semibold text-[var(--primary)]">{{ metric.value }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--surfaceTint)] text-zbb">
                            <i class="las text-xl" :class="metric.icon" aria-hidden="true"></i>
                        </div>
                    </div>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--card)] shadow-sm">
                <div class="border-b border-[var(--border)] px-5 pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex gap-1 rounded-lg bg-[var(--surfaceTint)] p-1">
                            <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold transition" :class="activeView === 'mine' ? 'bg-white text-zbb shadow-sm' : 'text-[var(--secondary)]'" @click="activeView = 'mine'">
                                Meine Meldungen
                            </button>
                            <button v-if="canManage" type="button" class="rounded-md px-4 py-2 text-sm font-semibold transition" :class="activeView === 'all' ? 'bg-white text-zbb shadow-sm' : 'text-[var(--secondary)]'" @click="activeView = 'all'">
                                Verwaltung
                            </button>
                        </div>
                        <p class="pb-3 text-sm text-[var(--secondary)] sm:pb-0">{{ visibleItems.length }} Meldung{{ visibleItems.length === 1 ? '' : 'en' }}</p>
                    </div>

                    <div class="grid gap-3 py-4 md:grid-cols-2 xl:grid-cols-6">
                        <label class="relative xl:col-span-2">
                            <i class="las la-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input v-model="search" type="search" placeholder="Nummer, Titel oder Beschreibung suchen" class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-zbb focus:ring-zbb" />
                        </label>
                        <select v-model="typeFilter" class="rounded-lg border-slate-300 py-2 text-sm focus:border-zbb focus:ring-zbb">
                            <option value="">Alle Arten</option>
                            <option v-for="entry in options.types" :key="entry.value" :value="entry.value">{{ entry.label }}</option>
                        </select>
                        <select v-model="statusFilter" class="rounded-lg border-slate-300 py-2 text-sm focus:border-zbb focus:ring-zbb">
                            <option value="">Alle Status</option>
                            <option v-for="entry in options.statuses" :key="entry.value" :value="entry.value">{{ entry.label }}</option>
                        </select>
                        <select v-model="priorityFilter" class="rounded-lg border-slate-300 py-2 text-sm focus:border-zbb focus:ring-zbb">
                            <option value="">Alle Prioritäten</option>
                            <option v-for="entry in options.priorities" :key="entry.value" :value="entry.value">{{ entry.label }}</option>
                        </select>
                        <select v-model="areaFilter" class="rounded-lg border-slate-300 py-2 text-sm focus:border-zbb focus:ring-zbb">
                            <option value="">Alle Bereiche</option>
                            <option v-for="area in options.areas" :key="area" :value="area">{{ area }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="visibleItems.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--surfaceTint)] text-left text-xs uppercase tracking-wide text-[var(--secondary)]">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Meldung</th>
                                <th class="px-4 py-3 font-semibold">Bereich</th>
                                <th v-if="canManage && activeView === 'all'" class="px-4 py-3 font-semibold">Gemeldet von</th>
                                <th class="px-4 py-3 font-semibold">Priorität</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Aktualisiert</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            <tr v-for="item in visibleItems" :key="item.id" class="cursor-pointer transition hover:bg-[var(--surfaceTint)]" @click="openDetail(item)">
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="item.type === 'bug' ? 'bg-rose-50 text-rose-600' : 'bg-orange-50 text-zbb'">
                                            <i class="las" :class="item.type === 'bug' ? 'la-bug' : 'la-lightbulb'"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-[var(--primary)]">{{ item.title }}</p>
                                            <p class="mt-0.5 text-xs text-[var(--secondary)]">{{ item.reference }} · {{ optionLabel('types', item.type) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-[var(--secondary)]">{{ item.area || '–' }}</td>
                                <td v-if="canManage && activeView === 'all'" class="px-4 py-4 text-[var(--secondary)]">{{ userName(item.user) }}</td>
                                <td class="px-4 py-4"><span :class="priorityClass(item.priority)">{{ optionLabel('priorities', item.priority) }}</span></td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClass(item.status)">{{ optionLabel('statuses', item.status) }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-xs text-[var(--secondary)]">{{ formatDate(item.updated_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--surfaceTint)] text-slate-400"><i class="las la-inbox text-2xl"></i></div>
                    <h2 class="mt-4 font-semibold text-[var(--primary)]">Keine Meldungen gefunden</h2>
                    <p class="mt-1 text-sm text-[var(--secondary)]">Erstellen Sie eine neue Meldung oder passen Sie die Filter an.</p>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="createOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4" @click.self="createOpen = false">
                <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-6 py-4">
                        <div><h2 class="text-xl font-semibold text-slate-950">Neue Programm-Meldung</h2><p class="text-sm text-slate-500">So konkret wie möglich beschreiben – Screenshots helfen besonders.</p></div>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="createOpen = false"><i class="las la-times text-xl"></i></button>
                    </div>
                    <form class="space-y-5 p-6" @submit.prevent="submitFeedback">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Art *</span><select v-model="form.type" required class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb"><option v-for="entry in options.types" :key="entry.value" :value="entry.value">{{ entry.label }}</option></select></label>
                            <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Programmbereich</span><select v-model="form.area" class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb"><option value="">Bitte auswählen</option><option v-for="area in options.areas" :key="area" :value="area">{{ area }}</option></select></label>
                        </div>
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Kurzer Titel *</span><input v-model="form.title" required maxlength="160" placeholder="Zum Beispiel: Speichern im Teilnehmerprofil funktioniert nicht" class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb" /></label>
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Beschreibung *</span><textarea v-model="form.description" required rows="5" maxlength="10000" placeholder="Was haben Sie gemacht und was ist passiert?" class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb"></textarea></label>
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Was sollte stattdessen passieren?</span><textarea v-model="form.expected_result" rows="3" maxlength="5000" placeholder="Beschreiben Sie das gewünschte Ergebnis." class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb"></textarea></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Dringlichkeit *</span><select v-model="form.priority" required class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb"><option v-for="entry in options.priorities" :key="entry.value" :value="entry.value">{{ entry.label }}</option></select></label>
                            <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Betroffene Seite</span><input v-model="form.page_url" maxlength="2048" placeholder="Wird beim Öffnen über den Kopfbereich übernommen" class="w-full rounded-lg border-slate-300 focus:border-zbb focus:ring-zbb" /></label>
                        </div>
                        <label class="block rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <span class="block text-sm font-semibold text-slate-700"><i class="las la-paperclip mr-1"></i>Screenshots oder Dateien</span>
                            <span class="mt-1 block text-xs text-slate-500">Bis zu 5 Dateien, jeweils maximal 10 MB: Bilder, PDF, Word oder Excel.</span>
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" class="mt-3 block w-full text-sm" @change="handleFiles" />
                            <span v-if="selectedFiles.length" class="mt-2 block text-xs font-medium text-zbb">{{ selectedFiles.length }} Datei(en) ausgewählt</span>
                        </label>
                        <div class="flex justify-end gap-3 border-t pt-5">
                            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="createOpen = false">Abbrechen</button>
                            <button type="submit" :disabled="saving" class="rounded-lg bg-zbb px-5 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60">{{ saving ? 'Wird gesendet …' : 'Meldung absenden' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="detailOpen && selected" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4" @click.self="detailOpen = false">
                <div class="max-h-[94vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="sticky top-0 z-10 flex items-start justify-between border-b bg-white px-6 py-4">
                        <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wider text-zbb">{{ selected.reference }}</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ selected.title }}</h2></div>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="detailOpen = false"><i class="las la-times text-xl"></i></button>
                    </div>

                    <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.8fr)]">
                        <div class="space-y-6">
                            <div class="flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ optionLabel('types', selected.type) }}</span><span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClass(selected.status)">{{ optionLabel('statuses', selected.status) }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs" :class="priorityClass(selected.priority)">{{ optionLabel('priorities', selected.priority) }}</span></div>
                            <section><h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Beschreibung</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ selected.description }}</p></section>
                            <section v-if="selected.expected_result"><h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Gewünschtes Ergebnis</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ selected.expected_result }}</p></section>
                            <section v-if="selected.attachments?.length"><h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Anhänge</h3><div class="mt-2 grid gap-2 sm:grid-cols-2"><a v-for="attachment in selected.attachments" :key="attachment.id" :href="attachment.download_url" class="flex items-center gap-3 rounded-lg border p-3 text-sm text-slate-700 hover:border-zbb hover:text-zbb"><i class="las la-file-alt text-xl"></i><span class="min-w-0"><span class="block truncate font-semibold">{{ attachment.original_name }}</span><span class="text-xs text-slate-500">{{ formatSize(attachment.size) }}</span></span></a></div></section>

                            <section class="border-t pt-5">
                                <h3 class="font-semibold text-slate-950">Rückmeldungen</h3>
                                <div v-if="selected.comments?.length" class="mt-3 space-y-3">
                                    <article v-for="comment in selected.comments" :key="comment.id" class="rounded-xl border p-4" :class="comment.is_internal ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50'">
                                        <div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-800">{{ userName(comment.user) }} <span v-if="comment.is_internal" class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] uppercase text-amber-900">Intern</span></p><time class="text-xs text-slate-500">{{ formatDate(comment.created_at) }}</time></div>
                                        <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ comment.body }}</p>
                                    </article>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-500">Noch keine Rückmeldungen vorhanden.</p>
                                <div class="mt-4 rounded-xl border p-4"><textarea v-model="commentBody" rows="3" maxlength="5000" placeholder="Rückmeldung schreiben …" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea><div class="mt-3 flex flex-wrap items-center justify-between gap-3"><label v-if="canManage" class="flex items-center gap-2 text-sm text-slate-600"><input v-model="commentInternal" type="checkbox" class="rounded border-slate-300 text-zbb focus:ring-zbb" />Nur interne Notiz</label><span v-else></span><button type="button" :disabled="savingComment || !commentBody.trim()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="addComment">Rückmeldung senden</button></div></div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section v-if="canManage" class="rounded-xl border border-orange-200 bg-orange-50/50 p-4">
                                <h3 class="font-semibold text-slate-950">Meldung bearbeiten</h3>
                                <div class="mt-4 space-y-3">
                                    <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Status</span><select v-model="management.status" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb"><option v-for="entry in options.statuses" :key="entry.value" :value="entry.value">{{ entry.label }}</option></select></label>
                                    <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Priorität</span><select v-model="management.priority" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb"><option v-for="entry in options.priorities" :key="entry.value" :value="entry.value">{{ entry.label }}</option></select></label>
                                    <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Verantwortlich</span><select v-model="management.assigned_to_user_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb"><option :value="null">Nicht zugewiesen</option><option v-for="user in assignees" :key="user.id" :value="user.id">{{ userName(user) }}</option></select></label>
                                    <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Veröffentlichte Version</span><input v-model="management.release_version" maxlength="80" placeholder="z. B. 2026.08.2" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb" /></label>
                                    <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Hinweis zum Statuswechsel</span><textarea v-model="management.status_note" rows="2" maxlength="500" class="w-full rounded-lg border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea></label>
                                    <button type="button" :disabled="saving" class="w-full rounded-lg bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60" @click="updateFeedback">Änderungen speichern</button>
                                </div>
                            </section>

                            <section class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold text-slate-950">Details</h3><dl class="mt-3 space-y-3 text-sm"><div><dt class="text-xs text-slate-500">Gemeldet von</dt><dd class="font-medium text-slate-800">{{ userName(selected.user) }}</dd></div><div><dt class="text-xs text-slate-500">Programmbereich</dt><dd class="font-medium text-slate-800">{{ selected.area || '–' }}</dd></div><div><dt class="text-xs text-slate-500">Erstellt</dt><dd class="font-medium text-slate-800">{{ formatDate(selected.created_at) }}</dd></div><div v-if="selected.assigned_to"><dt class="text-xs text-slate-500">Verantwortlich</dt><dd class="font-medium text-slate-800">{{ userName(selected.assigned_to) }}</dd></div><div v-if="selected.page_url"><dt class="text-xs text-slate-500">Betroffene Seite</dt><dd><a :href="selected.page_url" class="break-all text-zbb hover:underline">{{ selected.page_url }}</a></dd></div><div v-if="selected.app_version"><dt class="text-xs text-slate-500">Programmversion</dt><dd class="font-medium text-slate-800">{{ selected.app_version }}</dd></div></dl></section>

                            <section v-if="selected.history?.length" class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold text-slate-950">Statusverlauf</h3><ol class="mt-4 space-y-4 border-l border-slate-200 pl-4"><li v-for="entry in selected.history" :key="entry.id" class="relative"><span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-zbb ring-4 ring-white"></span><p class="text-sm font-medium text-slate-800">{{ optionLabel('statuses', entry.to_status) }}</p><p class="text-xs text-slate-500">{{ formatDate(entry.created_at) }} · {{ userName(entry.user) }}</p><p v-if="entry.note" class="mt-1 text-xs text-slate-600">{{ entry.note }}</p></li></ol></section>

                            <button v-if="canManage" type="button" class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" @click="deleteFeedback">Meldung löschen</button>
                        </aside>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

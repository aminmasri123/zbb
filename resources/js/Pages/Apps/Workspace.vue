<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    section: String,
    items: Array,
    projects: Array,
    people: Array,
    shareTeams: { type: Array, default: () => [] },
    visibilityOptions: Array,
    currentFolder: Object,
    breadcrumbs: { type: Array, default: () => [] },
    pagination: Object,
    fileFilters: Object,
    fileStats: Object,
    taskTemplates: { type: Array, default: () => [] },
    taskColumns: { type: Array, default: () => [] },
});

const page = usePage();
const nav = [
    { key: 'calendar', label: 'Kalender', route: 'apps.calendar', icon: 'la-calendar' },
    { key: 'contacts', label: 'Kontakte', route: 'apps.contacts', icon: 'la-address-book' },
    { key: 'files', label: 'Dateimanager', route: 'apps.files', icon: 'la-folder-open' },
    { key: 'tasks', label: 'Taskmanager', route: 'apps.tasks', icon: 'la-tasks' },
    { key: 'popups', label: 'Popups', route: 'apps.popups', icon: 'la-bullhorn' },
];

const title = computed(() => nav.find((item) => item.key === props.section)?.label || 'Apps');
const selectedShare = ref(null);
const selectedEdit = ref(null);
const selectedFile = ref(null);
const selectedWorkflowTemplate = ref(null);
const selectedOwnerTransfer = ref(null);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const showFolderModal = ref(false);
const showUploadModal = ref(false);
const uploadMenuOpen = ref(false);
const sharePersonSearch = ref('');
const shareTeamSearch = ref('');
const isFileDragOver = ref(false);
const dragDepth = ref(0);
const fileInput = ref(null);
const folderInput = ref(null);
const fileView = ref(localStorage.getItem('zbb-file-view') || 'grid');
const fileSearch = ref(props.fileFilters?.search || '');
const fileType = ref(props.fileFilters?.type || 'all');
const fileSort = ref(props.fileFilters?.sort || 'name');
const fileDirection = ref(props.fileFilters?.direction || 'asc');
const editFileForm = useForm({ name: '', notes: '', parent_id: '', visibility: 'private', project_id: '', team_id: '' });

const baseVisibility = () => ({
    visibility: 'private',
    project_id: '',
    team_id: '',
});

const forms = {
    folder: useForm({ name: '', parent_id: props.currentFolder?.id || '', ...baseVisibility() }),
    upload: useForm({ files: [], relative_paths: [], parent_id: props.currentFolder?.id || '', ...baseVisibility() }),
    calendar: useForm({ title: '', description: '', starts_at: '', ends_at: '', all_day: false, location: '', color: '#f97316', ...baseVisibility() }),
    contact: useForm({ name: '', organization: '', role: '', email: '', phone: '', notes: '', ...baseVisibility() }),
    task: useForm({ title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_at: '', ...baseVisibility() }),
    workflowTemplate: useForm({
        name: '',
        description: '',
        steps: [
            { title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_offset_days: '' },
        ],
        ...baseVisibility(),
    }),
    popup: useForm({ title: '', message: '', level: 'info', starts_at: '', ends_at: '', active: true, ...baseVisibility() }),
};

const shareForm = useForm({ person_ids: [], team_ids: [], emails: '', permission: 'view', message: '', send_notification: true });
const mailForm = useForm({ email: '', message: '' });
const workflowApplyForm = useForm({ project_id: '', assignee_person_id: '', start_date: '' });
const ownerTransferForm = useForm({ person_id: '' });
const currentUserId = computed(() => page.props.auth?.user?.id);
const filteredSharePeople = computed(() => {
    const search = sharePersonSearch.value.trim().toLowerCase();

    return (props.people || []).filter((person) => {
        const label = `${person.vorname || ''} ${person.nachname || ''} ${person.user?.email || ''} ${person.typ || ''}`.toLowerCase();
        return !search || label.includes(search);
    });
});
const filteredShareTeams = computed(() => {
    const search = shareTeamSearch.value.trim().toLowerCase();

    return (props.shareTeams || []).filter((team) => !search || `${team.name || ''}`.toLowerCase().includes(search));
});
const canCreateInCurrentFolder = computed(() => !props.currentFolder || props.currentFolder?.can?.write);

const tasksByStatus = computed(() => {
    const grouped = {};
    (props.taskColumns || []).forEach((column) => grouped[column.value] = []);
    (props.items || []).forEach((item) => {
        const key = grouped[item.status] ? item.status : 'open';
        grouped[key].push(item);
    });
    return grouped;
});

watch(() => props.currentFolder, (folder) => {
    forms.folder.parent_id = folder?.id || '';
    forms.upload.parent_id = folder?.id || '';
});

watch(fileView, (value) => localStorage.setItem('zbb-file-view', value));

function submitForm(key, routeName, options = {}) {
    forms[key].post(route(routeName), {
        preserveScroll: true,
        forceFormData: options.forceFormData || false,
        onSuccess: () => {
            forms[key].reset();
            if (key === 'folder' || key === 'upload') {
                forms[key].parent_id = props.currentFolder?.id || '';
            }
            options.onSuccess?.();
        },
    });
}

function resetUploadSelection() {
    forms.upload.files = [];
    forms.upload.relative_paths = [];
}

function openFolderModal() {
    forms.folder.clearErrors();
    forms.folder.name = '';
    forms.folder.parent_id = props.currentFolder?.id || '';
    showFolderModal.value = true;
}

function openUploadModal(picker = null) {
    forms.upload.clearErrors();
    forms.upload.parent_id = props.currentFolder?.id || '';
    uploadMenuOpen.value = false;
    showUploadModal.value = true;

    if (picker === 'files' || picker === 'folder') {
        nextTick(() => {
            if (picker === 'folder') {
                triggerFolderPicker();
            } else {
                triggerFilePicker();
            }
        });
    }
}

function triggerFilePicker() {
    fileInput.value?.click();
}

function triggerFolderPicker() {
    folderInput.value?.click();
}

function selectedUploadLabel() {
    const count = forms.upload.files.length;

    if (!count) {
        return 'Dateien oder Ordner auswählen';
    }

    return count === 1 ? forms.upload.relative_paths[0] || forms.upload.files[0]?.name : `${count} Dateien ausgewählt`;
}

function setUploadSelection(items) {
    forms.upload.clearErrors();
    forms.upload.files = items.map((item) => item.file);
    forms.upload.relative_paths = items.map((item) => item.relativePath || item.file.name);
}

function selectUploadFiles(event) {
    const files = Array.from(event.target.files || []);
    setUploadSelection(files.map((file) => ({
        file,
        relativePath: file.webkitRelativePath || file.name,
    })));
    event.target.value = '';
}

function submitUpload() {
    forms.upload.post(route('apps.files.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showUploadModal.value = false;
            resetUploadSelection();
            forms.upload.reset();
            forms.upload.parent_id = props.currentFolder?.id || '';
        },
    });
}

function hasFileDrag(event) {
    return Array.from(event.dataTransfer?.types || []).includes('Files');
}

function handleFileDragEnter(event) {
    if (!canCreateInCurrentFolder.value) return;
    if (!hasFileDrag(event)) return;
    dragDepth.value += 1;
    isFileDragOver.value = true;
}

function handleFileDragOver(event) {
    if (!canCreateInCurrentFolder.value) return;
    if (!hasFileDrag(event)) return;
    event.dataTransfer.dropEffect = 'copy';
    isFileDragOver.value = true;
}

function handleFileDragLeave(event) {
    if (!hasFileDrag(event)) return;
    dragDepth.value = Math.max(0, dragDepth.value - 1);
    isFileDragOver.value = dragDepth.value > 0;
}

async function handleFileDrop(event) {
    if (!canCreateInCurrentFolder.value) return;
    if (!hasFileDrag(event)) return;
    dragDepth.value = 0;
    isFileDragOver.value = false;

    const items = await collectDroppedUploadItems(event.dataTransfer);

    if (!items.length) return;

    setUploadSelection(items);
    openUploadModal();
}

async function collectDroppedUploadItems(dataTransfer) {
    const transferItems = Array.from(dataTransfer?.items || []);
    const entries = transferItems
        .map((item) => typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null)
        .filter(Boolean);

    if (entries.length) {
        const collected = [];

        for (const entry of entries) {
            collected.push(...await collectEntryFiles(entry));
        }

        return collected;
    }

    return Array.from(dataTransfer?.files || []).map((file) => ({
        file,
        relativePath: file.webkitRelativePath || file.name,
    }));
}

function readEntryFile(entry) {
    return new Promise((resolve, reject) => entry.file(resolve, reject));
}

function readDirectoryEntries(reader) {
    return new Promise((resolve, reject) => {
        const entries = [];

        const readBatch = () => {
            reader.readEntries((batch) => {
                if (!batch.length) {
                    resolve(entries);
                    return;
                }

                entries.push(...batch);
                readBatch();
            }, reject);
        };

        readBatch();
    });
}

async function collectEntryFiles(entry, prefix = '') {
    if (entry.isFile) {
        const file = await readEntryFile(entry);

        return [{
            file,
            relativePath: `${prefix}${file.name}`,
        }];
    }

    if (!entry.isDirectory) {
        return [];
    }

    const children = await readDirectoryEntries(entry.createReader());
    const files = [];

    for (const child of children) {
        files.push(...await collectEntryFiles(child, `${prefix}${entry.name}/`));
    }

    return files;
}

function addWorkflowStep() {
    forms.workflowTemplate.steps.push({ title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_offset_days: '' });
}

function removeWorkflowStep(index) {
    if (forms.workflowTemplate.steps.length === 1) return;
    forms.workflowTemplate.steps.splice(index, 1);
}

function submitWorkflowTemplate() {
    forms.workflowTemplate.post(route('apps.tasks.workflows.store'), {
        preserveScroll: true,
        onSuccess: () => {
            forms.workflowTemplate.reset();
            forms.workflowTemplate.steps = [{ title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_offset_days: '' }];
        },
    });
}

function openWorkflowTemplate(template) {
    selectedWorkflowTemplate.value = template;
    workflowApplyForm.reset();
    workflowApplyForm.project_id = template.project_id || '';
}

function applyWorkflowTemplate() {
    workflowApplyForm.post(route('apps.tasks.workflows.apply', selectedWorkflowTemplate.value.id), {
        preserveScroll: true,
        onSuccess: () => selectedWorkflowTemplate.value = null,
    });
}

function destroyItem(routeName, id, item = null) {
    deleteTarget.value = {
        routeName,
        id,
        item,
        name: item?.name || item?.title || item?.organization || 'Eintrag',
        type: item?.type === 'folder' ? 'Ordner' : item?.type === 'file' ? 'Datei' : 'Eintrag',
    };
}

function closeDeleteModal() {
    if (deleteProcessing.value) return;
    deleteTarget.value = null;
}

function confirmDeleteItem() {
    if (!deleteTarget.value) return;
    deleteProcessing.value = true;

    router.delete(route(deleteTarget.value.routeName, deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedFile.value?.id === deleteTarget.value?.id && deleteTarget.value?.routeName === 'apps.files.destroy') {
                selectedFile.value = null;
            }

            deleteTarget.value = null;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}

function canOwn(item) {
    return Number(item?.owner_user_id) === Number(currentUserId.value);
}

function canDo(item, action) {
    return Boolean(item?.can?.[action]);
}

function personLabel(person) {
    return `${person.vorname || ''} ${person.nachname || ''}`.trim() || person.user?.email || `Person ${person.id}`;
}

function personDetail(person) {
    const type = person.typ === 'teilnehmer' ? 'Teilnehmer' : 'Person';
    return person.user?.email ? `${type} - ${person.user.email}` : type;
}

function toggleSharePerson(id) {
    const value = Number(id);
    const current = shareForm.person_ids.map(Number);
    shareForm.person_ids = current.includes(value)
        ? current.filter((personId) => personId !== value)
        : [...current, value];
}

function toggleShareTeam(id) {
    const value = Number(id);
    const current = shareForm.team_ids.map(Number);
    shareForm.team_ids = current.includes(value)
        ? current.filter((teamId) => teamId !== value)
        : [...current, value];
}

function sharePermissionLabel(permission) {
    return ({ manage: 'Alles', edit: 'Schreiben', view: 'Lesen', owner: 'Besitzer' })[permission] || permission;
}

function visitFiles(params = {}) {
    router.get(route('apps.files'), {
        folder: props.currentFolder?.id || undefined,
        search: fileSearch.value || undefined,
        type: fileType.value !== 'all' ? fileType.value : undefined,
        sort: fileSort.value,
        direction: fileDirection.value,
        ...params,
    }, {
        preserveState: true,
        replace: true,
    });
}

function resetFileFilters() {
    fileSearch.value = '';
    fileType.value = 'all';
    fileSort.value = 'name';
    fileDirection.value = 'asc';
    visitFiles({ search: undefined, type: undefined, sort: 'name', direction: 'asc' });
}

function openFileEdit(item) {
    selectedEdit.value = item;
    editFileForm.name = item.name || '';
    editFileForm.notes = item.notes || '';
    editFileForm.parent_id = item.parent_id || '';
    editFileForm.visibility = item.visibility || 'private';
    editFileForm.project_id = item.project_id || '';
    editFileForm.team_id = item.team_id || '';
}

function submitFileEdit() {
    editFileForm.put(route('apps.files.update', selectedEdit.value.id), {
        preserveScroll: true,
        onSuccess: () => selectedEdit.value = null,
    });
}

function updateTaskStatus(item, status) {
    router.put(route('apps.tasks.update', item.id), {
        ...normalizeItem(item),
        assignee_person_id: item.assignee_person_id || '',
        status,
    }, { preserveScroll: true });
}

function statusLabel(value) {
    return props.taskColumns.find((column) => column.value === value)?.label || value;
}

function assigneeLabel(item) {
    return item.assignee ? `${item.assignee.nachname}, ${item.assignee.vorname}` : 'Nicht zugewiesen';
}

function priorityLabel(value) {
    return ({ low: 'Niedrig', normal: 'Normal', high: 'Hoch' })[value] || value;
}

function normalizeItem(item) {
    return {
        ...item,
        starts_at: item.starts_at ? item.starts_at.slice(0, 16) : '',
        ends_at: item.ends_at ? item.ends_at.slice(0, 16) : '',
        due_at: item.due_at ? item.due_at.slice(0, 10) : '',
        project_id: item.project_id || '',
        team_id: item.team_id || '',
    };
}

function openShare(item, type) {
    selectedShare.value = { ...item, shareType: type };
    shareForm.reset();
    shareForm.clearErrors();
    mailForm.reset();
    sharePersonSearch.value = '';
    shareTeamSearch.value = '';
}

function submitShare() {
    shareForm.post(route('apps.share', { type: selectedShare.value.shareType, id: selectedShare.value.id }), {
        preserveScroll: true,
        onSuccess: () => selectedShare.value = null,
    });
}

function openShareFromEdit() {
    const item = selectedEdit.value;
    selectedEdit.value = null;
    openShare(item, 'file');
}

function sendFileMail() {
    mailForm.post(route('apps.files.mail', selectedShare.value.id), {
        preserveScroll: true,
        onSuccess: () => selectedShare.value = null,
    });
}

function openOwnerTransfer(item) {
    selectedOwnerTransfer.value = item;
    ownerTransferForm.reset();
    ownerTransferForm.clearErrors();
}

function submitOwnerTransfer() {
    ownerTransferForm.put(route('apps.files.owner.update', selectedOwnerTransfer.value.id), {
        preserveScroll: true,
        onSuccess: () => selectedOwnerTransfer.value = null,
    });
}

function fileRoute(item) {
    return item.type === 'folder'
        ? route('apps.files', { folder: item.id })
        : route('apps.files.download', item.id);
}

function fileIcon(item) {
    if (item.type === 'folder') return 'la-folder';
    const mime = item.mime_type || '';
    if (mime.includes('pdf')) return 'la-file-pdf';
    if (mime.includes('image')) return 'la-file-image';
    if (mime.includes('spreadsheet') || mime.includes('excel')) return 'la-file-excel';
    if (mime.includes('word') || mime.includes('document')) return 'la-file-word';
    if (mime.includes('zip') || mime.includes('compressed')) return 'la-file-archive';
    return 'la-file';
}

function formatBytes(bytes = 0) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / Math.pow(1024, index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

function visibilityLabel(value) {
    return props.visibilityOptions.find((option) => option.value === value)?.label || value;
}

function ownerLabel(item) {
    return item.owner?.username || item.owner?.email || 'Unbekannt';
}
</script>

<template>
    <AppLayout :title="title">
        <template #header>{{ title }}</template>

        <div class="min-h-screen bg-gray-50 py-5">
            <div class="mx-auto max-w-7xl px-4">
                <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="route(item.route)"
                        :class="[
                            'inline-flex items-center gap-2 rounded border px-3 py-2 text-sm font-medium',
                            section === item.key ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 bg-white text-gray-700 hover:border-orange-300',
                        ]"
                    >
                        <i :class="['la', item.icon]"></i>
                        {{ item.label }}
                    </Link>
                </div>

                <div :class="section === 'files' ? 'grid grid-cols-1 gap-5' : 'grid grid-cols-1 gap-5 lg:grid-cols-[360px_1fr]'">
                    <aside v-if="section !== 'files'" class="rounded border border-gray-200 bg-white p-4 shadow-sm">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Neu anlegen</h2>

                        <form v-if="section === 'calendar'" class="space-y-3" @submit.prevent="submitForm('calendar', 'apps.calendar.store')">
                            <input v-model="forms.calendar.title" class="w-full rounded border-gray-300 text-sm" placeholder="Titel" />
                            <input v-model="forms.calendar.starts_at" type="datetime-local" class="w-full rounded border-gray-300 text-sm" />
                            <input v-model="forms.calendar.ends_at" type="datetime-local" class="w-full rounded border-gray-300 text-sm" />
                            <input v-model="forms.calendar.location" class="w-full rounded border-gray-300 text-sm" placeholder="Ort" />
                            <textarea v-model="forms.calendar.description" class="w-full rounded border-gray-300 text-sm" rows="3" placeholder="Beschreibung"></textarea>
                            <VisibilityFields :form="forms.calendar" :projects="projects" :options="visibilityOptions" />
                            <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Termin speichern</button>
                        </form>

                        <form v-if="section === 'contacts'" class="space-y-3" @submit.prevent="submitForm('contact', 'apps.contacts.store')">
                            <input v-model="forms.contact.name" class="w-full rounded border-gray-300 text-sm" placeholder="Name" />
                            <input v-model="forms.contact.organization" class="w-full rounded border-gray-300 text-sm" placeholder="Organisation" />
                            <input v-model="forms.contact.role" class="w-full rounded border-gray-300 text-sm" placeholder="Rolle / Funktion" />
                            <input v-model="forms.contact.email" class="w-full rounded border-gray-300 text-sm" placeholder="E-Mail" />
                            <input v-model="forms.contact.phone" class="w-full rounded border-gray-300 text-sm" placeholder="Telefon" />
                            <textarea v-model="forms.contact.notes" class="w-full rounded border-gray-300 text-sm" rows="3" placeholder="Notizen"></textarea>
                            <VisibilityFields :form="forms.contact" :projects="projects" :options="visibilityOptions" />
                            <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Kontakt speichern</button>
                        </form>

                        <form v-if="section === 'tasks'" class="space-y-3" @submit.prevent="submitForm('task', 'apps.tasks.store')">
                            <h3 class="text-sm font-semibold text-gray-700">Aufgabe</h3>
                            <input v-model="forms.task.title" class="w-full rounded border-gray-300 text-sm" placeholder="Aufgabe" />
                            <select v-model="forms.task.assignee_person_id" class="w-full rounded border-gray-300 text-sm">
                                <option value="">Keine Zuweisung</option>
                                <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="forms.task.status" class="rounded border-gray-300 text-sm">
                                    <option value="open">Offen</option>
                                    <option value="progress">In Arbeit</option>
                                    <option value="done">Erledigt</option>
                                </select>
                                <select v-model="forms.task.priority" class="rounded border-gray-300 text-sm">
                                    <option value="low">Niedrig</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Hoch</option>
                                </select>
                            </div>
                            <input v-model="forms.task.due_at" type="date" class="w-full rounded border-gray-300 text-sm" />
                            <textarea v-model="forms.task.description" class="w-full rounded border-gray-300 text-sm" rows="3" placeholder="Beschreibung"></textarea>
                            <VisibilityFields :form="forms.task" :projects="projects" :options="visibilityOptions" />
                            <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Aufgabe speichern</button>
                        </form>

                        <form v-if="section === 'tasks'" class="mt-6 space-y-3 border-t pt-4" @submit.prevent="submitWorkflowTemplate">
                            <h3 class="text-sm font-semibold text-gray-700">Workflow-Vorlage</h3>
                            <input v-model="forms.workflowTemplate.name" class="w-full rounded border-gray-300 text-sm" placeholder="Vorlagenname" />
                            <textarea v-model="forms.workflowTemplate.description" class="w-full rounded border-gray-300 text-sm" rows="2" placeholder="Beschreibung"></textarea>

                            <div class="space-y-3">
                                <div v-for="(step, index) in forms.workflowTemplate.steps" :key="index" class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-xs font-semibold uppercase text-gray-500">Schritt {{ index + 1 }}</span>
                                        <button type="button" class="text-xs text-red-600 disabled:text-gray-300" :disabled="forms.workflowTemplate.steps.length === 1" @click="removeWorkflowStep(index)">Entfernen</button>
                                    </div>
                                    <input v-model="step.title" class="mb-2 w-full rounded border-gray-300 text-sm" placeholder="Aufgabe" />
                                    <textarea v-model="step.description" class="mb-2 w-full rounded border-gray-300 text-sm" rows="2" placeholder="Beschreibung"></textarea>
                                    <select v-model="step.assignee_person_id" class="mb-2 w-full rounded border-gray-300 text-sm">
                                        <option value="">Zuweisung spaeter festlegen</option>
                                        <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                                    </select>
                                    <div class="grid grid-cols-3 gap-2">
                                        <select v-model="step.status" class="rounded border-gray-300 text-sm">
                                            <option value="open">Offen</option>
                                            <option value="progress">In Arbeit</option>
                                            <option value="done">Erledigt</option>
                                        </select>
                                        <select v-model="step.priority" class="rounded border-gray-300 text-sm">
                                            <option value="low">Niedrig</option>
                                            <option value="normal">Normal</option>
                                            <option value="high">Hoch</option>
                                        </select>
                                        <input v-model="step.due_offset_days" type="number" min="0" class="rounded border-gray-300 text-sm" placeholder="+ Tage" />
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="w-full rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="addWorkflowStep">Schritt hinzufügen</button>
                            <VisibilityFields :form="forms.workflowTemplate" :projects="projects" :options="visibilityOptions" />
                            <button class="w-full rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white">Vorlage speichern</button>
                        </form>

                        <form v-if="section === 'popups'" class="space-y-3" @submit.prevent="submitForm('popup', 'apps.popups.store')">
                            <input v-model="forms.popup.title" class="w-full rounded border-gray-300 text-sm" placeholder="Titel" />
                            <select v-model="forms.popup.level" class="w-full rounded border-gray-300 text-sm">
                                <option value="info">Info</option>
                                <option value="success">Erfolg</option>
                                <option value="warning">Warnung</option>
                                <option value="danger">Wichtig</option>
                            </select>
                            <textarea v-model="forms.popup.message" class="w-full rounded border-gray-300 text-sm" rows="4" placeholder="Nachricht"></textarea>
                            <input v-model="forms.popup.starts_at" type="datetime-local" class="w-full rounded border-gray-300 text-sm" />
                            <input v-model="forms.popup.ends_at" type="datetime-local" class="w-full rounded border-gray-300 text-sm" />
                            <label class="flex items-center gap-2 text-sm"><input v-model="forms.popup.active" type="checkbox" /> Aktiv</label>
                            <VisibilityFields :form="forms.popup" :projects="projects" :options="visibilityOptions" />
                            <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Popup speichern</button>
                        </form>
                    </aside>

                    <section v-if="section === 'files'" class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                        <div class="border-b bg-white px-4 py-4">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                        <Link :href="route('apps.files')" class="font-medium text-gray-700 hover:text-orange-600">Meine Ablage</Link>
                                        <template v-for="crumb in breadcrumbs" :key="crumb.id">
                                            <span>/</span>
                                            <Link :href="route('apps.files', { folder: crumb.id })" class="font-medium text-gray-700 hover:text-orange-600">{{ crumb.name }}</Link>
                                        </template>
                                    </div>
                                    <h2 class="mt-2 text-2xl font-semibold text-gray-950">{{ currentFolder?.name || 'Dateimanager' }}</h2>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <div v-if="canCreateInCurrentFolder" class="relative">
                                        <button type="button" class="inline-flex items-center gap-2 rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600" @click="uploadMenuOpen = !uploadMenuOpen">
                                            <i class="la la-cloud-upload-alt text-lg"></i>
                                            Hochladen
                                            <i class="la la-angle-down text-sm"></i>
                                        </button>
                                        <div v-if="uploadMenuOpen" class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-700 hover:bg-orange-50 hover:text-orange-700" @click="openUploadModal('files')">
                                                <i class="la la-file-upload text-lg"></i>
                                                Dateien auswählen
                                            </button>
                                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-700 hover:bg-orange-50 hover:text-orange-700" @click="openUploadModal('folder')">
                                                <i class="la la-folder-open text-lg"></i>
                                                Ordner auswählen
                                            </button>
                                        </div>
                                    </div>
                                    <button v-if="canCreateInCurrentFolder" type="button" class="inline-flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:border-orange-300 hover:text-orange-700" @click="openFolderModal">
                                        <i class="la la-folder-plus text-lg"></i>
                                        Ordner erstellen
                                    </button>
                                    <button type="button" :class="['rounded border px-3 py-2 text-sm', fileView === 'grid' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-gray-600']" title="Kachelansicht" @click="fileView = 'grid'">
                                        <i class="la la-th-large"></i>
                                    </button>
                                    <button type="button" :class="['rounded border px-3 py-2 text-sm', fileView === 'list' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-gray-600']" title="Listenansicht" @click="fileView = 'list'">
                                        <i class="la la-list"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto_auto_auto]">
                                <div class="relative">
                                    <i class="la la-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input v-model="fileSearch" class="w-full rounded border-gray-300 pl-9 text-sm" placeholder="Dateien, Ordner oder Notizen suchen" @keyup.enter="visitFiles({ page: 1 })" />
                                </div>
                                <select v-model="fileType" class="rounded border-gray-300 text-sm" @change="visitFiles({ page: 1 })">
                                    <option value="all">Alle Typen</option>
                                    <option value="folder">Nur Ordner</option>
                                    <option value="file">Nur Dateien</option>
                                </select>
                                <select v-model="fileSort" class="rounded border-gray-300 text-sm" @change="visitFiles({ page: 1 })">
                                    <option value="name">Name</option>
                                    <option value="updated">Zuletzt geändert</option>
                                    <option value="size">Größe</option>
                                </select>
                                <div class="flex gap-2">
                                    <button class="rounded border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-orange-300" @click="fileDirection = fileDirection === 'asc' ? 'desc' : 'asc'; visitFiles({ page: 1 })">
                                        <i :class="['la', fileDirection === 'asc' ? 'la-sort-alpha-down' : 'la-sort-alpha-up']"></i>
                                    </button>
                                    <button class="rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white" @click="visitFiles({ page: 1 })">Suchen</button>
                                    <button class="rounded border border-gray-200 px-3 py-2 text-sm text-gray-600" @click="resetFileFilters">Reset</button>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs text-gray-500">Einträge</p>
                                    <p class="text-lg font-semibold text-gray-950">{{ fileStats?.total || 0 }}</p>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs text-gray-500">Ordner</p>
                                    <p class="text-lg font-semibold text-gray-950">{{ fileStats?.folders || 0 }}</p>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs text-gray-500">Dateien</p>
                                    <p class="text-lg font-semibold text-gray-950">{{ fileStats?.files || 0 }}</p>
                                </div>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <p class="text-xs text-gray-500">Größe</p>
                                    <p class="text-lg font-semibold text-gray-950">{{ formatBytes(fileStats?.size || 0) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-0 xl:grid-cols-[1fr_320px]">
                            <div
                                :class="['min-h-[460px] p-4 transition', isFileDragOver ? 'bg-orange-50' : '']"
                                @dragenter.prevent="handleFileDragEnter"
                                @dragover.prevent="handleFileDragOver"
                                @dragleave.prevent="handleFileDragLeave"
                                @drop.prevent="handleFileDrop"
                            >
                                <div v-if="isFileDragOver" class="mb-4 rounded border border-dashed border-orange-300 bg-white px-4 py-5 text-center text-sm font-semibold text-orange-700">
                                    Dateien oder Ordner hier ablegen
                                </div>
                                <div v-if="items.length === 0" class="flex min-h-[320px] flex-col items-center justify-center rounded border border-dashed border-gray-300 text-center">
                                    <i class="la la-folder-open mb-3 text-5xl text-gray-300"></i>
                                    <p class="text-sm font-semibold text-gray-700">Keine Einträge gefunden</p>
                                    <p class="mt-1 text-xs text-gray-500">Dateien oder ganze Ordner hierher ziehen.</p>
                                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                                        <button v-if="canCreateInCurrentFolder" type="button" class="inline-flex items-center gap-2 rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white" @click="openUploadModal">
                                            <i class="la la-cloud-upload-alt"></i>
                                            Hochladen
                                        </button>
                                        <button v-if="canCreateInCurrentFolder" type="button" class="inline-flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700" @click="openFolderModal">
                                            <i class="la la-folder-plus"></i>
                                            Ordner erstellen
                                        </button>
                                    </div>
                                </div>

                                <div v-else-if="fileView === 'grid'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                    <div
                                        v-for="item in items"
                                        :key="item.id"
                                        :class="['group rounded border p-4 transition hover:border-orange-300 hover:shadow-sm', selectedFile?.id === item.id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-white']"
                                        @click="selectedFile = item"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <a :href="fileRoute(item)" class="flex min-w-0 items-center gap-3">
                                                <span :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded text-2xl', item.type === 'folder' ? 'bg-amber-100 text-amber-600' : 'bg-blue-50 text-blue-600']">
                                                    <i :class="['la', fileIcon(item)]"></i>
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-gray-950">{{ item.name }}</span>
                                                    <span class="mt-1 block text-xs text-gray-500">{{ item.type === 'folder' ? 'Ordner' : formatBytes(item.size) }}</span>
                                                </span>
                                            </a>
                                            <div class="flex shrink-0 items-center gap-1 opacity-100 md:opacity-0 md:group-hover:opacity-100">
                                                <button v-if="canDo(item, 'write') || canDo(item, 'share')" class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-orange-300" title="Bearbeiten" @click.stop="openFileEdit(item)">
                                                    <i class="la la-pen"></i>
                                                </button>
                                                <button v-if="canDo(item, 'delete')" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50" title="Löschen" @click.stop="destroyItem('apps.files.destroy', item.id, item)">
                                                    <i class="la la-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                                            <span>{{ visibilityLabel(item.visibility) }}</span>
                                            <span>{{ formatDate(item.updated_at) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="overflow-hidden rounded border border-gray-200">
                                    <div class="grid grid-cols-[1fr_110px_120px_120px] bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                                        <span>Name</span>
                                        <span>Größe</span>
                                        <span>Sichtbarkeit</span>
                                        <span>Geändert</span>
                                    </div>
                                    <div v-for="item in items" :key="item.id" class="grid cursor-pointer grid-cols-[1fr_110px_120px_120px] items-center border-t px-4 py-3 text-sm hover:bg-orange-50" @click="selectedFile = item">
                                        <div class="flex min-w-0 items-center justify-between gap-2">
                                            <a :href="fileRoute(item)" class="min-w-0 truncate font-semibold text-gray-900 hover:text-orange-600" @click.stop>
                                                <i :class="['la mr-2', fileIcon(item)]"></i>{{ item.name }}
                                            </a>
                                            <button v-if="canDo(item, 'delete')" class="shrink-0 rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50" title="Löschen" @click.stop="destroyItem('apps.files.destroy', item.id, item)">
                                                <i class="la la-trash"></i>
                                            </button>
                                        </div>
                                        <span class="text-gray-500">{{ item.type === 'folder' ? '-' : formatBytes(item.size) }}</span>
                                        <span class="text-gray-500">{{ visibilityLabel(item.visibility) }}</span>
                                        <span class="text-gray-500">{{ formatDate(item.updated_at) }}</span>
                                    </div>
                                </div>

                                <div v-if="pagination && pagination.last_page > 1" class="mt-4 flex items-center justify-between border-t pt-4 text-sm">
                                    <button class="rounded border px-3 py-2 disabled:opacity-40" :disabled="!pagination.prev_page_url" @click="visitFiles({ page: pagination.current_page - 1 })">Zurück</button>
                                    <span class="text-gray-500">Seite {{ pagination.current_page }} von {{ pagination.last_page }} · {{ pagination.total }} Einträge</span>
                                    <button class="rounded border px-3 py-2 disabled:opacity-40" :disabled="!pagination.next_page_url" @click="visitFiles({ page: pagination.current_page + 1 })">Weiter</button>
                                </div>
                            </div>

                            <aside class="border-t bg-gray-50 p-4 xl:border-l xl:border-t-0">
                                <div v-if="selectedFile" class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <span :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded text-2xl', selectedFile.type === 'folder' ? 'bg-amber-100 text-amber-600' : 'bg-blue-50 text-blue-600']">
                                            <i :class="['la', fileIcon(selectedFile)]"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-gray-950">{{ selectedFile.name }}</h3>
                                            <p class="text-sm text-gray-500">{{ selectedFile.type === 'folder' ? 'Ordner' : selectedFile.mime_type || 'Datei' }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <a :href="fileRoute(selectedFile)" class="rounded bg-gray-900 px-3 py-2 text-center font-semibold text-white">
                                            {{ selectedFile.type === 'folder' ? 'Öffnen' : 'Download' }}
                                        </a>
                                        <button v-if="canDo(selectedFile, 'share')" class="rounded border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700" @click="openShare(selectedFile, 'file')">Teilen</button>
                                        <button v-if="canDo(selectedFile, 'write')" class="rounded border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700" @click="openFileEdit(selectedFile)">Bearbeiten</button>
                                        <button v-if="canDo(selectedFile, 'transfer_owner')" class="rounded border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700" @click="openOwnerTransfer(selectedFile)">Besitzer</button>
                                        <button v-if="canDo(selectedFile, 'delete')" class="rounded border border-red-200 bg-white px-3 py-2 font-semibold text-red-600" @click="destroyItem('apps.files.destroy', selectedFile.id, selectedFile)">Löschen</button>
                                    </div>

                                    <dl class="space-y-2 rounded border border-gray-200 bg-white p-3 text-sm">
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Besitzer</dt><dd class="text-right text-gray-900">{{ ownerLabel(selectedFile) }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Größe</dt><dd class="text-right text-gray-900">{{ selectedFile.type === 'folder' ? '-' : formatBytes(selectedFile.size) }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Sichtbar</dt><dd class="text-right text-gray-900">{{ visibilityLabel(selectedFile.visibility) }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Geändert</dt><dd class="text-right text-gray-900">{{ formatDate(selectedFile.updated_at) }}</dd></div>
                                    </dl>

                                    <p v-if="selectedFile.notes" class="rounded border border-gray-200 bg-white p-3 text-sm text-gray-700">{{ selectedFile.notes }}</p>
                                </div>
                                <div v-else class="rounded border border-dashed border-gray-300 bg-white p-5 text-center text-sm text-gray-500">
                                    <i class="la la-info-circle mb-2 text-3xl text-gray-300"></i>
                                    <p>Eintrag auswählen, um Details und Aktionen zu sehen.</p>
                                </div>
                            </aside>
                        </div>
                    </section>

                    <section v-else-if="section === 'tasks'" class="space-y-4">
                        <div class="rounded border border-gray-200 bg-white shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Taskmanager Board</h2>
                                    <p class="text-sm text-gray-500">Aufgaben nach Status, Zuweisung, Projekt und Ersteller.</p>
                                </div>
                                <span class="text-sm text-gray-500">{{ items.length }} Aufgaben</span>
                            </div>

                            <div class="grid gap-3 p-4 xl:grid-cols-3">
                                <div v-for="column in taskColumns" :key="column.value" class="min-h-[420px] rounded border border-gray-200 bg-gray-50">
                                    <div class="flex items-center justify-between border-b px-3 py-3">
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900">{{ column.label }}</h3>
                                            <p class="text-xs text-gray-500">{{ column.hint }}</p>
                                        </div>
                                        <span class="rounded bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ tasksByStatus[column.value]?.length || 0 }}</span>
                                    </div>

                                    <div class="space-y-3 p-3">
                                        <div v-if="(tasksByStatus[column.value] || []).length === 0" class="rounded border border-dashed border-gray-300 bg-white p-4 text-center text-sm text-gray-500">
                                            Keine Aufgaben
                                        </div>

                                        <article v-for="item in tasksByStatus[column.value]" :key="item.id" class="rounded border border-gray-200 bg-white p-3 shadow-sm">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <h3 class="break-words text-sm font-semibold text-gray-950">{{ item.title }}</h3>
                                                    <p v-if="item.workflow_template" class="mt-1 text-xs text-orange-700">{{ item.workflow_template.name }}</p>
                                                </div>
                                                <span :class="['shrink-0 rounded px-2 py-0.5 text-xs font-semibold', item.priority === 'high' ? 'bg-red-100 text-red-700' : item.priority === 'low' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-700']">
                                                    {{ priorityLabel(item.priority) }}
                                                </span>
                                            </div>

                                            <p v-if="item.description" class="mt-2 line-clamp-3 text-sm text-gray-600">{{ item.description }}</p>

                                            <dl class="mt-3 space-y-1 text-xs text-gray-500">
                                                <div class="flex justify-between gap-3"><dt>Zugewiesen</dt><dd class="text-right text-gray-800">{{ assigneeLabel(item) }}</dd></div>
                                                <div class="flex justify-between gap-3"><dt>Erstellt von</dt><dd class="text-right text-gray-800">{{ ownerLabel(item) }}</dd></div>
                                                <div v-if="item.due_at" class="flex justify-between gap-3"><dt>Faellig</dt><dd class="text-right text-gray-800">{{ formatDate(item.due_at) }}</dd></div>
                                                <div class="flex justify-between gap-3"><dt>Sichtbar</dt><dd class="text-right text-gray-800">{{ visibilityLabel(item.visibility) }}</dd></div>
                                            </dl>

                                            <div class="mt-3 grid grid-cols-3 gap-1">
                                                <button
                                                    v-for="target in taskColumns"
                                                    :key="target.value"
                                                    :class="['rounded border px-2 py-1 text-xs font-semibold', item.status === target.value ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-gray-600 hover:border-orange-300']"
                                                    @click="updateTaskStatus(item, target.value)"
                                                >
                                                    {{ statusLabel(target.value) }}
                                                </button>
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <button class="rounded border px-2 py-1 text-xs hover:border-orange-400" @click="openShare(item, 'task')">Teilen</button>
                                                <button class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="destroyItem('apps.tasks.destroy', item.id, item)">Löschen</button>
                                            </div>
                                        </article>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded border border-gray-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Workflow-Vorlagen</h2>
                                    <p class="text-sm text-gray-500">Gespeicherte Muster können als Kopie in ein Projekt übernommen werden.</p>
                                </div>
                                <span class="text-sm text-gray-500">{{ taskTemplates.length }} Vorlagen</span>
                            </div>

                            <div class="grid gap-3 p-4 lg:grid-cols-2">
                                <div v-if="taskTemplates.length === 0" class="rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 lg:col-span-2">
                                    Noch keine Workflow-Vorlagen vorhanden.
                                </div>

                                <article v-for="template in taskTemplates" :key="template.id" class="rounded border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-950">{{ template.name }}</h3>
                                            <p v-if="template.description" class="mt-1 text-sm text-gray-600">{{ template.description }}</p>
                                            <p class="mt-1 text-xs text-gray-400">Erstellt von {{ ownerLabel(template) }} · {{ visibilityLabel(template.visibility) }}</p>
                                        </div>
                                        <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ template.steps?.length || 0 }} Schritte</span>
                                    </div>

                                    <ol class="mt-3 space-y-2">
                                        <li v-for="step in template.steps" :key="step.id" class="rounded bg-gray-50 px-3 py-2 text-sm">
                                            <div class="font-medium text-gray-900">{{ step.title }}</div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ statusLabel(step.status) }} · {{ priorityLabel(step.priority) }}
                                                <span v-if="step.assignee"> · {{ step.assignee.nachname }}, {{ step.assignee.vorname }}</span>
                                                <span v-if="step.due_offset_days !== null"> · +{{ step.due_offset_days }} Tage</span>
                                            </div>
                                        </li>
                                    </ol>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button class="rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white" @click="openWorkflowTemplate(template)">In Projekt kopieren</button>
                                        <button class="rounded border border-red-200 px-3 py-2 text-sm font-semibold text-red-600" @click="destroyItem('apps.tasks.workflows.destroy', template.id, template)">Deaktivieren</button>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section v-else class="rounded border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b px-4 py-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                                <Link v-if="section === 'files' && currentFolder" :href="route('apps.files')" class="text-sm text-orange-600">Zurück zur Dateiübersicht</Link>
                            </div>
                            <span class="text-sm text-gray-500">{{ items.length }} Einträge</span>
                        </div>

                        <div class="divide-y">
                            <div v-if="items.length === 0" class="p-8 text-center text-sm text-gray-500">Noch keine Einträge vorhanden.</div>

                            <div v-for="item in items" :key="item.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a v-if="section === 'files'" :href="fileRoute(item)" class="font-semibold text-gray-900 hover:text-orange-600">
                                            <i :class="['la mr-1', item.type === 'folder' ? 'la-folder' : 'la-file']"></i>{{ item.name }}
                                        </a>
                                        <h3 v-else class="font-semibold text-gray-900">{{ item.title || item.name }}</h3>
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ visibilityLabel(item.visibility) }}</span>
                                        <span v-if="item.priority === 'high'" class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">Hoch</span>
                                        <span v-if="item.active === false" class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inaktiv</span>
                                    </div>

                                    <p v-if="item.description || item.message || item.notes" class="mt-1 text-sm text-gray-600">{{ item.description || item.message || item.notes }}</p>
                                    <p v-if="section === 'calendar'" class="mt-1 text-sm text-gray-500">{{ item.starts_at }} <span v-if="item.ends_at">bis {{ item.ends_at }}</span> <span v-if="item.location">- {{ item.location }}</span></p>
                                    <p v-if="section === 'contacts'" class="mt-1 text-sm text-gray-500">{{ item.organization }} <span v-if="item.role">- {{ item.role }}</span> <span v-if="item.email">- {{ item.email }}</span> <span v-if="item.phone">- {{ item.phone }}</span></p>
                                    <p v-if="section === 'tasks'" class="mt-1 text-sm text-gray-500">Status: {{ item.status }} <span v-if="item.assignee">- {{ item.assignee.nachname }}, {{ item.assignee.vorname }}</span> <span v-if="item.due_at">- Faellig: {{ item.due_at }}</span></p>
                                    <p class="mt-1 text-xs text-gray-400">Erstellt von {{ ownerLabel(item) }}</p>
                                </div>

                                <div class="flex flex-wrap items-start gap-2 md:justify-end">
                                    <button v-if="section === 'tasks' && item.status !== 'done'" class="rounded border px-2 py-1 text-xs" @click="updateTaskStatus(item, 'done')">Erledigt</button>
                                    <button class="rounded border px-2 py-1 text-xs hover:border-orange-400" @click="openShare(item, section === 'files' ? 'file' : section === 'calendar' ? 'event' : section === 'contacts' ? 'contact' : section === 'tasks' ? 'task' : 'popup')">Teilen</button>
                                    <button
                                        class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                        @click="destroyItem(section === 'files' ? 'apps.files.destroy' : section === 'calendar' ? 'apps.calendar.destroy' : section === 'contacts' ? 'apps.contacts.destroy' : section === 'tasks' ? 'apps.tasks.destroy' : 'apps.popups.destroy', item.id, item)"
                                    >
                                        Löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded bg-white shadow-xl">
                <div class="flex items-start gap-3 border-b px-5 py-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-xl text-red-600">
                        <i class="la la-trash"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-950">{{ deleteTarget.type }} löschen?</h2>
                        <p class="mt-1 break-words text-sm text-gray-600">{{ deleteTarget.name }}</p>
                    </div>
                </div>

                <div class="space-y-3 px-5 py-4 text-sm text-gray-700">
                    <p>Bist du sicher, dass du diesen Eintrag löschen möchtest?</p>
                    <p v-if="deleteTarget.item?.type === 'folder'" class="rounded border border-red-100 bg-red-50 p-3 text-red-700">
                        Dieser Ordner und alle enthaltenen Dateien und Unterordner werden entfernt.
                    </p>
                    <p class="text-xs text-gray-500">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>

                <div class="flex justify-end gap-2 border-t px-5 py-4">
                    <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" :disabled="deleteProcessing" @click="closeDeleteModal">
                        Abbrechen
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded bg-red-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="deleteProcessing" @click="confirmDeleteItem">
                        <i class="la la-trash"></i>
                        {{ deleteProcessing ? 'Wird gelöscht ...' : 'Ja, löschen' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showFolderModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950">Ordner erstellen</h2>
                        <p class="text-sm text-gray-500">{{ currentFolder?.name || 'Meine Ablage' }}</p>
                    </div>
                    <button class="rounded p-1 text-2xl leading-none text-gray-500 hover:bg-gray-100" @click="showFolderModal = false">&times;</button>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submitForm('folder', 'apps.files.folder.store', { onSuccess: () => showFolderModal = false })">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Ordnername</label>
                        <input v-model="forms.folder.name" class="w-full rounded border-gray-300 text-sm" autofocus placeholder="Neuer Ordner" />
                        <p v-if="forms.folder.errors.name" class="mt-1 text-xs text-red-600">{{ forms.folder.errors.name }}</p>
                    </div>

                    <VisibilityFields :form="forms.folder" :projects="projects" :options="visibilityOptions" />

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="showFolderModal = false">Abbrechen</button>
                        <button class="inline-flex items-center gap-2 rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white" :disabled="forms.folder.processing">
                            <i class="la la-folder-plus"></i>
                            Erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950">Dateien oder Ordner hochladen</h2>
                        <p class="text-sm text-gray-500">{{ currentFolder?.name || 'Meine Ablage' }}</p>
                    </div>
                    <button class="rounded p-1 text-2xl leading-none text-gray-500 hover:bg-gray-100" @click="showUploadModal = false; resetUploadSelection()">&times;</button>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submitUpload">
                    <div
                        :class="['flex flex-col items-center justify-center rounded border border-dashed px-4 py-8 text-center transition', isFileDragOver ? 'border-orange-400 bg-orange-50' : 'border-gray-300 bg-gray-50 hover:border-orange-300 hover:bg-orange-50']"
                        @dragenter.prevent="handleFileDragEnter"
                        @dragover.prevent="handleFileDragOver"
                        @dragleave.prevent="handleFileDragLeave"
                        @drop.prevent="handleFileDrop"
                    >
                        <i class="la la-cloud-upload-alt mb-2 text-4xl text-orange-500"></i>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedUploadLabel() }}</span>
                        <span class="mt-1 text-xs text-gray-500">Dateien oder Ordner hierher ziehen. Maximal 50 MB je Datei.</span>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <button type="button" class="rounded border border-orange-200 bg-white px-3 py-2 text-sm font-semibold text-orange-700 hover:border-orange-400" @click="triggerFilePicker">
                                Dateien auswählen
                            </button>
                            <button type="button" class="rounded border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:border-orange-300 hover:text-orange-700" @click="triggerFolderPicker">
                                Ordner auswählen
                            </button>
                        </div>
                        <input ref="fileInput" type="file" class="hidden" multiple @change="selectUploadFiles" />
                        <input ref="folderInput" type="file" class="hidden" multiple webkitdirectory directory @change="selectUploadFiles" />
                    </div>
                    <p v-if="forms.upload.errors.file" class="text-xs text-red-600">{{ forms.upload.errors.file }}</p>
                    <p v-if="forms.upload.errors.files" class="text-xs text-red-600">{{ forms.upload.errors.files }}</p>
                    <p v-if="forms.upload.errors['files.0']" class="text-xs text-red-600">{{ forms.upload.errors['files.0'] }}</p>

                    <VisibilityFields :form="forms.upload" :projects="projects" :options="visibilityOptions" />

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="showUploadModal = false; resetUploadSelection()">Abbrechen</button>
                        <button class="inline-flex items-center gap-2 rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="forms.upload.processing || !forms.upload.files.length">
                            <i class="la la-cloud-upload-alt"></i>
                            Hochladen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="selectedOwnerTransfer && section === 'files'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950">Besitzer übergeben</h2>
                        <p class="text-sm text-gray-500">{{ selectedOwnerTransfer.name }}</p>
                    </div>
                    <button type="button" class="text-xl text-gray-500" @click="selectedOwnerTransfer = null">&times;</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitOwnerTransfer">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Neuer Besitzer</label>
                        <select v-model="ownerTransferForm.person_id" class="w-full rounded border-gray-300 text-sm">
                            <option value="">Person auswählen</option>
                            <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                        </select>
                        <p v-if="ownerTransferForm.errors.person_id" class="mt-1 text-xs text-red-600">{{ ownerTransferForm.errors.person_id }}</p>
                    </div>

                    <div class="rounded border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800">
                        Bei Ordnern werden auch alle Unterordner und Dateien an den neuen Besitzer übergeben.
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="selectedOwnerTransfer = null">Abbrechen</button>
                        <button class="rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="ownerTransferForm.processing || !ownerTransferForm.person_id">
                            Übergeben
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="selectedEdit && section === 'files'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-xl rounded bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Bearbeiten: {{ selectedEdit.name }}</h2>
                    <button class="text-xl" @click="selectedEdit = null">&times;</button>
                </div>

                <form class="space-y-3" @submit.prevent="submitFileEdit">
                    <input v-model="editFileForm.name" class="w-full rounded border-gray-300 text-sm" placeholder="Name" />
                    <textarea v-model="editFileForm.notes" rows="4" class="w-full rounded border-gray-300 text-sm" placeholder="Notizen"></textarea>
                    <VisibilityFields :form="editFileForm" :projects="projects" :options="visibilityOptions" />
                    <div class="flex justify-end gap-2 pt-2">
                        <button v-if="canDo(selectedEdit, 'share')" type="button" class="rounded border border-orange-200 bg-white px-3 py-2 text-sm font-semibold text-orange-700" @click="openShareFromEdit">Freigeben / Teilen</button>
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="selectedEdit = null">Abbrechen</button>
                        <button class="rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Speichern</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="selectedWorkflowTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-xl rounded bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Workflow kopieren: {{ selectedWorkflowTemplate.name }}</h2>
                    <button class="text-xl" @click="selectedWorkflowTemplate = null">&times;</button>
                </div>

                <form class="space-y-3" @submit.prevent="applyWorkflowTemplate">
                    <select v-model="workflowApplyForm.project_id" class="w-full rounded border-gray-300 text-sm">
                        <option value="">Projekt auswählen</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                    <select v-model="workflowApplyForm.assignee_person_id" class="w-full rounded border-gray-300 text-sm">
                        <option value="">Zuweisungen aus Vorlage behalten</option>
                        <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                    </select>
                    <input v-model="workflowApplyForm.start_date" type="date" class="w-full rounded border-gray-300 text-sm" />

                    <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                        Es werden {{ selectedWorkflowTemplate.steps?.length || 0 }} Aufgaben als Projektkopie erstellt. Besitzer ist die Person, die den Workflow jetzt anlegt.
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="selectedWorkflowTemplate = null">Abbrechen</button>
                        <button class="rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Kopieren</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="selectedShare" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-4xl rounded bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Freigeben: {{ selectedShare.title || selectedShare.name }}</h2>
                    <button class="text-xl" @click="selectedShare = null">&times;</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitShare">
                    <div class="grid gap-4 md:grid-cols-2">
                        <section class="rounded border border-gray-200 bg-gray-50 p-3">
                            <label class="mb-2 block text-sm font-semibold text-gray-800">Personen und Teilnehmer</label>
                            <input v-model="sharePersonSearch" class="mb-2 w-full rounded border-gray-300 text-sm" placeholder="Person suchen ..." />
                            <div class="max-h-52 overflow-y-auto rounded border border-gray-200 bg-white">
                                <button v-for="person in filteredSharePeople" :key="person.id" type="button" class="flex w-full items-start gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-orange-50" @click="toggleSharePerson(person.id)">
                                    <input type="checkbox" class="mt-1 rounded border-gray-300 text-orange-500" :checked="shareForm.person_ids.map(Number).includes(Number(person.id))" readonly />
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-gray-900">{{ personLabel(person) }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ personDetail(person) }}</span>
                                    </span>
                                </button>
                                <p v-if="filteredSharePeople.length === 0" class="p-3 text-sm text-gray-500">Keine Person gefunden.</p>
                            </div>
                        </section>

                        <section class="rounded border border-gray-200 bg-gray-50 p-3">
                            <label class="mb-2 block text-sm font-semibold text-gray-800">Teams</label>
                            <input v-model="shareTeamSearch" class="mb-2 w-full rounded border-gray-300 text-sm" placeholder="Team suchen ..." />
                            <div class="max-h-52 overflow-y-auto rounded border border-gray-200 bg-white">
                                <button v-for="team in filteredShareTeams" :key="team.id" type="button" class="flex w-full items-start gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-orange-50" @click="toggleShareTeam(team.id)">
                                    <input type="checkbox" class="mt-1 rounded border-gray-300 text-orange-500" :checked="shareForm.team_ids.map(Number).includes(Number(team.id))" readonly />
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-gray-900">{{ team.name }}</span>
                                        <span class="block text-xs text-gray-500">Team / Projekt</span>
                                    </span>
                                </button>
                                <p v-if="filteredShareTeams.length === 0" class="p-3 text-sm text-gray-500">Kein Team gefunden.</p>
                            </div>
                        </section>
                    </div>

                    <input v-model="shareForm.emails" class="w-full rounded border-gray-300 text-sm" placeholder="Zusaetzliche E-Mail-Adressen, getrennt mit Komma" />

                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="flex cursor-pointer gap-2 rounded border p-3 text-sm" :class="shareForm.permission === 'view' ? 'border-orange-400 bg-orange-50' : 'border-gray-200'">
                            <input v-model="shareForm.permission" type="radio" value="view" class="mt-1 border-gray-300 text-orange-500" />
                            <span><strong class="block">Lesen</strong><span class="text-xs text-gray-500">Oeffnen und herunterladen</span></span>
                        </label>
                        <label class="flex cursor-pointer gap-2 rounded border p-3 text-sm" :class="shareForm.permission === 'edit' ? 'border-orange-400 bg-orange-50' : 'border-gray-200'">
                            <input v-model="shareForm.permission" type="radio" value="edit" class="mt-1 border-gray-300 text-orange-500" />
                            <span><strong class="block">Schreiben</strong><span class="text-xs text-gray-500">Bearbeiten und hochladen</span></span>
                        </label>
                        <label class="flex cursor-pointer gap-2 rounded border p-3 text-sm" :class="shareForm.permission === 'manage' ? 'border-orange-400 bg-orange-50' : 'border-gray-200'">
                            <input v-model="shareForm.permission" type="radio" value="manage" class="mt-1 border-gray-300 text-orange-500" />
                            <span><strong class="block">Alles</strong><span class="text-xs text-gray-500">Teilen, loeschen, verwalten</span></span>
                        </label>
                    </div>

                    <textarea v-model="shareForm.message" rows="3" class="w-full rounded border-gray-300 text-sm" placeholder="Nachricht"></textarea>
                    <label class="flex items-center gap-2 text-sm"><input v-model="shareForm.send_notification" type="checkbox" /> Im Programm und per Mail benachrichtigen</label>

                    <div v-if="shareForm.errors.targets || shareForm.errors.emails" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ shareForm.errors.targets || shareForm.errors.emails }}
                    </div>

                    <div class="rounded border border-gray-200">
                        <div class="border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">Bereits geteilt</div>
                        <div v-if="selectedShare.shares?.length" class="divide-y">
                            <div v-for="share in selectedShare.shares" :key="share.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-900">{{ share.target_label }}</p>
                                    <p class="text-xs text-gray-500">{{ share.target_detail || share.target_type }}</p>
                                </div>
                                <span class="rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ share.permission_label || sharePermissionLabel(share.permission) }}</span>
                            </div>
                        </div>
                        <p v-else class="px-3 py-3 text-sm text-gray-500">Noch keine Freigaben vorhanden.</p>
                    </div>

                    <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="shareForm.processing">Freigabe speichern</button>

                </form>

                <form v-if="selectedShare.shareType === 'file' && selectedShare.type === 'file'" class="mt-5 space-y-3 border-t pt-4" @submit.prevent="sendFileMail">
                    <h3 class="text-sm font-semibold text-gray-800">Datei direkt per Mail verschicken</h3>
                    <input v-model="mailForm.email" class="w-full rounded border-gray-300 text-sm" placeholder="E-Mail-Adresse" />
                    <textarea v-model="mailForm.message" rows="3" class="w-full rounded border-gray-300 text-sm" placeholder="Nachricht"></textarea>
                    <button class="w-full rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white">Datei senden</button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script>
const VisibilityFields = {
    props: {
        form: Object,
        projects: Array,
        options: Array,
    },
    template: `
        <div class="space-y-2">
            <select v-model="form.visibility" class="w-full rounded border-gray-300 text-sm">
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select v-if="form.visibility === 'project'" v-model="form.project_id" class="w-full rounded border-gray-300 text-sm">
                <option value="">Aktuelles Projekt</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
            </select>
            <input v-if="form.visibility === 'team'" v-model="form.team_id" class="w-full rounded border-gray-300 text-sm" placeholder="Team-ID leer = aktuelles Team" />
        </div>
    `,
};

export default {
    components: { VisibilityFields },
};
</script>

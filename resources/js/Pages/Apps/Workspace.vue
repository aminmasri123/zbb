<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    section: String,
    items: Array,
    projects: Array,
    people: Array,
    visibilityOptions: Array,
    currentFolder: Object,
});

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

const baseVisibility = () => ({
    visibility: 'private',
    project_id: '',
    team_id: '',
});

const forms = {
    folder: useForm({ name: '', parent_id: props.currentFolder?.id || '', ...baseVisibility() }),
    upload: useForm({ file: null, parent_id: props.currentFolder?.id || '', ...baseVisibility() }),
    calendar: useForm({ title: '', description: '', starts_at: '', ends_at: '', all_day: false, location: '', color: '#f97316', ...baseVisibility() }),
    contact: useForm({ name: '', organization: '', role: '', email: '', phone: '', notes: '', ...baseVisibility() }),
    task: useForm({ title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_at: '', ...baseVisibility() }),
    popup: useForm({ title: '', message: '', level: 'info', starts_at: '', ends_at: '', active: true, ...baseVisibility() }),
};

const shareForm = useForm({ person_id: '', email: '', permission: 'view', message: '', send_email: false });
const mailForm = useForm({ email: '', message: '' });

watch(() => props.currentFolder, (folder) => {
    forms.folder.parent_id = folder?.id || '';
    forms.upload.parent_id = folder?.id || '';
});

function submitForm(key, routeName, options = {}) {
    forms[key].post(route(routeName), {
        preserveScroll: true,
        forceFormData: options.forceFormData || false,
        onSuccess: () => {
            forms[key].reset();
            if (key === 'folder' || key === 'upload') {
                forms[key].parent_id = props.currentFolder?.id || '';
            }
        },
    });
}

function destroyItem(routeName, id) {
    if (!confirm('Diesen Eintrag wirklich loeschen?')) return;
    router.delete(route(routeName, id), { preserveScroll: true });
}

function updateTaskStatus(item, status) {
    router.put(route('apps.tasks.update', item.id), {
        ...normalizeItem(item),
        assignee_person_id: item.assignee_person_id || '',
        status,
    }, { preserveScroll: true });
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
    selectedShare.value = { ...item, type };
    shareForm.reset();
    mailForm.reset();
}

function submitShare() {
    shareForm.post(route('apps.share', { type: selectedShare.value.type, id: selectedShare.value.id }), {
        preserveScroll: true,
        onSuccess: () => selectedShare.value = null,
    });
}

function sendFileMail() {
    mailForm.post(route('apps.files.mail', selectedShare.value.id), {
        preserveScroll: true,
        onSuccess: () => selectedShare.value = null,
    });
}

function fileRoute(item) {
    return item.type === 'folder'
        ? route('apps.files', { folder: item.id })
        : route('apps.files.download', item.id);
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

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-[360px_1fr]">
                    <aside class="rounded border border-gray-200 bg-white p-4 shadow-sm">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Neu anlegen</h2>

                        <template v-if="section === 'files'">
                            <form class="space-y-3" @submit.prevent="submitForm('folder', 'apps.files.folder.store')">
                                <h3 class="text-sm font-semibold text-gray-700">Ordner</h3>
                                <input v-model="forms.folder.name" class="w-full rounded border-gray-300 text-sm" placeholder="Ordnername" />
                                <VisibilityFields :form="forms.folder" :projects="projects" :options="visibilityOptions" />
                                <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Ordner erstellen</button>
                            </form>

                            <form class="mt-6 space-y-3 border-t pt-4" @submit.prevent="submitForm('upload', 'apps.files.upload', { forceFormData: true })">
                                <h3 class="text-sm font-semibold text-gray-700">Datei</h3>
                                <input type="file" class="w-full text-sm" @input="forms.upload.file = $event.target.files[0]" />
                                <VisibilityFields :form="forms.upload" :projects="projects" :options="visibilityOptions" />
                                <button class="w-full rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white">Hochladen</button>
                            </form>
                        </template>

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

                    <section class="rounded border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b px-4 py-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                                <Link v-if="section === 'files' && currentFolder" :href="route('apps.files')" class="text-sm text-orange-600">Zurueck zur Dateiuebersicht</Link>
                            </div>
                            <span class="text-sm text-gray-500">{{ items.length }} Eintraege</span>
                        </div>

                        <div class="divide-y">
                            <div v-if="items.length === 0" class="p-8 text-center text-sm text-gray-500">Noch keine Eintraege vorhanden.</div>

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
                                        @click="destroyItem(section === 'files' ? 'apps.files.destroy' : section === 'calendar' ? 'apps.calendar.destroy' : section === 'contacts' ? 'apps.contacts.destroy' : section === 'tasks' ? 'apps.tasks.destroy' : 'apps.popups.destroy', item.id)"
                                    >
                                        Loeschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div v-if="selectedShare" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-xl rounded bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Freigeben: {{ selectedShare.title || selectedShare.name }}</h2>
                    <button class="text-xl" @click="selectedShare = null">&times;</button>
                </div>

                <form class="space-y-3" @submit.prevent="submitShare">
                    <select v-model="shareForm.person_id" class="w-full rounded border-gray-300 text-sm">
                        <option value="">Person auswaehlen</option>
                        <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                    </select>
                    <input v-model="shareForm.email" class="w-full rounded border-gray-300 text-sm" placeholder="Oder E-Mail-Adresse" />
                    <select v-model="shareForm.permission" class="w-full rounded border-gray-300 text-sm">
                        <option value="view">Nur ansehen</option>
                        <option value="edit">Bearbeiten</option>
                    </select>
                    <textarea v-model="shareForm.message" rows="3" class="w-full rounded border-gray-300 text-sm" placeholder="Nachricht"></textarea>
                    <label class="flex items-center gap-2 text-sm"><input v-model="shareForm.send_email" type="checkbox" /> Freigabe per Mail informieren</label>
                    <button class="w-full rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white">Freigabe speichern</button>
                </form>

                <form v-if="selectedShare.type === 'file' && selectedShare.type !== 'folder'" class="mt-5 space-y-3 border-t pt-4" @submit.prevent="sendFileMail">
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

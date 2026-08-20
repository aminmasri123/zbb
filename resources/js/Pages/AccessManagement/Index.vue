<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    accessRequests: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    doors: { type: Array, default: () => [] },
    persons: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    currentUserId: { type: Number, required: true },
    accessPermissions: { type: Object, default: () => ({}) },
})

const activeTab = ref('requests')

const requestForm = useForm({
    requested_for_person_id: props.persons[0]?.id || '',
    access_profile_id: '',
    valid_from: '',
    valid_until: '',
    reason: '',
})

const doorForm = useForm({
    standort_id: '',
    room_from_id: '',
    room_to_id: '',
    name: '',
    code: '',
    external_reference: '',
    description: '',
})

const profileForm = useForm({
    name: '',
    description: '',
    door_ids: [],
})

const activeProfiles = computed(() => props.profiles.filter((profile) => profile.active))
const activeDoors = computed(() => props.doors.filter((door) => door.active))
const roomsForDoor = computed(() => props.rooms.filter((room) => Number(room.standort_id) === Number(doorForm.standort_id)))

const counters = computed(() => ({
    open: props.accessRequests.filter((item) => item.status === 'submitted').length,
    approved: props.accessRequests.filter((item) => item.status === 'approved').length,
    effective: props.accessRequests.filter((item) => ['effective', 'scheduled'].includes(item.effective_status)).length,
    ended: props.accessRequests.filter((item) => ['rejected', 'revoked', 'expired'].includes(item.effective_status)).length,
}))

function submitRequest() {
    requestForm.post(route('zutritt.antraege.store'), {
        preserveScroll: true,
        onSuccess: () => requestForm.reset('access_profile_id', 'valid_from', 'valid_until', 'reason'),
    })
}

function submitDoor() {
    doorForm.post(route('zutritt.tueren.store'), {
        preserveScroll: true,
        onSuccess: () => doorForm.reset(),
    })
}

function submitProfile() {
    profileForm.post(route('zutritt.profile.store'), {
        preserveScroll: true,
        onSuccess: () => profileForm.reset(),
    })
}

function decide(item, decision) {
    if (decision === 'approve' && !window.confirm(`Zutrittsantrag für ${item.requested_for_name} wirklich genehmigen?`)) return

    let comment = ''
    if (decision === 'reject') {
        comment = window.prompt('Bitte den Ablehnungsgrund eingeben:')
        if (!comment?.trim()) return
    }

    router.put(route('zutritt.antraege.decision', item.id), { decision, comment }, { preserveScroll: true })
}

function activate(item) {
    const technicalReference = window.prompt('Referenz aus der Zutrittsanlage oder Bearbeitungsnummer:')
    if (!technicalReference?.trim()) return

    const activationNote = window.prompt('Optionale Notiz zur manuellen Aktivierung:') || ''
    if (!window.confirm('Bestätigen, dass die Berechtigung manuell in der Zutrittsanlage eingerichtet wurde?')) return

    router.put(route('zutritt.antraege.activation', item.id), {
        technical_reference: technicalReference,
        activation_note: activationNote,
    }, { preserveScroll: true })
}

function revoke(item) {
    const note = window.prompt('Grund und Referenz für den manuellen Entzug:')
    if (!note?.trim()) return
    if (!window.confirm('Der Entzug wird dokumentiert. Wurde die technische Sperrung manuell ausgeführt oder verbindlich beauftragt?')) return

    router.put(route('zutritt.antraege.revocation', item.id), {
        revocation_note: note,
    }, { preserveScroll: true })
}

function canApproveItem(item) {
    return props.accessPermissions.canApprove
        && item.status === 'submitted'
        && Number(item.requested_by_user_id) !== Number(props.currentUserId)
}

function canActivateItem(item) {
    return props.accessPermissions.canActivate
        && item.status === 'approved'
        && Number(item.approved_by_user_id) !== Number(props.currentUserId)
}

function canRevokeItem(item) {
    return props.accessPermissions.canActivate && ['approved', 'provisioned'].includes(item.status)
}

function statusLabel(status) {
    return {
        submitted: 'Eingereicht',
        approved: 'Genehmigt',
        rejected: 'Abgelehnt',
        provisioned: 'Technisch erfasst',
        scheduled: 'Eingerichtet – künftig gültig',
        effective: 'Wirksam',
        expired: 'Abgelaufen',
        revoked: 'Entzogen',
    }[status] || status
}

function statusClass(status) {
    return {
        submitted: 'bg-blue-100 text-blue-800',
        approved: 'bg-amber-100 text-amber-800',
        rejected: 'bg-red-100 text-red-800',
        provisioned: 'bg-emerald-100 text-emerald-800',
        scheduled: 'bg-violet-100 text-violet-800',
        effective: 'bg-green-100 text-green-800',
        expired: 'bg-gray-200 text-gray-700',
        revoked: 'bg-red-100 text-red-800',
    }[status] || 'bg-gray-100 text-gray-700'
}

function eventLabel(type) {
    return {
        submitted: 'Antrag eingereicht',
        approved: 'Genehmigt',
        rejected: 'Abgelehnt',
        manually_provisioned: 'Manuell technisch aktiviert',
        revoked: 'Entzug dokumentiert',
    }[type] || type
}

function formatDate(value) {
    if (!value) return '–'
    return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function roomLabel(room) {
    return [room.raumnummer, room.name].filter(Boolean).join(' · ')
}

function doorConnection(door) {
    const from = door.room_from ? roomLabel(door.room_from) : 'Außenbereich'
    const to = door.room_to ? roomLabel(door.room_to) : 'nicht zugeordnet'
    return `${from} → ${to}`
}
</script>

<template>
    <AppLayout title="Zutrittsverwaltung">
        <Head title="Zutrittsverwaltung" />

        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Zutrittsverwaltung</h2>
                    <p class="mt-1 text-sm text-gray-600">Antrag, Genehmigung und manuelle technische Bearbeitung nachvollziehbar trennen.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                    Technische Aktivierung: manuell
                </span>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Offen zur Genehmigung</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-700">{{ counters.open }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Technisch zu bearbeiten</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-700">{{ counters.approved }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Eingerichtet / wirksam</p>
                    <p class="mt-1 text-2xl font-semibold text-green-700">{{ counters.effective }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Beendet</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-700">{{ counters.ended }}</p>
                </div>
            </section>

            <nav class="flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="activeTab === 'requests' ? 'bg-zbb text-white' : 'text-gray-600 hover:bg-gray-100'" @click="activeTab = 'requests'">
                    Anträge
                </button>
                <template v-if="accessPermissions.canManageMasterData">
                    <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="activeTab === 'doors' ? 'bg-zbb text-white' : 'text-gray-600 hover:bg-gray-100'" @click="activeTab = 'doors'">
                        Türen
                    </button>
                    <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="activeTab === 'profiles' ? 'bg-zbb text-white' : 'text-gray-600 hover:bg-gray-100'" @click="activeTab = 'profiles'">
                        Zutrittsprofile
                    </button>
                </template>
            </nav>

            <template v-if="activeTab === 'requests'">
                <section v-if="accessPermissions.canCreateRequest" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Neuen Zutrittsantrag stellen</h3>
                    <p class="mt-1 text-sm text-gray-600">Die Berechtigung wird erst nach Genehmigung manuell in der Zutrittsanlage eingerichtet.</p>

                    <div v-if="!activeProfiles.length" class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        Es gibt noch kein aktives Zutrittsprofil. Ein Administrator muss zuerst Türen und ein Profil anlegen.
                    </div>

                    <form v-else class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitRequest">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Person</span>
                            <select v-model="requestForm.requested_for_person_id" class="mt-1 w-full rounded-md border-gray-300" required>
                                <option value="" disabled>Bitte auswählen</option>
                                <option v-for="person in persons" :key="person.id" :value="person.id">{{ person.vorname }} {{ person.nachname }}</option>
                            </select>
                            <span v-if="requestForm.errors.requested_for_person_id" class="mt-1 block text-xs text-red-600">{{ requestForm.errors.requested_for_person_id }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Zutrittsprofil</span>
                            <select v-model="requestForm.access_profile_id" class="mt-1 w-full rounded-md border-gray-300" required>
                                <option value="" disabled>Bitte auswählen</option>
                                <option v-for="profile in activeProfiles" :key="profile.id" :value="profile.id">{{ profile.name }} ({{ profile.doors.length }} Türen)</option>
                            </select>
                            <span v-if="requestForm.errors.access_profile_id" class="mt-1 block text-xs text-red-600">{{ requestForm.errors.access_profile_id }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Gültig ab</span>
                            <input v-model="requestForm.valid_from" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300" required>
                            <span v-if="requestForm.errors.valid_from" class="mt-1 block text-xs text-red-600">{{ requestForm.errors.valid_from }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Gültig bis</span>
                            <input v-model="requestForm.valid_until" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300" required>
                            <span v-if="requestForm.errors.valid_until" class="mt-1 block text-xs text-red-600">{{ requestForm.errors.valid_until }}</span>
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-gray-700">Betriebliche Begründung</span>
                            <textarea v-model="requestForm.reason" rows="3" class="mt-1 w-full rounded-md border-gray-300" maxlength="3000" required></textarea>
                            <span v-if="requestForm.errors.reason" class="mt-1 block text-xs text-red-600">{{ requestForm.errors.reason }}</span>
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-md bg-zbb px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50" :disabled="requestForm.processing">
                                {{ requestForm.processing ? 'Wird eingereicht …' : 'Antrag einreichen' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="space-y-4">
                    <div v-if="!accessRequests.length" class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                        Noch keine Zutrittsanträge vorhanden.
                    </div>

                    <article v-for="item in accessRequests" :key="item.id" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ item.requested_for_name }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(item.effective_status)">{{ statusLabel(item.effective_status) }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-gray-700">{{ item.profile_snapshot?.name || 'Profil nicht mehr vorhanden' }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ formatDate(item.valid_from) }} bis {{ formatDate(item.valid_until) }}</p>
                                <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ item.reason }}</p>
                                <p class="mt-3 text-xs text-gray-500">Beantragt von {{ item.requested_by_name }} am {{ formatDate(item.submitted_at) }}</p>
                            </div>

                            <div class="flex min-w-fit flex-wrap gap-2">
                                <button v-if="canApproveItem(item)" type="button" class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700" @click="decide(item, 'approve')">Genehmigen</button>
                                <button v-if="canApproveItem(item)" type="button" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="decide(item, 'reject')">Ablehnen</button>
                                <button v-if="canActivateItem(item)" type="button" class="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700" @click="activate(item)">Manuell aktiviert</button>
                                <button v-if="canRevokeItem(item)" type="button" class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" @click="revoke(item)">Entzug dokumentieren</button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 rounded-md bg-gray-50 p-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Türen im Antrag</p>
                                <p class="mt-1 font-medium text-gray-800">{{ item.profile_snapshot?.doors?.length || 0 }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Genehmigung</p>
                                <p class="mt-1 text-gray-800">{{ item.approved_by?.name || 'offen' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Technische Bearbeitung</p>
                                <p class="mt-1 text-gray-800">{{ item.activated_by?.name || 'offen' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Technische Referenz</p>
                                <p class="mt-1 break-all text-gray-800">{{ item.technical_reference || '–' }}</p>
                            </div>
                        </div>

                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Verlauf und enthaltene Türen</summary>
                            <div class="mt-3 grid gap-5 lg:grid-cols-2">
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Türen</h4>
                                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                        <li v-for="door in item.profile_snapshot?.doors || []" :key="door.id">{{ door.location ? `${door.location} · ` : '' }}{{ door.name }}{{ door.code ? ` (${door.code})` : '' }}</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bearbeitungsverlauf</h4>
                                    <ol class="mt-2 space-y-2 text-sm text-gray-700">
                                        <li v-for="event in item.events || []" :key="event.id">
                                            <span class="font-medium">{{ eventLabel(event.event_type) }}</span>
                                            <span class="text-gray-500"> · {{ event.actor_name }} · {{ formatDate(event.created_at) }}</span>
                                            <p v-if="event.comment" class="mt-1 text-gray-600">{{ event.comment }}</p>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </details>
                    </article>
                </section>
            </template>

            <template v-if="activeTab === 'doors' && accessPermissions.canManageMasterData">
                <section class="grid gap-6 xl:grid-cols-[minmax(0,420px)_1fr]">
                    <form class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="submitDoor">
                        <h3 class="text-lg font-semibold text-gray-900">Tür anlegen</h3>
                        <p class="mt-1 text-sm text-gray-600">Eine Tür verbindet zwei räumliche Bereiche. Technische IDs sind zunächst optional.</p>

                        <div class="mt-5 space-y-4">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Standort</span>
                                <select v-model="doorForm.standort_id" class="mt-1 w-full rounded-md border-gray-300" required @change="doorForm.room_from_id = ''; doorForm.room_to_id = ''">
                                    <option value="" disabled>Bitte auswählen</option>
                                    <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                </select>
                                <span v-if="doorForm.errors.standort_id" class="mt-1 block text-xs text-red-600">{{ doorForm.errors.standort_id }}</span>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Bezeichnung</span>
                                <input v-model="doorForm.name" class="mt-1 w-full rounded-md border-gray-300" maxlength="160" required>
                                <span v-if="doorForm.errors.name" class="mt-1 block text-xs text-red-600">{{ doorForm.errors.name }}</span>
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Von Raum</span>
                                    <select v-model="doorForm.room_from_id" class="mt-1 w-full rounded-md border-gray-300">
                                        <option value="">Außenbereich / offen</option>
                                        <option v-for="room in roomsForDoor" :key="room.id" :value="room.id">{{ roomLabel(room) }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Nach Raum</span>
                                    <select v-model="doorForm.room_to_id" class="mt-1 w-full rounded-md border-gray-300">
                                        <option value="">Noch nicht zugeordnet</option>
                                        <option v-for="room in roomsForDoor" :key="room.id" :value="room.id">{{ roomLabel(room) }}</option>
                                    </select>
                                </label>
                            </div>
                            <span v-if="doorForm.errors.room_from_id || doorForm.errors.room_to_id" class="block text-xs text-red-600">{{ doorForm.errors.room_from_id || doorForm.errors.room_to_id }}</span>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Interner Türcode</span>
                                    <input v-model="doorForm.code" class="mt-1 w-full rounded-md border-gray-300" maxlength="80">
                                </label>
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Externe Referenz</span>
                                    <input v-model="doorForm.external_reference" class="mt-1 w-full rounded-md border-gray-300" maxlength="160">
                                </label>
                            </div>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Beschreibung</span>
                                <textarea v-model="doorForm.description" rows="3" class="mt-1 w-full rounded-md border-gray-300" maxlength="2000"></textarea>
                            </label>

                            <button type="submit" class="rounded-md bg-zbb px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="doorForm.processing">Tür speichern</button>
                        </div>
                    </form>

                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Erfasste Türen</h3>
                        <div class="mt-4 divide-y divide-gray-200">
                            <div v-if="!doors.length" class="py-8 text-center text-sm text-gray-500">Noch keine Türen erfasst.</div>
                            <div v-for="door in doors" :key="door.id" class="py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ door.name }}</p>
                                        <p class="mt-1 text-sm text-gray-600">{{ door.standort?.name || 'Standort entfernt' }} · {{ doorConnection(door) }}</p>
                                        <p v-if="door.code || door.external_reference" class="mt-1 text-xs text-gray-500">Code: {{ door.code || '–' }} · Extern: {{ door.external_reference || '–' }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="door.active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">{{ door.active ? 'Aktiv' : 'Inaktiv' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>
            </template>

            <template v-if="activeTab === 'profiles' && accessPermissions.canManageMasterData">
                <section class="grid gap-6 xl:grid-cols-[minmax(0,420px)_1fr]">
                    <form class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="submitProfile">
                        <h3 class="text-lg font-semibold text-gray-900">Zutrittsprofil anlegen</h3>
                        <p class="mt-1 text-sm text-gray-600">Ein Profil bündelt die Türen, die gemeinsam beantragt werden können.</p>

                        <div class="mt-5 space-y-4">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Profilname</span>
                                <input v-model="profileForm.name" class="mt-1 w-full rounded-md border-gray-300" maxlength="160" required>
                                <span v-if="profileForm.errors.name" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.name }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Beschreibung</span>
                                <textarea v-model="profileForm.description" rows="3" class="mt-1 w-full rounded-md border-gray-300" maxlength="2000"></textarea>
                            </label>

                            <fieldset>
                                <legend class="text-sm font-medium text-gray-700">Enthaltene Türen</legend>
                                <div v-if="!activeDoors.length" class="mt-2 rounded-md bg-amber-50 p-3 text-sm text-amber-800">Bitte zuerst mindestens eine Tür anlegen.</div>
                                <div v-else class="mt-2 max-h-72 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-3">
                                    <label v-for="door in activeDoors" :key="door.id" class="flex items-start gap-2 text-sm text-gray-700">
                                        <input v-model="profileForm.door_ids" type="checkbox" :value="door.id" class="mt-0.5 rounded border-gray-300 text-zbb focus:ring-zbb">
                                        <span>{{ door.standort?.name ? `${door.standort.name} · ` : '' }}{{ door.name }}</span>
                                    </label>
                                </div>
                                <span v-if="profileForm.errors.door_ids" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.door_ids }}</span>
                            </fieldset>

                            <button type="submit" class="rounded-md bg-zbb px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="profileForm.processing || !activeDoors.length">Profil speichern</button>
                        </div>
                    </form>

                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Zutrittsprofile</h3>
                        <div class="mt-4 space-y-4">
                            <div v-if="!profiles.length" class="py-8 text-center text-sm text-gray-500">Noch keine Profile vorhanden.</div>
                            <article v-for="profile in profiles" :key="profile.id" class="rounded-md border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ profile.name }}</h4>
                                        <p v-if="profile.description" class="mt-1 text-sm text-gray-600">{{ profile.description }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="profile.active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">{{ profile.active ? 'Aktiv' : 'Inaktiv' }}</span>
                                </div>
                                <ul class="mt-3 flex flex-wrap gap-2">
                                    <li v-for="door in profile.doors" :key="door.id" class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ door.name }}</li>
                                </ul>
                            </article>
                        </div>
                    </section>
                </section>
            </template>
        </div>
    </AppLayout>
</template>

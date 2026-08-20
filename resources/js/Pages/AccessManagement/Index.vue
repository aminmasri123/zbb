<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    accessRequests: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    floorPlans: { type: Array, default: () => [] },
    doors: { type: Array, default: () => [] },
    persons: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    currentUserId: { type: Number, required: true },
    accessPermissions: { type: Object, default: () => ({}) },
})

const activeTab = ref('requests')
const selectedFloorPlanId = ref(props.floorPlans[0]?.id || '')
const floorCanvas = ref(null)
const floorPlanFileInput = ref(null)
const planRooms = ref([])
const planDoors = ref([])
const selectedRoomToAdd = ref('')
const selectedDoorToAdd = ref('')
const selectedPlacement = ref(null)
const layoutSaving = ref(false)
const layoutMessage = ref('')
let dragState = null

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

const floorPlanForm = useForm({
    standort_id: '',
    floor_label: '',
    name: '',
    image: null,
})

const activeProfiles = computed(() => props.profiles.filter((profile) => profile.active))
const activeDoors = computed(() => props.doors.filter((door) => door.active))
const roomsForDoor = computed(() => props.rooms.filter((room) => Number(room.standort_id) === Number(doorForm.standort_id)))
const selectedFloorPlan = computed(() => props.floorPlans.find((plan) => Number(plan.id) === Number(selectedFloorPlanId.value)) || null)
const floorLabelsForPlan = computed(() => [...new Set(props.rooms
    .filter((room) => Number(room.standort_id) === Number(floorPlanForm.standort_id))
    .map((room) => room.etage)
    .filter(Boolean))])
const availablePlanRooms = computed(() => {
    const plan = selectedFloorPlan.value
    if (!plan) return []
    const placed = new Set(planRooms.value.map((item) => Number(item.room_id)))
    return props.rooms.filter((room) => Number(room.standort_id) === Number(plan.standort_id)
        && (!room.etage || room.etage === plan.floor_label)
        && !placed.has(Number(room.id)))
})
const availablePlanDoors = computed(() => {
    const plan = selectedFloorPlan.value
    if (!plan) return []
    const placed = new Set(planDoors.value.map((item) => Number(item.door_id)))
    return props.doors.filter((door) => Number(door.standort_id) === Number(plan.standort_id) && !placed.has(Number(door.id)))
})
const selectedPlacementItem = computed(() => {
    if (!selectedPlacement.value) return null
    const collection = selectedPlacement.value.kind === 'room' ? planRooms.value : planDoors.value
    return collection[selectedPlacement.value.index] || null
})

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

function submitFloorPlan() {
    floorPlanForm.post(route('zutritt.grundrisse.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            activeTab.value = 'floorPlans'
            floorPlanForm.reset()
            if (floorPlanFileInput.value) floorPlanFileInput.value.value = ''
        },
    })
}

function setFloorPlanImage(event) {
    floorPlanForm.image = event.target.files?.[0] || null
}

function loadFloorPlan() {
    const plan = selectedFloorPlan.value
    planRooms.value = (plan?.rooms || []).map((item) => ({
        ...item,
        room_id: Number(item.room_id),
        x_percent: Number(item.x_percent),
        y_percent: Number(item.y_percent),
        width_percent: Number(item.width_percent),
        height_percent: Number(item.height_percent),
    }))
    planDoors.value = (plan?.doors || []).map((item) => ({
        ...item,
        door_id: Number(item.door_id),
        x_percent: Number(item.x_percent),
        y_percent: Number(item.y_percent),
        rotation_degrees: Number(item.rotation_degrees),
    }))
    selectedPlacement.value = null
    selectedRoomToAdd.value = ''
    selectedDoorToAdd.value = ''
    layoutMessage.value = ''
}

function addRoomToPlan() {
    const room = props.rooms.find((item) => Number(item.id) === Number(selectedRoomToAdd.value))
    if (!room) return

    const offset = (planRooms.value.length * 3) % 45
    planRooms.value.push({
        room_id: Number(room.id),
        room,
        x_percent: 5 + offset,
        y_percent: 5 + offset,
        width_percent: 20,
        height_percent: 12,
    })
    selectedPlacement.value = { kind: 'room', index: planRooms.value.length - 1 }
    selectedRoomToAdd.value = ''
    layoutMessage.value = 'Ungespeicherte Änderungen'
}

function addDoorToPlan() {
    const door = props.doors.find((item) => Number(item.id) === Number(selectedDoorToAdd.value))
    if (!door) return

    const offset = (planDoors.value.length * 3) % 40
    planDoors.value.push({
        door_id: Number(door.id),
        door,
        x_percent: 50 + offset,
        y_percent: 50,
        rotation_degrees: 0,
    })
    selectedPlacement.value = { kind: 'door', index: planDoors.value.length - 1 }
    selectedDoorToAdd.value = ''
    layoutMessage.value = 'Ungespeicherte Änderungen'
}

function startPlacementDrag(kind, index, event) {
    if (event.button !== 0 || !floorCanvas.value) return
    event.preventDefault()
    const item = kind === 'room' ? planRooms.value[index] : planDoors.value[index]
    const bounds = floorCanvas.value.getBoundingClientRect()
    selectedPlacement.value = { kind, index }
    dragState = {
        kind,
        index,
        startX: event.clientX,
        startY: event.clientY,
        originX: Number(item.x_percent),
        originY: Number(item.y_percent),
        bounds,
    }
    window.addEventListener('pointermove', movePlacement)
    window.addEventListener('pointerup', stopPlacementDrag, { once: true })
}

function movePlacement(event) {
    if (!dragState) return
    const collection = dragState.kind === 'room' ? planRooms.value : planDoors.value
    const item = collection[dragState.index]
    if (!item) return

    const deltaX = ((event.clientX - dragState.startX) / dragState.bounds.width) * 100
    const deltaY = ((event.clientY - dragState.startY) / dragState.bounds.height) * 100
    const maxX = dragState.kind === 'room' ? 100 - Number(item.width_percent) : 100
    const maxY = dragState.kind === 'room' ? 100 - Number(item.height_percent) : 100
    item.x_percent = clamp(dragState.originX + deltaX, 0, maxX)
    item.y_percent = clamp(dragState.originY + deltaY, 0, maxY)
    layoutMessage.value = 'Ungespeicherte Änderungen'
}

function stopPlacementDrag() {
    dragState = null
    window.removeEventListener('pointermove', movePlacement)
}

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum)
}

function removeSelectedPlacement() {
    if (!selectedPlacement.value) return
    const collection = selectedPlacement.value.kind === 'room' ? planRooms.value : planDoors.value
    collection.splice(selectedPlacement.value.index, 1)
    selectedPlacement.value = null
    layoutMessage.value = 'Ungespeicherte Änderungen'
}

function normalizeSelectedRoom() {
    const item = selectedPlacementItem.value
    if (!item || selectedPlacement.value?.kind !== 'room') return
    item.width_percent = Number(item.width_percent)
    item.height_percent = Number(item.height_percent)
    item.x_percent = clamp(Number(item.x_percent), 0, 100 - item.width_percent)
    item.y_percent = clamp(Number(item.y_percent), 0, 100 - item.height_percent)
    layoutMessage.value = 'Ungespeicherte Änderungen'
}

function saveFloorPlanLayout() {
    if (!selectedFloorPlan.value) return
    layoutSaving.value = true
    layoutMessage.value = ''
    router.put(route('zutritt.grundrisse.layout.update', selectedFloorPlan.value.id), {
        rooms: planRooms.value.map((item) => ({
            room_id: item.room_id,
            x_percent: Number(item.x_percent.toFixed(4)),
            y_percent: Number(item.y_percent.toFixed(4)),
            width_percent: Number(item.width_percent),
            height_percent: Number(item.height_percent),
        })),
        doors: planDoors.value.map((item) => ({
            door_id: item.door_id,
            x_percent: Number(item.x_percent.toFixed(4)),
            y_percent: Number(item.y_percent.toFixed(4)),
            rotation_degrees: Number(item.rotation_degrees),
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { layoutMessage.value = 'Gespeichert' },
        onError: () => { layoutMessage.value = 'Bitte Eingaben prüfen' },
        onFinish: () => { layoutSaving.value = false },
    })
}

function deleteFloorPlan() {
    const plan = selectedFloorPlan.value
    if (!plan || !window.confirm(`Grundriss „${plan.name}“ wirklich entfernen? Räume und Türen selbst bleiben erhalten.`)) return
    router.delete(route('zutritt.grundrisse.destroy', plan.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedFloorPlanId.value = props.floorPlans[0]?.id || ''
        },
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

function planRoomLabel(item) {
    return roomLabel(item.room || props.rooms.find((room) => Number(room.id) === Number(item.room_id)) || {})
}

function planDoorLabel(item) {
    const door = item.door || props.doors.find((candidate) => Number(candidate.id) === Number(item.door_id))
    return door?.name || `Tür ${item.door_id}`
}

watch(selectedFloorPlanId, loadFloorPlan)
watch(() => props.floorPlans, (plans) => {
    if (!plans.some((plan) => Number(plan.id) === Number(selectedFloorPlanId.value))) {
        selectedFloorPlanId.value = plans[0]?.id || ''
    }
    loadFloorPlan()
}, { deep: true })

loadFloorPlan()

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', movePlacement)
    window.removeEventListener('pointerup', stopPlacementDrag)
})
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
                    <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="activeTab === 'floorPlans' ? 'bg-zbb text-white' : 'text-gray-600 hover:bg-gray-100'" @click="activeTab = 'floorPlans'">
                        2D-Grundrisse
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

            <template v-if="activeTab === 'floorPlans' && accessPermissions.canManageMasterData">
                <section class="grid gap-6 xl:grid-cols-[minmax(0,360px)_1fr]">
                    <aside class="space-y-5">
                        <form class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="submitFloorPlan">
                            <h3 class="text-lg font-semibold text-gray-900">2D-Grundriss anlegen</h3>
                            <p class="mt-1 text-sm text-gray-600">Lade einen Bildplan für einen Standort und eine Etage hoch. Räume und Türen werden anschließend darauf platziert.</p>

                            <div class="mt-5 space-y-4">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Standort</span>
                                    <select v-model="floorPlanForm.standort_id" class="mt-1 w-full rounded-md border-gray-300" required @change="floorPlanForm.floor_label = ''">
                                        <option value="" disabled>Bitte auswählen</option>
                                        <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                    </select>
                                    <span v-if="floorPlanForm.errors.standort_id" class="mt-1 block text-xs text-red-600">{{ floorPlanForm.errors.standort_id }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Etage</span>
                                    <input v-model="floorPlanForm.floor_label" list="floor-label-options" class="mt-1 w-full rounded-md border-gray-300" maxlength="80" placeholder="z. B. Erdgeschoss" required>
                                    <datalist id="floor-label-options">
                                        <option v-for="floor in floorLabelsForPlan" :key="floor" :value="floor"></option>
                                    </datalist>
                                    <span v-if="floorPlanForm.errors.floor_label" class="mt-1 block text-xs text-red-600">{{ floorPlanForm.errors.floor_label }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Bezeichnung</span>
                                    <input v-model="floorPlanForm.name" class="mt-1 w-full rounded-md border-gray-300" maxlength="160" placeholder="z. B. Hauptgebäude EG" required>
                                    <span v-if="floorPlanForm.errors.name" class="mt-1 block text-xs text-red-600">{{ floorPlanForm.errors.name }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Grundrissbild</span>
                                    <input ref="floorPlanFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:font-semibold" required @change="setFloorPlanImage">
                                    <span class="mt-1 block text-xs text-gray-500">PNG, JPG oder WebP, maximal 10 MB</span>
                                    <span v-if="floorPlanForm.errors.image" class="mt-1 block text-xs text-red-600">{{ floorPlanForm.errors.image }}</span>
                                </label>

                                <button type="submit" class="rounded-md bg-zbb px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="floorPlanForm.processing">
                                    {{ floorPlanForm.processing ? 'Wird hochgeladen …' : 'Grundriss anlegen' }}
                                </button>
                            </div>
                        </form>

                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                            <p class="font-semibold">Bedienung</p>
                            <ol class="mt-2 list-decimal space-y-1 pl-5">
                                <li>Raum oder Tür auswählen und hinzufügen.</li>
                                <li>Element mit der Maus an die richtige Position ziehen.</li>
                                <li>Raumgröße oder Türdrehung einstellen.</li>
                                <li>Anordnung speichern.</li>
                            </ol>
                        </div>
                    </aside>

                    <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div v-if="!floorPlans.length" class="flex min-h-96 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                            <div>
                                <i class="las la-map text-5xl text-gray-300"></i>
                                <p class="mt-3 font-semibold text-gray-700">Noch kein 2D-Grundriss vorhanden</p>
                                <p class="mt-1 text-sm text-gray-500">Lege links zuerst einen Standort- und Etagenplan an.</p>
                            </div>
                        </div>

                        <template v-else-if="selectedFloorPlan">
                            <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
                                <label class="block min-w-64">
                                    <span class="text-sm font-medium text-gray-700">Grundriss auswählen</span>
                                    <select v-model="selectedFloorPlanId" class="mt-1 w-full rounded-md border-gray-300">
                                        <option v-for="plan in floorPlans" :key="plan.id" :value="plan.id">{{ plan.standort?.name }} · {{ plan.floor_label }} · {{ plan.name }}</option>
                                    </select>
                                </label>
                                <button type="button" class="w-fit rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" @click="deleteFloorPlan">
                                    Grundriss entfernen
                                </button>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                                    <label class="text-sm font-medium text-gray-700">Raum platzieren</label>
                                    <div class="mt-2 flex gap-2">
                                        <select v-model="selectedRoomToAdd" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                            <option value="">Nicht platzierten Raum auswählen</option>
                                            <option v-for="room in availablePlanRooms" :key="room.id" :value="room.id">{{ room.etage ? `${room.etage} · ` : '' }}{{ roomLabel(room) }}</option>
                                        </select>
                                        <button type="button" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!selectedRoomToAdd" @click="addRoomToPlan">Hinzufügen</button>
                                    </div>
                                </div>

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                                    <label class="text-sm font-medium text-gray-700">Tür platzieren</label>
                                    <div class="mt-2 flex gap-2">
                                        <select v-model="selectedDoorToAdd" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                            <option value="">Nicht platzierte Tür auswählen</option>
                                            <option v-for="door in availablePlanDoors" :key="door.id" :value="door.id">{{ door.name }}{{ door.code ? ` (${door.code})` : '' }}</option>
                                        </select>
                                        <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!selectedDoorToAdd" @click="addDoorToPlan">Hinzufügen</button>
                                    </div>
                                </div>
                            </div>

                            <div ref="floorCanvas" class="relative mt-5 select-none overflow-hidden rounded-lg border-2 border-gray-300 bg-gray-100 shadow-inner touch-none">
                                <img :src="selectedFloorPlan.image_url" :alt="selectedFloorPlan.name" class="block h-auto w-full" draggable="false">
                                <div class="absolute inset-0">
                                    <button
                                        v-for="(item, index) in planRooms"
                                        :key="`room-${item.room_id}`"
                                        type="button"
                                        class="absolute flex cursor-move items-center justify-center overflow-hidden border-2 bg-blue-400/30 px-1 text-center text-xs font-semibold text-blue-950 shadow-sm"
                                        :class="selectedPlacement?.kind === 'room' && selectedPlacement.index === index ? 'z-20 border-blue-700 ring-2 ring-white' : 'z-10 border-blue-500'"
                                        :style="{ left: `${item.x_percent}%`, top: `${item.y_percent}%`, width: `${item.width_percent}%`, height: `${item.height_percent}%` }"
                                        :title="planRoomLabel(item)"
                                        @pointerdown="startPlacementDrag('room', index, $event)"
                                    >
                                        <span class="truncate">{{ planRoomLabel(item) }}</span>
                                    </button>

                                    <button
                                        v-for="(item, index) in planDoors"
                                        :key="`door-${item.door_id}`"
                                        type="button"
                                        class="absolute z-30 flex h-7 w-7 cursor-move items-center justify-center rounded-full border-2 bg-amber-400 text-amber-950 shadow-md"
                                        :class="selectedPlacement?.kind === 'door' && selectedPlacement.index === index ? 'border-red-700 ring-2 ring-white' : 'border-amber-900'"
                                        :style="{ left: `${item.x_percent}%`, top: `${item.y_percent}%`, transform: `translate(-50%, -50%) rotate(${item.rotation_degrees}deg)` }"
                                        :title="planDoorLabel(item)"
                                        @pointerdown="startPlacementDrag('door', index, $event)"
                                    >
                                        <i class="las la-door-open text-base"></i>
                                    </button>
                                </div>
                            </div>

                            <div v-if="selectedPlacementItem" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ausgewähltes Element</p>
                                        <p class="font-semibold text-gray-900">{{ selectedPlacement?.kind === 'room' ? planRoomLabel(selectedPlacementItem) : planDoorLabel(selectedPlacementItem) }}</p>
                                    </div>
                                    <button type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50" @click="removeSelectedPlacement">Vom Plan entfernen</button>
                                </div>

                                <div v-if="selectedPlacement?.kind === 'room'" class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm text-gray-700">Breite: {{ selectedPlacementItem.width_percent }} %</span>
                                        <input v-model.number="selectedPlacementItem.width_percent" type="range" min="2" max="80" step="1" class="mt-2 w-full" @input="normalizeSelectedRoom">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm text-gray-700">Höhe: {{ selectedPlacementItem.height_percent }} %</span>
                                        <input v-model.number="selectedPlacementItem.height_percent" type="range" min="2" max="80" step="1" class="mt-2 w-full" @input="normalizeSelectedRoom">
                                    </label>
                                </div>

                                <label v-else class="mt-4 block">
                                    <span class="text-sm text-gray-700">Drehung: {{ selectedPlacementItem.rotation_degrees }}°</span>
                                    <input v-model.number="selectedPlacementItem.rotation_degrees" type="range" min="0" max="359" step="1" class="mt-2 w-full" @input="layoutMessage = 'Ungespeicherte Änderungen'">
                                </label>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm" :class="layoutMessage === 'Gespeichert' ? 'text-green-700' : 'text-amber-700'">{{ layoutMessage }}</p>
                                <button type="button" class="rounded-md bg-zbb px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="layoutSaving" @click="saveFloorPlanLayout">
                                    {{ layoutSaving ? 'Wird gespeichert …' : '2D-Anordnung speichern' }}
                                </button>
                            </div>
                        </template>
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

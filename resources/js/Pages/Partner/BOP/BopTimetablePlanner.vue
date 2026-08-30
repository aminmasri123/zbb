<script setup>
import axios from 'axios'
import { ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  partnerId: [Number, String],
  schuljahr: String,
  teil: String,
  schoolName: String,
})

const emit = defineEmits(['close', 'saved'])
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const success = ref('')
const options = ref({ areas: [], supervisors: [] })
const workshopDates = ref([])
const timetables = ref([])
const preview = ref(null)
const form = ref(emptyForm())

function emptyForm() {
  return {
    schedule_date: '', start_time: '09:00', end_time: '15:00', slot_minutes: 1,
    group_count: 4, areas: [], events: [],
  }
}

watch(() => props.visible, (visible) => {
  if (visible) load()
}, { immediate: true })

async function load() {
  if (!props.partnerId || !props.schuljahr) return
  loading.value = true
  error.value = ''
  try {
    const response = await axios.get(route('bop.run.show', {
      partner: props.partnerId, schuljahr: props.schuljahr, teil: props.teil || '_all',
    }))
    const workshop = (response.data.phases || []).find((phase) => phase.phase_type === 'workshop_days') || {}
    options.value = response.data.options || { areas: [], supervisors: [] }
    workshopDates.value = workshop.dates || []
    timetables.value = workshop.timetables || []

    const firstSaved = timetables.value[0] || null
    if (firstSaved) {
      applyTimetable(firstSaved)
    } else {
      const suggestedGroupCount = response.data.suggested_planned_classes?.length || 4
      form.value = {
        ...emptyForm(),
        schedule_date: workshopDates.value[0] || '',
        start_time: String(workshop.start_time || '09:00').slice(0, 5),
        end_time: String(workshop.end_time || '15:00').slice(0, 5),
        group_count: suggestedGroupCount,
      }
    }
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Die Zeitplandaten konnten nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

function dateValue(value) {
  return String(value || '').slice(0, 10)
}

function dateLabel(value) {
  return value ? new Date(`${dateValue(value)}T00:00:00`).toLocaleDateString('de-DE') : '–'
}

function applyTimetable(timetable) {
  const config = timetable.config || {}
  form.value = {
    schedule_date: dateValue(timetable.schedule_date),
    start_time: String(config.start_time || '09:00').slice(0, 5),
    end_time: String(config.end_time || '15:00').slice(0, 5),
    slot_minutes: 1,
    group_count: Math.max(1, Number(config.groups?.length || 4)),
    areas: (config.areas || []).map((area) => ({
      bereich_id: Number(area.bereich_id),
      supervisor_person_id: area.supervisor_person_id ? Number(area.supervisor_person_id) : null,
    })),
    events: (config.events || []).map((event) => ({
      title: event.title || '', type: event.type || 'shared',
      group_scope: event.group_scope || 'all',
      start_time: String(event.start_time || '').slice(0, 5),
      end_time: String(event.end_time || '').slice(0, 5),
    })),
  }
  preview.value = timetable
}

function changeDate() {
  const saved = timetables.value.find((item) => dateValue(item.schedule_date) === form.value.schedule_date)
  if (saved) applyTimetable(saved)
  else preview.value = null
  success.value = ''
}

function groups() {
  const count = Math.max(1, Math.min(50, Number(form.value.group_count || 1)))
  return Array.from({ length: count }, (_, index) => `G${index + 1}`)
}

function halfLabel(scope) {
  const labels = groups()
  const halfSize = Math.ceil(labels.length / 2)
  const selected = scope === 'first_half' ? labels.slice(0, halfSize) : labels.slice(halfSize)
  if (!selected.length) return 'keine Gruppe'
  if (selected.length <= 6) return selected.join(', ')
  return `${selected[0]}–${selected[selected.length - 1]}`
}

function areaSetting(areaId) {
  return form.value.areas.find((area) => Number(area.bereich_id) === Number(areaId))
}

function toggleArea(areaId) {
  const existing = areaSetting(areaId)
  form.value.areas = existing
    ? form.value.areas.filter((area) => Number(area.bereich_id) !== Number(areaId))
    : [...form.value.areas, { bereich_id: Number(areaId), supervisor_person_id: null }]
  preview.value = null
}

function addEvent() {
  form.value.events.push({ title: '', type: 'shared', group_scope: 'all', start_time: '', end_time: '' })
  preview.value = null
}

function removeEvent(index) {
  form.value.events.splice(index, 1)
  preview.value = null
}

function requestPayload(persist) {
  return {
    schuljahr: props.schuljahr,
    teil: props.teil || '_all',
    schedule_date: form.value.schedule_date,
    start_time: form.value.start_time,
    end_time: form.value.end_time,
    slot_minutes: Number(form.value.slot_minutes),
    groups: groups(),
    areas: form.value.areas.map((area) => ({
      bereich_id: Number(area.bereich_id),
      supervisor_person_id: area.supervisor_person_id ? Number(area.supervisor_person_id) : null,
    })),
    events: form.value.events.map((event) => ({ ...event })),
    persist,
  }
}

async function generate(persist = false) {
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await axios.post(route('bop.run.timetable.generate', { partner: props.partnerId }), requestPayload(persist))
    preview.value = response.data.timetable
    success.value = response.data.message
    if (persist) {
      timetables.value = [
        ...timetables.value.filter((item) => dateValue(item.schedule_date) !== form.value.schedule_date),
        response.data.timetable,
      ].sort((left, right) => dateValue(left.schedule_date).localeCompare(dateValue(right.schedule_date)))
      if (!workshopDates.value.includes(form.value.schedule_date)) workshopDates.value.push(form.value.schedule_date)
      emit('saved', response.data)
    }
  } catch (exception) {
    const validation = exception.response?.data?.errors
    error.value = validation ? Object.values(validation)[0]?.[0] : (exception.response?.data?.message || 'Der Zeitplan konnte nicht erzeugt werden.')
  } finally {
    busy.value = false
  }
}

function rows() {
  if (!preview.value) return []
  const entries = preview.value.entries || []
  const labels = preview.value.config?.groups || groups()
  return labels.map((group) => ({
    group,
    entries: entries.filter((entry) => {
      if (entry.group_key) return entry.group_key === group
      const eventGroups = entry.meta?.group_labels
      return !Array.isArray(eventGroups) || eventGroups.includes(group)
    })
      .sort((left, right) => String(left.start_time).localeCompare(String(right.start_time))),
  }))
}

function typeLabel(type) {
  return ({ shared: 'Gemeinsam', break: 'Pause', extra: 'Zusatz', area: 'Bereich' })[type] || type
}
</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[110] overflow-hidden bg-slate-950/55 p-3 md:p-6">
    <section class="mx-auto flex h-full max-w-7xl flex-col overflow-hidden rounded-xl bg-gray-50 shadow-2xl">
      <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b bg-white px-5 py-4">
        <div><h2 class="text-xl font-bold text-gray-900">Zeitplan · {{ schoolName }}</h2><p class="text-sm text-gray-500">{{ schuljahr }} · {{ teil || 'Gesamt' }}</p></div>
        <button type="button" class="rounded border px-4 py-2 text-sm font-semibold" :disabled="busy" @click="$emit('close')">Schließen</button>
      </header>

      <div v-if="loading" class="flex-1 p-12 text-center text-gray-500">Zeitplandaten werden geladen …</div>
      <div v-else class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
        <div class="rounded border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">Gemeinsame Termine dürfen bei allen Gruppen gleichzeitig stattfinden. Pausen können für alle Gruppen oder gestaffelt für eine Gruppenhälfte geplant werden. Die Dauer der Bereiche wird automatisch aus der jeweils verbleibenden Tageszeit berechnet. Jeder normale Bereich und jeder Anleiter wird zeitgleich höchstens einmal eingeplant.</div>
        <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ error }}</div>
        <div v-if="success" class="rounded border border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700">{{ success }}</div>

        <section class="space-y-4 rounded-lg border bg-white p-4">
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-xs font-semibold text-gray-700">Tag<input v-model="form.schedule_date" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" @change="changeDate" /></label>
            <label class="text-xs font-semibold text-gray-700">Beginn<input v-model="form.start_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" @input="preview = null" /></label>
            <label class="text-xs font-semibold text-gray-700">Ende<input v-model="form.end_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" @input="preview = null" /></label>
          </div>
          <div v-if="workshopDates.length" class="flex flex-wrap gap-1"><button v-for="date in workshopDates" :key="date" type="button" class="rounded-full bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800" @click="form.schedule_date = date; changeDate()">{{ dateLabel(date) }}</button></div>
          <label class="block max-w-xs text-xs font-semibold text-gray-700">Anzahl Gruppen<input v-model.number="form.group_count" type="number" min="1" max="50" class="mt-1 w-full rounded border-gray-300 text-sm" @input="preview = null" /><span class="mt-1 block font-normal text-gray-500">Die Gruppen werden automatisch als G1, G2, G3 usw. angelegt.</span></label>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <h3 class="mb-3 font-bold text-gray-900">Bereiche und Anleiter</h3>
          <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="area in options.areas" :key="area.id" class="rounded border p-3">
              <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" :checked="Boolean(areaSetting(area.id))" @change="toggleArea(area.id)" />{{ area.name }}</label>
              <div v-if="areaSetting(area.id)" class="mt-2">
                <p class="mb-2 text-[11px] font-semibold text-orange-700">Dauer wird automatisch berechnet</p>
                <label class="text-[11px] text-gray-600">Anleiter<select v-model="areaSetting(area.id).supervisor_person_id" class="mt-1 w-full rounded border-gray-300 text-xs" @change="preview = null"><option :value="null">Nicht festgelegt</option><option v-for="person in options.supervisors" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 flex items-center justify-between gap-2"><div><h3 class="font-bold text-gray-900">Aktivitäten und Pausen</h3><p class="text-xs text-gray-500">Für gestaffelte Pausen zwei Einträge anlegen: einmal Hälfte 1 und einmal Hälfte 2.</p></div><button type="button" class="rounded border px-3 py-1.5 text-xs font-semibold" @click="addEvent">+ Aktivität</button></div>
          <div class="space-y-2">
            <div v-for="(event, index) in form.events" :key="index" class="grid gap-2 rounded border bg-gray-50 p-2 md:grid-cols-[1.4fr_8rem_10rem_8rem_8rem_auto]">
              <input v-model="event.title" class="rounded border-gray-300 text-sm" placeholder="Begrüßung" @input="preview = null" />
              <select v-model="event.type" class="rounded border-gray-300 text-xs" @change="preview = null"><option value="shared">Gemeinsam</option><option value="break">Pause</option><option value="extra">Zusatz</option></select>
              <select v-model="event.group_scope" class="rounded border-gray-300 text-xs" @change="preview = null"><option value="all">Alle Gruppen</option><option value="first_half">Hälfte 1 ({{ halfLabel('first_half') }})</option><option value="second_half" :disabled="groups().length < 2">Hälfte 2 ({{ halfLabel('second_half') }})</option></select>
              <input v-model="event.start_time" type="time" class="rounded border-gray-300 text-sm" @input="preview = null" />
              <input v-model="event.end_time" type="time" class="rounded border-gray-300 text-sm" @input="preview = null" />
              <button type="button" class="px-2 font-bold text-red-600" @click="removeEvent(index)">×</button>
            </div>
          </div>
        </section>

        <div class="flex flex-wrap gap-2"><button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="generate(false)">{{ busy ? 'Berechnet …' : 'Vorschau erzeugen' }}</button><button type="button" class="rounded bg-orange-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !preview" @click="generate(true)">Zeitplan speichern</button></div>

        <section v-if="preview" class="overflow-hidden rounded-lg border bg-white">
          <div class="border-b bg-gray-50 px-4 py-3"><h3 class="font-bold">Zeitplan · {{ dateLabel(form.schedule_date) }}</h3><p class="text-xs font-semibold text-orange-700">Berechnete Bereichsdauer: {{ preview.config?.calculated_area_duration_minutes || preview.config?.areas?.[0]?.duration_minutes || '–' }} Minuten</p><p class="text-xs text-gray-500">Gemeinsame Einträge werden pro Gruppe angezeigt, aber nur einmal gespeichert.</p></div>
          <div class="overflow-x-auto"><table class="min-w-full divide-y text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Gruppe</th><th class="px-3 py-2">Zeit</th><th class="px-3 py-2">Aktivität</th><th class="px-3 py-2">Art / Anleiter</th></tr></thead><tbody class="divide-y"><template v-for="row in rows()" :key="row.group"><tr v-for="(entry, index) in row.entries" :key="`${row.group}-${entry.start_time}-${entry.title}`" :class="entry.type === 'area' ? 'bg-white' : 'bg-blue-50'"><td class="px-3 py-2 font-bold text-orange-800">{{ index === 0 ? row.group : '' }}</td><td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ String(entry.start_time).slice(0, 5) }}–{{ String(entry.end_time).slice(0, 5) }}</td><td class="px-3 py-2 font-semibold">{{ entry.title }}</td><td class="px-3 py-2 text-xs text-gray-600">{{ typeLabel(entry.type) }}<span v-if="entry.meta?.supervisor_name"> · {{ entry.meta.supervisor_name }}</span></td></tr></template></tbody></table></div>
        </section>
      </div>
    </section>
  </div>
</template>

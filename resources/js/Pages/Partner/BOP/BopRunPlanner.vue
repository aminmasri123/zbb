<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  partnerId: [Number, String],
  schuljahr: String,
  teil: String,
  schoolName: String,
})

const emit = defineEmits(['close', 'saved'])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const context = ref(null)
const classes = ref([])
const students = ref([])
const options = ref({ areas: [], rooms: [], supervisors: [] })
const schoolType = ref('Gemeinschaftsschule')
const status = ref('planning')
const phases = ref([])
const newDates = ref({})
const updatingParticipant = ref(null)

const phaseDefinitions = [
  { type: 'pa_preparation', label: '1. Vorbereitung PA', hint: 'Gesamte Schule oder getrennt nach Klassen; auf Wunsch Gruppen erzeugen.' },
  { type: 'pa', label: '2. Potenzialanalyse', hint: 'Beliebig viele PA-Tage für Schule, Klassen oder ausgewählte Teilnehmer.' },
  { type: 'pa_feedback', label: '3. Feedbackgespräch PA', hint: 'Meist ein Schultag; einzelne Teilnehmer können später als erledigt markiert werden.' },
  { type: 'roll_day', label: '4. Rolltag', hint: 'Nach Schule, Klasse oder automatisch in gleichmäßige Gruppen aufteilen.' },
  { type: 'workshop_days', label: '5. Werkstatttage', hint: 'Alle tatsächlichen Tage speichern; die Bereichseinteilung bleibt das führende Gruppensystem.' },
  { type: 'wt_feedback', label: '6. Feedbackgespräch WT', hint: 'Gesamte Schule oder abweichende Klassen-/Teilnehmerauswahl.' },
]

const sortedStudentsByClass = computed(() => {
  const groups = new Map()
  students.value.forEach((student) => {
    const key = student.class_name || 'Ohne Klasse'
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key).push(student)
  })
  return [...groups.entries()].map(([name, items]) => ({ name, students: items }))
})

const totalDates = computed(() => phases.value.reduce((sum, phase) => sum + (phase.dates?.length || 0), 0))
const firstDate = computed(() => phases.value.flatMap((phase) => phase.dates || []).sort()[0] || null)
const lastDate = computed(() => phases.value.flatMap((phase) => phase.dates || []).sort().at(-1) || null)

watch(() => props.visible, (visible) => {
  if (visible) load()
}, { immediate: true })

async function load() {
  if (!props.partnerId || !props.schuljahr || !props.teil) return
  loading.value = true
  error.value = ''
  try {
    const response = await axios.get(route('bop.run.show', {
      partner: props.partnerId,
      schuljahr: props.schuljahr,
      teil: props.teil,
    }))
    hydrate(response.data)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Der BOP-Ablauf konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

function hydrate(data) {
  context.value = data.context
  classes.value = data.classes || []
  students.value = data.students || []
  options.value = data.options || { areas: [], rooms: [], supervisors: [] }
  schoolType.value = data.run?.school_type || data.school_type_suggestion || 'Gemeinschaftsschule'
  status.value = data.run?.status || 'planning'
  phases.value = (data.phases || []).map((phase) => ({
    ...phase,
    dates: [...(phase.dates || [])].sort(),
    selected_classes: [...(phase.selected_classes || [])],
    participant_ids: [...(phase.participant_ids || [])].map(Number),
    start_time: String(phase.start_time || '08:00').slice(0, 5),
    end_time: String(phase.end_time || '16:00').slice(0, 5),
  }))
}

function phaseFor(type) {
  return phases.value.find((phase) => phase.phase_type === type)
}

function addDate(phase) {
  const date = newDates.value[phase.phase_type]
  if (!date) return
  phase.dates = [...new Set([...(phase.dates || []), date])].sort()
  newDates.value[phase.phase_type] = ''
}

function removeDate(phase, date) {
  phase.dates = phase.dates.filter((value) => value !== date)
}

function toggleValue(values, value) {
  const normalized = String(value)
  return values.map(String).includes(normalized)
    ? values.filter((item) => String(item) !== normalized)
    : [...values, value]
}

function toggleClass(phase, className) {
  phase.selected_classes = toggleValue(phase.selected_classes || [], className)
}

function toggleStudent(phase, studentId) {
  phase.participant_ids = toggleValue(phase.participant_ids || [], Number(studentId)).map(Number)
}

function selectedCount(phase) {
  if (phase.scope_type === 'school') return students.value.length
  if (phase.scope_type === 'classes') return students.value.filter((student) => phase.selected_classes.includes(student.class_name)).length
  return phase.participant_ids.length
}

function dateLabel(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString('de-DE') : '–'
}

function phaseNeedsOwnGroups(phase) {
  return !['none', 'existing_assignment'].includes(phase.group_mode)
}

function isFeedbackPhase(type) {
  return ['pa_feedback', 'wt_feedback'].includes(type)
}

function studentForRecord(record) {
  return students.value.find((student) => Number(student.id) === Number(record.personen_ist_schueler_id))
}

async function setParticipantCompleted(record, completed) {
  updatingParticipant.value = record.id
  error.value = ''
  try {
    const response = await axios.put(route('bop.run.participant.status', { participant: record.id }), {
      completion_status: completed ? 'completed' : 'planned',
      notes: record.notes || null,
    })
    Object.assign(record, response.data.participant)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Der Gesprächsstatus konnte nicht gespeichert werden.'
  } finally {
    updatingParticipant.value = null
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const response = await axios.put(route('bop.run.update', {
      partner: props.partnerId,
      schuljahr: props.schuljahr,
      teil: props.teil,
    }), {
      school_type: schoolType.value,
      status: status.value,
      phases: phases.value,
    })
    hydrate(response.data)
    emit('saved', response.data)
  } catch (exception) {
    const validation = exception.response?.data?.errors
    error.value = validation ? Object.values(validation)[0]?.[0] : (exception.response?.data?.message || 'Der BOP-Ablauf konnte nicht gespeichert werden.')
  } finally {
    saving.value = false
  }
}

function closeOnEscape(event) {
  if (event.key === 'Escape' && props.visible && !saving.value) emit('close')
}

onMounted(() => window.addEventListener('keydown', closeOnEscape))
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape))
</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[100] overflow-y-auto bg-slate-950/55 p-3 md:p-6" @click.self="$emit('close')">
    <section class="mx-auto min-h-[calc(100vh-24px)] max-w-7xl rounded-xl bg-gray-50 shadow-2xl md:min-h-0">
      <header class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 rounded-t-xl border-b bg-white px-5 py-4">
        <div>
          <h2 class="text-xl font-bold text-gray-900">BOP-Ablauf · {{ schoolName }}</h2>
          <p class="text-sm text-gray-500">{{ schuljahr }} · {{ teil }} · {{ students.length }} Teilnehmer</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded border px-4 py-2 text-sm font-semibold" :disabled="saving" @click="$emit('close')">Schließen</button>
          <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading || saving" @click="save">{{ saving ? 'Speichert …' : 'Alles speichern' }}</button>
        </div>
      </header>

      <div v-if="loading" class="p-12 text-center text-gray-500">BOP-Daten werden geladen …</div>
      <div v-else class="space-y-5 p-5">
        <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ error }}</div>

        <section class="grid gap-4 rounded-lg border bg-white p-4 md:grid-cols-4">
          <label class="text-sm font-semibold text-gray-700">Schulform
            <select v-model="schoolType" class="mt-1 w-full rounded border-gray-300 text-sm"><option>Gemeinschaftsschule</option><option>Förderschule</option></select>
          </label>
          <label class="text-sm font-semibold text-gray-700">Status
            <select v-model="status" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="planning">In Planung</option><option value="confirmed">Bestätigt</option><option value="completed">Abgeschlossen</option></select>
          </label>
          <div class="rounded bg-orange-50 p-3 text-sm"><span class="block text-xs text-gray-500">Erster Besuch</span><strong>{{ dateLabel(firstDate) }}</strong></div>
          <div class="rounded bg-orange-50 p-3 text-sm"><span class="block text-xs text-gray-500">Letzter Besuch</span><strong>{{ dateLabel(lastDate) }}</strong><span class="ml-2 text-xs text-gray-500">({{ totalDates }} Termine)</span></div>
        </section>

        <section v-for="definition in phaseDefinitions" :key="definition.type" class="rounded-lg border bg-white shadow-sm">
          <template v-if="phaseFor(definition.type)">
            <div class="border-b px-4 py-3">
              <h3 class="font-bold text-gray-900">{{ definition.label }}</h3>
              <p class="text-xs text-gray-500">{{ definition.hint }}</p>
            </div>
            <div class="grid gap-5 p-4 xl:grid-cols-[1fr_1.1fr_1fr]">
              <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">Tage</label>
                <div class="flex gap-2">
                  <input v-model="newDates[definition.type]" type="date" class="min-w-0 flex-1 rounded border-gray-300 text-sm" @keydown.enter.prevent="addDate(phaseFor(definition.type))" />
                  <button type="button" class="rounded bg-gray-900 px-3 text-sm font-semibold text-white" @click="addDate(phaseFor(definition.type))">+ Tag</button>
                </div>
                <div class="flex min-h-9 flex-wrap gap-2">
                  <button v-for="date in phaseFor(definition.type).dates" :key="date" type="button" class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-800" @click="removeDate(phaseFor(definition.type), date)">{{ dateLabel(date) }} ×</button>
                  <span v-if="!phaseFor(definition.type).dates.length" class="text-xs text-gray-400">Noch kein Termin gespeichert.</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <label class="text-xs text-gray-600">Beginn<input v-model="phaseFor(definition.type).start_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                  <label class="text-xs text-gray-600">Ende<input v-model="phaseFor(definition.type).end_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                </div>
              </div>

              <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">Wer nimmt teil? <span class="text-orange-600">{{ selectedCount(phaseFor(definition.type)) }}</span></label>
                <select v-model="phaseFor(definition.type).scope_type" class="w-full rounded border-gray-300 text-sm">
                  <option value="school">Gesamte Schule</option><option value="classes">Bestimmte Klassen</option><option value="participants">Einzelne Teilnehmer</option>
                </select>
                <div v-if="phaseFor(definition.type).scope_type === 'classes'" class="flex flex-wrap gap-2">
                  <label v-for="className in classes" :key="className" class="inline-flex items-center gap-2 rounded border px-3 py-2 text-sm">
                    <input type="checkbox" :checked="phaseFor(definition.type).selected_classes.includes(className)" @change="toggleClass(phaseFor(definition.type), className)" />{{ className }}
                  </label>
                </div>
                <div v-if="phaseFor(definition.type).scope_type === 'participants'" class="max-h-60 overflow-y-auto rounded border">
                  <template v-for="(classGroup, classIndex) in sortedStudentsByClass" :key="classGroup.name">
                    <div v-if="classIndex" class="border-t-4 border-orange-100"></div>
                    <div class="sticky top-0 bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700">Klasse {{ classGroup.name }}</div>
                    <label v-for="student in classGroup.students" :key="student.id" class="flex items-center gap-2 border-t px-3 py-1.5 text-sm">
                      <input type="checkbox" :checked="phaseFor(definition.type).participant_ids.map(Number).includes(Number(student.id))" @change="toggleStudent(phaseFor(definition.type), student.id)" />{{ student.name }}
                    </label>
                  </template>
                </div>
              </div>

              <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">Gruppen</label>
                <select v-model="phaseFor(definition.type).group_mode" class="w-full rounded border-gray-300 text-sm">
                  <option value="none">Keine Gruppe / Einzelgespräche</option><option value="school">Eine Gruppe: gesamte Schule</option><option value="class">Eine Gruppe pro Klasse</option><option value="balanced">Gleichmäßig aufteilen</option><option v-if="definition.type === 'workshop_days'" value="existing_assignment">Bestehende Bereichseinteilung verwenden</option>
                </select>
                <label v-if="phaseFor(definition.type).group_mode === 'balanced'" class="block text-xs text-gray-600">Anzahl Gruppen<input v-model.number="phaseFor(definition.type).group_count" type="number" min="1" max="50" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                <label v-if="phaseNeedsOwnGroups(phaseFor(definition.type))" class="flex items-start gap-2 rounded border bg-gray-50 p-2 text-xs text-gray-700">
                  <input v-model="phaseFor(definition.type).generate_groups" type="checkbox" class="mt-0.5" />Bestehende Anwesenheitsgruppen und Tageseinträge automatisch erzeugen
                </label>
                <template v-if="phaseFor(definition.type).generate_groups">
                  <select v-model="phaseFor(definition.type).supervisor_person_id" class="w-full rounded border-gray-300 text-sm"><option :value="null">Betreuung automatisch</option><option v-for="item in options.supervisors" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                  <select v-model="phaseFor(definition.type).bereich_id" class="w-full rounded border-gray-300 text-sm"><option :value="null">Bereich automatisch</option><option v-for="item in options.areas" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                  <select v-model="phaseFor(definition.type).raum_id" class="w-full rounded border-gray-300 text-sm"><option :value="null">Raum automatisch</option><option v-for="item in options.rooms" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                </template>
                <label class="flex items-start gap-2 rounded border border-blue-100 bg-blue-50 p-2 text-xs text-blue-800"><input v-model="phaseFor(definition.type).publish_to_calendar" type="checkbox" class="mt-0.5" />Optional im Projektkalender veröffentlichen; die BOP-Daten bleiben unabhängig gespeichert.</label>
                <textarea v-model="phaseFor(definition.type).notes" rows="2" class="w-full rounded border-gray-300 text-sm" placeholder="Bemerkung"></textarea>
              </div>

              <div v-if="isFeedbackPhase(definition.type) && phaseFor(definition.type).participants?.length" class="xl:col-span-3">
                <div class="mb-2 flex items-center justify-between">
                  <h4 class="text-sm font-bold text-gray-800">Geführte Gespräche abhaken</h4>
                  <span class="text-xs text-gray-500">Wird sofort gespeichert</span>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                  <label v-for="record in phaseFor(definition.type).participants" :key="record.id" class="flex items-center gap-2 rounded border px-3 py-2 text-sm" :class="record.completion_status === 'completed' ? 'border-green-200 bg-green-50 text-green-800' : 'bg-white'">
                    <input type="checkbox" :checked="record.completion_status === 'completed'" :disabled="updatingParticipant === record.id" @change="setParticipantCompleted(record, $event.target.checked)" />
                    <span class="min-w-0"><strong class="block truncate">{{ studentForRecord(record)?.name || `Teilnehmer ${record.personen_ist_schueler_id}` }}</strong><span class="text-xs opacity-70">Klasse {{ studentForRecord(record)?.class_name || record.class_name || '–' }}</span></span>
                  </label>
                </div>
              </div>
            </div>
          </template>
        </section>

        <footer class="sticky bottom-0 z-20 flex flex-wrap items-center justify-end gap-2 rounded-lg border bg-white/95 px-5 py-4 shadow-lg backdrop-blur">
          <button type="button" class="rounded border px-4 py-2 text-sm font-semibold" :disabled="saving" @click="$emit('close')">Schließen</button>
          <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading || saving" @click="save">{{ saving ? 'Speichert …' : 'Alles speichern' }}</button>
        </footer>
      </div>
    </section>
  </div>
</template>

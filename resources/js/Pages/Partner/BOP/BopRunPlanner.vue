<script setup>
import axios from 'axios'
import Swal from 'sweetalert2'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  partnerId: [Number, String],
  schuljahr: String,
  teil: String,
  schoolName: String,
  schoolYears: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const context = ref(null)
const hasSavedRun = ref(false)
const classes = ref([])
const students = ref([])
const options = ref({ areas: [], rooms: [], supervisors: [] })
const schoolType = ref('Gemeinschaftsschule')
const status = ref('planning')
const plannedClasses = ref([])
const newPlannedClass = ref({ name: '', expected_participants: 30 })
const phases = ref([])
const newDates = ref({})
const updatingParticipant = ref(null)
const selectedSchoolYear = ref(props.schuljahr || '')
const loadedSchoolYear = ref(null)
const parts = ref(['1'])
const participantPartsEnabled = ref(true)
const newPart = ref('')
const dateRanges = ref({})
const timetableBusy = ref(false)
const timetableError = ref('')
const timetableSuccess = ref('')
const timetablePreview = ref(null)
const timetableForm = ref({
  schedule_date: '', start_time: '09:00', end_time: '15:00', slot_minutes: 15,
  group_labels: 'G1, G2, G3, G4', areas: [], events: [],
})

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
const expectedParticipants = computed(() => plannedClasses.value.reduce((sum, item) => sum + Number(item.expected_participants || 0), 0))

watch(() => props.visible, (visible) => {
  if (visible) {
    selectedSchoolYear.value = props.schuljahr || selectedSchoolYear.value
    load()
  }
}, { immediate: true })

async function load() {
  if (!props.partnerId || !selectedSchoolYear.value) return
  loading.value = true
  error.value = ''
  try {
    const response = await axios.get(route('bop.run.show', {
      partner: props.partnerId,
      schuljahr: selectedSchoolYear.value,
      teil: '_all',
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
  hasSavedRun.value = Boolean(data.run)
  loadedSchoolYear.value = data.run?.schuljahr || null
  if (data.run?.schuljahr) selectedSchoolYear.value = data.run.schuljahr
  classes.value = data.classes || []
  students.value = data.students || []
  options.value = data.options || { areas: [], rooms: [], supervisors: [] }
  schoolType.value = data.run?.school_type || data.school_type_suggestion || 'Gemeinschaftsschule'
  status.value = data.run?.status || 'planning'
  participantPartsEnabled.value = data.participant_parts_enabled !== false
  parts.value = participantPartsEnabled.value
    ? [...(data.run ? (data.run.parts?.length ? data.run.parts : ['1']) : (data.suggested_parts?.length ? data.suggested_parts : ['1']))].map(String)
    : ['1']
  plannedClasses.value = (data.run ? (data.run.planned_classes || []) : (data.suggested_planned_classes || [])).map((item) => ({
    name: String(item.name || ''),
    expected_participants: Number(item.expected_participants || 0),
    part: String(item.part || '1'),
  }))
  phases.value = (data.phases || []).map((phase) => ({
    ...phase,
    dates: [...(phase.dates || [])].sort(),
    selected_classes: [...(phase.selected_classes || [])],
    days_per_class: Number(phase.days_per_class || 2),
    class_date_assignments: { ...(phase.class_date_assignments || {}) },
    part_date_assignments: { ...(phase.part_date_assignments || {}) },
    participant_ids: [...(phase.participant_ids || [])].map(Number),
    start_time: String(phase.start_time || '08:00').slice(0, 5),
    end_time: String(phase.end_time || '16:00').slice(0, 5),
  }))
  dateRanges.value = Object.fromEntries(phases.value.map((phase) => [phase.phase_type, { from: '', to: '' }]))
  hydrateTimetable()
}

function phaseFor(type) {
  return phases.value.find((phase) => phase.phase_type === type)
}

function timetableDate(value) {
  return String(value || '').slice(0, 10)
}

function savedTimetableForDate(date) {
  return (phaseFor('workshop_days')?.timetables || []).find((item) => timetableDate(item.schedule_date) === date)
}

function applyTimetable(timetable = null) {
  const workshop = phaseFor('workshop_days')
  const config = timetable?.config || {}
  const defaultGroups = plannedClasses.value.length
    ? plannedClasses.value.map((item) => item.name)
    : Array.from({ length: Math.max(1, Number(workshop?.group_count || 4)) }, (_, index) => `G${index + 1}`)

  timetableForm.value = {
    schedule_date: timetableDate(timetable?.schedule_date) || workshop?.dates?.[0] || '',
    start_time: String(config.start_time || workshop?.start_time || '09:00').slice(0, 5),
    end_time: String(config.end_time || workshop?.end_time || '15:00').slice(0, 5),
    slot_minutes: Number(timetable?.slot_minutes || 15),
    group_labels: (config.groups?.length ? config.groups : defaultGroups).join(', '),
    areas: (config.areas || []).map((area) => ({
      bereich_id: Number(area.bereich_id),
      duration_minutes: Number(area.duration_minutes || 60),
      supervisor_person_id: area.supervisor_person_id ? Number(area.supervisor_person_id) : null,
    })),
    events: (config.events || []).map((event) => ({
      title: event.title || '', type: event.type || 'shared',
      start_time: String(event.start_time || '').slice(0, 5),
      end_time: String(event.end_time || '').slice(0, 5),
    })),
  }
  timetablePreview.value = timetable
}

function hydrateTimetable() {
  const workshop = phaseFor('workshop_days')
  const firstSaved = workshop?.timetables?.[0] || null
  applyTimetable(firstSaved)
}

function changeTimetableDate() {
  const saved = savedTimetableForDate(timetableForm.value.schedule_date)
  if (saved) {
    applyTimetable(saved)
    return
  }
  timetablePreview.value = null
  timetableSuccess.value = ''
}

function timetableGroups() {
  return [...new Set(String(timetableForm.value.group_labels || '')
    .split(/[,;\n]+/)
    .map((value) => value.trim())
    .filter(Boolean))]
}

function areaSetting(areaId) {
  return timetableForm.value.areas.find((area) => Number(area.bereich_id) === Number(areaId))
}

function toggleTimetableArea(areaId) {
  const existing = areaSetting(areaId)
  if (existing) {
    timetableForm.value.areas = timetableForm.value.areas.filter((area) => Number(area.bereich_id) !== Number(areaId))
    timetablePreview.value = null
    return
  }
  timetableForm.value.areas.push({ bereich_id: Number(areaId), duration_minutes: 60, supervisor_person_id: null })
  timetablePreview.value = null
}

function addTimetableEvent() {
  timetableForm.value.events.push({ title: '', type: 'shared', start_time: '', end_time: '' })
  timetablePreview.value = null
}

function removeTimetableEvent(index) {
  timetableForm.value.events.splice(index, 1)
  timetablePreview.value = null
}

function timetableRows() {
  if (!timetablePreview.value) return []
  const entries = timetablePreview.value.entries || []
  const groups = timetablePreview.value.config?.groups || timetableGroups()
  return groups.map((group) => ({
    group,
    entries: entries
      .filter((entry) => entry.group_key === group || !entry.group_key)
      .sort((left, right) => String(left.start_time).localeCompare(String(right.start_time))),
  }))
}

function timetableTypeLabel(type) {
  return ({ shared: 'Gemeinsam', break: 'Pause', extra: 'Zusatz', area: 'Bereich' })[type] || type
}

function timetableRequest(persist) {
  return {
    schuljahr: selectedSchoolYear.value,
    teil: '_all',
    schedule_date: timetableForm.value.schedule_date,
    start_time: timetableForm.value.start_time,
    end_time: timetableForm.value.end_time,
    slot_minutes: Number(timetableForm.value.slot_minutes),
    groups: timetableGroups(),
    areas: timetableForm.value.areas.map((area) => ({
      bereich_id: Number(area.bereich_id),
      duration_minutes: Number(area.duration_minutes),
      supervisor_person_id: area.supervisor_person_id ? Number(area.supervisor_person_id) : null,
    })),
    events: timetableForm.value.events.map((event) => ({ ...event })),
    persist,
  }
}

async function generateTimetable(persist = false) {
  timetableBusy.value = true
  timetableError.value = ''
  timetableSuccess.value = ''
  const formSnapshot = JSON.parse(JSON.stringify(timetableForm.value))
  try {
    const saved = await save()
    if (!saved) return
    timetableForm.value = formSnapshot
    const response = await axios.post(route('bop.run.timetable.generate', { partner: props.partnerId }), timetableRequest(persist))
    timetablePreview.value = response.data.timetable
    timetableSuccess.value = response.data.message

    if (persist) {
      const workshop = phaseFor('workshop_days')
      const otherTimetables = (workshop.timetables || [])
        .filter((item) => timetableDate(item.schedule_date) !== timetableForm.value.schedule_date)
      workshop.timetables = [...otherTimetables, response.data.timetable]
        .sort((left, right) => timetableDate(left.schedule_date).localeCompare(timetableDate(right.schedule_date)))
    }
  } catch (exception) {
    const validation = exception.response?.data?.errors
    timetableError.value = validation
      ? Object.values(validation)[0]?.[0]
      : (exception.response?.data?.message || 'Der Zeitplan konnte nicht erzeugt werden.')
  } finally {
    timetableBusy.value = false
  }
}

async function addDate(phase) {
  const date = newDates.value[phase.phase_type]
  if (!date) return
  const warning = dateWarning(date)
  if (warning) {
    const result = await Swal.fire({
      title: warning.title,
      text: `${dateLabel(date)} ist ${warning.reason}. Soll der Termin trotzdem hinzugefügt werden?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Trotzdem hinzufügen',
      cancelButtonText: 'Abbrechen',
      confirmButtonColor: '#f97316',
    })
    if (!result.isConfirmed) return
  }
  phase.dates = [...new Set([...(phase.dates || []), date])].sort()
  newDates.value[phase.phase_type] = ''
}

function removeDate(phase, date) {
  phase.dates = phase.dates.filter((value) => value !== date)
  Object.keys(phase.class_date_assignments || {}).forEach((className) => {
    phase.class_date_assignments[className] = (phase.class_date_assignments[className] || []).filter((value) => value !== date)
  })
  Object.keys(phase.part_date_assignments || {}).forEach((part) => {
    phase.part_date_assignments[part] = (phase.part_date_assignments[part] || []).filter((value) => value !== date)
  })
}

function toggleValue(values, value) {
  const normalized = String(value)
  return values.map(String).includes(normalized)
    ? values.filter((item) => String(item) !== normalized)
    : [...values, value]
}

function toggleClass(phase, className) {
  phase.selected_classes = toggleValue(phase.selected_classes || [], className)
  if (phase.selected_classes.includes(className) && !phase.class_date_assignments[className]) {
    phase.class_date_assignments[className] = []
  }
}

function toggleClassDate(phase, className, date) {
  const assigned = phase.class_date_assignments[className] || []
  if (assigned.includes(date)) {
    phase.class_date_assignments[className] = assigned.filter((value) => value !== date)
    return
  }
  if (assigned.length >= Number(phase.days_per_class || 2)) return
  phase.class_date_assignments[className] = [...assigned, date].sort()
}

function autoAssignClassDates(phase) {
  const days = Math.max(1, Number(phase.days_per_class || 2))
  phase.class_date_assignments = Object.fromEntries(phase.selected_classes.map((className, index) => [
    className,
    phase.dates.slice(index * days, (index + 1) * days),
  ]))
}

function assignSameClassDates(phase) {
  const days = Math.max(1, Number(phase.days_per_class || 1))
  const sharedDates = phase.dates.slice(0, days)
  phase.class_date_assignments = Object.fromEntries(phase.selected_classes.map((className) => [className, [...sharedDates]]))
}

function togglePartDate(phase, part, date) {
  const assigned = phase.part_date_assignments[part] || []
  phase.part_date_assignments[part] = assigned.includes(date)
    ? assigned.filter((value) => value !== date)
    : [...assigned, date].sort()
}

function assignAllWorkshopDates(phase) {
  phase.part_date_assignments = Object.fromEntries(parts.value.map((part) => [part, [...phase.dates]]))
}

function refreshClasses() {
  classes.value = [...new Set([
    ...students.value.map((student) => student.class_name).filter(Boolean),
    ...plannedClasses.value.map((item) => item.name).filter(Boolean),
  ])].sort((a, b) => String(a).localeCompare(String(b), 'de', { numeric: true }))
}

function addPlannedClass() {
  const name = String(newPlannedClass.value.name || '').trim()
  if (!name) return
  const existing = plannedClasses.value.find((item) => item.name.toLocaleLowerCase('de') === name.toLocaleLowerCase('de'))
  if (existing) {
    existing.expected_participants = Number(newPlannedClass.value.expected_participants || existing.expected_participants || 0)
  } else {
    plannedClasses.value.push({ name, expected_participants: Number(newPlannedClass.value.expected_participants || 0), part: parts.value[0] || '1' })
  }
  newPlannedClass.value = { name: '', expected_participants: 30 }
  refreshClasses()
}

function addPart() {
  if (!participantPartsEnabled.value) return
  const value = String(newPart.value || '').trim()
  if (!value || parts.value.includes(value)) return
  parts.value.push(value)
  const workshop = phaseFor('workshop_days')
  if (workshop && !workshop.part_date_assignments[value]) workshop.part_date_assignments[value] = []
  parts.value.sort((a, b) => a.localeCompare(b, 'de', { numeric: true }))
  newPart.value = ''
}

function removePart(part) {
  if (parts.value.length === 1) return
  parts.value = parts.value.filter((item) => item !== part)
  plannedClasses.value.forEach((item) => {
    if (item.part === part) item.part = parts.value[0]
  })
  const workshop = phaseFor('workshop_days')
  if (workshop) delete workshop.part_date_assignments[part]
}

async function addDateRange(phase) {
  const range = dateRanges.value[phase.phase_type] || {}
  if (!range.from || !range.to || range.to < range.from) return
  const dates = []
  const cursor = new Date(`${range.from}T12:00:00`)
  const end = new Date(`${range.to}T12:00:00`)
  while (cursor <= end) {
    dates.push(isoDate(cursor))
    cursor.setDate(cursor.getDate() + 1)
  }
  const blocked = dates.filter((date) => dateWarning(date))
  let selected = dates
  if (blocked.length) {
    const result = await Swal.fire({
      title: 'Wochenenden oder Feiertage im Zeitraum',
      text: `${blocked.length} von ${dates.length} Tagen sind keine regulären Arbeitstage.`,
      icon: 'warning', showCancelButton: true, showDenyButton: true,
      confirmButtonText: 'Nur Arbeitstage', denyButtonText: 'Alle Tage', cancelButtonText: 'Abbrechen',
    })
    if (result.isDismissed) return
    if (result.isConfirmed) selected = dates.filter((date) => !dateWarning(date))
  }
  phase.dates = [...new Set([...(phase.dates || []), ...selected])].sort()
  dateRanges.value[phase.phase_type] = { from: '', to: '' }
}

function removePlannedClass(index) {
  plannedClasses.value.splice(index, 1)
  refreshClasses()
}

function easterDate(year) {
  const a = year % 19, b = Math.floor(year / 100), c = year % 100, d = Math.floor(b / 4), e = b % 4
  const f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30
  const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7
  const m = Math.floor((a + 11 * h + 22 * l) / 451)
  return new Date(year, Math.floor((h + l - 7 * m + 114) / 31) - 1, ((h + l - 7 * m + 114) % 31) + 1, 12)
}

function isoDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function shiftedDate(date, days) {
  const result = new Date(date)
  result.setDate(result.getDate() + days)
  return result
}

function saarlandHolidays(year) {
  const easter = easterDate(year)
  return {
    [`${year}-01-01`]: 'Neujahr', [`${year}-05-01`]: 'Tag der Arbeit',
    [`${year}-08-15`]: 'Mariä Himmelfahrt', [`${year}-10-03`]: 'Tag der Deutschen Einheit',
    [`${year}-11-01`]: 'Allerheiligen', [`${year}-12-25`]: '1. Weihnachtstag', [`${year}-12-26`]: '2. Weihnachtstag',
    [isoDate(shiftedDate(easter, -2))]: 'Karfreitag', [isoDate(shiftedDate(easter, 1))]: 'Ostermontag',
    [isoDate(shiftedDate(easter, 39))]: 'Christi Himmelfahrt', [isoDate(shiftedDate(easter, 50))]: 'Pfingstmontag',
    [isoDate(shiftedDate(easter, 60))]: 'Fronleichnam',
  }
}

function dateWarning(value) {
  const date = new Date(`${value}T12:00:00`)
  if (Number.isNaN(date.getTime())) return null
  const holiday = saarlandHolidays(date.getFullYear())[value]
  if (holiday) return { title: 'Feiertag erkannt', reason: `der gesetzliche Feiertag „${holiday}“ im Saarland` }
  if ([0, 6].includes(date.getDay())) return { title: 'Wochenende erkannt', reason: date.getDay() === 6 ? 'ein Samstag' : 'ein Sonntag' }
  return null
}

function toggleStudent(phase, studentId) {
  phase.participant_ids = toggleValue(phase.participant_ids || [], Number(studentId)).map(Number)
}

function selectedCount(phase) {
  if (phase.scope_type === 'school') return students.value.length
  if (phase.scope_type === 'classes') return students.value.filter((student) => phase.selected_classes.includes(student.class_name)).length
  return phase.participant_ids.length
}

function selectedCountLabel(phase) {
  const actual = selectedCount(phase)
  const selectedClassNames = phase.scope_type === 'classes' ? phase.selected_classes : classes.value
  const expected = plannedClasses.value
    .filter((item) => phase.scope_type !== 'participants' && selectedClassNames.includes(item.name))
    .reduce((sum, item) => sum + Number(item.expected_participants || 0), 0)
  return expected > 0 ? `${actual} erfasst / ${expected} erwartet` : String(actual)
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
      schuljahr: selectedSchoolYear.value,
      teil: '_all',
    }), {
      school_type: schoolType.value,
      original_schuljahr: loadedSchoolYear.value,
      status: status.value,
      parts: parts.value,
      planned_classes: plannedClasses.value,
      phases: phases.value,
    })
    hydrate(response.data)
    emit('saved', response.data)
    return response.data
  } catch (exception) {
    const validation = exception.response?.data?.errors
    error.value = validation ? Object.values(validation)[0]?.[0] : (exception.response?.data?.message || 'Der BOP-Ablauf konnte nicht gespeichert werden.')
    return null
  } finally {
    saving.value = false
  }
}

async function resetPlanning(forcedMode = null) {
  let mode = forcedMode
  if (!mode) {
    const choice = await Swal.fire({
      title: 'Alle Planungstermine zurücksetzen?',
      html: `<strong>${schoolName}</strong><br>${selectedSchoolYear}<br><br>Klassen, Teile und Bemerkungen bleiben erhalten.`,
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Termine zurücksetzen', cancelButtonText: 'Abbrechen', confirmButtonColor: '#f97316',
    })
    if (!choice.isConfirmed) return
    mode = 'dates'
  }
  if (mode === 'full') {
    const confirmation = await Swal.fire({
      title: 'Gesamte Planung wirklich löschen?',
      text: 'Planungsphasen, Zuordnungen und Kalendertermine werden entfernt. Importierte Teilnehmer und erfasste Anwesenheiten bleiben erhalten.',
      icon: 'error', showCancelButton: true,
      confirmButtonText: 'Gesamte Planung löschen', cancelButtonText: 'Abbrechen', confirmButtonColor: '#dc2626',
    })
    if (!confirmation.isConfirmed) return
  }

  saving.value = true
  error.value = ''
  try {
    const response = await axios.delete(route('bop.run.reset', { partner: props.partnerId }), {
      data: { schuljahr: selectedSchoolYear.value, teil: '_all', mode },
    })
    hydrate(response.data)
    emit('saved', response.data)
    await Swal.fire({ title: 'Zurückgesetzt', text: response.data.message, icon: 'success', confirmButtonText: 'OK' })
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Die BOP-Planung konnte nicht zurückgesetzt werden.'
  } finally {
    saving.value = false
  }
}

</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[100] overflow-hidden bg-slate-950/55 p-3 md:p-6">
    <section class="mx-auto flex h-full max-w-7xl flex-col overflow-hidden rounded-xl bg-gray-50 shadow-2xl">
      <header class="z-20 flex shrink-0 flex-wrap items-center justify-between gap-3 rounded-t-xl border-b bg-white px-5 py-4">
        <div>
          <h2 class="text-xl font-bold text-gray-900">BOP-Ablauf · {{ schoolName }}</h2>
          <p class="text-sm text-gray-500">{{ selectedSchoolYear }} · Gesamtplanung · {{ students.length }} Teilnehmer</p>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="hasSavedRun" type="button" class="rounded border border-orange-300 px-3 py-2 text-sm font-semibold text-orange-700" :disabled="loading || saving" @click="resetPlanning()">Termine zurücksetzen</button>
          <button v-if="hasSavedRun" type="button" class="rounded border border-red-300 px-3 py-2 text-sm font-semibold text-red-700" :disabled="loading || saving" @click="resetPlanning('full')">Planung löschen</button>
          <button type="button" class="rounded border px-4 py-2 text-sm font-semibold" :disabled="saving" @click="$emit('close')">Schließen</button>
          <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading || saving" @click="save">{{ saving ? 'Speichert …' : 'Alles speichern' }}</button>
        </div>
      </header>

      <div v-if="loading" class="min-h-0 flex-1 overflow-y-auto p-12 text-center text-gray-500">BOP-Daten werden geladen …</div>
      <div v-else class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
        <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ error }}</div>

        <section class="grid gap-4 rounded-lg border bg-white p-4 md:grid-cols-5">
          <label class="text-sm font-semibold text-gray-700">Schuljahr
            <div class="mt-1 flex gap-1"><input v-model="selectedSchoolYear" list="bop-school-years" type="text" class="min-w-0 flex-1 rounded border-gray-300 text-sm" placeholder="2026" /><datalist id="bop-school-years"><option v-for="year in schoolYears" :key="year" :value="year"></option></datalist><button type="button" class="rounded border px-2 text-xs" :disabled="loading || saving" @click="load">Laden</button></div>
            <span class="mt-1 block text-xs font-normal text-gray-500">Frei eingeben oder vorhandenes Schuljahr auswählen</span>
          </label>
          <label class="text-sm font-semibold text-gray-700">Schulform
            <select v-model="schoolType" class="mt-1 w-full rounded border-gray-300 text-sm"><option>Gemeinschaftsschule</option><option>Förderschule</option></select>
          </label>
          <label class="text-sm font-semibold text-gray-700">Status
            <select v-model="status" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="planning">In Planung</option><option value="confirmed">Bestätigt</option><option value="completed">Abgeschlossen</option></select>
          </label>
          <div class="rounded bg-orange-50 p-3 text-sm"><span class="block text-xs text-gray-500">Erster Besuch</span><strong>{{ dateLabel(firstDate) }}</strong></div>
          <div class="rounded bg-orange-50 p-3 text-sm"><span class="block text-xs text-gray-500">Letzter Besuch</span><strong>{{ dateLabel(lastDate) }}</strong><span class="ml-2 text-xs text-gray-500">({{ totalDates }} Termine)</span></div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div><h3 class="font-bold text-gray-900">Vorläufige Klassenplanung</h3><p class="text-xs text-gray-500">Klassen und erwartete Teilnehmer können schon vor dem Teilnehmerimport geplant werden.</p></div>
            <span class="rounded bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-800">{{ expectedParticipants }} Teilnehmer erwartet</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <input v-model="newPlannedClass.name" type="text" class="w-40 rounded border-gray-300 text-sm" placeholder="Klasse, z. B. 7.1" @keydown.enter.prevent="addPlannedClass" />
            <input v-model.number="newPlannedClass.expected_participants" type="number" min="0" max="500" class="w-44 rounded border-gray-300 text-sm" placeholder="Erwartete Teilnehmer" @keydown.enter.prevent="addPlannedClass" />
            <button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white" @click="addPlannedClass">+ Klasse</button>
          </div>
          <div v-if="participantPartsEnabled" class="mt-3 flex flex-wrap items-center gap-2 rounded border bg-gray-50 p-2">
            <strong class="text-xs text-gray-700">Teile:</strong>
            <span v-for="part in parts" :key="part" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-semibold shadow-sm">Teil {{ part }}<button v-if="parts.length > 1" type="button" class="text-red-600" @click="removePart(part)">×</button></span>
            <input v-model="newPart" type="text" class="w-24 rounded border-gray-300 py-1 text-xs" placeholder="z. B. 2" @keydown.enter.prevent="addPart" />
            <button type="button" class="rounded border px-2 py-1 text-xs font-semibold" @click="addPart">+ Teil</button>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <div v-for="(item, index) in plannedClasses" :key="`${item.name}-${index}`" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-sm">
              <strong>{{ item.name }}</strong><input v-model.number="item.expected_participants" type="number" min="0" max="500" class="w-16 rounded border-gray-300 px-1 py-0.5 text-xs" title="Erwartete Teilnehmer" /><span class="text-xs text-gray-500">TN</span>
              <select v-if="participantPartsEnabled && parts.length > 1" v-model="item.part" class="rounded border-gray-300 py-0.5 text-xs"><option v-for="part in parts" :key="part" :value="part">Teil {{ part }}</option></select><span v-else-if="participantPartsEnabled" class="text-xs text-gray-500">Teil 1</span>
              <button type="button" class="font-bold text-red-600" title="Planungsklasse entfernen" @click="removePlannedClass(index)">×</button>
            </div>
            <span v-if="!plannedClasses.length" class="text-xs text-gray-400">Noch keine vorläufige Klasse eingetragen.</span>
          </div>
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
                <div v-if="['pa', 'workshop_days'].includes(definition.type)" class="rounded border bg-gray-50 p-2">
                  <div class="mb-1 text-xs font-semibold text-gray-700">Zeitraum von–bis hinzufügen</div>
                  <div class="grid grid-cols-2 gap-1">
                    <input v-model="dateRanges[definition.type].from" type="date" class="min-w-0 rounded border-gray-300 text-xs" />
                    <input v-model="dateRanges[definition.type].to" type="date" class="min-w-0 rounded border-gray-300 text-xs" />
                  </div>
                  <button type="button" class="mt-2 w-full rounded border bg-white px-2 py-1 text-xs font-semibold" @click="addDateRange(phaseFor(definition.type))">Zeitraum hinzufügen</button>
                </div>
                <div class="flex min-h-9 flex-wrap gap-2">
                  <button v-for="date in phaseFor(definition.type).dates" :key="date" type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="dateWarning(date) ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'" :title="dateWarning(date)?.reason" @click="removeDate(phaseFor(definition.type), date)">{{ dateLabel(date) }} <span v-if="dateWarning(date)">⚠</span> ×</button>
                  <span v-if="!phaseFor(definition.type).dates.length" class="text-xs text-gray-400">Noch kein Termin gespeichert.</span>
                </div>
                <div v-if="definition.type === 'workshop_days' && participantPartsEnabled" class="space-y-2 rounded border border-orange-200 bg-orange-50 p-3">
                  <div class="flex items-center justify-between gap-2"><strong class="text-xs text-orange-900">Werkstatttage nach Teil</strong><button type="button" class="rounded border border-orange-300 bg-white px-2 py-1 text-xs font-semibold text-orange-800" @click="assignAllWorkshopDates(phaseFor(definition.type))">Alle Teile: alle Termine</button></div>
                  <div v-for="part in parts" :key="`workshop-part-${part}`" class="rounded bg-white p-2">
                    <strong class="mb-1 block text-xs">Teil {{ part }}</strong>
                    <div class="flex flex-wrap gap-1">
                      <label v-for="date in phaseFor(definition.type).dates" :key="`${part}-${date}`" class="inline-flex items-center gap-1 rounded border px-2 py-1 text-xs" :class="(phaseFor(definition.type).part_date_assignments[part] || []).includes(date) ? 'border-orange-400 bg-orange-100' : 'bg-white'">
                        <input type="checkbox" :checked="(phaseFor(definition.type).part_date_assignments[part] || []).includes(date)" @change="togglePartDate(phaseFor(definition.type), part, date)" />{{ dateLabel(date) }}
                      </label>
                      <span v-if="!phaseFor(definition.type).dates.length" class="text-xs text-gray-500">Zuerst Werkstatttage hinzufügen.</span>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <label class="text-xs text-gray-600">Beginn<input v-model="phaseFor(definition.type).start_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                  <label class="text-xs text-gray-600">Ende<input v-model="phaseFor(definition.type).end_time" type="time" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                </div>
                <label v-if="definition.type !== 'workshop_days' && phaseFor(definition.type).scope_type === 'classes'" class="block text-xs font-semibold text-gray-700">Tage pro Klasse
                  <input v-model.number="phaseFor(definition.type).days_per_class" type="number" min="1" max="20" class="mt-1 w-full rounded border-gray-300 text-sm" />
                  <span class="mt-1 block font-normal text-gray-500">{{ definition.type === 'pa' ? 'PA-Standard: 2 Tage' : 'Standard: 1 Tag' }}</span>
                </label>
              </div>

              <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">Wer nimmt teil? <span class="text-orange-600">{{ selectedCountLabel(phaseFor(definition.type)) }}</span></label>
                <select v-model="phaseFor(definition.type).scope_type" class="w-full rounded border-gray-300 text-sm">
                  <option value="school">Gesamte Schule</option><option value="classes">Bestimmte Klassen</option><option value="participants">Einzelne Teilnehmer</option>
                </select>
                <div v-if="phaseFor(definition.type).scope_type === 'classes'" class="flex flex-wrap gap-2">
                  <label v-for="className in classes" :key="className" class="inline-flex items-center gap-2 rounded border px-3 py-2 text-sm">
                    <input type="checkbox" :checked="phaseFor(definition.type).selected_classes.includes(className)" @change="toggleClass(phaseFor(definition.type), className)" />{{ className }}
                  </label>
                </div>
                <div v-if="definition.type !== 'workshop_days' && phaseFor(definition.type).scope_type === 'classes' && phaseFor(definition.type).selected_classes.length" class="space-y-2 rounded border border-orange-200 bg-orange-50 p-3">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-xs text-orange-900">Welche Klasse kommt wann?</strong>
                    <div class="flex flex-wrap gap-1"><button type="button" class="rounded border border-orange-300 bg-white px-2 py-1 text-xs font-semibold text-orange-800" @click="assignSameClassDates(phaseFor(definition.type))">Alle gleichzeitig</button><button type="button" class="rounded bg-orange-600 px-2 py-1 text-xs font-semibold text-white" @click="autoAssignClassDates(phaseFor(definition.type))">Getrennt verteilen</button></div>
                  </div>
                  <div v-for="className in phaseFor(definition.type).selected_classes" :key="`dates-${className}`" class="rounded bg-white p-2">
                    <div class="mb-1 flex justify-between text-xs"><strong>Klasse {{ className }}</strong><span>{{ (phaseFor(definition.type).class_date_assignments[className] || []).length }} / {{ phaseFor(definition.type).days_per_class }} Tage</span></div>
                    <div class="flex flex-wrap gap-1">
                      <label v-for="date in phaseFor(definition.type).dates" :key="`${className}-${date}`" class="inline-flex items-center gap-1 rounded border px-2 py-1 text-xs" :class="(phaseFor(definition.type).class_date_assignments[className] || []).includes(date) ? 'border-orange-400 bg-orange-100' : 'bg-white'">
                        <input type="checkbox" :checked="(phaseFor(definition.type).class_date_assignments[className] || []).includes(date)" :disabled="!(phaseFor(definition.type).class_date_assignments[className] || []).includes(date) && (phaseFor(definition.type).class_date_assignments[className] || []).length >= phaseFor(definition.type).days_per_class" @change="toggleClassDate(phaseFor(definition.type), className, date)" />{{ dateLabel(date) }}
                      </label>
                      <span v-if="!phaseFor(definition.type).dates.length" class="text-xs text-gray-500">Zuerst Termine hinzufügen.</span>
                    </div>
                  </div>
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

              <section v-if="definition.type === 'workshop_days'" class="space-y-4 rounded-lg border-2 border-orange-200 bg-orange-50/40 p-4 xl:col-span-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h4 class="font-bold text-gray-900">Tageszeitplan für Bereiche</h4>
                    <p class="text-xs text-gray-600">Gemeinsame Termine gelten für alle Gruppen. Jeder normale Bereich und jeder Anleiter wird zeitgleich höchstens einmal eingeplant.</p>
                  </div>
                  <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-orange-800 shadow-sm">Konfliktprüfung aktiv</span>
                </div>

                <div v-if="timetableError" class="rounded border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ timetableError }}</div>
                <div v-if="timetableSuccess" class="rounded border border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700">{{ timetableSuccess }}</div>

                <div class="grid gap-3 md:grid-cols-4">
                  <label class="text-xs font-semibold text-gray-700">Werkstatttag
                    <select v-model="timetableForm.schedule_date" class="mt-1 w-full rounded border-gray-300 bg-white text-sm" @change="changeTimetableDate">
                      <option value="">Tag auswählen</option>
                      <option v-for="date in phaseFor('workshop_days').dates" :key="`timetable-${date}`" :value="date">{{ dateLabel(date) }}</option>
                    </select>
                  </label>
                  <label class="text-xs font-semibold text-gray-700">Tagesbeginn
                    <input v-model="timetableForm.start_time" type="time" class="mt-1 w-full rounded border-gray-300 bg-white text-sm" @input="timetablePreview = null" />
                  </label>
                  <label class="text-xs font-semibold text-gray-700">Tagesende
                    <input v-model="timetableForm.end_time" type="time" class="mt-1 w-full rounded border-gray-300 bg-white text-sm" @input="timetablePreview = null" />
                  </label>
                  <label class="text-xs font-semibold text-gray-700">Zeitraster
                    <select v-model.number="timetableForm.slot_minutes" class="mt-1 w-full rounded border-gray-300 bg-white text-sm" @change="timetablePreview = null">
                      <option :value="5">5 Minuten</option><option :value="10">10 Minuten</option><option :value="15">15 Minuten</option><option :value="30">30 Minuten</option>
                    </select>
                  </label>
                </div>

                <label class="block text-xs font-semibold text-gray-700">Gruppen
                  <textarea v-model="timetableForm.group_labels" rows="2" class="mt-1 w-full rounded border-gray-300 bg-white text-sm" placeholder="G1, G2, G3, G4" @input="timetablePreview = null"></textarea>
                  <span class="mt-1 block font-normal text-gray-500">Gruppennamen mit Komma, Semikolon oder Zeilenumbruch trennen.</span>
                </label>

                <div>
                  <h5 class="mb-2 text-sm font-bold text-gray-800">Bereiche und Anleiter</h5>
                  <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="item in options.areas" :key="`timetable-area-${item.id}`" class="rounded border bg-white p-3">
                      <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" :checked="Boolean(areaSetting(item.id))" class="rounded border-gray-300 text-orange-600" @change="toggleTimetableArea(item.id)" />{{ item.name }}
                      </label>
                      <div v-if="areaSetting(item.id)" class="mt-2 grid grid-cols-[7rem_1fr] gap-2">
                        <label class="text-[11px] text-gray-600">Dauer (Min.)
                          <input v-model.number="areaSetting(item.id).duration_minutes" type="number" :step="timetableForm.slot_minutes" :min="timetableForm.slot_minutes" max="480" class="mt-1 w-full rounded border-gray-300 text-xs" @input="timetablePreview = null" />
                        </label>
                        <label class="text-[11px] text-gray-600">Anleiter
                          <select v-model="areaSetting(item.id).supervisor_person_id" class="mt-1 w-full rounded border-gray-300 text-xs" @change="timetablePreview = null">
                            <option :value="null">Noch nicht festgelegt</option>
                            <option v-for="supervisor in options.supervisors" :key="`${item.id}-${supervisor.id}`" :value="supervisor.id">{{ supervisor.name }}</option>
                          </select>
                        </label>
                      </div>
                    </div>
                    <p v-if="!options.areas.length" class="text-xs text-gray-500">Im aktiven Projekt sind noch keine Bereiche hinterlegt.</p>
                  </div>
                </div>

                <div>
                  <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div><h5 class="text-sm font-bold text-gray-800">Gemeinsame Aktivitäten</h5><p class="text-xs text-gray-500">Zum Beispiel Begrüßung, gemeinsame Pause oder Standortbestimmung.</p></div>
                    <button type="button" class="rounded border border-orange-300 bg-white px-3 py-1.5 text-xs font-semibold text-orange-800" @click="addTimetableEvent">+ Gemeinsame Aktivität</button>
                  </div>
                  <div class="space-y-2">
                    <div v-for="(event, eventIndex) in timetableForm.events" :key="`timetable-event-${eventIndex}`" class="grid gap-2 rounded border bg-white p-2 md:grid-cols-[1.4fr_9rem_8rem_8rem_auto]">
                      <input v-model="event.title" type="text" class="rounded border-gray-300 text-sm" placeholder="Begrüßung" @input="timetablePreview = null" />
                      <select v-model="event.type" class="rounded border-gray-300 text-xs" @change="timetablePreview = null"><option value="shared">Gemeinsam</option><option value="break">Pause</option><option value="extra">Zusatzaktivität</option></select>
                      <input v-model="event.start_time" type="time" class="rounded border-gray-300 text-sm" title="Beginn" @input="timetablePreview = null" />
                      <input v-model="event.end_time" type="time" class="rounded border-gray-300 text-sm" title="Ende" @input="timetablePreview = null" />
                      <button type="button" class="rounded px-2 font-bold text-red-600" title="Aktivität entfernen" @click="removeTimetableEvent(eventIndex)">×</button>
                    </div>
                    <p v-if="!timetableForm.events.length" class="rounded border border-dashed bg-white p-3 text-xs text-gray-500">Noch keine gemeinsame Aktivität eingetragen.</p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="timetableBusy" @click="generateTimetable(false)">{{ timetableBusy ? 'Berechnet …' : 'Vorschau erzeugen' }}</button>
                  <button type="button" class="rounded bg-orange-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="timetableBusy || !timetablePreview" @click="generateTimetable(true)">Zeitplan speichern</button>
                </div>

                <div v-if="timetablePreview" class="overflow-hidden rounded-lg border bg-white">
                  <div class="border-b bg-gray-50 px-4 py-3">
                    <h5 class="font-bold text-gray-900">Zeitplanvorschau · {{ dateLabel(timetableForm.schedule_date) }}</h5>
                    <p class="text-xs text-gray-500">Gemeinsame Einträge werden bei jeder Gruppe angezeigt, sind technisch aber nur ein gemeinsamer Termin.</p>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="min-w-full divide-y text-sm">
                      <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-2">Gruppe</th><th class="px-3 py-2">Zeit</th><th class="px-3 py-2">Aktivität</th><th class="px-3 py-2">Art / Anleiter</th></tr></thead>
                      <tbody class="divide-y">
                        <template v-for="row in timetableRows()" :key="`row-${row.group}`">
                          <tr v-for="(entry, entryIndex) in row.entries" :key="`${row.group}-${entry.start_time}-${entry.title}`" :class="entry.type === 'area' ? 'bg-white' : 'bg-blue-50'">
                            <td class="px-3 py-2 font-bold text-orange-800">{{ entryIndex === 0 ? row.group : '' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ String(entry.start_time).slice(0, 5) }}–{{ String(entry.end_time).slice(0, 5) }}</td>
                            <td class="px-3 py-2 font-semibold">{{ entry.title }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600"><span class="rounded bg-gray-100 px-2 py-0.5">{{ timetableTypeLabel(entry.type) }}</span><span v-if="entry.meta?.supervisor_name" class="ml-2">{{ entry.meta.supervisor_name }}</span></td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </div>
              </section>

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

      </div>
    </section>
  </div>
</template>

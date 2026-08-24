<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Swal from 'sweetalert2'
import axios from 'axios'
import { jsPDF } from 'jspdf'
import SignatureBox from '@/Components/SignatureBox.vue'
import {
  drawBopAttendanceFooter,
  loadBopAttendanceFooterImage,
} from '@/utils/bopAttendanceFooter'
import { usePermissions } from '@/utils/permissions'
import { resolvePaSignatureKey } from '@/utils/paSignatureKeyResolver'
import { prepareSignaturesForPdf } from '@/utils/signatures'

const props = defineProps({
  visible: Boolean,
  partnerId: [String, Number],
  schuljahr: [String, Number],
  teil: [String, Number],
  klasse: {
    type: String,
    default: '',
  },
  klassen: {
    type: Array,
    default: () => [],
  },
  listType: {
    type: String,
    default: 'pa',
  },
})

const emit = defineEmits(['update:visible', 'close'])
const { can } = usePermissions()
const canArchiveAttendance = computed(() => can('anwesenheit.archiv'))
const canGenerateBopGroups = computed(() => can('einteilung.planning'))
const PaSwal = Swal.mixin({
  customClass: {
    container: 'pa-swal-container',
  },
})

const localVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value),
})

const normalizedListType = computed(() => props.listType === 'pa_preparation' ? 'pa_preparation' : 'pa')
const isPreparationPa = computed(() => normalizedListType.value === 'pa_preparation')
const modalTitle = computed(() => isPreparationPa.value ? 'Anwesenheitsliste Vorbereitung PA' : 'Anwesenheitsliste PA')
const dayPluralLabel = computed(() => isPreparationPa.value ? 'Vorbereitung PA' : 'PA-Tage')
const dateConfigTitle = computed(() => isPreparationPa.value ? 'Vorbereitung PA' : 'PA-Termine')
const primaryDateLabel = computed(() => isPreparationPa.value ? 'Termin Vorbereitung PA' : 'PA-Tag 1')
const createDaysButtonText = computed(() => isPreparationPa.value ? 'Vorbereitungstag übernehmen' : 'PA-Tage übernehmen')
const noDaysText = computed(() => isPreparationPa.value ? 'Kein Vorbereitungstag angelegt.' : 'Keine PA-Tage angelegt.')
const sheetTitle = computed(() => isPreparationPa.value
  ? 'Vorbereitung PA mit digitalen Unterschriften'
  : 'Potenzialanalyse mit digitalen Unterschriften')
const pdfDocumentTitle = computed(() => isPreparationPa.value
  ? 'Anwesenheitsliste Vorbereitung PA'
  : 'Teilnehmendenliste zum Nachweis der Potenzialanalyse – PA')
const pdfFilenamePrefix = computed(() => isPreparationPa.value ? 'Anwesenheitsliste_Vorbereitung_PA' : 'Anwesenheitsliste_PA')
const selectedDaysSummary = computed(() => {
  const label = isPreparationPa.value ? (selectedDays.value.length === 1 ? 'Termin' : 'Termine') : (selectedDays.value.length === 1 ? 'Tag' : 'Tage')

  return `${sheetParticipants.value.length} Teilnehmer / ${selectedDays.value.length} ${label}`
})

const form = reactive({
  exportFormat: 'A4',
  startDate: '',
  endDate: '',
  feedbackDate: '',
  exportMode: props.klasse ? 'klasse' : 'alle',
  klasse: props.klasse || '',
})

const previewContext = ref(null)
const allParticipants = ref([])
const days = ref([])
const selectedDayId = ref(null)
const manualDate = ref('')
const manualNote = ref('')
const loadingPreview = ref(false)
const exportingWord = ref(false)
const exportingPdf = ref(false)
const creatingArchiveFolder = ref(false)
const signatures = reactive({})
const pendingSignatureChanges = reactive({})
const signatureHistoryVisible = ref(false)
const signatureHistoryLoading = ref(false)
const signatureHistoryRestoringId = ref(null)
const signatureHistoryVersions = ref([])
const signatureHistoryContext = ref(null)
const signatureHistoryOverviewVisible = ref(false)
const signatureHistoryOverviewLoading = ref(false)
const signatureHistoryOverviewItems = ref([])
const signatureHistoryOverviewSearch = ref('')
const baseDraftSchedule = ref({ form: {}, days: [], selectedDayId: null })
const classSchedules = ref({})
const activeScheduleClass = ref(null)
const draftRevision = ref(0)
const draftSaving = ref(false)
const draftLoading = ref(false)
const draftLoaded = ref(false)
const draftHydrating = ref(false)
const draftDirty = ref(false)
const draftSaveBlocked = ref(false)
const draftSaveError = ref('')
const draftLastSavedAt = ref(null)
const draftExpiresAt = ref(null)
const sheetFullscreen = ref(false)
const generateGroupsFromPlan = ref(false)
const draftAutoSaveDelayMs = 5000
const draftPollIntervalMs = 12000
let draftSaveTimer = null
let draftPollTimer = null
let previousBodyOverflow = ''
let draftSaveRequestId = 0
let draftSaveQueue = Promise.resolve()
let draftSaveQueueDepth = 0
let draftSaveGeneration = 0

const selectedDays = computed(() => days.value.filter((day) => day.selected))
const selectedDay = computed(() => days.value.find((day) => day.id === selectedDayId.value) || selectedDays.value[0] || null)
const sheetParticipants = computed(() => allParticipants.value)
const hasClassSpecificSchedules = computed(() => !isPreparationPa.value && Object.keys(classSchedules.value).length > 0)
const classScheduleOverview = computed(() => form.exportMode === 'alle' && hasClassSpecificSchedules.value)
const classedParticipants = computed(() => sheetParticipants.value.map((participant, index) => ({
  participant,
  index,
  startsClass: form.exportMode === 'alle'
    && (index === 0 || String(sheetParticipants.value[index - 1]?.klasse || '') !== String(participant.klasse || '')),
})))
const signatureCount = computed(() => Object.values(signatures).filter(Boolean).length)
const filteredSignatureHistoryOverviewItems = computed(() => {
  const needle = signatureHistoryOverviewSearch.value.trim().toLocaleLowerCase('de-DE')
  if (!needle) return signatureHistoryOverviewItems.value

  return signatureHistoryOverviewItems.value.filter((item) => [
    item.participant_name,
    item.class_name,
    item.day_label,
    item.signed_for_date ? dateLabel(item.signed_for_date) : '',
    signatureHistoryActionLabel(item.current_action),
  ].some((value) => String(value || '').toLocaleLowerCase('de-DE').includes(needle)))
})
const scopeReady = computed(() => props.partnerId
  && props.schuljahr
  && props.teil
  && (form.exportMode === 'alle' || form.klasse))
const draftScopeReady = computed(() => props.partnerId
  && props.schuljahr
  && props.teil
  && (!isPreparationPa.value || form.klasse))
const draftStatusText = computed(() => {
  if (draftSaveBlocked.value) return 'Speichern fehlgeschlagen'
  if (draftLoading.value) return 'wird geladen'
  if (draftSaving.value) return 'wird gespeichert'
  if (draftDirty.value) return 'Änderungen offen'
  if (draftLastSavedAt.value) {
    return `gespeichert ${new Date(draftLastSavedAt.value).toLocaleTimeString('de-DE', {
      hour: '2-digit',
      minute: '2-digit',
    })}`
  }

  return 'bereit'
})
const draftExpiryText = computed(() => {
  if (!draftExpiresAt.value) return null

  return new Date(draftExpiresAt.value).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
})
const periodText = computed(() => {
  const values = selectedDays.value.map((day) => day.date).sort()
  if (!values.length) return ''

  return values.length === 1
    ? `(${dateLabel(values[0])})`
    : `(${dateLabel(values[0])} + ${dateLabel(values.at(-1))})`
})
const sheetSectionClass = computed(() => sheetFullscreen.value
  ? 'fixed inset-0 z-[9999] overflow-hidden border-0 bg-white p-4 shadow-2xl'
  : 'rounded border border-gray-200 bg-white p-4'
)
const sheetTableWrapperClass = computed(() => sheetFullscreen.value
  ? 'h-[calc(100vh-116px)] overflow-auto rounded border border-gray-300 bg-white'
  : 'max-h-[52vh] overflow-auto rounded border border-gray-300 bg-white'
)

const dateLabel = (date) => {
  if (!date) return ''
  return new Date(`${date}T00:00:00`).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const weekdayLabel = (date) => {
  if (!date) return ''
  return new Date(`${date}T00:00:00`).toLocaleDateString('de-DE', { weekday: 'short' })
}

const dayTypeLabel = (day) => {
  if (day?.type_label) return day.type_label
  if (day?.type === 'feedback') return 'Feedbackgespräch'
  if (day?.type === 'preparation') return 'Vorbereitung PA'

  return 'PA-Tag'
}

const dayColumnLabel = (index) => isPreparationPa.value ? 'Termin' : `Tag ${index + 1}`

const participantClassName = (participant) => String(participant?.klasse || '').trim()

const isParticipantExpectedOnDay = (day, participant) => {
  if (!day || !participant || !Array.isArray(day.eligible_classes)) return true

  return day.eligible_classes.includes(participantClassName(participant))
}

const signatureDayId = (day, participant) => {
  const className = participantClassName(participant)

  return day?.signature_ids_by_class?.[className] || day?.id
}

const signatureKey = (day, participant) => resolvePaSignatureKey({
  day,
  participant,
  signatures,
  preferredDayId: signatureDayId(day, participant),
})

const readBlobError = async (error) => {
  let data = error.response?.data

  if (data instanceof Blob) {
    try {
      data = JSON.parse(await data.text())
    } catch {
      data = null
    }
  }

  const firstFieldError = data?.errors ? Object.values(data.errors)?.[0]?.[0] : null
  return firstFieldError || data?.message || 'Die Aktion konnte nicht ausgeführt werden.'
}

const safePdfFilePart = (value, fallback = 'Datei') => {
  const safeValue = String(value || '').replace(/[^A-Za-z0-9_\-.]+/g, '_')

  return safeValue || fallback
}

const requestScopePayload = () => ({
  schuleId: props.partnerId,
  schuljahr: props.schuljahr,
  teil: props.teil,
  listType: normalizedListType.value,
  exportMode: form.exportMode,
  klasse: form.exportMode === 'klasse' ? form.klasse : '',
})

// Die normale PA-Liste hat einen gemeinsamen Unterschriftenstand fÃ¼r alle Klassen.
// Die aktuelle Klasse steuert nur den sichtbaren Ausschnitt und den Export.
const draftScopePayload = () => isPreparationPa.value
  ? requestScopePayload()
  : {
      schuleId: props.partnerId,
      schuljahr: props.schuljahr,
      teil: props.teil,
      listType: normalizedListType.value,
      exportMode: 'alle',
      klasse: '',
    }

const cloneForDraft = (value) => JSON.parse(JSON.stringify(value ?? null))

const daySnapshotForDraft = (day) => ({
  id: day?.id || null,
  date: day?.date || null,
  type: day?.type || 'pa_day',
  type_label: day?.type_label || null,
  source: day?.source || 'manual',
  selected: day?.selected !== false,
  note: day?.note || null,
})

const daysSnapshotForDraft = () => days.value.map(daySnapshotForDraft)

const currentScheduleSnapshot = () => ({
  form: {
    exportFormat: form.exportFormat,
    startDate: form.startDate,
    endDate: form.endDate,
    feedbackDate: form.feedbackDate,
  },
  days: daysSnapshotForDraft(),
  selectedDayId: selectedDayId.value,
})

const buildDraftPayload = ({ signaturesPayload = { ...signatures } } = {}) => {
  if (isPreparationPa.value) {
    return {
      version: 1,
      listType: normalizedListType.value,
      form: { ...form },
      days: daysSnapshotForDraft(),
      selectedDayId: selectedDayId.value,
      signatures: signaturesPayload,
    }
  }

  const baseSchedule = form.exportMode === 'alle' && !hasClassSpecificSchedules.value
    ? currentScheduleSnapshot()
    : cloneForDraft(baseDraftSchedule.value)
  const classSchedulePatch = {}

  if (activeScheduleClass.value) {
    classSchedulePatch[activeScheduleClass.value] = currentScheduleSnapshot()
  }

  return {
    version: 2,
    listType: normalizedListType.value,
    form: baseSchedule?.form || {},
    days: baseSchedule?.days || [],
    selectedDayId: baseSchedule?.selectedDayId || null,
    classSchedules: classSchedulePatch,
    signatures: signaturesPayload,
  }
}

const signatureHistoryActionLabel = (action) => ({
  captured: 'Unterschrieben',
  replaced: 'Neu unterschrieben',
  restored: 'Wiederhergestellt',
  deleted: 'Gelöscht',
  imported: 'Aus bestehendem Entwurf übernommen',
}[action] || action)

const signatureHistoryTimestamp = (value) => {
  if (!value) return ''

  return new Date(value).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const signatureSnapshot = (signaturePayload = {}) => JSON.stringify(
  Object.entries(signaturePayload || {})
    .filter(([, value]) => Boolean(value))
    .sort(([left], [right]) => left.localeCompare(right))
)

const hasSignature = (day, participant) => Boolean(
  day
  && participant
  && isParticipantExpectedOnDay(day, participant)
  && signatures[signatureKey(day, participant)]
)

const expectedSignatureCountForDay = (day) => sheetParticipants.value
  .filter((participant) => isParticipantExpectedOnDay(day, participant))
  .length

const signedCountForDay = (day) => sheetParticipants.value
  .filter((participant) => isParticipantExpectedOnDay(day, participant) && hasSignature(day, participant))
  .length

const selectedDaysPayload = () => selectedDays.value.map((day) => ({
  id: day.id,
  date: day.date,
  type: day.type,
  selected: day.selected,
  source: day.source,
  note: day.note || null,
}))

const previewDaysPayload = () => {
  if (isPreparationPa.value) {
    return preparationDaysPayload()
  }

  const preservedDays = selectedDays.value
    .filter((day) => !['range', 'pa-term'].includes(day.source) && day.type !== 'feedback' && day.source !== 'feedback')
    .map((day) => ({
      id: day.id,
      date: day.date,
      type: day.type,
      selected: day.selected,
      source: day.source,
      note: day.note || null,
    }))

  return [...paTermDaysPayload(), ...preservedDays]
}

const syncSignatures = (nextSignatures = {}, { removeMissing = true } = {}) => {
  if (removeMissing) {
    Object.keys(signatures).forEach((key) => {
      if (!nextSignatures[key]) delete signatures[key]
    })
  }

  Object.entries(nextSignatures || {}).forEach(([key, value]) => {
    if (value && signatures[key] !== value) {
      signatures[key] = value
    }
  })
}

const normalizeDraftSchedule = (schedule = {}) => ({
  form: cloneForDraft(schedule?.form) || {},
  days: Array.isArray(schedule?.days) ? cloneForDraft(schedule.days) : [],
  selectedDayId: typeof schedule?.selectedDayId === 'string' ? schedule.selectedDayId : null,
})

const dayWithGroups = (day) => {
  const expectedParticipants = allParticipants.value
    .filter((participant) => isParticipantExpectedOnDay(day, participant))

  return {
    ...day,
    groups: [{
      id: `pa-all-${day.date}`,
      label: 'Alle Teilnehmer',
      bereich: null,
      runde: null,
      participants: expectedParticipants,
      participants_count: expectedParticipants.length,
    }],
    participants_count: expectedParticipants.length,
  }
}

const clearPendingSignatureChanges = () => {
  Object.keys(pendingSignatureChanges).forEach((key) => delete pendingSignatureChanges[key])
}

const mergedScheduleForAllClasses = () => {
  const classNames = [...new Set(allParticipants.value.map(participantClassName))]
  const mergedDays = new Map()

  classNames.forEach((className) => {
    const schedule = normalizeDraftSchedule(
      classSchedules.value[className] || baseDraftSchedule.value
    )

    schedule.days
      .filter((day) => day?.date && day.selected !== false)
      .forEach((day) => {
        const type = day.type || 'pa_day'
        const mergeKey = `${day.date}|${type}`
        const existing = mergedDays.get(mergeKey) || {
          ...cloneForDraft(day),
          id: `all-${type}-${day.date}`,
          type,
          source: 'class-schedule',
          selected: true,
          eligible_classes: [],
          signature_ids_by_class: {},
        }

        if (!existing.eligible_classes.includes(className)) {
          existing.eligible_classes.push(className)
        }
        existing.signature_ids_by_class[className] = day.id
        mergedDays.set(mergeKey, existing)
      })
  })

  const merged = [...mergedDays.values()]
    .map((day) => ({
      ...day,
      eligible_classes: [...day.eligible_classes].sort((left, right) => left.localeCompare(right, 'de')),
    }))
    .sort((left, right) => left.date.localeCompare(right.date) || left.type.localeCompare(right.type))

  return {
    form: cloneForDraft(baseDraftSchedule.value.form) || {},
    days: merged,
    selectedDayId: merged[0]?.id || null,
  }
}

const applyScheduleToView = (schedule) => {
  const normalized = normalizeDraftSchedule(schedule)

  form.exportFormat = normalized.form.exportFormat || form.exportFormat
  form.startDate = normalized.form.startDate || ''
  form.endDate = normalized.form.endDate || ''
  form.feedbackDate = normalized.form.feedbackDate || ''
  days.value = normalized.days.map(dayWithGroups)
  selectedDayId.value = normalized.selectedDayId && days.value.some((day) => day.id === normalized.selectedDayId)
    ? normalized.selectedDayId
    : selectedDays.value[0]?.id || days.value[0]?.id || null
}

const hydrateDays = (payloadDays) => {
  const previous = new Map(days.value.map((day) => [day.id, day]))

  days.value = (payloadDays || []).map((day) => {
    const old = previous.get(day.id)
    return dayWithGroups({
      ...day,
      selected: old?.selected ?? day.selected ?? true,
      note: old?.note ?? day.note ?? '',
    })
  })

  if (!selectedDayId.value || !days.value.some((day) => day.id === selectedDayId.value)) {
    selectedDayId.value = selectedDays.value[0]?.id || days.value[0]?.id || null
  }
}

const applyDraftPayload = (payload) => {
  if (!payload) return

  draftHydrating.value = true

  try {
    baseDraftSchedule.value = normalizeDraftSchedule(payload)
    classSchedules.value = Object.fromEntries(
      Object.entries(payload.classSchedules || {})
        .filter(([className, schedule]) => className && schedule && typeof schedule === 'object')
        .map(([className, schedule]) => [className, normalizeDraftSchedule(schedule)])
    )

    if (isPreparationPa.value) {
      activeScheduleClass.value = null
      applyScheduleToView(baseDraftSchedule.value)
    } else if (form.exportMode === 'klasse' && form.klasse) {
      activeScheduleClass.value = String(form.klasse).trim()
      applyScheduleToView(
        classSchedules.value[activeScheduleClass.value] || baseDraftSchedule.value
      )
    } else {
      activeScheduleClass.value = null
      applyScheduleToView(
        hasClassSpecificSchedules.value
          ? mergedScheduleForAllClasses()
          : baseDraftSchedule.value
      )
    }

    syncSignatures(payload.signatures || {}, { removeMissing: true })
    clearPendingSignatureChanges()
    draftDirty.value = false
  } finally {
    draftHydrating.value = false
  }
}

const loadDraft = async ({ silent = true } = {}) => {
  if (!draftScopeReady.value) return
  if (!silent) draftLoading.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.digital.draft.show'), draftScopePayload())

    if (!draftDirty.value) {
      if (response.data.exists && response.data.payload) {
        applyDraftPayload(response.data.payload)
      } else if (!response.data.exists) {
        draftHydrating.value = true
        baseDraftSchedule.value = normalizeDraftSchedule(currentScheduleSnapshot())
        classSchedules.value = {}
        activeScheduleClass.value = !isPreparationPa.value && form.exportMode === 'klasse' && form.klasse
          ? String(form.klasse).trim()
          : null
        syncSignatures({}, { removeMissing: true })
        clearPendingSignatureChanges()
        draftHydrating.value = false
      }
    }

    draftRevision.value = response.data.revision || 0
    draftLastSavedAt.value = response.data.updated_at || null
    draftExpiresAt.value = response.data.expires_at || null
    draftLoaded.value = true
  } catch (error) {
    if (!silent) {
      PaSwal.fire('Fehler', await readBlobError(error), 'error')
    }
  } finally {
    draftLoading.value = false
  }
}

const performDraftSave = async ({ silent, draftPayload, requestSignatureSnapshot, generation }) => {
  if (generation !== draftSaveGeneration) return

  const requestId = ++draftSaveRequestId

  try {
    const response = await axios.put(route('anwesenheitsliste.PA.digital.draft.store'), {
      ...draftScopePayload(),
      payload: draftPayload,
    })

    const signatureChangedDuringSave = signatureSnapshot(signatures) !== requestSignatureSnapshot
    const isLatestSaveResponse = generation === draftSaveGeneration && requestId === draftSaveRequestId

    Object.entries(draftPayload.signatures || {}).forEach(([key, value]) => {
      if (Object.prototype.hasOwnProperty.call(pendingSignatureChanges, key)
        && pendingSignatureChanges[key] === value) {
        delete pendingSignatureChanges[key]
      }
    })

    if (isLatestSaveResponse) {
      draftSaveBlocked.value = false
      draftSaveError.value = ''
      if (response.data.payload && !signatureChangedDuringSave) applyDraftPayload(response.data.payload)
      draftRevision.value = response.data.revision || draftRevision.value
      draftLastSavedAt.value = response.data.updated_at || new Date().toISOString()
      draftExpiresAt.value = response.data.expires_at || draftExpiresAt.value
      draftLoaded.value = true
      draftDirty.value = signatureChangedDuringSave || Object.keys(pendingSignatureChanges).length > 0
    }
  } catch (error) {
    if (generation === draftSaveGeneration && requestId === draftSaveRequestId && ![401, 419].includes(error?.response?.status)) {
      draftSaveBlocked.value = true
      draftSaveError.value = 'Die letzte Änderung wurde nicht vom Server bestätigt. Weitere Unterschriften sind gesperrt. Bitte Verbindung und Server prüfen und dann erneut speichern.'
      draftDirty.value = true
    }
    if (!silent && generation === draftSaveGeneration && requestId === draftSaveRequestId) {
      PaSwal.fire('Fehler', await readBlobError(error), 'error')
    }
  }
}

const saveDraft = ({ silent = true, payload = null, signatureSnapshotGuard = null } = {}) => {
  if (!draftScopeReady.value) return Promise.resolve()

  const queuedSave = {
    silent,
    draftPayload: payload || buildDraftPayload({ signaturesPayload: { ...pendingSignatureChanges } }),
    requestSignatureSnapshot: signatureSnapshotGuard ?? signatureSnapshot(signatures),
    generation: draftSaveGeneration,
  }

  draftSaveQueueDepth++
  draftSaving.value = true

  const task = draftSaveQueue.then(() => performDraftSave(queuedSave))
  draftSaveQueue = task.catch(() => {})

  return task.finally(() => {
    draftSaveQueueDepth = Math.max(0, draftSaveQueueDepth - 1)
    draftSaving.value = draftSaveQueueDepth > 0
  })
}

const captureSignature = (day, participant, value) => {
  if (!day || !participant || !previewContext.value || draftHydrating.value) return
  if (!isParticipantExpectedOnDay(day, participant) || !value) return

  signatures[signatureKey(day, participant)] = value
  pendingSignatureChanges[signatureKey(day, participant)] = value
  draftDirty.value = true
}

const saveCompletedSignature = (day, participant, value) => {
  if (!day || !participant || !previewContext.value || draftHydrating.value) return
  if (!isParticipantExpectedOnDay(day, participant)) return
  if (!value) return

  const key = signatureKey(day, participant)
  captureSignature(day, participant, value)
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = null

  const signatureGuard = signatureSnapshot(signatures)
  const payload = buildDraftPayload({ signaturesPayload: { [key]: value } })
  saveDraft({ silent: true, payload, signatureSnapshotGuard: signatureGuard })
}

const removeSignature = (day, participant) => {
  if (!day || !participant) return

  const key = signatureKey(day, participant)
  delete signatures[key]
  pendingSignatureChanges[key] = ''
  draftDirty.value = true
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = null

  const payload = buildDraftPayload({ signaturesPayload: { [key]: '' } })
  saveDraft({ silent: true, payload, signatureSnapshotGuard: signatureSnapshot(signatures) })
}

const scheduleDraftSave = () => {
  if (!draftLoaded.value || draftHydrating.value || !previewContext.value) return

  draftDirty.value = true
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = window.setTimeout(() => {
    draftSaveTimer = null
    saveDraft({
      silent: true,
      payload: buildDraftPayload({ signaturesPayload: {} }),
      signatureSnapshotGuard: signatureSnapshot(signatures),
    })
  }, draftAutoSaveDelayMs)
}

const flushDraftSave = async () => {
  if (!draftDirty.value || !previewContext.value) return

  const payload = buildDraftPayload({ signaturesPayload: { ...pendingSignatureChanges } })
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = null
  await saveDraft({ silent: true, payload })
}

const loadSignatureHistory = async (context) => {
  signatureHistoryContext.value = context
  signatureHistoryVersions.value = []
  signatureHistoryVisible.value = true
  signatureHistoryLoading.value = true

  try {
    await flushDraftSave()
    await draftSaveQueue
    const response = await axios.post(route('anwesenheitsliste.PA.digital.signature.history'), {
      ...draftScopePayload(),
      signatureKey: context.signatureKey,
    })

    signatureHistoryVersions.value = response.data.versions || []
    if (response.data.participant) {
      signatureHistoryContext.value.participantName = response.data.participant.name || signatureHistoryContext.value.participantName
      signatureHistoryContext.value.className = response.data.participant.class_name || signatureHistoryContext.value.className
    }
  } catch (error) {
    signatureHistoryVisible.value = false
    PaSwal.fire('Verlauf nicht verfügbar', await readBlobError(error), 'error')
  } finally {
    signatureHistoryLoading.value = false
  }
}

const openSignatureHistory = (day, participant) => loadSignatureHistory({
  signatureKey: signatureKey(day, participant),
  participantName: [participant?.vorname, participant?.nachname].filter(Boolean).join(' '),
  className: participant?.klasse || '',
  dayLabel: dayTypeLabel(day),
  date: day?.date || '',
})

const openSignatureHistoryFromOverview = (item) => {
  signatureHistoryOverviewVisible.value = false

  return loadSignatureHistory({
    signatureKey: item.signature_key,
    participantName: item.participant_name,
    className: item.class_name || '',
    dayLabel: item.day_label || 'PA-Tag',
    date: item.signed_for_date || '',
  })
}

const openSignatureHistoryOverview = async () => {
  signatureHistoryOverviewVisible.value = true
  signatureHistoryOverviewLoading.value = true
  signatureHistoryOverviewSearch.value = ''

  try {
    await flushDraftSave()
    await draftSaveQueue
    const response = await axios.post(route('anwesenheitsliste.PA.digital.signature.histories'), draftScopePayload())
    signatureHistoryOverviewItems.value = response.data.subjects || []
  } catch (error) {
    signatureHistoryOverviewVisible.value = false
    PaSwal.fire('Übersicht nicht verfügbar', await readBlobError(error), 'error')
  } finally {
    signatureHistoryOverviewLoading.value = false
  }
}

const restoreSignatureVersion = async (version) => {
  if (!version?.can_restore || !signatureHistoryContext.value) return

  const result = await PaSwal.fire({
    title: `Version ${version.version} wiederherstellen?`,
    text: 'Die aktuelle Unterschrift bleibt im Verlauf erhalten. Die ausgewählte Version wird als neue aktuelle Version gespeichert.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Wiederherstellen',
    cancelButtonText: 'Abbrechen',
  })

  if (!result.isConfirmed) return

  signatureHistoryRestoringId.value = version.id

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.digital.signature.restore'), {
      ...draftScopePayload(),
      signatureKey: signatureHistoryContext.value.signatureKey,
      versionId: version.id,
    })

    signatures[signatureHistoryContext.value.signatureKey] = response.data.signature
    signatureHistoryVersions.value = response.data.versions || []
    draftRevision.value = response.data.revision || draftRevision.value
    draftLastSavedAt.value = response.data.updated_at || new Date().toISOString()
    draftSaveBlocked.value = false
    draftSaveError.value = ''
    PaSwal.fire('Wiederhergestellt', 'Die ausgewählte Unterschrift ist jetzt wieder aktuell.', 'success')
  } catch (error) {
    PaSwal.fire('Wiederherstellung fehlgeschlagen', await readBlobError(error), 'error')
  } finally {
    signatureHistoryRestoringId.value = null
  }
}

const startDraftPolling = () => {
  window.clearInterval(draftPollTimer)
  draftPollTimer = window.setInterval(() => {
    if (!props.visible || draftDirty.value || draftSaving.value || draftHydrating.value) return

    loadDraft({ silent: true })
  }, draftPollIntervalMs)
}

const stopDraftTimers = () => {
  window.clearTimeout(draftSaveTimer)
  window.clearInterval(draftPollTimer)
  draftSaveTimer = null
  draftPollTimer = null
}

const loadPreview = async ({ includeDraft = false } = {}) => {
  if (!scopeReady.value) {
    PaSwal.fire('Klasse fehlt', 'Bitte eine Klasse auswählen oder Alle Klassen verwenden.', 'warning')
    return
  }

  loadingPreview.value = true

  try {
    if (generateGroupsFromPlan.value) {
      await axios.post(route('bop.run.groups.generate', {
        partner: props.partnerId,
        phaseType: isPreparationPa.value ? 'pa_preparation' : 'pa',
      }), {
        schuljahr: props.schuljahr,
        teil: props.teil,
      })
    }

    const response = await axios.post(route('anwesenheitsliste.PA.digital.preview'), {
      ...requestScopePayload(),
      startDate: form.startDate || null,
      endDate: isPreparationPa.value ? null : form.endDate || null,
      feedbackDate: isPreparationPa.value ? null : form.feedbackDate || null,
      days: previewDaysPayload(),
    })

    previewContext.value = response.data.context
    allParticipants.value = response.data.participants || []
    const responseDays = response.data.days || []
    if (classScheduleOverview.value) {
      draftHydrating.value = true
      try {
        applyScheduleToView(mergedScheduleForAllClasses())
      } finally {
        draftHydrating.value = false
      }
    } else {
      hydrateDays(responseDays.length ? responseDays : days.value)
    }
    if (includeDraft) await loadDraft({ silent: true })
  } catch (error) {
    PaSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    loadingPreview.value = false
  }
}

const paTermDaysPayload = () => {
  const termDays = [
    { id: 'pa-tag-1', date: form.startDate, note: 'PA-Tag 1' },
    { id: 'pa-tag-2', date: form.endDate, note: 'PA-Tag 2' },
  ]

  return termDays
    .filter((day) => day.date)
    .map((day) => ({
      id: `${day.id}-${day.date}`,
      date: day.date,
      type: 'pa_day',
      selected: true,
      source: 'pa-term',
      note: day.note,
    }))
}

const warnAboutUnsavedDraft = (event) => {
  if (window.__zbbSessionExpired || !props.visible || (!draftDirty.value && !draftSaveBlocked.value)) return

  event.preventDefault()
  event.returnValue = ''
}

const preparationDaysPayload = () => {
  if (!form.startDate) return []

  return [{
    id: `pa-vorbereitung-${form.startDate}`,
    date: form.startDate,
    type: 'preparation',
    selected: true,
    source: 'pa-preparation',
    note: 'Vorbereitung PA',
  }]
}

const appendPaDays = (dayPayloads, source = 'manual') => {
  const existing = new Set(days.value.map((day) => day.date))
  const generated = dayPayloads
    .filter((day) => !existing.has(day.date))
    .map((day) => {
      const type = day.type || 'pa_day'

      return dayWithGroups({
        id: day.id || `${source}-${day.date}`,
        date: day.date,
        date_label: dateLabel(day.date),
        type,
        type_label: day.type_label || dayTypeLabel({ type }),
        source: day.source || source,
        selected: true,
        note: day.note || '',
      })
    })

  if (!generated.length) return

  days.value = [...days.value, ...generated].sort((a, b) => a.date.localeCompare(b.date))
  selectedDayId.value = selectedDayId.value || generated[0]?.id || null
}

const createPaTermDays = () => {
  if (isPreparationPa.value) {
    if (!form.startDate) {
      PaSwal.fire('Termin fehlt', 'Bitte den Termin für die Vorbereitung PA eintragen.', 'warning')
      return
    }

    days.value = days.value.filter((day) => day.source !== 'pa-preparation')
    appendPaDays(preparationDaysPayload(), 'pa-preparation')
    scheduleDraftSave()
    return
  }

  if (!form.startDate || !form.endDate) {
    PaSwal.fire('PA-Termine fehlen', 'Bitte PA-Tag 1 und PA-Tag 2 eintragen.', 'warning')
    return
  }

  if (new Date(`${form.endDate}T00:00:00`) < new Date(`${form.startDate}T00:00:00`)) {
    PaSwal.fire('Datum prüfen', 'PA-Tag 2 muss nach PA-Tag 1 liegen.', 'warning')
    return
  }

  days.value = days.value.filter((day) => day.source !== 'range' && day.source !== 'pa-term')
  appendPaDays(paTermDaysPayload(), 'pa-term')
  scheduleDraftSave()
}

const addManualDay = () => {
  if (!manualDate.value) return

  const exists = days.value.some((day) => day.date === manualDate.value)
  if (exists) {
    PaSwal.fire('Datum vorhanden', 'Dieser Tag ist bereits in der Liste.', 'info')
    return
  }

  days.value.push(dayWithGroups({
    id: `manual-${manualDate.value}`,
    date: manualDate.value,
    date_label: dateLabel(manualDate.value),
    type: 'pa_day',
    type_label: 'PA-Tag',
    source: 'manual',
    selected: true,
    note: manualNote.value,
  }))

  days.value.sort((a, b) => a.date.localeCompare(b.date))
  selectedDayId.value = selectedDayId.value || `manual-${manualDate.value}`
  manualDate.value = ''
  manualNote.value = ''
}

const resetDraftMeta = () => {
  draftLoaded.value = false
  draftDirty.value = false
  draftRevision.value = 0
  draftSaving.value = false
  draftLoading.value = false
  draftLastSavedAt.value = null
  draftExpiresAt.value = null
}

const reloadScope = async () => {
  if (!props.visible || draftHydrating.value) return

  await flushDraftSave()
  stopDraftTimers()
  draftHydrating.value = true
  days.value = []
  selectedDayId.value = null
  if (isPreparationPa.value) {
    syncSignatures({}, { removeMissing: true })
    clearPendingSignatureChanges()
  }
  resetDraftMeta()
  draftHydrating.value = false
  if (!scopeReady.value) return
  await loadPreview({ includeDraft: true })
  startDraftPolling()
}

const handleWordExport = async () => {
  if (isPreparationPa.value) return

  if (classScheduleOverview.value) {
    PaSwal.fire(
      'Klasse auswählen',
      'Bei getrennten Klassenterminen bitte zuerst „Eine Klasse“ auswählen und diese Klasse exportieren.',
      'info'
    )
    return
  }

  if (!form.startDate || !form.endDate || (form.exportMode === 'klasse' && !form.klasse)) {
    PaSwal.fire('Angaben fehlen', 'Bitte PA-Tag 1, PA-Tag 2 und Klasse prüfen.', 'warning')
    return
  }

  exportingWord.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.export.word'), {
      startDate: form.startDate,
      endDate: form.endDate,
      schuleId: props.partnerId,
      schuljahr: props.schuljahr,
      teil: props.teil,
      exportMode: form.exportMode,
      klasse: form.exportMode === 'klasse' ? form.klasse : '',
    }, { responseType: 'blob' })

    const disposition = response.headers['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] || (form.exportMode === 'alle'
      ? 'Anwesenheitslisten_PA_alle_Klassen.zip'
      : `Anwesenheitsliste_PA_${form.klasse}.docx`)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    PaSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    exportingWord.value = false
  }
}

const createArchiveFolder = async () => {
  if (!scopeReady.value) return

  creatingArchiveFolder.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.digital.archive.folder'), draftScopePayload())
    PaSwal.fire(
      'Archiv-Ordner erstellt',
      `Der Ordner wurde angelegt/aktualisiert: ${response.data.folder}`,
      'success'
    )
  } catch (error) {
    PaSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    creatingArchiveFolder.value = false
  }
}

const storeSignedPdfInFolder = async (pdfBlob, filename) => {
  const formData = new FormData()
  formData.append('schuleId', props.partnerId)
  formData.append('schuljahr', props.schuljahr)
  formData.append('teil', props.teil)
  formData.append('listType', normalizedListType.value)
  formData.append('exportMode', form.exportMode)
  formData.append('klasse', form.exportMode === 'klasse' ? form.klasse : '')
  formData.append('filename', filename)
  formData.append('pdf', pdfBlob, filename)

  const response = await axios.post(route('anwesenheitsliste.PA.digital.pdf.store'), formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  draftRevision.value = response.data.revision || draftRevision.value
  draftLastSavedAt.value = response.data.updated_at || draftLastSavedAt.value
  draftExpiresAt.value = response.data.expires_at || draftExpiresAt.value

  return response.data
}

const applyPdfPrintInk = (doc) => {
  doc.setTextColor(0, 0, 0)
  doc.setDrawColor(0, 0, 0)
}

const drawPdfSignature = (doc, signature, x, y, width, height) => {
  if (!signature || width <= 0 || height <= 0) return

  const imageSource = typeof signature === 'string' ? signature : signature.source
  const signatureImageRatio = typeof signature === 'string' ? 420 / 120 : signature.aspectRatio
  const paddingX = Math.min(width * 0.04, 0.8)
  const paddingY = Math.min(height * 0.08, 0.45)
  const boxWidth = Math.max(0.1, width - (paddingX * 2))
  const boxHeight = Math.max(0.1, height - (paddingY * 2))
  let imageWidth = Math.min(boxWidth, boxHeight * signatureImageRatio)
  let imageHeight = imageWidth / signatureImageRatio

  if (imageHeight > boxHeight) {
    imageHeight = boxHeight
    imageWidth = imageHeight * signatureImageRatio
  }

  doc.addImage(
    imageSource,
    'PNG',
    x + ((width - imageWidth) / 2),
    y + ((height - imageHeight) / 2),
    imageWidth,
    imageHeight
  )
}

const pdfFormat = () => form.exportFormat === 'A3' ? 'a3' : 'a4'

const pdfLayout = (doc) => {
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const widthScale = pageWidth / 297
  const rowScale = form.exportFormat === 'A3' ? widthScale : 1
  const tableWidth = (isPreparationPa.value ? 283 : 244.8) * widthScale

  return {
    pageWidth,
    pageHeight,
    widthScale,
    rowScale,
    tableX: (pageWidth - tableWidth) / 2,
    tableY: (isPreparationPa.value ? 48 : 62) * widthScale,
    tableWidth,
    headHeight: (isPreparationPa.value ? 13.8 : 18) * rowScale,
    rowHeight: (isPreparationPa.value ? 6.15 : 6.5) * rowScale,
    rowsPerPage: 17,
    firstParticipantPageRows: isPreparationPa.value ? 17 : 13,
    secondParticipantPageRows: isPreparationPa.value ? 17 : 21,
    secondPageTableY: 25 * widthScale,
    headerX: (isPreparationPa.value ? 7 : 20) * widthScale,
    headerPageY: (isPreparationPa.value ? 7 : 15) * widthScale,
    headerTitleY: (isPreparationPa.value ? 14 : 28) * widthScale,
    headerFirstRowY: (isPreparationPa.value ? 22 : 36) * widthScale,
    headerRowGap: 5.2 * widthScale,
    headerPeriodX: 190 * widthScale,
    headerPeriodValueX: 214 * widthScale,
    headerLabelWidth: 25 * widthScale,
    headerValueWidth: 75 * widthScale,
    headerSecondLabelWidth: 50 * widthScale,
    headerSecondValueWidth: 97 * widthScale,
  }
}

const pdfColumns = (layout) => {
  if (!isPreparationPa.value) {
    const days = Array.from({ length: 3 }, (_, index) => selectedDays.value[index] || null)

    return [
      { key: 'nr', label: 'Nr.', width: 14.9 * layout.widthScale },
      { key: 'nachname', label: 'Name', width: 32.5 * layout.widthScale },
      { key: 'vorname', label: 'Vorname', width: 30 * layout.widthScale },
      ...days.map((day, index) => ({
        key: day?.id || `termin-${index + 1}`,
        day,
        isDay: true,
        label: `Termin ${index + 1}`,
        width: 42.5 * layout.widthScale,
      })),
      { key: 'gesamtstunden', label: 'Gesamt-\nstunden:', width: 22.4 * layout.widthScale },
      { key: 'zertifikat', label: 'Zertifikat\nJa/nein', width: 17.5 * layout.widthScale },
    ]
  }

  const staticColumns = [
    { key: 'nr', label: 'Nr.', width: 8 },
    { key: 'nachname', label: 'Name', width: 34 },
    { key: 'vorname', label: 'Vorname', width: 31 },
    { key: 'klasse', label: 'Klasse', width: 17 },
  ]
  const staticWidth = staticColumns.reduce((sum, column) => sum + column.width, 0)
  const dayCount = Math.max(selectedDays.value.length, 1)
  const preparationSignatureWidth = form.exportFormat === 'A3' ? 75 : 60
  const dayWidth = isPreparationPa.value
    ? preparationSignatureWidth
    : Math.max(18, (layout.tableWidth - staticWidth) / dayCount)

  return [
    ...staticColumns,
    ...selectedDays.value.map((day, index) => ({
      key: day.id,
      day,
      label: dayColumnLabel(index),
      width: dayWidth,
    })),
  ]
}

const drawPdfPageNumber = (doc, pageNumber, totalPages, layout) => {
  applyPdfPrintInk(doc)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(form.exportFormat === 'A3' ? 9.5 : 8)
  doc.text(`Seite ${pageNumber} von ${totalPages}`, layout.pageWidth - (47 * layout.widthScale), layout.headerPageY)
}

const drawPdfHeader = (doc, pageNumber, totalPages, layout, trainerPage = false) => {
  const x0 = layout.headerX
  const x1 = x0 + layout.headerLabelWidth
  const x2 = x1 + layout.headerValueWidth
  const x3 = x2 + layout.headerSecondLabelWidth
  const rowY = (index) => layout.headerFirstRowY + (index * layout.headerRowGap)
  const school = previewContext.value?.schule?.name || 'Schule'
  const classText = previewContext.value?.klasse || (form.exportMode === 'klasse' ? form.klasse : '')
  const title = trainerPage
    ? 'Unterschriftenliste zum Nachweis der Potenzialanalyse - PA/ Ausbilder/-innen'
    : pdfDocumentTitle.value

  drawPdfPageNumber(doc, pageNumber, totalPages, layout)
  doc.setFont('helvetica', 'bold')

  if (trainerPage) {
    doc.setFontSize(form.exportFormat === 'A3' ? 17 : 12)
    doc.text(title, x0, 36 * layout.widthScale)
    return
  }

  doc.setFontSize(form.exportFormat === 'A3' ? 17 : 12)
  doc.text(title, x0, layout.headerTitleY)

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(form.exportFormat === 'A3' ? 14 : 10)
  doc.text('Zeitraum:', layout.headerPeriodX, layout.headerTitleY)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(form.exportFormat === 'A3' ? 14 : 10)
  doc.text(periodText.value || '', layout.headerPeriodValueX, layout.headerTitleY, {
    maxWidth: layout.pageWidth - layout.headerPeriodValueX - (7 * layout.widthScale),
  })

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(form.exportFormat === 'A3' ? 12.5 : 9)
  doc.text('Schule:', x0, rowY(0))
  doc.text(String(school), x1, rowY(0), { maxWidth: layout.headerValueWidth - 2 })
  doc.text('Schulform:', x0, rowY(2))
  doc.text(String(previewContext.value?.schulform || ''), x1, rowY(2), { maxWidth: layout.headerValueWidth - 2 })
  doc.text('Klasse/n:', x0, rowY(3))
  doc.text(String(classText || ''), x1, rowY(3), { maxWidth: layout.headerValueWidth - 2 })

  doc.text('Zuwendungsempfänger/', x2, rowY(0))
  doc.text('- ZBB -', x3, rowY(0), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('Ausführende Stelle:', x2, rowY(1))
  doc.text('Zentrum für Bildung und Beruf Saar gGmbH in Burbach', x3, rowY(1), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('AZ/FKZ:', x2, rowY(2))
  doc.text('4.5-3444-10/0004', x3, rowY(2), { maxWidth: layout.headerSecondValueWidth - 2 })
}

const drawPdfTableHeader = (doc, columns, layout) => {
  let cursorX = layout.tableX
  const y = layout.tableY

  applyPdfPrintInk(doc)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(6.4)
  doc.setLineWidth(0.25)

  if (!isPreparationPa.value) {
    const topHeight = 10.5 * layout.rowScale
    const bottomHeight = 7.5 * layout.rowScale
    const pad = 1.2 * layout.widthScale
    let dayIndex = 0

    columns.forEach((column) => {
      if (column.isDay) {
        const groupLabel = dayIndex < 2 ? 'PA' : 'Auswertungsgespräch'
        doc.text(groupLabel, cursorX + (column.width / 2), y - (2 * layout.rowScale), { align: 'center' })
        dayIndex++
      }
      cursorX += column.width
    })

    cursorX = layout.tableX
    columns.forEach((column) => {
      doc.rect(cursorX, y, column.width, topHeight)

      if (column.isDay) {
        doc.setFont('helvetica', 'normal')
        doc.text(`${column.label}:`, cursorX + pad, y + (3.2 * layout.rowScale))
        if (column.day?.date) {
          doc.setFont('helvetica', 'bold')
          doc.text(dateLabel(column.day.date), cursorX + pad, y + (6.9 * layout.rowScale), {
            maxWidth: column.width - (2 * pad),
          })
        }
      } else if (['gesamtstunden', 'zertifikat'].includes(column.key)) {
        doc.setFont('helvetica', 'normal')
        String(column.label).split('\n').forEach((line, lineIndex) => {
          doc.text(line, cursorX + pad, y + (3.2 * layout.rowScale) + (lineIndex * 3 * layout.rowScale))
        })
      }

      cursorX += column.width
    })

    cursorX = layout.tableX
    columns.forEach((column) => {
      const bottomY = y + topHeight
      if (['gesamtstunden', 'zertifikat'].includes(column.key)) {
        doc.setFillColor(190, 190, 190)
        doc.rect(cursorX, bottomY, column.width, bottomHeight, 'FD')
      } else {
        doc.rect(cursorX, bottomY, column.width, bottomHeight)
      }

      doc.setFont('helvetica', 'bold')
      if (column.isDay) {
        doc.text('Unterschrift', cursorX + pad, bottomY + (3 * layout.rowScale))
        doc.text('Schüler/-in', cursorX + pad, bottomY + (6 * layout.rowScale))
      } else if (!['gesamtstunden', 'zertifikat'].includes(column.key)) {
        doc.text(column.label, cursorX + pad, bottomY + (4.7 * layout.rowScale))
      }

      cursorX += column.width
    })

    return
  }

  columns.forEach((column) => {
    doc.rect(cursorX, y, column.width, layout.headHeight)

    if (column.day) {
      doc.text(column.label, cursorX + 1.2, y + 4)
      doc.setFont('helvetica', 'normal')
      doc.text(dateLabel(column.day.date), cursorX + 1.2, y + 8)
      doc.text(dayTypeLabel(column.day), cursorX + 1.2, y + 11)
      doc.text('Unterschrift', cursorX + 1.2, y + 14)
      doc.setFont('helvetica', 'bold')
    } else {
      doc.text(column.label, cursorX + 1.2, y + 9)
    }

    cursorX += column.width
  })
}

const drawPdfRows = (doc, columns, rows, page, layout, pdfSignatures) => {
  const pageStart = isPreparationPa.value
    ? (page - 1) * layout.rowsPerPage
    : (page === 1 ? 0 : layout.firstParticipantPageRows)
  const pageRows = isPreparationPa.value
    ? layout.rowsPerPage
    : (page === 1 ? layout.firstParticipantPageRows : layout.secondParticipantPageRows)
  const rowsY = !isPreparationPa.value && page === 2
    ? layout.secondPageTableY
    : layout.tableY + layout.headHeight

  Array.from({ length: pageRows }).forEach((_, index) => {
    const participant = rows[pageStart + index] || null
    const rowNumber = pageStart + index + 1
    const y = rowsY + (index * layout.rowHeight)
    let cursorX = layout.tableX

    columns.forEach((column) => {
      applyPdfPrintInk(doc)
      doc.setLineWidth(0.25)
      doc.rect(cursorX, y, column.width, layout.rowHeight)
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(6.4)
      const textY = y + 4.4

      if (column.key === 'nr') {
        doc.text(String(rowNumber), cursorX + 1.2, textY)
      } else if (column.key === 'nachname') {
        doc.text(String(participant?.nachname || ''), cursorX + 1.2, textY, { maxWidth: column.width - 2.4 })
      } else if (column.key === 'vorname') {
        doc.text(String(participant?.vorname || ''), cursorX + 1.2, textY, { maxWidth: column.width - 2.4 })
      } else if (column.key === 'klasse') {
        doc.text(String(participant?.klasse || ''), cursorX + 1.2, textY, { maxWidth: column.width - 2.4 })
      } else if (column.day && participant && isParticipantExpectedOnDay(column.day, participant)) {
        const signature = pdfSignatures[signatureKey(column.day, participant)]
        if (signature) {
          drawPdfSignature(doc, signature, cursorX + 1, y + 0.5, column.width - 2, layout.rowHeight - 1)
        }
      }

      cursorX += column.width
    })
  })
}

const drawTrainerTable = (doc, layout) => {
  const tableDays = Array.from({ length: 3 }, (_, index) => selectedDays.value[index] || { date: '' })
  const tableWidth = 201.6 * layout.widthScale
  const tableX = (layout.pageWidth - tableWidth) / 2
  const columnWidth = tableWidth / 4
  const rowHeights = [7.5, 7.5, 9, 9].map((height) => height * layout.rowScale)
  const labels = [
    'Datum',
    'Zeitstunden',
    'Name/Vorname\nAusbilder/-in',
    'Unterschrift\nAusbilder/-in',
  ]
  let y = 48 * layout.widthScale

  applyPdfPrintInk(doc)
  doc.setLineWidth(0.25)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(form.exportFormat === 'A3' ? 9 : 7.5)

  labels.forEach((label, rowIndex) => {
    const rowHeight = rowHeights[rowIndex]
    doc.rect(tableX, y, columnWidth, rowHeight)
    label.split('\n').forEach((line, lineIndex) => {
      doc.text(line, tableX + (2 * layout.widthScale), y + (3.8 * layout.rowScale) + (lineIndex * 3.4 * layout.rowScale))
    })

    tableDays.forEach((day, dayIndex) => {
      const x = tableX + columnWidth + (dayIndex * columnWidth)
      doc.rect(x, y, columnWidth, rowHeight)
      if (rowIndex === 0 && day.date) {
        doc.text(dateLabel(day.date), x + (2 * layout.widthScale), y + (4.2 * layout.rowScale))
      }
    })

    y += rowHeight
  })
}

const createSignedPdf = async () => {
  if (!selectedDays.value.length) {
    PaSwal.fire('Keine Tage', isPreparationPa.value ? 'Bitte den Vorbereitungstag übernehmen.' : 'Bitte mindestens einen PA-Tag auswählen.', 'warning')
    return
  }

  if (!isPreparationPa.value && classScheduleOverview.value) {
    PaSwal.fire(
      'Klasse auswählen',
      'Die Übersicht enthält unterschiedliche Klassentermine. Bitte für die PDF jeweils „Eine Klasse“ auswählen.',
      'info'
    )
    return
  }

  if (!isPreparationPa.value && sheetParticipants.value.length > 34) {
    PaSwal.fire(
      'Maximal 34 Teilnehmer/-innen',
      'Bitte eine einzelne Klasse mit höchstens 34 Teilnehmer/-innen auswählen. So bleiben die Teilnehmer auf Seite 1 und 2 und die Ausbilderliste auf Seite 3.',
      'warning'
    )
    return
  }

  exportingPdf.value = true

  try {
    const footerImage = await loadBopAttendanceFooterImage()
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: pdfFormat() })
    const layout = pdfLayout(doc)
    const columns = pdfColumns(layout)
    const rows = sheetParticipants.value
    const pdfSignatures = await prepareSignaturesForPdf(signatures)
    const calculatedParticipantPages = Math.max(1, Math.ceil(Math.max(rows.length, 1) / layout.rowsPerPage))
    const participantPages = isPreparationPa.value ? calculatedParticipantPages : 2
    const totalPages = participantPages + (isPreparationPa.value ? 0 : 1)

    for (let page = 1; page <= participantPages; page++) {
      if (page > 1) doc.addPage()
      if (!isPreparationPa.value && page === 2) {
        drawPdfPageNumber(doc, page, totalPages, layout)
      } else {
        drawPdfHeader(doc, page, totalPages, layout)
        drawPdfTableHeader(doc, columns, layout)
      }
      drawPdfRows(doc, columns, rows, page, layout, pdfSignatures)
      drawBopAttendanceFooter(doc, footerImage)
    }

    if (!isPreparationPa.value) {
      doc.addPage()
      drawPdfHeader(doc, totalPages, totalPages, layout, true)
      drawTrainerTable(doc, layout)
      drawBopAttendanceFooter(doc, footerImage)
    }

    const school = previewContext.value?.schule?.name || 'Schule'
    const classPart = form.exportMode === 'klasse' && form.klasse ? `_Klasse_${safePdfFilePart(form.klasse, 'Klasse')}` : ''
    const filename = `${pdfFilenamePrefix.value}_${safePdfFilePart(school, 'Schule')}_${safePdfFilePart(props.schuljahr, 'Schuljahr')}_Teil_${safePdfFilePart(props.teil, 'Teil')}${classPart}.pdf`
    const pdfBlob = doc.output('blob')
    let folderSave = null
    let folderSaveError = null

    try {
      folderSave = await storeSignedPdfInFolder(pdfBlob, filename)
    } catch (error) {
      folderSaveError = await readBlobError(error)
    }

    doc.save(filename)

    if (folderSave) {
      PaSwal.fire(
        'PDF erstellt',
        `Die PDF wurde heruntergeladen und im Ordner gespeichert: ${folderSave.folder}`,
        'success'
      )
    } else if (folderSaveError) {
      PaSwal.fire(
        'PDF heruntergeladen',
        `Die lokale PDF wurde erstellt, aber die Ordner-Speicherung ist fehlgeschlagen: ${folderSaveError}`,
        'warning'
      )
    }
  } catch (error) {
    PaSwal.fire('Fehler', 'Das PDF konnte nicht erstellt werden.', 'error')
  } finally {
    exportingPdf.value = false
  }
}

const clearDraft = async () => {
  const result = await PaSwal.fire({
    title: 'Entwurf leeren?',
    text: `Alle Klassentermine und Unterschriften dieser ${modalTitle.value} werden unwiderruflich gelöscht.`,
    icon: 'warning',
    input: 'text',
    inputLabel: 'Zur Bestätigung delete eingeben',
    inputPlaceholder: 'delete',
    showCancelButton: true,
    confirmButtonText: 'Endgültig leeren',
    cancelButtonText: 'Abbrechen',
    preConfirm: (value) => {
      if (String(value || '').trim().toLowerCase() !== 'delete') {
        PaSwal.showValidationMessage('Bitte delete eingeben.')
        return false
      }

      return true
    },
  })

  if (!result.isConfirmed) return

  try {
    await axios.post(route('anwesenheitsliste.PA.digital.draft.clear'), draftScopePayload())

    window.clearTimeout(draftSaveTimer)
    draftSaveTimer = null
    draftSaveGeneration++
    draftSaveRequestId++
    draftHydrating.value = true
    form.startDate = ''
    form.endDate = ''
    form.feedbackDate = ''
    previewContext.value = null
    allParticipants.value = []
    days.value = []
    selectedDayId.value = null
    baseDraftSchedule.value = { form: {}, days: [], selectedDayId: null }
    classSchedules.value = {}
    activeScheduleClass.value = null
    manualDate.value = ''
    manualNote.value = ''
    syncSignatures({}, { removeMissing: true })
    clearPendingSignatureChanges()
    draftHydrating.value = false
    draftDirty.value = false
    draftRevision.value = 0
    draftLastSavedAt.value = null
    draftExpiresAt.value = null
    PaSwal.fire('Gelöscht', 'Der zentrale Entwurf wurde geleert.', 'success')
  } catch (error) {
    PaSwal.fire('Fehler', await readBlobError(error), 'error')
  }
}

const closeSheetFullscreen = () => {
  sheetFullscreen.value = false
}

const toggleSheetFullscreen = () => {
  sheetFullscreen.value = !sheetFullscreen.value
}

const handleSheetFullscreenKeydown = (event) => {
  if (event.key === 'Escape') closeSheetFullscreen()
}

const resetState = () => {
  stopDraftTimers()
  closeSheetFullscreen()
  draftHydrating.value = true
  draftSaveBlocked.value = false
  draftSaveError.value = ''
  form.exportFormat = 'A4'
  form.startDate = ''
  form.endDate = ''
  form.feedbackDate = ''
  generateGroupsFromPlan.value = false
  form.exportMode = props.klasse ? 'klasse' : 'alle'
  form.klasse = props.klasse || ''
  previewContext.value = null
  allParticipants.value = []
  days.value = []
  selectedDayId.value = null
  baseDraftSchedule.value = { form: {}, days: [], selectedDayId: null }
  classSchedules.value = {}
  activeScheduleClass.value = null
  manualDate.value = ''
  manualNote.value = ''
  Object.keys(signatures).forEach((key) => delete signatures[key])
  clearPendingSignatureChanges()
  signatureHistoryVisible.value = false
  signatureHistoryLoading.value = false
  signatureHistoryRestoringId.value = null
  signatureHistoryVersions.value = []
  signatureHistoryContext.value = null
  signatureHistoryOverviewVisible.value = false
  signatureHistoryOverviewLoading.value = false
  signatureHistoryOverviewItems.value = []
  signatureHistoryOverviewSearch.value = ''
  resetDraftMeta()
  draftHydrating.value = false
}

const onHide = () => {
  emit('close')
}

const loadBopPlanDefaults = async () => {
  if (form.startDate || days.value.length) return

  try {
    const response = await axios.get(route('bop.run.show', {
      partner: props.partnerId,
      schuljahr: props.schuljahr,
      teil: props.teil,
    }))
    const plannedPhases = response.data?.phases || []
    const mainType = isPreparationPa.value ? 'pa_preparation' : 'pa'
    const phase = plannedPhases.find((item) => item.phase_type === mainType)
    const plannedDates = [...(phase?.dates || [])].sort()

    if (plannedDates.length) {
      form.startDate = plannedDates[0]
      form.endDate = isPreparationPa.value ? '' : (plannedDates.at(-1) || plannedDates[0])
      appendPaDays(plannedDates.map((date, index) => ({
        id: `bop-plan-${mainType}-${date}`,
        date,
        type: isPreparationPa.value ? 'preparation' : 'pa_day',
        source: 'bop-plan',
        note: isPreparationPa.value ? 'Vorbereitung PA' : `PA-Tag ${index + 1}`,
      })), 'bop-plan')
    }

    if (!isPreparationPa.value) {
      const feedback = plannedPhases.find((item) => item.phase_type === 'pa_feedback')
      form.feedbackDate = [...(feedback?.dates || [])].sort()[0] || ''
    }
  } catch {
    // Ein fehlender Plan blockiert die bisherige manuelle Listenerstellung nicht.
  }
}

watch(
  () => props.visible,
  async (visible) => {
    if (visible) {
      window.addEventListener('beforeunload', warnAboutUnsavedDraft)
      await loadBopPlanDefaults()
      loadPreview({ includeDraft: true })
      startDraftPolling()
    } else {
      window.removeEventListener('beforeunload', warnAboutUnsavedDraft)
      flushDraftSave()
      resetState()
    }
  },
  { immediate: true }
)

watch(form, scheduleDraftSave, { deep: true, flush: 'sync' })
watch(days, scheduleDraftSave, { deep: true, flush: 'sync' })
watch(selectedDayId, scheduleDraftSave, { flush: 'sync' })
watch(sheetFullscreen, (fullscreen) => {
  if (typeof document === 'undefined') return

  if (fullscreen) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', handleSheetFullscreenKeydown)
    return
  }

  document.body.style.overflow = previousBodyOverflow
  window.removeEventListener('keydown', handleSheetFullscreenKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnAboutUnsavedDraft)
  flushDraftSave()
  stopDraftTimers()
  closeSheetFullscreen()
  window.removeEventListener('keydown', handleSheetFullscreenKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <Dialog
    v-model:visible="localVisible"
    :header="modalTitle"
    :modal="true"
    class="w-full max-w-7xl"
    @hide="onHide"
  >
    <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
      <section class="space-y-4 rounded border border-gray-200 bg-white p-4">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500">Kontext</p>
          <h2 class="text-base font-bold text-gray-900">
            {{ previewContext?.schule?.name || 'Schule' }}
          </h2>
          <p class="text-sm text-gray-600">Schuljahr {{ schuljahr }} / Teil {{ teil }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="text-sm font-semibold text-gray-700">
            <span class="mb-1 block">Format</span>
            <select v-model="form.exportFormat" class="w-full rounded border-gray-300 text-sm">
              <option value="A4">A4</option>
              <option value="A3">A3</option>
            </select>
          </label>

          <label class="text-sm font-semibold text-gray-700">
            <span class="mb-1 block">Auswahl</span>
            <select v-model="form.exportMode" class="w-full rounded border-gray-300 text-sm" @change="reloadScope">
              <option value="alle">Alle Klassen</option>
              <option value="klasse">Eine Klasse</option>
            </select>
          </label>

        </div>

        <label v-if="form.exportMode === 'klasse'" class="block text-sm font-semibold text-gray-700">
          <span class="mb-1 block">Klasse</span>
          <select v-model="form.klasse" class="w-full rounded border-gray-300 text-sm" @change="reloadScope">
            <option value="" disabled>Klasse auswählen</option>
            <option v-for="klasseOption in klassen" :key="klasseOption" :value="klasseOption">
              {{ klasseOption }}{{ classSchedules[String(klasseOption).trim()] ? ' – eigene Termine' : '' }}
            </option>
          </select>
        </label>

        <div
          v-if="!isPreparationPa && form.exportMode === 'klasse' && form.klasse"
          class="rounded border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900"
        >
          <strong class="block">Eigener Terminplan für Klasse {{ form.klasse }}</strong>
          Änderungen an den PA-Daten gelten nur für diese Klasse. Bereits gespeicherte Unterschriften bleiben zentral erhalten.
        </div>

        <div v-if="classScheduleOverview" class="rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
          <strong class="block">Getrennte Klassentermine aktiv</strong>
          In dieser Ansicht werden alle Klassentermine zusammengeführt. Zum Ändern der Daten bitte „Eine Klasse“ auswählen.
        </div>

        <div v-if="!classScheduleOverview" class="rounded border border-gray-200 p-3">
          <p class="mb-3 text-sm font-semibold text-gray-700">{{ dateConfigTitle }}</p>
          <div class="grid gap-3" :class="isPreparationPa ? 'grid-cols-1' : 'grid-cols-2'">
            <label class="text-xs font-semibold text-gray-600">
              <span class="mb-1 block">{{ primaryDateLabel }}</span>
              <input v-model="form.startDate" type="date" class="w-full rounded border-gray-300 text-sm" />
            </label>
            <label v-if="!isPreparationPa" class="text-xs font-semibold text-gray-600">
              <span class="mb-1 block">PA-Tag 2</span>
              <input v-model="form.endDate" type="date" class="w-full rounded border-gray-300 text-sm" />
            </label>
          </div>

          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 rounded bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-900"
            @click="createPaTermDays"
          >
            <i class="la la-calendar-plus"></i>
            {{ createDaysButtonText }}
          </button>
        </div>

        <label v-if="!isPreparationPa && !classScheduleOverview" class="block text-sm font-semibold text-gray-700">
          <span class="mb-1 block">Feedbackgespräch</span>
          <input v-model="form.feedbackDate" type="date" class="w-full rounded border-gray-300 text-sm" />
        </label>

        <label v-if="canGenerateBopGroups" class="flex items-start gap-2 rounded border border-orange-200 bg-orange-50 p-3 text-xs text-orange-900">
          <input v-model="generateGroupsFromPlan" type="checkbox" class="mt-0.5 rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
          <span><strong class="block">Anwesenheitsgruppen erzeugen</strong>Beim Laden der Liste werden die im BOP-Ablauf gespeicherte Teilnehmerauswahl, Gruppenart und Termine verwendet.</span>
        </label>

        <div v-if="!isPreparationPa && !classScheduleOverview" class="rounded border border-gray-200 p-3">
          <p class="mb-3 text-sm font-semibold text-gray-700">Sondertag</p>
          <div class="grid gap-2">
            <input v-model="manualDate" type="date" class="w-full rounded border-gray-300 text-sm" />
            <input v-model="manualNote" type="text" class="w-full rounded border-gray-300 text-sm" placeholder="Notiz" />
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="addManualDay"
            >
              <i class="la la-plus"></i>
              Hinzufügen
            </button>
          </div>
        </div>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-zbb/90 disabled:opacity-50"
          :disabled="loadingPreview || !scopeReady"
          @click="loadPreview"
        >
          <i class="la la-sync"></i>
          {{ loadingPreview ? 'Lädt...' : 'Vorschau laden' }}
        </button>
      </section>

      <section class="min-w-0 space-y-4">
        <div class="rounded border border-gray-200 bg-white p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-gray-500">{{ dayPluralLabel }}</p>
              <h3 class="text-base font-bold text-gray-900">
                {{ selectedDays.length }} ausgewählt / {{ days.length }} in der Vorschau
              </h3>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-if="!isPreparationPa"
                type="button"
                class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="exportingWord"
                @click="handleWordExport"
              >
                <i class="la la-file-word"></i>
                {{ exportingWord ? 'Exportiert...' : 'Word' }}
              </button>
              <button
                v-if="canArchiveAttendance"
                type="button"
                class="inline-flex items-center gap-2 rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50"
                :disabled="exportingPdf || selectedDays.length === 0"
                @click="createSignedPdf"
              >
                <i class="la la-file-signature"></i>
                {{ exportingPdf ? 'Erstellt...' : 'PDF mit Unterschrift' }}
              </button>
              <button
                v-if="canArchiveAttendance"
                type="button"
                class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="creatingArchiveFolder"
                @click="createArchiveFolder"
              >
                <i class="la la-folder-plus"></i>
                {{ creatingArchiveFolder ? 'Erstellt...' : 'Archiv-Ordner' }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                :disabled="draftLoading || !draftLoaded"
                title="Alle Unterschriftsverläufe dieser PA anzeigen"
                @click="openSignatureHistoryOverview"
              >
                <i class="la la-history"></i>
                Alle Verläufe
              </button>
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="draftSaving || !draftLoaded"
                title="Entwurf speichern"
                @click="saveDraft({ silent: false })"
              >
                <i class="la la-save"></i>
              </button>
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="draftLoading || draftDirty"
                title="Entwurf von anderen Geräten aktualisieren"
                @click="loadDraft({ silent: false })"
              >
                <i class="la la-cloud-download-alt"></i>
              </button>
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50"
                :disabled="draftSaving || draftLoading || !scopeReady"
                title="Zentralen Entwurf leeren"
                @click="clearDraft"
              >
                <i class="la la-trash"></i>
              </button>
            </div>
          </div>
          <p class="mt-2 text-xs text-gray-500">
            Zentraler Entwurf: {{ draftStatusText }} / {{ signatureCount }} Unterschriften / Revision {{ draftRevision }}
            <span v-if="draftExpiryText"> / Rohdaten bis {{ draftExpiryText }}</span>
          </p>
          <div v-if="draftSaveBlocked" class="mt-3 rounded border border-red-400 bg-red-50 p-3 text-sm font-semibold text-red-800" role="alert">
            <span>{{ draftSaveError }}</span>
            <button
              type="button"
              class="ml-3 rounded border border-red-500 bg-white px-3 py-1 text-xs font-bold text-red-800 hover:bg-red-100 disabled:opacity-50"
              :disabled="draftSaving"
              @click="saveDraft({ silent: false })"
            >
              Jetzt erneut speichern
            </button>
          </div>

          <div v-if="days.length === 0" class="mt-4 rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            {{ noDaysText }}
          </div>

          <div v-else class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <button
              v-for="day in days"
              :key="day.id"
              type="button"
              class="rounded border p-3 text-left transition"
              :class="selectedDay?.id === day.id ? 'border-zbb bg-orange-50' : 'border-gray-200 bg-white hover:bg-gray-50'"
              @click="selectedDayId = day.id"
            >
              <div class="flex items-start justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900" @click.stop>
                  <input v-model="day.selected" type="checkbox" class="rounded border-gray-300 text-zbb" :disabled="classScheduleOverview" />
                  <span>{{ dateLabel(day.date) }}</span>
                </label>
                <span class="rounded bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700">
                  {{ dayTypeLabel(day) }}
                </span>
              </div>

              <p class="mt-1 text-xs text-gray-500">
                {{ weekdayLabel(day.date) }} / {{ day.participants_count }} Teilnehmer /
                {{ signedCountForDay(day) }}/{{ expectedSignatureCountForDay(day) }} unterschrieben
              </p>

              <p v-if="classScheduleOverview && day.eligible_classes?.length" class="mt-1 text-[11px] font-semibold text-blue-700">
                Klassen: {{ day.eligible_classes.join(', ') }}
              </p>

              <input
                v-model="day.note"
                type="text"
                class="mt-3 w-full rounded border-gray-300 text-xs"
                placeholder="Notiz"
                :disabled="classScheduleOverview"
                @click.stop
              />
            </button>
          </div>
        </div>

        <div v-if="selectedDays.length" :class="sheetSectionClass">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-gray-500">Digitales Original-Blatt</p>
              <h3 class="text-base font-bold text-gray-900">
                {{ sheetTitle }}
              </h3>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600">{{ selectedDaysSummary }}</span>
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                :title="sheetFullscreen ? 'Vollbild verlassen' : 'Digitales Blatt im Vollbild anzeigen'"
                @click="toggleSheetFullscreen"
              >
                <i :class="sheetFullscreen ? 'la la-compress' : 'la la-expand'"></i>
              </button>
            </div>
          </div>

          <div :class="sheetTableWrapperClass">
            <table class="min-w-[980px] border-collapse text-[11px]">
              <thead class="sticky top-0 z-10 bg-white">
                <tr>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Nr.</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Name</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Vorname</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Klasse</th>
                  <th
                    v-for="(day, index) in selectedDays"
                    :key="`head-${day.id}`"
                    class="min-w-[132px] border border-gray-800 px-2 py-2 text-left align-top font-semibold"
                  >
                    <span class="block">{{ dayColumnLabel(index) }}</span>
                    <span class="block font-normal">Datum: {{ dateLabel(day.date) }}</span>
                    <span class="block font-normal">{{ dayTypeLabel(day) }}</span>
                    <span class="block font-normal">Unterschrift Schüler/-in</span>
                    <span class="mt-1 block text-[10px] font-semibold text-emerald-700">
                      {{ signedCountForDay(day) }}/{{ expectedSignatureCountForDay(day) }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <template
                  v-for="row in classedParticipants"
                  :key="row.participant.person_id || row.participant.id"
                >
                  <tr v-if="row.startsClass" class="bg-slate-100">
                    <th
                      :colspan="4 + selectedDays.length"
                      class="border border-gray-800 px-3 py-2 text-left text-xs font-bold text-gray-800"
                    >
                      Klasse {{ row.participant.klasse || 'ohne Klassenangabe' }}
                    </th>
                  </tr>
                  <tr>
                    <td class="border border-gray-800 px-2 py-2 align-middle">{{ row.index + 1 }}</td>
                    <td class="border border-gray-800 px-2 py-2 align-middle font-medium">{{ row.participant.nachname }}</td>
                    <td class="border border-gray-800 px-2 py-2 align-middle">{{ row.participant.vorname }}</td>
                    <td class="border border-gray-800 px-2 py-2 align-middle">{{ row.participant.klasse }}</td>
                    <td
                      v-for="day in selectedDays"
                      :key="`${day.id}-${row.participant.person_id || row.participant.id}`"
                      class="border border-gray-800 p-1 align-middle"
                      :class="isParticipantExpectedOnDay(day, row.participant)
                        ? (hasSignature(day, row.participant) ? 'bg-emerald-50' : 'bg-white')
                        : 'bg-gray-100 text-gray-400'"
                    >
                      <div v-if="isParticipantExpectedOnDay(day, row.participant)" class="relative">
                        <span
                          v-if="hasSignature(day, row.participant)"
                          class="pointer-events-none absolute right-1 top-1 z-10 text-[11px] text-emerald-700"
                          title="Unterschrieben"
                        >
                          <i class="la la-check-circle"></i>
                        </span>
                        <SignatureBox
                          :disabled="draftSaveBlocked"
                          :model-value="signatures[signatureKey(day, row.participant)] || ''"
                          :participant-name="[row.participant.vorname, row.participant.nachname].filter(Boolean).join(' ')"
                          compact
                          @update:model-value="captureSignature(day, row.participant, $event)"
                          @completed="saveCompletedSignature(day, row.participant, $event)"
                          @cleared="removeSignature(day, row.participant)"
                        />
                        <button
                          type="button"
                          class="mt-1 inline-flex w-full items-center justify-center gap-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-700 hover:bg-slate-100"
                          title="Versionsverlauf dieser Unterschrift anzeigen"
                          @click="openSignatureHistory(day, row.participant)"
                        >
                          <i class="la la-history"></i>
                          Verlauf
                        </button>
                      </div>
                      <div v-else class="px-2 py-3 text-center text-[10px] font-semibold uppercase tracking-wide">
                        Nicht vorgesehen
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div class="flex justify-center bg-white px-4 pb-4 pt-5">
            <img
              src="/img/bop/kooperationspartner.png"
              alt="BOP Kooperationspartner und Förderer"
              class="h-auto w-full max-w-[720px]"
            >
          </div>
        </div>
      </section>
    </div>

    <Dialog
      v-model:visible="signatureHistoryOverviewVisible"
      modal
      append-to="body"
      class="w-full max-w-4xl"
      header="Alle Unterschriftsverläufe"
    >
      <div class="space-y-4">
        <div class="rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
          Hier erscheinen auch ältere Unterschriften, deren damaliges PA-Datum nicht mehr in der aktuellen Auswahl sichtbar ist.
        </div>

        <input
          v-model="signatureHistoryOverviewSearch"
          type="search"
          class="w-full rounded border-slate-300 text-sm"
          placeholder="Nach Schüler, Klasse, Tag, Datum oder Status suchen"
        />

        <div v-if="signatureHistoryOverviewLoading" class="py-8 text-center text-sm text-slate-500">
          Unterschriftsverläufe werden geladen ...
        </div>

        <div
          v-else-if="filteredSignatureHistoryOverviewItems.length === 0"
          class="rounded border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500"
        >
          Keine passenden Unterschriftsverläufe gefunden.
        </div>

        <div v-else class="max-h-[65vh] overflow-auto rounded border border-slate-200">
          <table class="min-w-full text-left text-sm">
            <thead class="sticky top-0 bg-slate-100 text-xs uppercase text-slate-600">
              <tr>
                <th class="px-3 py-2">Schüler/-in</th>
                <th class="px-3 py-2">Klasse</th>
                <th class="px-3 py-2">PA-Tag</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2 text-right">Versionen</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in filteredSignatureHistoryOverviewItems"
                :key="item.signature_key"
                class="cursor-pointer border-t border-slate-200 hover:bg-slate-50"
                tabindex="0"
                @click="openSignatureHistoryFromOverview(item)"
                @keydown.enter="openSignatureHistoryFromOverview(item)"
              >
                <td class="px-3 py-2 font-semibold text-slate-900">{{ item.participant_name }}</td>
                <td class="px-3 py-2 text-slate-600">{{ item.class_name || '–' }}</td>
                <td class="px-3 py-2 text-slate-600">
                  <span class="block">{{ item.day_label || 'PA-Tag' }}</span>
                  <span class="text-xs">{{ item.signed_for_date ? dateLabel(item.signed_for_date) : 'Datum unbekannt' }}</span>
                </td>
                <td class="px-3 py-2">
                  <span
                    class="rounded px-2 py-1 text-xs font-semibold"
                    :class="item.has_current_signature ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                  >
                    {{ signatureHistoryActionLabel(item.current_action) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right font-bold text-slate-700">{{ item.version_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Dialog>

    <Dialog
      v-model:visible="signatureHistoryVisible"
      modal
      append-to="body"
      class="w-full max-w-3xl"
      header="Unterschriftsverlauf"
    >
      <div v-if="signatureHistoryContext" class="space-y-4">
        <div class="rounded border border-slate-200 bg-slate-50 p-3">
          <p class="font-bold text-slate-900">{{ signatureHistoryContext.participantName }}</p>
          <p class="text-sm text-slate-600">
            Klasse {{ signatureHistoryContext.className || '–' }} /
            {{ signatureHistoryContext.dayLabel }} /
            {{ dateLabel(signatureHistoryContext.date) }}
          </p>
        </div>

        <div v-if="signatureHistoryLoading" class="py-8 text-center text-sm text-slate-500">
          Verlauf wird geladen ...
        </div>

        <div
          v-else-if="signatureHistoryVersions.length === 0"
          class="rounded border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500"
        >
          Für diesen Schüler und PA-Tag wurde noch keine Unterschriftsversion gespeichert.
        </div>

        <div v-else class="max-h-[65vh] space-y-3 overflow-y-auto pr-1">
          <article
            v-for="version in signatureHistoryVersions"
            :key="version.id"
            class="rounded border p-3"
            :class="version.is_current ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white'"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="text-sm text-slate-900">Version {{ version.version }}</strong>
                  <span
                    v-if="version.is_current"
                    class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white"
                  >
                    Aktuell
                  </span>
                  <span class="text-xs font-semibold text-slate-600">
                    {{ signatureHistoryActionLabel(version.action) }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                  {{ signatureHistoryTimestamp(version.created_at) }}
                  <span v-if="version.actor_name"> / {{ version.actor_name }}</span>
                  <span v-if="version.source_draft_revision"> / Entwurf {{ version.source_draft_revision }}</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ version.day_label || signatureHistoryContext.dayLabel }}
                  <span v-if="version.signed_for_date"> / {{ dateLabel(version.signed_for_date) }}</span>
                  <span v-if="version.class_name"> / Klasse {{ version.class_name }}</span>
                </p>
              </div>

              <button
                v-if="version.can_restore"
                type="button"
                class="inline-flex items-center gap-1 rounded border border-blue-300 bg-white px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                :disabled="signatureHistoryRestoringId !== null"
                @click="restoreSignatureVersion(version)"
              >
                <i class="la la-undo"></i>
                {{ signatureHistoryRestoringId === version.id ? 'Wird wiederhergestellt ...' : 'Wiederherstellen' }}
              </button>
            </div>

            <div v-if="version.signature" class="mt-3 rounded border border-slate-200 bg-white p-2">
              <img :src="version.signature" alt="Gespeicherte Unterschrift" class="h-24 w-full object-contain" />
            </div>
            <p v-else-if="version.action === 'deleted'" class="mt-3 text-xs font-semibold text-red-700">
              In dieser Version wurde die Unterschrift gelöscht.
            </p>
            <p v-else-if="!version.signature_available" class="mt-3 text-xs font-semibold text-red-700">
              Das verschlüsselte Bild dieser Version konnte nicht gelesen werden.
            </p>
          </article>
        </div>
      </div>
    </Dialog>

    <template #footer>
      <Button
        label="Schließen"
        icon="pi pi-times"
        class="p-button-text"
        @click="localVisible = false"
      />
    </template>
  </Dialog>
</template>

<style>
.pa-swal-container {
  z-index: 13000 !important;
}
</style>

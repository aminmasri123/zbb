<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Swal from 'sweetalert2'
import axios from 'axios'
import { jsPDF } from 'jspdf'
import SignatureBox from '@/Components/SignatureBox.vue'

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
})

const emit = defineEmits(['update:visible', 'close'])
const PaSwal = Swal.mixin({
  customClass: {
    container: 'pa-swal-container',
  },
})

const localVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value),
})

const form = reactive({
  exportFormat: 'A4',
  startDate: '',
  endDate: '',
  includeSaturday: false,
  includeSunday: false,
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
const draftRevision = ref(0)
const draftSaving = ref(false)
const draftLoading = ref(false)
const draftLoaded = ref(false)
const draftHydrating = ref(false)
const draftDirty = ref(false)
const draftLastSavedAt = ref(null)
const draftExpiresAt = ref(null)
const sheetFullscreen = ref(false)
const draftAutoSaveDelayMs = 5000
const draftPollIntervalMs = 12000
let draftSaveTimer = null
let draftPollTimer = null
let previousBodyOverflow = ''
let draftSaveRequestId = 0

const selectedDays = computed(() => days.value.filter((day) => day.selected))
const selectedDay = computed(() => days.value.find((day) => day.id === selectedDayId.value) || selectedDays.value[0] || null)
const sheetParticipants = computed(() => allParticipants.value)
const signatureCount = computed(() => Object.values(signatures).filter(Boolean).length)
const scopeReady = computed(() => props.partnerId && props.schuljahr && props.teil && (form.exportMode === 'alle' || form.klasse))
const draftStatusText = computed(() => {
  if (draftLoading.value) return 'wird geladen'
  if (draftSaving.value) return 'wird gespeichert'
  if (draftDirty.value) return 'Aenderungen offen'
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

  return values.length === 1 ? dateLabel(values[0]) : `${dateLabel(values[0])} - ${dateLabel(values.at(-1))}`
})
const sheetSectionClass = computed(() => sheetFullscreen.value
  ? 'fixed inset-0 z-[9999] overflow-hidden border-0 bg-white p-4 shadow-2xl'
  : 'rounded border border-gray-200 bg-white p-4'
)
const sheetTableWrapperClass = computed(() => sheetFullscreen.value
  ? 'h-[calc(100vh-116px)] overflow-auto rounded border border-gray-300 bg-white'
  : 'max-h-[52vh] overflow-auto rounded border border-gray-300 bg-white'
)

const toDateInput = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

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

const dayTypeLabel = (day) => day?.type_label || (day?.type === 'feedback' ? 'Feedbackgespraech' : 'PA-Tag')

const signatureKey = (day, participant) => `${day.id}:${participant.person_id || participant.id}`

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
  return firstFieldError || data?.message || 'Die Aktion konnte nicht ausgefuehrt werden.'
}

const safePdfFilePart = (value, fallback = 'Datei') => {
  const safeValue = String(value || '').replace(/[^A-Za-z0-9_\-.]+/g, '_')

  return safeValue || fallback
}

const draftScopePayload = () => ({
  schuleId: props.partnerId,
  schuljahr: props.schuljahr,
  teil: props.teil,
  exportMode: form.exportMode,
  klasse: form.exportMode === 'klasse' ? form.klasse : '',
})

const cloneForDraft = (value) => JSON.parse(JSON.stringify(value ?? null))

const buildDraftPayload = ({ signaturesPayload = { ...signatures } } = {}) => ({
  version: 1,
  form: { ...form },
  days: cloneForDraft(days.value) || [],
  selectedDayId: selectedDayId.value,
  signatures: signaturesPayload,
})

const signatureSnapshot = (signaturePayload = {}) => JSON.stringify(
  Object.entries(signaturePayload || {})
    .filter(([, value]) => Boolean(value))
    .sort(([left], [right]) => left.localeCompare(right))
)

const hasSignature = (day, participant) => Boolean(day && participant && signatures[signatureKey(day, participant)])

const signedCountForDay = (day) => sheetParticipants.value
  .filter((participant) => hasSignature(day, participant))
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
  const preservedDays = selectedDays.value
    .filter((day) => day.source !== 'range' && day.type !== 'feedback' && day.source !== 'feedback')
    .map((day) => ({
      id: day.id,
      date: day.date,
      type: day.type,
      selected: day.selected,
      source: day.source,
      note: day.note || null,
    }))

  if (!form.startDate || !form.endDate) return preservedDays

  const rangeDays = rangeDates(form.startDate, form.endDate).map((date) => ({
    id: `range-${date}`,
    date,
    type: 'pa_day',
    selected: true,
    source: 'range',
    note: null,
  }))

  return [...rangeDays, ...preservedDays]
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

const dayWithGroups = (day) => ({
  ...day,
  groups: [{
    id: `pa-all-${day.date}`,
    label: 'Alle Teilnehmer',
    bereich: null,
    runde: null,
    participants: allParticipants.value,
    participants_count: allParticipants.value.length,
  }],
  participants_count: allParticipants.value.length,
})

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
    if (payload.form) {
      form.exportFormat = payload.form.exportFormat || form.exportFormat
      form.startDate = payload.form.startDate || ''
      form.endDate = payload.form.endDate || ''
      form.includeSaturday = Boolean(payload.form.includeSaturday)
      form.includeSunday = Boolean(payload.form.includeSunday)
      form.feedbackDate = payload.form.feedbackDate || ''
      form.exportMode = payload.form.exportMode || form.exportMode
      form.klasse = payload.form.klasse || form.klasse || ''
    }

    if (Array.isArray(payload.days) && payload.days.length) {
      days.value = payload.days.map(dayWithGroups)
      selectedDayId.value = payload.selectedDayId && days.value.some((day) => day.id === payload.selectedDayId)
        ? payload.selectedDayId
        : selectedDays.value[0]?.id || days.value[0]?.id || null
    } else if (payload.selectedDayId) {
      selectedDayId.value = payload.selectedDayId
    }

    syncSignatures(payload.signatures || {}, { removeMissing: true })
    draftDirty.value = false
  } finally {
    draftHydrating.value = false
  }
}

const loadDraft = async ({ silent = true } = {}) => {
  if (!scopeReady.value) return
  if (!silent) draftLoading.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.digital.draft.show'), draftScopePayload())

    if (!draftDirty.value) {
      if (response.data.exists && response.data.payload) {
        applyDraftPayload(response.data.payload)
      } else if (!response.data.exists) {
        draftHydrating.value = true
        syncSignatures({}, { removeMissing: true })
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

const saveDraft = async ({ silent = true, payload = null, signatureSnapshotGuard = null } = {}) => {
  if (!scopeReady.value) return

  const draftPayload = payload || buildDraftPayload()
  const requestSignatureSnapshot = signatureSnapshotGuard ?? signatureSnapshot(signatures)
  const requestId = ++draftSaveRequestId
  draftSaving.value = true

  try {
    const response = await axios.put(route('anwesenheitsliste.PA.digital.draft.store'), {
      ...draftScopePayload(),
      payload: draftPayload,
    })

    const signatureChangedDuringSave = signatureSnapshot(signatures) !== requestSignatureSnapshot
    const isLatestSaveResponse = requestId === draftSaveRequestId

    if (isLatestSaveResponse) {
      if (response.data.payload && !signatureChangedDuringSave) applyDraftPayload(response.data.payload)
      draftRevision.value = response.data.revision || draftRevision.value
      draftLastSavedAt.value = response.data.updated_at || new Date().toISOString()
      draftExpiresAt.value = response.data.expires_at || draftExpiresAt.value
      draftLoaded.value = true
      draftDirty.value = signatureChangedDuringSave
    }
  } catch (error) {
    if (!silent && requestId === draftSaveRequestId) {
      PaSwal.fire('Fehler', await readBlobError(error), 'error')
    }
  } finally {
    if (requestId === draftSaveRequestId) {
      draftSaving.value = false
    }
  }
}

const saveCompletedSignature = (day, participant, value) => {
  if (!day || !participant || !previewContext.value || draftHydrating.value) return
  if (!value) return

  const key = signatureKey(day, participant)
  signatures[key] = value
  draftDirty.value = true
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

const flushDraftSave = () => {
  if (!draftDirty.value || !previewContext.value) return

  const payload = buildDraftPayload()
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = null
  saveDraft({ silent: true, payload })
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
    PaSwal.fire('Klasse fehlt', 'Bitte eine Klasse auswaehlen oder Alle Klassen verwenden.', 'warning')
    return
  }

  loadingPreview.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.digital.preview'), {
      ...draftScopePayload(),
      startDate: form.startDate || null,
      endDate: form.endDate || null,
      feedbackDate: form.feedbackDate || null,
      includeSaturday: form.includeSaturday,
      includeSunday: form.includeSunday,
      days: previewDaysPayload(),
    })

    previewContext.value = response.data.context
    allParticipants.value = response.data.participants || []
    const responseDays = response.data.days || []
    hydrateDays(responseDays.length ? responseDays : days.value)
    if (includeDraft) await loadDraft({ silent: true })
  } catch (error) {
    PaSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    loadingPreview.value = false
  }
}

const rangeDates = (startValue, endValue) => {
  if (!startValue || !endValue) return []

  const start = new Date(`${startValue}T00:00:00`)
  const end = new Date(`${endValue}T00:00:00`)

  if (end < start) return []

  const values = []
  for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
    const weekday = date.getDay()
    if (weekday === 6 && !form.includeSaturday) continue
    if (weekday === 0 && !form.includeSunday) continue
    values.push(toDateInput(date))
  }

  return values
}

const appendPaDays = (dates, source = 'manual') => {
  const existing = new Set(days.value.map((day) => day.date))
  const generated = dates
    .filter((date) => !existing.has(date))
    .map((date) => dayWithGroups({
      id: `${source}-${date}`,
      date,
      date_label: dateLabel(date),
      type: 'pa_day',
      type_label: 'PA-Tag',
      source,
      selected: true,
      note: '',
    }))

  if (!generated.length) return

  days.value = [...days.value, ...generated].sort((a, b) => a.date.localeCompare(b.date))
  selectedDayId.value = selectedDayId.value || generated[0]?.id || null
}

const createRangeDays = () => {
  if (!form.startDate || !form.endDate) {
    PaSwal.fire('Zeitraum fehlt', 'Bitte Start- und Enddatum eintragen.', 'warning')
    return
  }

  if (new Date(`${form.endDate}T00:00:00`) < new Date(`${form.startDate}T00:00:00`)) {
    PaSwal.fire('Datum pruefen', 'Das Enddatum muss nach dem Startdatum liegen.', 'warning')
    return
  }

  appendPaDays(rangeDates(form.startDate, form.endDate), 'range')
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

  flushDraftSave()
  stopDraftTimers()
  draftHydrating.value = true
  days.value = []
  selectedDayId.value = null
  syncSignatures({}, { removeMissing: true })
  resetDraftMeta()
  draftHydrating.value = false
  await loadPreview({ includeDraft: true })
  startDraftPolling()
}

const handleWordExport = async () => {
  if (!form.startDate || !form.endDate || (form.exportMode === 'klasse' && !form.klasse)) {
    PaSwal.fire('Angaben fehlen', 'Bitte Zeitraum und Klasse pruefen.', 'warning')
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

const signatureImageRatio = 420 / 120

const drawPdfSignature = (doc, signature, x, y, width, height) => {
  if (!signature || width <= 0 || height <= 0) return

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
    signature,
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
  const rowHeight = form.exportFormat === 'A3' ? 7.1 : 6.5
  const tableY = form.exportFormat === 'A3' ? 42 : 39
  const bottomMargin = 12
  const headHeight = 16
  const rowsPerPage = Math.floor((pageHeight - tableY - headHeight - bottomMargin) / rowHeight)

  return {
    pageWidth,
    pageHeight,
    tableX: 8,
    tableY,
    tableWidth: pageWidth - 16,
    headHeight,
    rowHeight,
    rowsPerPage: Math.max(rowsPerPage, 10),
  }
}

const pdfColumns = (layout) => {
  const staticColumns = [
    { key: 'nr', label: 'Nr.', width: 8 },
    { key: 'nachname', label: 'Name', width: 34 },
    { key: 'vorname', label: 'Vorname', width: 31 },
    { key: 'klasse', label: 'Klasse', width: 17 },
  ]
  const staticWidth = staticColumns.reduce((sum, column) => sum + column.width, 0)
  const dayCount = Math.max(selectedDays.value.length, 1)
  const dayWidth = Math.max(18, (layout.tableWidth - staticWidth) / dayCount)

  return [
    ...staticColumns,
    ...selectedDays.value.map((day, index) => ({
      key: day.id,
      day,
      label: `Tag ${index + 1}`,
      width: dayWidth,
    })),
  ]
}

const drawPdfHeader = (doc, pageNumber, totalPages, layout) => {
  const x = layout.tableX
  const school = previewContext.value?.schule?.name || 'Schule'
  const classText = previewContext.value?.klasse || (form.exportMode === 'klasse' ? form.klasse : '')

  applyPdfPrintInk(doc)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10.5)
  doc.text('Anwesenheitsliste Potenzialanalyse (PA)', x, 13)
  doc.setFontSize(8)
  doc.text(`Seite ${pageNumber} von ${totalPages}`, layout.pageWidth - 40, 13)

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(7.2)
  doc.text('Schule:', x, 21)
  doc.text(String(school), x + 18, 21, { maxWidth: 82 })
  doc.text('Schulform:', x, 27)
  doc.text(String(previewContext.value?.schulform || ''), x + 18, 27, { maxWidth: 82 })
  doc.text('Klasse/n:', x, 33)
  doc.text(String(classText || ''), x + 18, 33, { maxWidth: 82 })

  doc.text('Zuwendungsempfaenger:', x + 110, 21)
  doc.text('- ZBB -', x + 150, 21)
  doc.text('Ausfuehrende Stelle:', x + 110, 27)
  doc.text('Zentrum fuer Bildung und Beruf Saar gGmbH in Burbach', x + 150, 27, { maxWidth: 100 })
  doc.text('Zeitraum:', x + 110, 33)
  doc.text(periodText.value || '', x + 150, 33, { maxWidth: 100 })
}

const drawPdfTableHeader = (doc, columns, layout) => {
  let cursorX = layout.tableX
  const y = layout.tableY

  applyPdfPrintInk(doc)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(6.4)
  doc.setLineWidth(0.25)

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

const drawPdfRows = (doc, columns, rows, page, layout) => {
  const pageStart = (page - 1) * layout.rowsPerPage

  Array.from({ length: layout.rowsPerPage }).forEach((_, index) => {
    const participant = rows[pageStart + index] || null
    const rowNumber = pageStart + index + 1
    const y = layout.tableY + layout.headHeight + (index * layout.rowHeight)
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
      } else if (column.day && participant) {
        const signature = signatures[signatureKey(column.day, participant)]
        if (signature) {
          drawPdfSignature(doc, signature, cursorX + 1, y + 0.5, column.width - 2, layout.rowHeight - 1)
        }
      }

      cursorX += column.width
    })
  })
}

const createSignedPdf = async () => {
  if (!selectedDays.value.length) {
    PaSwal.fire('Keine Tage', 'Bitte mindestens einen PA-Tag auswaehlen.', 'warning')
    return
  }

  exportingPdf.value = true

  try {
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: pdfFormat() })
    const layout = pdfLayout(doc)
    const columns = pdfColumns(layout)
    const rows = sheetParticipants.value
    const totalPages = Math.max(1, Math.ceil(Math.max(rows.length, 1) / layout.rowsPerPage))

    for (let page = 1; page <= totalPages; page++) {
      if (page > 1) doc.addPage()
      drawPdfHeader(doc, page, totalPages, layout)
      drawPdfTableHeader(doc, columns, layout)
      drawPdfRows(doc, columns, rows, page, layout)
    }

    const school = previewContext.value?.schule?.name || 'Schule'
    const classPart = form.exportMode === 'klasse' && form.klasse ? `_Klasse_${safePdfFilePart(form.klasse, 'Klasse')}` : ''
    const filename = `Anwesenheitsliste_PA_${safePdfFilePart(school, 'Schule')}_${safePdfFilePart(props.schuljahr, 'Schuljahr')}_Teil_${safePdfFilePart(props.teil, 'Teil')}${classPart}.pdf`
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
    text: 'Der zentrale Zwischenstand dieser PA-Anwesenheitsliste wird geloescht.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ja, leeren',
    cancelButtonText: 'Abbrechen',
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(route('anwesenheitsliste.PA.digital.draft.destroy'), {
      data: draftScopePayload(),
    })

    window.clearTimeout(draftSaveTimer)
    draftSaveTimer = null
    draftSaveRequestId++
    draftHydrating.value = true
    previewContext.value = null
    allParticipants.value = []
    days.value = []
    selectedDayId.value = null
    manualDate.value = ''
    manualNote.value = ''
    syncSignatures({}, { removeMissing: true })
    draftHydrating.value = false
    draftDirty.value = false
    draftRevision.value = 0
    draftLastSavedAt.value = null
    draftExpiresAt.value = null
    PaSwal.fire('Geloescht', 'Der zentrale Entwurf wurde geleert.', 'success')
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
  form.exportFormat = 'A4'
  form.startDate = ''
  form.endDate = ''
  form.includeSaturday = false
  form.includeSunday = false
  form.feedbackDate = ''
  form.exportMode = props.klasse ? 'klasse' : 'alle'
  form.klasse = props.klasse || ''
  previewContext.value = null
  allParticipants.value = []
  days.value = []
  selectedDayId.value = null
  manualDate.value = ''
  manualNote.value = ''
  Object.keys(signatures).forEach((key) => delete signatures[key])
  resetDraftMeta()
  draftHydrating.value = false
}

const onHide = () => {
  emit('close')
}

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      loadPreview({ includeDraft: true })
      startDraftPolling()
    } else {
      flushDraftSave()
      resetState()
    }
  },
  { immediate: true }
)

watch(form, scheduleDraftSave, { deep: true })
watch(days, scheduleDraftSave, { deep: true })
watch(selectedDayId, scheduleDraftSave)
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
    header="Anwesenheitsliste PA"
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
            <option value="" disabled>Klasse auswaehlen</option>
            <option v-for="klasseOption in klassen" :key="klasseOption" :value="klasseOption">
              {{ klasseOption }}
            </option>
          </select>
        </label>

        <div class="rounded border border-gray-200 p-3">
          <p class="mb-3 text-sm font-semibold text-gray-700">Zeitraum</p>
          <div class="grid grid-cols-2 gap-3">
            <label class="text-xs font-semibold text-gray-600">
              <span class="mb-1 block">Von</span>
              <input v-model="form.startDate" type="date" class="w-full rounded border-gray-300 text-sm" />
            </label>
            <label class="text-xs font-semibold text-gray-600">
              <span class="mb-1 block">Bis</span>
              <input v-model="form.endDate" type="date" class="w-full rounded border-gray-300 text-sm" />
            </label>
          </div>

          <div class="mt-3 flex flex-wrap gap-3 text-sm text-gray-700">
            <label class="inline-flex items-center gap-2">
              <input v-model="form.includeSaturday" type="checkbox" class="rounded border-gray-300 text-zbb" />
              <span>Samstag</span>
            </label>
            <label class="inline-flex items-center gap-2">
              <input v-model="form.includeSunday" type="checkbox" class="rounded border-gray-300 text-zbb" />
              <span>Sonntag</span>
            </label>
          </div>

          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 rounded bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-900"
            @click="createRangeDays"
          >
            <i class="la la-calendar-plus"></i>
            Tage uebernehmen
          </button>
        </div>

        <label class="block text-sm font-semibold text-gray-700">
          <span class="mb-1 block">Feedbackgespräch</span>
          <input v-model="form.feedbackDate" type="date" class="w-full rounded border-gray-300 text-sm" />
        </label>

        <div class="rounded border border-gray-200 p-3">
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
              Hinzufuegen
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
          {{ loadingPreview ? 'Laedt...' : 'Vorschau laden' }}
        </button>
      </section>

      <section class="min-w-0 space-y-4">
        <div class="rounded border border-gray-200 bg-white p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-gray-500">PA-Tage</p>
              <h3 class="text-base font-bold text-gray-900">
                {{ selectedDays.length }} ausgewaehlt / {{ days.length }} in der Vorschau
              </h3>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="exportingWord"
                @click="handleWordExport"
              >
                <i class="la la-file-word"></i>
                {{ exportingWord ? 'Exportiert...' : 'Word' }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-50"
                :disabled="exportingPdf || selectedDays.length === 0"
                @click="createSignedPdf"
              >
                <i class="la la-file-signature"></i>
                {{ exportingPdf ? 'Erstellt...' : 'PDF mit Unterschrift' }}
              </button>
              <button
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
                title="Entwurf von anderen Geraeten aktualisieren"
                @click="loadDraft({ silent: false })"
              >
                <i class="la la-cloud-download-alt"></i>
              </button>
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50"
                :disabled="draftSaving || draftLoading"
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

          <div v-if="days.length === 0" class="mt-4 rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            Keine PA-Tage angelegt.
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
                  <input v-model="day.selected" type="checkbox" class="rounded border-gray-300 text-zbb" />
                  <span>{{ dateLabel(day.date) }}</span>
                </label>
                <span class="rounded bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700">
                  {{ dayTypeLabel(day) }}
                </span>
              </div>

              <p class="mt-1 text-xs text-gray-500">
                {{ weekdayLabel(day.date) }} / {{ day.participants_count }} Teilnehmer /
                {{ signedCountForDay(day) }}/{{ sheetParticipants.length }} unterschrieben
              </p>

              <input
                v-model="day.note"
                type="text"
                class="mt-3 w-full rounded border-gray-300 text-xs"
                placeholder="Notiz"
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
                Potenzialanalyse mit digitalen Unterschriften
              </h3>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600">{{ sheetParticipants.length }} Teilnehmer / {{ selectedDays.length }} Tage</span>
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
                    <span class="block">Tag {{ index + 1 }}</span>
                    <span class="block font-normal">Datum: {{ dateLabel(day.date) }}</span>
                    <span class="block font-normal">{{ dayTypeLabel(day) }}</span>
                    <span class="block font-normal">Unterschrift Schueler/-in</span>
                    <span class="mt-1 block text-[10px] font-semibold text-emerald-700">
                      {{ signedCountForDay(day) }}/{{ sheetParticipants.length }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(participant, index) in sheetParticipants" :key="participant.person_id || participant.id">
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ index + 1 }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle font-medium">{{ participant.nachname }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ participant.vorname }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ participant.klasse }}</td>
                  <td
                    v-for="day in selectedDays"
                    :key="`${day.id}-${participant.person_id || participant.id}`"
                    class="border border-gray-800 p-1 align-middle"
                    :class="hasSignature(day, participant) ? 'bg-emerald-50' : 'bg-white'"
                  >
                    <div class="relative">
                      <span
                        v-if="hasSignature(day, participant)"
                        class="pointer-events-none absolute right-1 top-1 z-10 text-[11px] text-emerald-700"
                        title="Unterschrieben"
                      >
                        <i class="la la-check-circle"></i>
                      </span>
                      <SignatureBox
                        :model-value="signatures[signatureKey(day, participant)] || ''"
                        compact
                        @update:model-value="saveCompletedSignature(day, participant, $event)"
                        @cleared="removeSignature(day, participant)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <template #footer>
      <Button
        label="Schliessen"
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

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
})

const emit = defineEmits(['update:visible', 'close'])
const BibbSwal = Swal.mixin({
  customClass: {
    container: 'bibb-swal-container',
  },
})

const localVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value),
})

const form = reactive({
  exportFormat: 'A3',
  rolltagDate: '',
  startDate: '',
  endDate: '',
  includeSaturday: false,
  includeSunday: false,
  feedbackDate: '',
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
const signatures = reactive({})
const draftRevision = ref(0)
const draftSaving = ref(false)
const draftLoading = ref(false)
const draftLoaded = ref(false)
const draftHydrating = ref(false)
const draftDirty = ref(false)
const draftLastSavedAt = ref(null)
const sheetFullscreen = ref(false)
const bibbFooterImageSrc = '/img/bibb/logoleiste_bop_2020.jpg'
let bibbFooterImagePromise = null
let draftSaveTimer = null
let draftPollTimer = null
let previousBodyOverflow = ''

const selectedDays = computed(() => days.value.filter((day) => day.selected))
const selectedDay = computed(() => days.value.find((day) => day.id === selectedDayId.value) || selectedDays.value[0] || null)
const dayRows = computed(() => selectedDay.value ? participantsForDay(selectedDay.value) : [])
const programDays = computed(() => selectedDays.value.filter((day) => day.type !== 'feedback').slice(0, 10))
const feedbackDay = computed(() => {
  if (!form.feedbackDate) return null

  return {
    id: `feedback-${form.feedbackDate}`,
    date: form.feedbackDate,
    date_label: dateLabel(form.feedbackDate),
    type: 'feedback',
    type_label: 'Feedbackgespraech',
    source: 'feedback',
    selected: true,
    groups: [{
      id: `feedback-all-${form.feedbackDate}`,
      label: 'Alle Teilnehmer',
      bereich: null,
      runde: null,
      participants: allParticipants.value,
      participants_count: allParticipants.value.length,
    }],
    participants_count: allParticipants.value.length,
  }
})
const signatureDays = computed(() => [...programDays.value, ...(feedbackDay.value ? [feedbackDay.value] : [])])
const sheetParticipants = computed(() => {
  if (allParticipants.value.length) return allParticipants.value

  const byPerson = new Map()
  selectedDays.value.forEach((day) => {
    participantsForDay(day).forEach((participant) => {
      byPerson.set(participant.person_id || participant.id, participant)
    })
  })

  return Array.from(byPerson.values()).sort((a, b) => {
    const classCompare = String(a.klasse || '').localeCompare(String(b.klasse || ''), 'de', { numeric: true })
    if (classCompare !== 0) return classCompare
    return String(a.nachname || '').localeCompare(String(b.nachname || ''), 'de')
  })
})
const klasseText = computed(() => {
  if (previewContext.value?.klasse) return previewContext.value.klasse

  const values = [...new Set(sheetParticipants.value.map((participant) => participant.klasse).filter(Boolean))]
  return values.join(', ')
})
const schulformText = computed(() => previewContext.value?.schulform || '')
const bereicheText = computed(() => {
  if (previewContext.value?.bereiche) return previewContext.value.bereiche

  const values = []
  selectedDays.value.forEach((day) => {
    ;(day.groups || []).forEach((group) => {
      if (group.bereich && !values.includes(group.bereich)) values.push(group.bereich)
    })
  })
  return values.join('/ ')
})
const signatureCount = computed(() => Object.values(signatures).filter(Boolean).length)
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

const normalizeDayType = (type) => {
  if (type === 'rolltag') return 'Rolltag'
  if (type === 'group_day') return 'Gruppentag'
  if (type === 'program_day') return 'Programmtag'
  if (type === 'feedback') return 'Feedbackgespraech'
  return 'Manueller Tag'
}

const groupSubtitle = (group) => {
  const pieces = []
  if (group.runde) pieces.push(`Runde ${group.runde}`)
  if (group.bereich) pieces.push(group.bereich)
  return pieces.join(' / ')
}

const signatureKey = (day, participant) => `${day.id}:${participant.person_id || participant.id}`

function participantsForDay(day) {
  const rows = []

  ;(day.groups || []).forEach((group) => {
    ;(group.participants || []).forEach((participant) => {
      rows.push({
        ...participant,
        group_label: group.label,
        bereich: group.bereich,
        runde: group.runde,
      })
    })
  })

  return rows
}

const participantGroupText = (participant) => {
  const pieces = []
  if (participant.group_label) pieces.push(participant.group_label)
  if (participant.runde) pieces.push(`R${participant.runde}`)
  if (participant.bereich) pieces.push(participant.bereich)
  return pieces.join(' / ')
}

const participantCanSignDay = (participant, day) => {
  if (!day || day.type === 'rolltag' || day.type === 'program_day' || day.type === 'feedback' || day.source === 'manual' || day.source === 'auto') return true

  const participantId = participant.person_id || participant.id
  return participantsForDay(day).some((entry) => (entry.person_id || entry.id) === participantId)
}

const hasSignature = (day, participant) => Boolean(day && participant && signatures[signatureKey(day, participant)])

const expectedSignatureCountForDay = (day) => sheetParticipants.value
  .filter((participant) => participantCanSignDay(participant, day))
  .length

const signedCountForDay = (day) => sheetParticipants.value
  .filter((participant) => participantCanSignDay(participant, day) && hasSignature(day, participant))
  .length

const selectedProgramDaysPayload = () => programDays.value.map((day) => ({
  id: day.id,
  date: day.date,
  type: day.type,
  selected: day.selected,
  note: day.note || null,
}))

const manualDaysPayload = () => days.value
  .filter((day) => day.source === 'manual' && day.selected)
  .map((day) => ({
    date: day.date,
    type: day.type,
    note: day.note || null,
  }))

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

const draftScopePayload = () => ({
  schuleIdInputBibb: props.partnerId,
  schuljahrInputBibb: props.schuljahr,
  teilInputBibb: props.teil,
})

const cloneForDraft = (value) => JSON.parse(JSON.stringify(value ?? null))

const buildDraftPayload = () => ({
  version: 1,
  form: { ...form },
  days: cloneForDraft(days.value) || [],
  selectedDayId: selectedDayId.value,
  signatures: { ...signatures },
})

const signatureSnapshot = (signaturePayload = {}) => JSON.stringify(
  Object.entries(signaturePayload || {})
    .filter(([, value]) => Boolean(value))
    .sort(([left], [right]) => left.localeCompare(right))
)

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

const applyDraftPayload = (payload) => {
  if (!payload) return

  draftHydrating.value = true

  try {
    if (payload.form) {
      form.exportFormat = payload.form.exportFormat || form.exportFormat
      form.rolltagDate = payload.form.rolltagDate || ''
      form.startDate = payload.form.startDate || ''
      form.endDate = payload.form.endDate || ''
      form.includeSaturday = Boolean(payload.form.includeSaturday)
      form.includeSunday = Boolean(payload.form.includeSunday)
      form.feedbackDate = payload.form.feedbackDate || ''
    }

    if (Array.isArray(payload.days) && payload.days.length) {
      days.value = payload.days
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
  if (!props.partnerId || !props.schuljahr || !props.teil) return
  if (!silent) draftLoading.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.POBO.bibb.draft.show'), draftScopePayload())

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
    draftLoaded.value = true
  } catch (error) {
    if (!silent) {
      BibbSwal.fire('Fehler', await readBlobError(error), 'error')
    }
  } finally {
    draftLoading.value = false
  }
}

const saveDraft = async ({ silent = true, payload = null } = {}) => {
  if (!props.partnerId || !props.schuljahr || !props.teil) return

  const draftPayload = payload || buildDraftPayload()
  const requestSignatureSnapshot = signatureSnapshot(draftPayload.signatures)
  draftSaving.value = true

  try {
    const response = await axios.put(route('anwesenheitsliste.POBO.bibb.draft.store'), {
      ...draftScopePayload(),
      payload: draftPayload,
    })

    const signatureChangedDuringSave = signatureSnapshot(signatures) !== requestSignatureSnapshot
    if (response.data.payload && !signatureChangedDuringSave) applyDraftPayload(response.data.payload)
    draftRevision.value = response.data.revision || draftRevision.value
    draftLastSavedAt.value = response.data.updated_at || new Date().toISOString()
    draftLoaded.value = true
    draftDirty.value = signatureChangedDuringSave
  } catch (error) {
    if (!silent) {
      BibbSwal.fire('Fehler', await readBlobError(error), 'error')
    }
  } finally {
    draftSaving.value = false
  }
}

const removeSignature = (day, participant) => {
  if (!day || !participant) return

  const key = signatureKey(day, participant)
  delete signatures[key]
  draftDirty.value = true
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = null

  const payload = buildDraftPayload()
  payload.signatures[key] = ''
  saveDraft({ silent: true, payload })
}

const scheduleDraftSave = () => {
  if (!draftLoaded.value || draftHydrating.value || !previewContext.value) return

  draftDirty.value = true
  window.clearTimeout(draftSaveTimer)
  draftSaveTimer = window.setTimeout(() => {
    draftSaveTimer = null
    saveDraft({ silent: true })
  }, 900)
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
  }, 12000)
}

const stopDraftTimers = () => {
  window.clearTimeout(draftSaveTimer)
  window.clearInterval(draftPollTimer)
  draftSaveTimer = null
  draftPollTimer = null
}

const closeSheetFullscreen = () => {
  sheetFullscreen.value = false
}

const toggleSheetFullscreen = () => {
  sheetFullscreen.value = !sheetFullscreen.value
}

const handleSheetFullscreenKeydown = (event) => {
  if (event.key === 'Escape') {
    closeSheetFullscreen()
  }
}

const clearDraft = async () => {
  const result = await BibbSwal.fire({
    title: 'Entwurf leeren?',
    text: 'Der zentrale Zwischenstand dieser Anwesenheitsliste wird geloescht.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ja, leeren',
    cancelButtonText: 'Abbrechen',
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(route('anwesenheitsliste.POBO.bibb.draft.destroy'), {
      data: draftScopePayload(),
    })

    draftHydrating.value = true
    syncSignatures({}, { removeMissing: true })
    draftHydrating.value = false
    draftDirty.value = false
    draftRevision.value = 0
    draftLastSavedAt.value = null
    BibbSwal.fire('Geloescht', 'Der zentrale Entwurf wurde geleert.', 'success')
  } catch (error) {
    BibbSwal.fire('Fehler', await readBlobError(error), 'error')
  }
}

const loadBibbFooterImage = () => {
  if (!bibbFooterImagePromise) {
    bibbFooterImagePromise = new Promise((resolve, reject) => {
      const image = new Image()
      image.onload = () => resolve(image)
      image.onerror = reject
      image.src = bibbFooterImageSrc
    })
  }

  return bibbFooterImagePromise
}

const pdfFormat = () => form.exportFormat === 'A3' ? 'a3' : 'a4'

const pdfLayout = (doc) => {
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const widthScale = pageWidth / 297
  const rowScale = form.exportFormat === 'A3' ? widthScale : 1
  const tableX = 7 * widthScale

  return {
    pageWidth,
    pageHeight,
    widthScale,
    rowScale,
    tableX,
    headerX: tableX,
    headerTitleY: form.exportFormat === 'A3' ? 16 : 11,
    headerFirstRowY: form.exportFormat === 'A3' ? 24 : 18,
    headerRowGap: 5.6,
    headerPageX: form.exportFormat === 'A3' ? tableX + 225 : tableX + 205,
    headerLabelWidth: 24.92,
    headerValueWidth: 75,
    headerSecondLabelWidth: 50,
    headerSecondValueWidth: 96.45,
    tableWidth: 281 * widthScale,
    tableY: form.exportFormat === 'A3' ? 54 : 50,
    tableHeadTopHeight: 9.4 * rowScale,
    tableHeadBottomHeight: 5.8 * rowScale,
    rowHeight: 7.2 * rowScale,
    rowsPerPage: form.exportFormat === 'A3' ? 17 : 13,
    footerWidth: 141.7,
    footerHeight: 24.9,
    footerBottom: 6,
  }
}

const ensureManualDayGroups = (day) => ({
  ...day,
  groups: day.groups?.length ? day.groups : [{
    id: `manual-all-${day.date}`,
    label: 'Alle Teilnehmer',
    bereich: null,
    runde: null,
    participants: allParticipants.value,
    participants_count: allParticipants.value.length,
  }],
  participants_count: day.participants_count || allParticipants.value.length,
})

const rangeDates = (startValue, endValue, { excludeFeedback = true } = {}) => {
  if (!startValue || !endValue) return []

  const start = new Date(`${startValue}T00:00:00`)
  const end = new Date(`${endValue}T00:00:00`)

  if (end < start) {
    return []
  }

  const values = []

  for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
    const weekday = date.getDay()
    if (weekday === 6 && !form.includeSaturday) continue
    if (weekday === 0 && !form.includeSunday) continue

    const value = toDateInput(date)
    if (excludeFeedback && form.feedbackDate && value === form.feedbackDate) continue

    values.push(value)
  }

  return values
}

const appendProgramDays = (dates, source = 'auto') => {
  const existing = new Set(days.value.map((day) => day.date))
  const generated = dates
    .filter((date) => !existing.has(date))
    .map((date) => ensureManualDayGroups({
      id: `${source}-${date}`,
      date,
      date_label: dateLabel(date),
      type: 'program_day',
      type_label: 'Programmtag',
      source,
      selected: true,
      note: '',
    }))

  if (!generated.length) return

  days.value = [...days.value, ...generated].sort((a, b) => a.date.localeCompare(b.date))
}

const syncAutoProgramDays = () => {
  const start = form.startDate || form.rolltagDate
  const end = form.endDate || form.feedbackDate
  if (!start || !end) return

  appendProgramDays(rangeDates(start, end), 'auto')
}

const createRangeDays = () => {
  const start = form.startDate || form.rolltagDate
  const end = form.endDate || form.feedbackDate

  if (!start || !end) {
    BibbSwal.fire('Zeitraum fehlt', 'Bitte Anfangs- und Enddatum oder Rolltag und Feedbackgespraech waehlen.', 'warning')
    return
  }

  if (new Date(`${end}T00:00:00`) < new Date(`${start}T00:00:00`)) {
    BibbSwal.fire('Datum pruefen', 'Das Enddatum muss nach dem Anfangsdatum liegen.', 'warning')
    return
  }

  appendProgramDays(rangeDates(start, end), 'manual')
}

const addManualDay = () => {
  if (!manualDate.value) return

  const exists = days.value.some((day) => day.date === manualDate.value)
  if (exists) {
    BibbSwal.fire('Datum vorhanden', 'Dieser Tag ist bereits in der Vorschau.', 'info')
    return
  }

  days.value.push(ensureManualDayGroups({
    id: `manual-${manualDate.value}`,
    date: manualDate.value,
    date_label: dateLabel(manualDate.value),
    type: 'program_day',
    type_label: 'Programmtag',
    source: 'manual',
    selected: true,
    note: manualNote.value,
  }))

  days.value.sort((a, b) => a.date.localeCompare(b.date))
  manualDate.value = ''
  manualNote.value = ''
}
const hydrateDays = (payloadDays) => {
  const previous = new Map(days.value.map((day) => [day.id, day]))

  days.value = (payloadDays || []).map((day) => {
    const old = previous.get(day.id)
    return {
      ...day,
      selected: old?.selected ?? true,
      note: old?.note ?? day.note ?? '',
    }
  })

  if (!selectedDayId.value || !days.value.some((day) => day.id === selectedDayId.value)) {
    selectedDayId.value = selectedDays.value[0]?.id || days.value[0]?.id || null
  }
}

const loadPreview = async ({ includeDraft = false } = {}) => {
  loadingPreview.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.POBO.bibb.preview'), {
      schuleIdInputBibb: props.partnerId,
      schuljahrInputBibb: props.schuljahr,
      teilInputBibb: props.teil,
      rolltagDate: form.rolltagDate || null,
      manualDays: manualDaysPayload(),
    })

    previewContext.value = response.data.context
    allParticipants.value = response.data.participants || []
    hydrateDays(response.data.days || [])
    syncAutoProgramDays()
    if (includeDraft) await loadDraft({ silent: true })
  } catch (error) {
    BibbSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    loadingPreview.value = false
  }
}

const handleWordExport = async () => {
  if (selectedDays.value.length === 0) {
    BibbSwal.fire('Keine Tage', 'Bitte mindestens einen Tag auswaehlen.', 'warning')
    return
  }

  exportingWord.value = true

  try {
    const response = await axios.post(route('anwesenheitsliste.POBO.bibb.export.word'), {
      exportFormat: form.exportFormat,
      schuleIdInputBibb: props.partnerId,
      schuljahrInputBibb: props.schuljahr,
      teilInputBibb: props.teil,
      feedbackDate: form.feedbackDate || null,
      days: selectedProgramDaysPayload(),
    }, { responseType: 'blob' })

    const disposition = response.headers['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] || 'Anwesenheitsliste_BIBB.docx'
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    BibbSwal.fire('Fehler', await readBlobError(error), 'error')
  } finally {
    exportingWord.value = false
  }
}

const drawOriginalHeader = (doc, pageNumber, totalPages, layout) => {
  const school = previewContext.value?.schule?.name || 'Schule'
  const x0 = layout.headerX
  const x1 = x0 + layout.headerLabelWidth
  const x2 = x1 + layout.headerValueWidth
  const x3 = x2 + layout.headerSecondLabelWidth
  const rowY = (index) => layout.headerFirstRowY + (index * layout.headerRowGap)
  const periodText = [
    programDays.value[0] ? dateLabel(programDays.value[0].date) : '',
    programDays.value.at(-1) ? dateLabel(programDays.value.at(-1).date) : '',
  ].filter(Boolean).join(' - ')

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(7)
  doc.text('Unterschriftenliste zum Nachweis der praxisorientierten Berufsorientierung - BO-Tage/ Ausbilder/-innen', x0, layout.headerTitleY)
  doc.text(`Seite ${pageNumber} von ${totalPages}`, layout.headerPageX, layout.headerTitleY)

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(6.5)
  doc.text('Schule:', x0, rowY(0))
  doc.text(school, x1, rowY(0), { maxWidth: layout.headerValueWidth - 2 })
  doc.text('Schulform:', x0, rowY(2))
  doc.text(schulformText.value || '', x1, rowY(2), { maxWidth: layout.headerValueWidth - 2 })
  doc.text('Klasse/n:', x0, rowY(3))
  doc.text(klasseText.value || '', x1, rowY(3), { maxWidth: layout.headerValueWidth - 2 })
  doc.text('Berufsfelder:', x0, rowY(4))
  doc.text(bereicheText.value || '', x1, rowY(4), { maxWidth: layout.headerValueWidth - 2 })

  doc.text('Zuwendungsempfänger/', x2, rowY(0))
  doc.text('- ZBB -', x3, rowY(0), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('Ausführende Stelle:', x2, rowY(1))
  doc.text('Zentrum für Bildung und Beruf Saar gGmbH in Burbach', x3, rowY(1), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('AZ/:', x2, rowY(2))
  doc.text('4.5-3444-10/0004', x3, rowY(2), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('Zeitraum vom:', x2, rowY(3))
  doc.text(periodText, x3, rowY(3), { maxWidth: layout.headerSecondValueWidth - 2 })
  doc.text('PA durchgeführt:', x2, rowY(4))
  doc.text('Ja', x3, rowY(4), { maxWidth: layout.headerSecondValueWidth - 2 })
}

const originalColumns = (layout) => {
  const sw = (value) => value * layout.widthScale
  const tableWidth = layout.tableWidth
  const staticWidth = sw(6 + 22 + 20 + 15 + 21 + 11 + 11)
  const termCount = Math.max(programDays.value.length, 1)
  const termWidth = Math.max(sw(13), (tableWidth - staticWidth) / termCount)
  const columns = [
    { key: 'nr', label: 'Nr.', width: sw(6) },
    { key: 'nachname', label: 'Name', width: sw(22) },
    { key: 'vorname', label: 'Vorname', width: sw(20) },
    { key: 'klasse', label: 'Klasse', width: sw(15) },
  ]

  programDays.value.forEach((day, index) => {
    columns.push({
      key: day.id,
      day,
      label: `Termin ${index + 1}`,
      width: termWidth,
    })
  })

  columns.push({ key: 'feedback', day: feedbackDay.value, label: 'Termin 11\nFeedbackgespräch', width: sw(21) })
  columns.push({ key: 'angebot', label: 'Ange-\nbotstag', width: sw(11) })
  columns.push({ key: 'zertifikat', label: 'Zertifikat\nja/nein', width: sw(11) })

  return columns
}

const drawOriginalTableHeader = (doc, columns, x, y, layout) => {
  let cursorX = x
  const pad = Math.max(1, layout.widthScale)
  const topHeight = layout.tableHeadTopHeight
  const bottomHeight = layout.tableHeadBottomHeight
  doc.setDrawColor(0, 0, 0)
  doc.setLineWidth(0.1)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(5.3)

  columns.forEach((column) => {
    doc.rect(cursorX, y, column.width, topHeight)
    if (column.day) {
      doc.text(column.label, cursorX + pad, y + (2.4 * layout.rowScale))
      doc.text('Datum:', cursorX + pad, y + (4.4 * layout.rowScale))
      doc.text(dateLabel(column.day.date), cursorX + pad, y + (6.5 * layout.rowScale), { maxWidth: column.width - (2 * pad) })
    } else if (column.key === 'feedback') {
      doc.text('Termin 11', cursorX + pad, y + (2.4 * layout.rowScale))
      doc.text('Feedback-', cursorX + pad, y + (4.4 * layout.rowScale))
      doc.text('gespräch', cursorX + pad, y + (6.5 * layout.rowScale))
      if (column.day?.date) {
        doc.text(dateLabel(column.day.date), cursorX + pad, y + (8.5 * layout.rowScale), { maxWidth: column.width - (2 * pad) })
      }
    }
    cursorX += column.width
  })

  cursorX = x
  columns.forEach((column) => {
    doc.rect(cursorX, y + topHeight, column.width, bottomHeight)
    if (column.day) {
      doc.text('Unterschrift', cursorX + pad, y + topHeight + (2.2 * layout.rowScale))
      doc.text('Schüler/-in', cursorX + pad, y + topHeight + (4.1 * layout.rowScale))
    } else {
      const lines = String(column.label).split('\n')
      lines.forEach((line, index) => doc.text(line, cursorX + pad, y + topHeight + (2.2 * layout.rowScale) + (index * 1.9 * layout.rowScale)))
    }
    cursorX += column.width
  })
}

const drawOriginalFooter = (doc, footerImage, layout) => {
  const x = (layout.pageWidth - layout.footerWidth) / 2
  const y = layout.pageHeight - layout.footerHeight - layout.footerBottom
  doc.addImage(footerImage, 'JPEG', x, y, layout.footerWidth, layout.footerHeight)
  doc.setTextColor(17, 24, 39)
}

const createSignedPdf = async () => {
  if (selectedDays.value.length === 0) {
    BibbSwal.fire('Keine Tage', 'Bitte mindestens einen Tag auswaehlen.', 'warning')
    return
  }

  exportingPdf.value = true

  try {
    const footerImage = await loadBibbFooterImage()
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: pdfFormat() })
    const layout = pdfLayout(doc)
    const rows = sheetParticipants.value
    const rowsPerPage = layout.rowsPerPage
    const totalPages = Math.max(1, Math.ceil(rows.length / rowsPerPage))
    const columns = originalColumns(layout)

    for (let page = 1; page <= totalPages; page++) {
      if (page > 1) doc.addPage()

      drawOriginalHeader(doc, page, totalPages, layout)
      drawOriginalTableHeader(doc, columns, layout.tableX, layout.tableY, layout)

      Array.from({ length: rowsPerPage }).forEach((_, index) => {
        const absoluteIndex = ((page - 1) * rowsPerPage) + index
        const participant = rows[absoluteIndex] || null
        const y = layout.tableY + layout.tableHeadTopHeight + layout.tableHeadBottomHeight + (index * layout.rowHeight)
        let cursorX = layout.tableX

        columns.forEach((column) => {
          doc.setDrawColor(0, 0, 0)
          doc.rect(cursorX, y, column.width, layout.rowHeight)
          doc.setFont('helvetica', 'normal')
          doc.setFontSize(5.3)
          const pad = Math.max(1, layout.widthScale)
          const textY = y + (4.7 * layout.rowScale)

          if (column.key === 'nr') {
            doc.text(String(absoluteIndex + 1), cursorX + pad, textY)
          } else if (column.key === 'nachname') {
            doc.text(String(participant?.nachname || ''), cursorX + pad, textY, { maxWidth: column.width - (2 * pad) })
          } else if (column.key === 'vorname') {
            doc.text(String(participant?.vorname || ''), cursorX + pad, textY, { maxWidth: column.width - (2 * pad) })
          } else if (column.key === 'klasse') {
            doc.text(String(participant?.klasse || ''), cursorX + pad, textY, { maxWidth: column.width - (2 * pad) })
          } else if (column.day && participant) {
            const key = signatureKey(column.day, participant)
            const signature = signatures[key]
            if (signature) {
              doc.addImage(signature, 'PNG', cursorX + pad, y + (0.8 * layout.rowScale), column.width - (2 * pad), layout.rowHeight - (1.4 * layout.rowScale))
            }
          }

          cursorX += column.width
        })
      })

      drawOriginalFooter(doc, footerImage, layout)
    }

    const school = previewContext.value?.schule?.name || 'Schule'
    const safeSchool = school.replace(/[^A-Za-z0-9_\-.]+/g, '_')
    doc.save(`Anwesenheitsliste_BIBB_${safeSchool}_${props.schuljahr}_Teil_${props.teil}.pdf`)
  } catch (error) {
    BibbSwal.fire('Fehler', 'Das PDF konnte nicht erstellt werden.', 'error')
  } finally {
    exportingPdf.value = false
  }
}

const resetState = () => {
  stopDraftTimers()
  closeSheetFullscreen()
  draftHydrating.value = true
  draftLoaded.value = false
  draftDirty.value = false
  form.exportFormat = 'A3'
  form.rolltagDate = ''
  form.startDate = ''
  form.endDate = ''
  form.includeSaturday = false
  form.includeSunday = false
  form.feedbackDate = ''
  previewContext.value = null
  allParticipants.value = []
  days.value = []
  selectedDayId.value = null
  manualDate.value = ''
  manualNote.value = ''
  Object.keys(signatures).forEach((key) => delete signatures[key])
  draftRevision.value = 0
  draftSaving.value = false
  draftLoading.value = false
  draftHydrating.value = false
  draftLastSavedAt.value = null
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
watch(signatures, scheduleDraftSave, { deep: true })
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
    header="Anwesenheitsliste BIBB"
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
              <option value="A3">A3</option>
              <option value="A4">A4</option>
            </select>
          </label>

          <label class="text-sm font-semibold text-gray-700">
            <span class="mb-1 block">Rolltag</span>
            <input v-model="form.rolltagDate" type="date" class="w-full rounded border-gray-300 text-sm" />
          </label>
        </div>

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

        <label class="block text-sm font-semibold text-gray-700">
          <span class="mb-1 block">Feedbackgespraech</span>
          <input v-model="form.feedbackDate" type="date" class="w-full rounded border-gray-300 text-sm" />
        </label>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-zbb/90 disabled:opacity-50"
          :disabled="loadingPreview"
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
              <p class="text-xs font-semibold uppercase text-gray-500">Tage</p>
              <h3 class="text-base font-bold text-gray-900">
                {{ selectedDays.length }} ausgewaehlt / {{ days.length }} in der Vorschau
              </h3>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                :disabled="exportingWord || selectedDays.length === 0"
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
          </p>

          <div v-if="days.length === 0" class="mt-4 rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            Keine Tage gefunden.
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
                  {{ normalizeDayType(day.type) }}
                </span>
              </div>

              <p class="mt-1 text-xs text-gray-500">
                {{ weekdayLabel(day.date) }} / {{ day.participants_count }} Teilnehmer /
                {{ signedCountForDay(day) }}/{{ expectedSignatureCountForDay(day) }} unterschrieben
              </p>

              <div class="mt-3 space-y-1">
                <div v-for="group in day.groups" :key="group.id" class="rounded bg-gray-50 px-2 py-1">
                  <p class="text-xs font-semibold text-gray-800">
                    {{ group.label }}
                    <span v-if="group.bereich">, Bereich {{ group.bereich }}</span>
                  </p>
                  <p class="text-[11px] text-gray-500">
                    {{ groupSubtitle(group) || 'Gemeinsame Liste' }} / {{ group.participants_count }} TN
                  </p>
                </div>
              </div>

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

        <div v-if="programDays.length" :class="sheetSectionClass">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-gray-500">Digitales Original-Blatt</p>
              <h3 class="text-base font-bold text-gray-900">
                Unterschriften direkt in den Termin-Spalten
              </h3>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600">{{ sheetParticipants.length }} Teilnehmer / {{ programDays.length }} Termine</span>
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
            <table class="min-w-[1180px] border-collapse text-[11px]">
              <thead class="sticky top-0 z-10 bg-white">
                <tr>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Nr.</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Name</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Vorname</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Klasse</th>
                  <th
                    v-for="(day, index) in programDays"
                    :key="`head-${day.id}`"
                    class="min-w-[118px] border border-gray-800 px-2 py-2 text-left align-top font-semibold"
                  >
                    <span class="block">Termin {{ index + 1 }}</span>
                    <span class="block font-normal">Datum: {{ dateLabel(day.date) }}</span>
                    <span class="block font-normal">{{ normalizeDayType(day.type) }}</span>
                    <span class="mt-1 block text-[10px] font-semibold text-emerald-700">
                      {{ signedCountForDay(day) }}/{{ expectedSignatureCountForDay(day) }}
                    </span>
                  </th>
                  <th class="min-w-[110px] border border-gray-800 px-2 py-2 text-left align-top font-semibold">
                    <span class="block">Termin 11</span>
                    <span class="block font-normal">Feedbackgespräch</span>
                    <span class="block font-normal">Datum: {{ form.feedbackDate ? dateLabel(form.feedbackDate) : '-' }}</span>
                    <span v-if="feedbackDay" class="mt-1 block text-[10px] font-semibold text-emerald-700">
                      {{ signedCountForDay(feedbackDay) }}/{{ expectedSignatureCountForDay(feedbackDay) }}
                    </span>
                  </th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Angebotstag</th>
                  <th class="border border-gray-800 px-2 py-2 text-left font-semibold">Zertifikat</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(participant, index) in sheetParticipants" :key="participant.person_id || participant.id">
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ index + 1 }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle font-medium">{{ participant.nachname }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ participant.vorname }}</td>
                  <td class="border border-gray-800 px-2 py-2 align-middle">{{ participant.klasse }}</td>
                  <td
                    v-for="day in programDays"
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
                        v-if="participantCanSignDay(participant, day)"
                        v-model="signatures[signatureKey(day, participant)]"
                        compact
                        @cleared="removeSignature(day, participant)"
                      />
                      <div v-else class="h-10 min-w-[92px] rounded bg-gray-100"></div>
                    </div>
                  </td>
                  <td
                    class="border border-gray-800 p-1 align-middle"
                    :class="feedbackDay && hasSignature(feedbackDay, participant) ? 'bg-emerald-50' : 'bg-white'"
                  >
                    <div class="relative">
                      <span
                        v-if="feedbackDay && hasSignature(feedbackDay, participant)"
                        class="pointer-events-none absolute right-1 top-1 z-10 text-[11px] text-emerald-700"
                        title="Unterschrieben"
                      >
                        <i class="la la-check-circle"></i>
                      </span>
                      <SignatureBox
                        v-if="feedbackDay"
                        v-model="signatures[signatureKey(feedbackDay, participant)]"
                        compact
                        @cleared="removeSignature(feedbackDay, participant)"
                      />
                    </div>
                  </td>
                  <td class="border border-gray-800 p-1 align-middle"></td>
                  <td class="border border-gray-800 p-1 align-middle"></td>
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
.bibb-swal-container {
  z-index: 13000 !important;
}
</style>

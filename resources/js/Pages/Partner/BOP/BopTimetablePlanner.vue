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
const exportBusy = ref(false)
const error = ref('')
const success = ref('')
const options = ref({ areas: [], supervisors: [] })
const workshopDates = ref([])
const timetables = ref([])
const breakDefaults = ref([])
const preview = ref(null)
const selectedArea = ref(null)
const timelineExportRef = ref(null)
const form = ref(emptyForm())

function emptyForm() {
  return {
    schedule_date: '', start_time: '09:00', end_time: '15:00', slot_minutes: 1,
    group_count: 4, areas: [], events: [], area_orders: {},
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
    breakDefaults.value = response.data.run?.break_defaults || []

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
        events: breakDefaults.value.map(normaliseBreakDefault),
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
    area_orders: Object.fromEntries(Object.entries(config.area_orders || {}).map(([group, areaIds]) => [
      group, areaIds.map(Number),
    ])),
  }
  selectedArea.value = null
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

function normaliseBreakDefault(event) {
  return {
    title: event.title || 'Pause',
    type: 'break',
    group_scope: event.group_scope || 'all',
    start_time: String(event.start_time || '').slice(0, 5),
    end_time: String(event.end_time || '').slice(0, 5),
  }
}

function eventGroups(event) {
  const labels = groups()
  const halfSize = Math.ceil(labels.length / 2)
  if (event.group_scope === 'first_half') return labels.slice(0, halfSize)
  if (event.group_scope === 'second_half') return labels.slice(halfSize)
  return labels
}

function groupBreakCount(group) {
  return form.value.events.filter((event) => event.type === 'break' && eventGroups(event).includes(group)).length
}

function everyGroupHasTwoBreaks() {
  return groups().every((group) => groupBreakCount(group) === 2)
}

function applyBreakDefaults() {
  if (!breakDefaults.value.length) return
  form.value.events = [
    ...form.value.events.filter((event) => event.type !== 'break'),
    ...breakDefaults.value.map(normaliseBreakDefault),
  ]
  preview.value = null
  success.value = 'Die gespeicherten Standardpausen wurden übernommen.'
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
  form.value.area_orders = {}
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
    area_orders: form.value.area_orders || {},
    persist,
  }
}

async function generate(persist = false) {
  if (!everyGroupHasTwoBreaks()) {
    error.value = 'Bitte für jede Gruppe genau zwei Pausen festlegen.'
    success.value = ''
    return false
  }
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await axios.post(route('bop.run.timetable.generate', { partner: props.partnerId }), requestPayload(persist))
    preview.value = response.data.timetable
    success.value = response.data.message
    if (persist) {
      breakDefaults.value = response.data.break_defaults || (response.data.timetable.config?.events || [])
        .filter((event) => event.type === 'break')
        .map(normaliseBreakDefault)
      timetables.value = [
        ...timetables.value.filter((item) => dateValue(item.schedule_date) !== form.value.schedule_date),
        response.data.timetable,
      ].sort((left, right) => dateValue(left.schedule_date).localeCompare(dateValue(right.schedule_date)))
      if (!workshopDates.value.includes(form.value.schedule_date)) workshopDates.value.push(form.value.schedule_date)
      emit('saved', response.data)
    }
    return true
  } catch (exception) {
    const validation = exception.response?.data?.errors
    error.value = validation ? Object.values(validation)[0]?.[0] : (exception.response?.data?.message || 'Der Zeitplan konnte nicht erzeugt werden.')
    return false
  } finally {
    busy.value = false
  }
}

function currentAreaOrder(group) {
  const configured = preview.value?.config?.area_orders?.[group]
  if (Array.isArray(configured) && configured.length) return configured.map(Number)
  const row = rows().find((item) => item.group === group)
  return [...new Set((row?.entries || []).filter((entry) => entry.type === 'area').map((entry) => Number(entry.bereich_id)))]
}

function isSelectedArea(group, entry) {
  return entry.type === 'area'
    && selectedArea.value?.group === group
    && Number(selectedArea.value?.bereich_id) === Number(entry.bereich_id)
}

async function selectAreaForSwap(group, entry) {
  if (entry.type !== 'area' || busy.value) return
  const areaId = Number(entry.bereich_id)
  if (!selectedArea.value) {
    selectedArea.value = { group, bereich_id: areaId, title: entry.title }
    success.value = `${entry.title} in ${group} ausgewählt. Jetzt den zweiten Bereich anklicken.`
    error.value = ''
    return
  }
  if (selectedArea.value.group !== group) {
    error.value = `Bitte den zweiten Bereich ebenfalls in ${selectedArea.value.group} auswählen.`
    return
  }
  if (Number(selectedArea.value.bereich_id) === areaId) {
    selectedArea.value = null
    success.value = ''
    return
  }

  const oldPreview = preview.value
  const oldOrders = Object.fromEntries(Object.entries(form.value.area_orders || {}).map(([key, ids]) => [key, [...ids]]))
  const nextOrders = Object.fromEntries((preview.value?.config?.groups || groups()).map((label) => [
    label, [...currentAreaOrder(label)],
  ]))
  const order = nextOrders[group]
  const firstIndex = order.indexOf(Number(selectedArea.value.bereich_id))
  const secondIndex = order.indexOf(areaId)
  ;[order[firstIndex], order[secondIndex]] = [order[secondIndex], order[firstIndex]]
  form.value.area_orders = nextOrders
  selectedArea.value = null

  if (!await generate(false)) {
    form.value.area_orders = oldOrders
    preview.value = oldPreview
  } else {
    success.value = 'Die beiden Bereiche wurden getauscht und der Zeitplan wurde neu geprüft.'
  }
}

async function resetAreaOrders() {
  form.value.area_orders = {}
  selectedArea.value = null
  await generate(false)
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

const timelinePixelsPerMinute = 5
const timelineGroupWidth = 72

function timeMinutes(value) {
  const [hours, minutes] = String(value || '00:00').slice(0, 5).split(':').map(Number)
  return (hours * 60) + minutes
}

function timelineStart() {
  return timeMinutes(preview.value?.config?.start_time || form.value.start_time)
}

function timelineEnd() {
  return timeMinutes(preview.value?.config?.end_time || form.value.end_time)
}

function timelineWidth() {
  return Math.max(1, timelineEnd() - timelineStart()) * timelinePixelsPerMinute
}

function timelineMarks() {
  const start = timelineStart()
  const end = timelineEnd()
  const marks = []
  for (let minute = start; minute <= end; minute += 15) {
    marks.push({ minute, label: `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}` })
  }
  if (marks.at(-1)?.minute !== end) {
    marks.push({ minute: end, label: `${String(Math.floor(end / 60)).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}` })
  }
  return marks.map((mark) => ({
    ...mark,
    offset: (mark.minute - start) * timelinePixelsPerMinute,
    isEnd: mark.minute === end,
  }))
}

function timelineRowStyle() {
  const stepWidth = 15 * timelinePixelsPerMinute
  return {
    width: `${timelineWidth()}px`,
    backgroundImage: `repeating-linear-gradient(to right, transparent 0, transparent ${stepWidth - 1}px, #d1d5db ${stepWidth - 1}px, #d1d5db ${stepWidth}px)`,
  }
}

function timelineEntryStyle(entry) {
  const start = timeMinutes(entry.start_time)
  const end = timeMinutes(entry.end_time)
  return {
    left: `${Math.max(0, start - timelineStart()) * timelinePixelsPerMinute + 1}px`,
    width: `${Math.max(3, (end - start) * timelinePixelsPerMinute - 2)}px`,
  }
}

function timelineEntryClass(entry) {
  if (entry.type === 'break') return 'border-slate-500 bg-slate-200 text-slate-900'
  if (entry.type === 'shared') return 'border-blue-500 bg-blue-100 text-blue-950'
  if (entry.type === 'extra') return 'border-violet-500 bg-violet-100 text-violet-950'
  const colors = [
    'border-orange-500 bg-orange-100 text-orange-950',
    'border-emerald-500 bg-emerald-100 text-emerald-950',
    'border-cyan-500 bg-cyan-100 text-cyan-950',
    'border-amber-500 bg-amber-100 text-amber-950',
    'border-rose-500 bg-rose-100 text-rose-950',
  ]
  return colors[Math.abs(Number(entry.bereich_id || 0)) % colors.length]
}

function entryDuration(entry) {
  return Math.max(0, timeMinutes(entry.end_time) - timeMinutes(entry.start_time))
}

function entryTooltip(entry) {
  const supervisor = entry.meta?.supervisor_name ? ` · ${entry.meta.supervisor_name}` : ''
  const action = entry.type === 'area' ? ' · Zum Tauschen anklicken' : ''
  return `${String(entry.start_time).slice(0, 5)}–${String(entry.end_time).slice(0, 5)} · ${entry.title} · ${typeLabel(entry.type)}${supervisor}${action}`
}

function exportFileName(extension) {
  const school = String(props.schoolName || 'Schule').replace(/[^a-zA-Z0-9äöüÄÖÜß_-]+/g, '_')
  const date = dateValue(form.value.schedule_date) || 'Zeitplan'
  return `Zeitplan_${school}_${date}.${extension}`
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

async function exportExcel() {
  if (!preview.value || exportBusy.value) return
  exportBusy.value = true
  error.value = ''
  try {
    const response = await axios.post(route('bop.run.timetable.export.excel', { partner: props.partnerId }), {
      schedule_date: dateValue(form.value.schedule_date),
      config: preview.value.config,
      entries: preview.value.entries || [],
    }, { responseType: 'blob' })
    downloadBlob(response.data, exportFileName('xlsx'))
    success.value = 'Der farbige Zeitplan wurde als Excel-Datei im Querformat exportiert.'
  } catch (exception) {
    error.value = 'Der Excel-Export konnte nicht erstellt werden.'
  } finally {
    exportBusy.value = false
  }
}

function pdfEntryFill(entry) {
  if (entry.type === 'break') return [226, 232, 240]
  if (entry.type === 'shared') return [219, 234, 254]
  if (entry.type === 'extra') return [237, 233, 254]
  return [
    [255, 237, 213], [209, 250, 229], [207, 250, 254], [254, 243, 199], [255, 228, 230],
  ][Math.abs(Number(entry.bereich_id || 0)) % 5]
}

function addPdfDetailPages(pdf) {
  const margin = 8
  const pageWidth = pdf.internal.pageSize.getWidth()
  const pageHeight = pdf.internal.pageSize.getHeight()
  const columns = [18, 38, 142, 28, 24, pageWidth - (margin * 2) - 250]
  const headings = ['Gruppe', 'Von–Bis', 'Aktivität', 'Art', 'Min.', 'Anleiter']
  let y = 0

  const startPage = () => {
    pdf.addPage('a3', 'landscape')
    pdf.setTextColor(17, 24, 39)
    pdf.setFont('helvetica', 'bold')
    pdf.setFontSize(13)
    pdf.text(`Detailübersicht · ${props.schoolName || ''} · ${dateLabel(form.value.schedule_date)}`, margin, margin + 4)
    y = margin + 10
    let x = margin
    pdf.setFillColor(251, 191, 36)
    pdf.setFontSize(8)
    headings.forEach((heading, index) => {
      pdf.rect(x, y, columns[index], 8, 'FD')
      pdf.text(heading, x + 1.5, y + 5.2)
      x += columns[index]
    })
    y += 8
  }

  startPage()
  rows().forEach((row) => {
    row.entries.forEach((entry) => {
      const values = [
        [row.group],
        [`${String(entry.start_time).slice(0, 5)}–${String(entry.end_time).slice(0, 5)}`],
        pdf.splitTextToSize(String(entry.title || ''), columns[2] - 3),
        [typeLabel(entry.type)],
        [String(entryDuration(entry))],
        pdf.splitTextToSize(String(entry.meta?.supervisor_name || '–'), columns[5] - 3),
      ]
      const lineCount = Math.max(...values.map((value) => value.length))
      const rowHeight = Math.max(9, (lineCount * 3.5) + 3)
      if (y + rowHeight > pageHeight - margin) startPage()

      let x = margin
      const fill = pdfEntryFill(entry)
      pdf.setFillColor(...fill)
      pdf.setFont('helvetica', 'normal')
      pdf.setFontSize(7.5)
      values.forEach((value, index) => {
        pdf.rect(x, y, columns[index], rowHeight, 'FD')
        pdf.text(value, x + 1.5, y + 4.3)
        x += columns[index]
      })
      y += rowHeight
    })
  })
}

async function exportPdf() {
  if (!timelineExportRef.value || exportBusy.value) return
  exportBusy.value = true
  error.value = ''
  const element = timelineExportRef.value
  const scrollContainer = element.parentElement
  const previousScrollLeft = scrollContainer?.scrollLeft || 0
  try {
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])
    if (scrollContainer) scrollContainer.scrollLeft = 0
    element.classList.add('timeline-export-pdf')
    await new Promise((resolve) => requestAnimationFrame(resolve))
    const canvas = await html2canvas(element, {
      scale: 2,
      backgroundColor: '#ffffff',
      width: element.scrollWidth,
      height: element.scrollHeight,
      windowWidth: element.scrollWidth,
      logging: false,
    })
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a3' })
    const margin = 8
    const headerHeight = 16
    const pageWidth = pdf.internal.pageSize.getWidth()
    const pageHeight = pdf.internal.pageSize.getHeight()
    const imageWidth = pageWidth - (margin * 2)
    const pixelsPerMm = canvas.width / imageWidth
    const availableHeight = pageHeight - margin - headerHeight
    const sourceSliceHeight = Math.max(1, Math.floor(availableHeight * pixelsPerMm))

    for (let sourceY = 0, page = 0; sourceY < canvas.height; sourceY += sourceSliceHeight, page++) {
      if (page > 0) pdf.addPage('a3', 'landscape')
      pdf.setFontSize(13)
      pdf.text(`Zeitplan · ${props.schoolName || ''} · ${dateLabel(form.value.schedule_date)}`, margin, margin + 4)
      const sliceHeight = Math.min(sourceSliceHeight, canvas.height - sourceY)
      const slice = document.createElement('canvas')
      slice.width = canvas.width
      slice.height = sliceHeight
      slice.getContext('2d').drawImage(canvas, 0, sourceY, canvas.width, sliceHeight, 0, 0, canvas.width, sliceHeight)
      pdf.addImage(slice.toDataURL('image/png'), 'PNG', margin, headerHeight, imageWidth, sliceHeight / pixelsPerMm)
    }

    addPdfDetailPages(pdf)
    pdf.save(exportFileName('pdf'))
    success.value = 'Der Zeitplan wurde vollständig als PDF exportiert. Zusätzliche Detailseiten zeigen alle Texte ungekürzt.'
  } catch (exception) {
    error.value = 'Der PDF-Export konnte nicht erstellt werden.'
  } finally {
    element.classList.remove('timeline-export-pdf')
    if (scrollContainer) scrollContainer.scrollLeft = previousScrollLeft
    exportBusy.value = false
  }
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
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-bold text-gray-900">Aktivitäten und Pausen</h3><p class="text-xs text-gray-500">Jede Gruppe erhält genau zwei Pausen. Beim Speichern werden diese Zeiten automatisch als Standard gespeichert.</p></div><div class="flex gap-2"><button v-if="breakDefaults.length" type="button" class="rounded border border-orange-300 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-800" @click="applyBreakDefaults">Standardpausen übernehmen</button><button type="button" class="rounded border px-3 py-1.5 text-xs font-semibold" @click="addEvent">+ Aktivität</button></div></div>
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
          <div class="mt-3 rounded border px-3 py-2 text-xs" :class="everyGroupHasTwoBreaks() ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900'"><span v-if="everyGroupHasTwoBreaks()">✓ Jede Gruppe hat zwei Pausen.</span><span v-else>Bitte für jede Gruppe genau zwei Pausen festlegen.<template v-for="group in groups()" :key="group"><span v-if="groupBreakCount(group) !== 2" class="ml-2 font-semibold">{{ group }}: {{ groupBreakCount(group) }}</span></template></span></div>
        </section>

        <div class="flex flex-wrap gap-2"><button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="generate(false)">{{ busy ? 'Berechnet …' : 'Vorschau erzeugen' }}</button><button type="button" class="rounded bg-orange-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !preview" @click="generate(true)">Zeitplan speichern</button></div>

        <section v-if="preview" class="overflow-hidden rounded-lg border bg-white">
          <div class="border-b bg-gray-50 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold">Zeitplan · {{ dateLabel(form.schedule_date) }}</h3><div class="flex gap-2"><button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 disabled:opacity-50" :disabled="exportBusy" @click="exportExcel">Excel exportieren</button><button type="button" class="rounded bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" :disabled="exportBusy" @click="exportPdf">{{ exportBusy ? 'Export wird erstellt …' : 'PDF exportieren' }}</button></div></div>
            <p class="text-xs font-semibold text-orange-700">Bereichsdauer: <template v-if="preview.config?.actual_area_duration_min_minutes !== preview.config?.actual_area_duration_max_minutes">{{ preview.config?.actual_area_duration_min_minutes }}–{{ preview.config?.actual_area_duration_max_minutes }} Minuten</template><template v-else>{{ preview.config?.actual_area_duration_min_minutes || preview.config?.calculated_area_duration_minutes || preview.config?.areas?.[0]?.duration_minutes || '–' }} Minuten</template></p>
            <div class="mt-2 flex flex-wrap items-center gap-2 rounded border border-cyan-200 bg-cyan-50 px-2.5 py-2 text-xs text-cyan-900"><span v-if="selectedArea" class="font-semibold">{{ selectedArea.title }} in {{ selectedArea.group }} ausgewählt – jetzt den Tauschbereich anklicken.</span><span v-else>Bereiche tauschen: zuerst den ersten, danach den zweiten Bereich derselben Gruppe anklicken.</span><button v-if="Object.keys(form.area_orders || {}).length" type="button" class="ml-auto rounded border border-cyan-300 bg-white px-2 py-1 font-semibold" :disabled="busy" @click="resetAreaOrders">Automatische Reihenfolge wiederherstellen</button></div>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-gray-600"><span class="font-semibold">Horizontal scrollen für den ganzen Tag</span><span class="inline-flex items-center gap-1"><i class="h-3 w-3 rounded-sm border border-orange-500 bg-orange-100"></i>Bereich</span><span class="inline-flex items-center gap-1"><i class="h-3 w-3 rounded-sm border border-slate-500 bg-slate-200"></i>Pause</span><span class="inline-flex items-center gap-1"><i class="h-3 w-3 rounded-sm border border-blue-500 bg-blue-100"></i>Gemeinsam</span><span class="inline-flex items-center gap-1"><i class="h-3 w-3 rounded-sm border border-violet-500 bg-violet-100"></i>Zusatz</span></div>
          </div>
          <div class="overflow-x-auto bg-white">
            <div ref="timelineExportRef" class="min-w-max" :style="{ width: `${timelineWidth() + timelineGroupWidth}px` }">
              <div class="flex h-9 border-b border-gray-400 bg-amber-400 text-[11px] font-bold text-gray-950">
                <div class="sticky left-0 z-30 flex w-[72px] shrink-0 items-center justify-center border-r border-gray-500 bg-amber-400">Gruppe</div>
                <div class="relative h-full shrink-0" :style="{ width: `${timelineWidth()}px` }">
                  <div v-for="mark in timelineMarks()" :key="mark.minute" class="absolute inset-y-0 border-l border-amber-700" :style="{ left: `${mark.offset}px` }">
                    <span class="absolute top-2 whitespace-nowrap" :class="mark.isEnd ? '-translate-x-full pr-1' : 'pl-1'">{{ mark.label }}</span>
                  </div>
                </div>
              </div>
              <div v-for="row in rows()" :key="row.group" class="timetable-row flex h-14 border-b border-gray-300 last:border-b-0">
                <div class="sticky left-0 z-20 flex w-[72px] shrink-0 items-center justify-center border-r border-gray-500 bg-amber-400 text-sm font-extrabold text-gray-950">{{ row.group }}</div>
                <div class="relative h-full shrink-0 bg-white" :style="timelineRowStyle()">
                  <div
                    v-for="entry in row.entries"
                    :key="`${row.group}-${entry.start_time}-${entry.end_time}-${entry.title}`"
                    class="timetable-entry absolute top-1.5 flex h-11 items-center overflow-hidden rounded-sm border px-1.5 shadow-sm"
                    :class="[timelineEntryClass(entry), entry.type === 'area' ? 'cursor-pointer hover:ring-2 hover:ring-cyan-500' : '', isSelectedArea(row.group, entry) ? 'z-10 ring-4 ring-cyan-600' : '']"
                    :style="timelineEntryStyle(entry)"
                    :title="entryTooltip(entry)"
                    @click="selectAreaForSwap(row.group, entry)"
                  >
                    <div class="min-w-0 leading-tight"><div class="timetable-entry-title truncate text-xs font-bold">{{ entry.title }} <span class="font-normal">({{ entryDuration(entry) }})</span></div><div class="timetable-entry-details truncate text-[9px] font-semibold opacity-80">{{ String(entry.start_time).slice(0, 5) }}–{{ String(entry.end_time).slice(0, 5) }}<span v-if="entry.meta?.supervisor_name"> · {{ entry.meta.supervisor_name }}</span></div></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<style scoped>
.timeline-export-pdf .timetable-row {
  height: 76px !important;
}

.timeline-export-pdf .timetable-entry {
  top: 6px !important;
  height: 64px !important;
  align-items: flex-start !important;
  padding-top: 8px;
  padding-bottom: 6px;
}

.timeline-export-pdf .timetable-entry-title,
.timeline-export-pdf .timetable-entry-details {
  overflow: visible;
  white-space: normal;
  text-overflow: clip;
}
</style>

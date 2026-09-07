<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import Dialog from 'primevue/dialog'
import SignatureBox from '@/Components/SignatureBox.vue'

const props = defineProps({ groupId: { type: Number, required: true } })
const visible = ref(false)
const lists = ref([])
const selected = ref('')
const date = ref('')
const dates = ref([])
const weekendsHidden = ref(false)
const participants = ref([])
const rows = ref([])
const search = ref('')
const canRemove = ref(false)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const current = computed(() => lists.value.find(list => `${list.type}:${list.id}` === selected.value))
const pending = computed(() => rows.value.some(row => row.pending))
const busy = computed(() => loading.value || saving.value || pending.value)
const collator = new Intl.Collator('de', { sensitivity: 'base', numeric: true })
const shownDates = computed(() => date.value ? dates.value.filter(day => day === date.value) : dates.value)
const shownParticipants = computed(() => [...participants.value]
  .filter(person => `${person.nachname} ${person.vorname} ${person.klasse || ''}`.toLocaleLowerCase('de').includes(search.value.trim().toLocaleLowerCase('de')))
  .sort((a, b) => collator.compare(a.nachname, b.nachname) || collator.compare(a.vorname, b.vorname)))
const cells = computed(() => {
  const result = {}
  for (const row of rows.value) (result[`${row.person_id}|${row.date}`] ||= []).push(row)
  return result
})
const cell = (person, day) => cells.value[`${person.person_id}|${day}`] || []
const countSigned = day => rows.value.filter(row => row.date === day && row.signed).length
const countTotal = day => rows.value.filter(row => row.date === day).length
const formattedDate = day => day.split('-').reverse().join('.')
const dayName = day => new Date(`${day}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'short' })
const dayType = type => ({ feedback: 'Feedback', preparation: 'Vorbereitung', pa_day: 'PA', rolltag: 'Rolltag', program_day: 'BO-Tag' }[type] || 'BO-Tag')
const scope = () => ({ type: current.value.type, draft_id: current.value.id })
const rowScope = row => ({ ...scope(), date: row.date, key: row.key })
const showError = e => { error.value = e.response?.data?.message || 'Die Verbindung zum Server ist fehlgeschlagen. Bitte erneut versuchen.' }
const applyOverview = data => {
  rows.value = data.rows
  dates.value = data.dates
  weekendsHidden.value = data.weekends_hidden
  if (date.value && !dates.value.includes(date.value)) date.value = ''
  participants.value = data.participants
  canRemove.value = data.can_remove
}
const fetchOverview = async () => {
  const response = await axios.get(route('gruppe.signatures.overview', props.groupId), { params: scope() })
  return response.data
}
const loadRows = async () => {
  if (!current.value) return
  loading.value = true
  error.value = ''
  try { applyOverview(await fetchOverview()) } catch (e) { showError(e) } finally { loading.value = false }
}
const changeList = async () => {
  date.value = ''
  rows.value = []
  dates.value = []
  participants.value = []
  await loadRows()
}
const open = async () => {
  visible.value = true
  loading.value = true
  error.value = ''
  search.value = ''
  rows.value = []
  lists.value = []
  participants.value = []
  dates.value = []
  try {
    const response = await axios.get(route('gruppe.signatures.index', props.groupId))
    lists.value = response.data.lists
    selected.value = lists.value.length ? `${lists.value[0].type}:${lists.value[0].id}` : ''
    await changeList()
  } catch (e) { showError(e) } finally { loading.value = false }
}
const save = async (row, signature) => {
  if (saving.value || row.signed || !signature) return
  saving.value = true
  error.value = ''
  row.pending = signature
  try {
    await axios.post(route('gruppe.signatures.store', props.groupId), { ...rowScope(row), signature })
    // Keep the confirmed image if refreshing the overview fails afterwards.
    row.signature_url = signature
    row.signed = true
    row.pending = null
    row.expected_hash = null
    applyOverview(await fetchOverview())
  } catch (e) {
    showError(e)
    if (e.response?.status === 409) {
      try {
        const data = await fetchOverview()
        if (data.rows.some(item => item.key === row.key && item.signed)) applyOverview(data)
      } catch (_) { /* Preserve unconfirmed input for retry. */ }
    }
  } finally { saving.value = false }
}
const remove = async row => {
  if (busy.value || !canRemove.value || !row.expected_hash) return
  saving.value = true
  try {
    const answer = await Swal.fire({
      title: 'Unterschrift entfernen?',
      text: `${row.vorname} ${row.nachname} · ${formattedDate(row.date)}: Die Unterschrift wird auch aus der zentralen Liste entfernt. Eine verschlüsselte Kopie bleibt zur Wiederherstellung erhalten.`,
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Unterschrift entfernen', cancelButtonText: 'Abbrechen',
      confirmButtonColor: '#b91c1c', focusCancel: true, customClass: { container: 'signature-swal-container' },
    })
    if (!answer.isConfirmed) return
    error.value = ''
    await axios.delete(route('gruppe.signatures.destroy', props.groupId), { data: { ...rowScope(row), expected_hash: row.expected_hash } })
    row.signed = false
    row.signature_url = null
    applyOverview(await fetchOverview())
  } catch (e) {
    showError(e)
    if (e.response?.status === 409) {
      try { applyOverview(await fetchOverview()) } catch (_) { /* Refresh remains available. */ }
    }
  } finally { saving.value = false }
}
const restore = async row => {
  if (busy.value || !canRemove.value || !row.removal_id) return
  saving.value = true
  error.value = ''
  try {
    await axios.post(route('gruppe.signatures.restore', props.groupId), { ...rowScope(row), removal_id: row.removal_id })
    applyOverview(await fetchOverview())
  } catch (e) { showError(e) } finally { saving.value = false }
}
</script>

<template>
  <button type="button" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" @click="open">Unterschriften PA / BO-BIBB</button>
  <Dialog v-model:visible="visible" modal header="Unterschriften dieser Gruppe" :style="{ width: '1400px', maxWidth: '96vw' }" :closable="!busy" :close-on-escape="!busy">
    <p class="mb-2 text-sm text-gray-600">Alle Gruppentermine im Überblick. Unterschriften werden direkt in der zentralen PA- bzw. BO/BIBB-Liste gespeichert.</p>
    <p v-if="canRemove" class="mb-4 text-sm text-gray-600">Sie dürfen Unterschriften entfernen und wiederherstellen. Jede Entfernung wird mit Benutzer und Zeitpunkt protokolliert.</p>
    <div v-if="lists.length" class="mb-4 flex flex-wrap items-end gap-3">
      <label class="min-w-0 flex-1 text-sm" style="flex-basis: 280px">Liste
        <select v-model="selected" :disabled="busy" class="mt-1 w-full rounded border-gray-300" @change="changeList">
          <option v-for="list in lists" :key="`${list.type}:${list.id}`" :value="`${list.type}:${list.id}`">{{ list.label }}</option>
        </select>
      </label>
      <label class="text-sm">Termine
        <select v-model="date" :disabled="busy" class="mt-1 block rounded border-gray-300">
          <option value="">Alle Termine</option>
          <option v-for="day in dates" :key="day" :value="day">{{ formattedDate(day) }}</option>
        </select>
      </label>
      <label class="text-sm">Teilnehmer suchen
        <input v-model="search" :disabled="busy" type="search" placeholder="Name oder Klasse" class="mt-1 block w-full rounded border-gray-300">
      </label>
      <button type="button" :disabled="busy" class="rounded border border-gray-300 px-3 py-2 text-sm disabled:opacity-50" @click="loadRows">Aktualisieren</button>
    </div>
    <p v-if="error" role="alert" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ error }}</p>
    <p v-if="saving" role="status" class="mb-2 text-sm text-gray-600">Änderung wird bearbeitet …</p>
    <p v-if="loading" role="status">Unterschriften werden geladen …</p>
    <p v-else-if="!lists.length && !error" class="rounded bg-amber-50 p-3">Für die Gruppentermine ist noch keine passende zentrale Liste vorbereitet. Bitte die zuständige Person die PA- oder BO/BIBB-Termine in der Schulliste speichern lassen.</p>
    <p v-else-if="!dates.length && weekendsHidden && !error" class="rounded bg-amber-50 p-3">Keine sichtbaren Termine. Samstag und Sonntag sind in den Projekteinstellungen für Gruppenunterschriften ausgeblendet.</p>
    <template v-else-if="participants.length && dates.length">
      <p class="mb-2 text-xs text-gray-600">{{ shownParticipants.length }} Teilnehmer · {{ shownDates.length }} Termine · Sortierung nach Nachname. Bei vielen Terminen seitlich scrollen.</p>
      <p v-if="weekendsHidden" class="mb-2 text-xs text-gray-500">Samstag und Sonntag sind gemäß Projekteinstellung ausgeblendet.</p>
      <div class="signature-grid rounded border border-gray-200" role="region" aria-label="Unterschriften nach Teilnehmer und Termin" tabindex="0">
        <table class="w-full text-left text-sm">
          <caption class="sr-only">Unterschriften dieser Gruppe, nach Nachname sortiert, mit einer Spalte je Termin</caption>
          <thead><tr>
            <th scope="col" class="person-column p-3">Nachname, Vorname <span class="block text-xs font-normal text-gray-500">Klasse</span></th>
            <th v-for="day in shownDates" :key="day" scope="col" class="date-column p-3">
              {{ dayName(day) }} {{ formattedDate(day) }}
              <span class="block text-xs font-normal text-gray-600">{{ countSigned(day) }} / {{ countTotal(day) }} unterschrieben</span>
            </th>
          </tr></thead>
          <tbody><tr v-for="person in shownParticipants" :key="person.person_id" class="border-t">
            <th scope="row" class="person-column p-3 font-normal">{{ person.nachname }}, {{ person.vorname }}<span class="mt-1 block text-xs text-gray-500">Klasse {{ person.klasse || '–' }}</span></th>
            <td v-for="day in shownDates" :key="day" class="date-column border-l p-2 align-top">
              <div v-for="row in cell(person, day)" :key="row.key" class="py-1">
                <span v-if="cell(person, day).length > 1" class="text-xs text-gray-600">{{ dayType(row.type) }}</span>
                <template v-if="row.signed">
                  <img :src="row.signature_url" :alt="`Unterschrift ${person.vorname} ${person.nachname}, ${formattedDate(day)}`" loading="lazy" decoding="async" class="h-12 w-full object-contain" @error="row.imageError = true" @load="row.imageError = false">
                  <span class="text-xs text-emerald-700">Gespeichert</span>
                  <span v-if="row.imageError" class="block text-xs text-amber-700">Vorschau nicht verfügbar. Bitte aktualisieren.</span>
                  <button v-if="canRemove" type="button" :disabled="busy || !row.expected_hash" :aria-label="`Unterschrift von ${person.vorname} ${person.nachname} am ${formattedDate(day)} entfernen`" class="ml-3 text-xs text-red-700 underline disabled:opacity-50" @click="remove(row)">Entfernen</button>
                </template>
                <template v-else>
                  <SignatureBox :model-value="row.pending || ''" :disabled="saving || loading || pending" :allow-clear="false" :participant-name="`${person.vorname} ${person.nachname} · ${formattedDate(day)}`" empty-label="Unterschreiben" lazy-preview compact @completed="save(row, $event)" />
                  <button v-if="row.pending && !saving" type="button" class="mt-2 text-xs font-semibold text-red-700" @click="save(row, row.pending)">Speichern erneut versuchen</button>
                  <button v-else-if="row.removal_id && canRemove" type="button" :disabled="busy" :aria-label="`Unterschrift von ${person.vorname} ${person.nachname} am ${formattedDate(day)} wiederherstellen`" class="mt-1 text-xs text-blue-700 underline disabled:opacity-50" @click="restore(row)">Entfernte Unterschrift wiederherstellen</button>
                </template>
              </div>
              <span v-if="!cell(person, day).length" class="block py-3 text-xs text-gray-500">Kein Unterschriftsfeld in dieser Liste</span>
            </td>
          </tr></tbody>
        </table>
      </div>
      <p v-if="!shownParticipants.length" class="mt-3 text-sm text-gray-600">Keine Teilnehmer zur Suche gefunden.</p>
      <p class="mt-3 text-xs text-gray-600">Unterschreiben ist nur an zugeordneten Gruppentagen mit vorbereitetem Termin in der gewählten zentralen Liste möglich.</p>
    </template>
  </Dialog>
</template>

<style scoped>
.signature-grid { overflow: auto; max-height: 62vh; }
.signature-grid th, .signature-grid td { vertical-align: top; }
.signature-grid thead th { position: sticky; top: 0; background: #f1f5f9; z-index: 2; }
.signature-grid .person-column { position: sticky; left: 0; min-width: 190px; max-width: 230px; background: white; z-index: 1; overflow-wrap: anywhere; }
.signature-grid thead .person-column { z-index: 3; background: #f1f5f9; }
.signature-grid .date-column { min-width: 220px; }
@media (max-width: 640px) {
  .signature-grid .person-column { min-width: 130px; max-width: 150px; }
  .signature-grid .date-column { min-width: 205px; }
}
</style>

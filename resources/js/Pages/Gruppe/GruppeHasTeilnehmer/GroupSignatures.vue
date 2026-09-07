<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import Dialog from 'primevue/dialog'
import SignatureBox from '@/Components/SignatureBox.vue'

const props = defineProps({ groupId: { type: Number, required: true } })
const visible = ref(false)
const lists = ref([])
const selected = ref('')
const date = ref('')
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const current = computed(() => lists.value.find(list => `${list.type}:${list.id}` === selected.value))
const collator = new Intl.Collator('de', { sensitivity: 'base', numeric: true })
const sortedRows = computed(() => [...rows.value].sort((a, b) => collator.compare(a.nachname, b.nachname) || collator.compare(a.vorname, b.vorname)))
const scope = () => ({ type: current.value.type, draft_id: current.value.id, date: date.value })
const showError = (e) => { error.value = e.response?.data?.message || 'Die Verbindung zum Server ist fehlgeschlagen. Bitte erneut versuchen.' }
const loadRows = async () => {
  rows.value = []
  if (!current.value || !date.value) return
  loading.value = true
  error.value = ''
  try {
    const response = await axios.get(route('gruppe.signatures.show', props.groupId), { params: scope() })
    rows.value = response.data.rows
  } catch (e) { showError(e) } finally { loading.value = false }
}
const changeList = async () => {
  date.value = current.value?.dates?.[0] || ''
  await loadRows()
}
const open = async () => {
  visible.value = true
  loading.value = true
  error.value = ''
  rows.value = []
  lists.value = []
  try {
    const response = await axios.get(route('gruppe.signatures.index', props.groupId))
    lists.value = response.data.lists
    selected.value = lists.value.length ? `${lists.value[0].type}:${lists.value[0].id}` : ''
    await changeList()
  } catch (e) { showError(e) } finally { loading.value = false }
}
const save = async (row, signature) => {
  if (saving.value || row.signed) return
  saving.value = true
  error.value = ''
  row.pending = signature
  try {
    await axios.post(route('gruppe.signatures.store', props.groupId), { ...scope(), key: row.key, signature })
    row.signature = signature
    row.signed = true
    row.pending = null
  } catch (e) {
    showError(e)
    if (e.response?.status === 409) {
      // Another device has already saved this field. Keep that confirmed value.
      try {
        const response = await axios.get(route('gruppe.signatures.show', props.groupId), { params: scope() })
        const confirmed = response.data.rows.find(item => item.key === row.key && item.signed)
        if (confirmed) { Object.assign(row, confirmed); row.pending = null }
      } catch (_) { /* Keep the unsaved value available for retry. */ }
    }
  } finally { saving.value = false }
}
</script>

<template>
  <button type="button" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" @click="open">Unterschriften PA / BO-BIBB</button>
  <Dialog v-model:visible="visible" modal header="Unterschriften dieser Gruppe" :style="{ width: '900px', maxWidth: '96vw' }" :closable="!saving && !rows.some(row => row.pending)" :close-on-escape="!saving && !rows.some(row => row.pending)">
    <p class="mb-4 text-sm text-gray-600">Nur Teilnehmer dieser Gruppe. Gespeicherte Unterschriften erscheinen direkt in der zentralen Liste und können hier weder gelöscht noch überschrieben werden.</p>
    <div v-if="lists.length" class="mb-4 flex flex-wrap gap-3">
      <label class="flex-1 text-sm">Liste
        <select v-model="selected" :disabled="loading || saving || rows.some(row => row.pending)" class="mt-1 w-full rounded border-gray-300" @change="changeList">
          <option v-for="list in lists" :key="`${list.type}:${list.id}`" :value="`${list.type}:${list.id}`">{{ list.label }}</option>
        </select>
      </label>
      <label class="text-sm">Termin
        <select v-model="date" :disabled="loading || saving || rows.some(row => row.pending)" class="mt-1 block rounded border-gray-300" @change="loadRows">
          <option v-for="day in current?.dates || []" :key="day" :value="day">{{ day.split('-').reverse().join('.') }}</option>
        </select>
      </label>
    </div>
    <p v-if="error" role="alert" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ error }}</p>
    <p v-if="loading" role="status">Unterschriften werden geladen …</p>
    <p v-else-if="!lists.length && !error" class="rounded bg-amber-50 p-3">Für die Gruppentermine ist noch keine passende zentrale Liste vorbereitet. Bitte die zuständige Person die PA- oder BO/BIBB-Termine in der Schulliste speichern lassen.</p>
    <table v-else-if="rows.length" class="w-full text-left text-sm">
      <thead><tr><th class="p-2">Nachname, Vorname</th><th class="p-2">Klasse</th><th class="p-2">Unterschrift</th></tr></thead>
      <tbody><tr v-for="row in sortedRows" :key="row.key" class="border-t">
        <td class="p-2">{{ row.nachname }}, {{ row.vorname }}</td><td class="p-2">{{ row.klasse }}</td>
        <td class="w-64 p-2">
          <template v-if="row.signed"><img :src="row.signature" alt="Gespeicherte Unterschrift" loading="lazy" class="h-12 w-48 object-contain"><span class="text-xs text-emerald-700">Gespeichert</span></template>
          <template v-else>
            <SignatureBox :model-value="row.pending || ''" :disabled="saving || !!row.pending" :allow-clear="false" :participant-name="`${row.vorname} ${row.nachname}`" lazy-preview compact @completed="save(row, $event)" />
            <button v-if="row.pending && !saving" type="button" class="mt-2 font-semibold text-red-700" @click="save(row, row.pending)">Speichern erneut versuchen</button>
          </template>
        </td>
      </tr></tbody>
    </table>
  </Dialog>
</template>

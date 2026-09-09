<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePermissions } from '@/utils/permissions';

const props = defineProps({ groupId: Number, from: String, until: String, participantIds: { type: Array, default: () => [] } });
const active = computed(() => usePage().props.currentProjekt?.features?.daily_documentation === true);
const { can } = usePermissions();
const today = () => new Date().toLocaleDateString('sv-SE');
const iso = value => value?.slice(0, 10);
const parseDate = value => new Date(`${value}T12:00:00`);
const formatDate = (value, options) => new Intl.DateTimeFormat('de-DE', options).format(parseDate(value));
const addDays = (value, count) => { const date = parseDate(value); date.setDate(date.getDate() + count); return date.toLocaleDateString('sv-SE'); };
const monday = value => { const date = parseDate(value); const day = date.getDay() || 7; date.setDate(date.getDate() - day + 1); return date.toLocaleDateString('sv-SE'); };
const lastDate = computed(() => [iso(props.until), today()].filter(Boolean).sort()[0]);
const firstDate = computed(() => iso(props.from) || lastDate.value);
const open = ref(false);
const selectedDate = ref(lastDate.value);
const weekStart = ref(monday(lastDate.value));
const data = ref(null);
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const message = ref('');
const form = ref(null);
const reuse = ref('');
let version = 0;
let closed = false;

const weekDays = computed(() => Array.from({ length: 7 }, (_, index) => {
  const date = addDays(weekStart.value, index);
  const tasks = (data.value?.tasks || []).filter(task => iso(task.performed_on) === date);
  return { date, tasks, enabled: date >= firstDate.value && date <= lastDate.value, participants: new Set(tasks.flatMap(task => task.assignments.map(item => item.project_person_id))).size };
}));
const selectedTasks = computed(() => weekDays.value.find(day => day.date === selectedDate.value)?.tasks || []);
const participants = computed(() => (data.value?.participants || []).map(person => ({
  ...person,
  participationId: data.value.participations.find(item => Number(item.personen_id) === Number(person.id))?.id,
})).filter(person => person.participationId));
const participantName = id => { const person = participants.value.find(item => Number(item.participationId) === Number(id)); return person ? `${person.vorname} ${person.nachname}` : ''; };
const weekLabel = computed(() => `${formatDate(weekStart.value, { day: '2-digit', month: '2-digit' })} – ${formatDate(addDays(weekStart.value, 6), { day: '2-digit', month: '2-digit', year: 'numeric' })}`);
const canPrevious = computed(() => weekStart.value > monday(firstDate.value));
const canNext = computed(() => addDays(weekStart.value, 7) <= lastDate.value);
const explain = exception => Object.values(exception.response?.data?.errors || {}).flat().join(' ') || exception.response?.data?.message || 'Die Aktion konnte nicht abgeschlossen werden.';

async function load() {
  if (!open.value) return;
  const request = ++version;
  loading.value = true;
  error.value = '';
  try {
    const response = await axios.get(route('daily-tasks.index', props.groupId), { params: { from: weekStart.value, until: addDays(weekStart.value, 6) } });
    if (request === version && !closed) data.value = response.data;
  } catch (exception) {
    if (request === version) error.value = explain(exception);
  } finally {
    if (request === version) loading.value = false;
  }
}
function openOverview() {
  selectedDate.value = lastDate.value;
  weekStart.value = monday(selectedDate.value);
  open.value = true;
  form.value = null;
  message.value = '';
}
function openNewTask() {
  openOverview();
  newTask();
}
function closeOverview() { if (!busy.value) { open.value = false; form.value = null; } }
function selectDay(day) { if (day.enabled && !busy.value && !form.value?.id) selectedDate.value = day.date; }
function moveWeek(days) {
  if (form.value?.id || busy.value) return;
  weekStart.value = addDays(weekStart.value, days);
  const available = Array.from({ length: 7 }, (_, index) => addDays(weekStart.value, index)).filter(date => date >= firstDate.value && date <= lastDate.value);
  selectedDate.value = days < 0 ? available.at(-1) : available[0];
}
function newTask() { form.value = { id: null, revision: 0, description: '', selected: [], notes: {} }; reuse.value = ''; message.value = ''; }
function edit(task) { form.value = { id: task.id, revision: task.revision, description: task.description, selected: task.assignments.map(item => item.project_person_id), notes: Object.fromEntries(task.assignments.map(item => [item.project_person_id, item.observation || ''])) }; reuse.value = ''; message.value = ''; }
async function save() {
  busy.value = true;
  error.value = '';
  try {
    await axios.post(route('daily-tasks.store', props.groupId), { id: form.value.id, revision: form.value.revision, description: form.value.description, performed_on: selectedDate.value, assignments: form.value.selected.map(id => ({ project_person_id: id, observation: form.value.notes[id] || '' })) });
    form.value = null;
    await load();
    message.value = 'Tagesdokumentation gespeichert.';
  } catch (exception) { error.value = explain(exception); } finally { busy.value = false; }
}
async function remove(task) {
  const confirmation = await Swal.fire({ title: 'Aufgabe für diesen Tag löschen?', text: 'Die Zuordnungen und Beobachtungen dieses Eintrags werden entfernt. Andere Tage bleiben erhalten.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Löschen', cancelButtonText: 'Abbrechen' });
  if (!confirmation.isConfirmed) return;
  busy.value = true;
  try { await axios.delete(route('daily-tasks.destroy', [props.groupId, task.id]), { data: { revision: task.revision } }); await load(); } catch (exception) { error.value = explain(exception); } finally { busy.value = false; }
}

watch(() => [open.value, active.value, props.groupId, weekStart.value, props.participantIds.join(',')], () => { if (open.value && active.value) load(); });
onBeforeUnmount(() => { closed = true; version++; });
</script>

<template>
  <section v-if="active" class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div><h2 class="font-semibold text-gray-800"><i class="pi pi-calendar mr-2 text-zbb"></i>Tagesdokumentation</h2><p class="mt-1 text-sm text-gray-600">Aufgaben und individuelle Beobachtungen in der Wochenübersicht erfassen.</p></div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="rounded-lg bg-zbb px-4 py-2.5 font-semibold text-white shadow-sm hover:opacity-90" @click="openOverview">Wochenübersicht öffnen</button>
        <button v-if="can('teilnehmer.update')" type="button" class="rounded-lg border border-zbb bg-white px-4 py-2.5 font-semibold text-zbb shadow-sm hover:bg-blue-50" aria-label="Neue Aufgabe hinzufügen" @click="openNewTask"><span class="mr-1 text-lg leading-none">+</span> Aufgabe</button>
      </div>
    </div>
  </section>

  <div v-if="open" class="fixed inset-0 z-[1000] flex items-center justify-center bg-gray-900/60 p-3" @mousedown.self="closeOverview">
    <section role="dialog" aria-modal="true" aria-labelledby="daily-title" class="flex max-h-[95vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-start justify-between border-b px-5 py-4">
        <div><h2 id="daily-title" class="text-xl font-semibold">Tagesdokumentation</h2><p class="mt-1 text-sm text-gray-500">Wochenübersicht · {{ weekLabel }}</p></div>
        <button type="button" aria-label="Schließen" :disabled="busy" class="rounded p-2 text-xl text-gray-500 hover:bg-gray-100" @click="closeOverview">×</button>
      </header>
      <div class="overflow-y-auto p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
          <button type="button" :disabled="!canPrevious||busy||!!form?.id" class="rounded-lg border px-3 py-2 disabled:opacity-30" @click="moveWeek(-7)"><i class="pi pi-chevron-left"></i><span class="ml-2 hidden sm:inline">Vorherige Woche</span></button>
          <strong class="text-center">{{ weekLabel }}</strong>
          <button type="button" :disabled="!canNext||busy||!!form?.id" class="rounded-lg border px-3 py-2 disabled:opacity-30" @click="moveWeek(7)"><span class="mr-2 hidden sm:inline">Nächste Woche</span><i class="pi pi-chevron-right"></i></button>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
          <button v-for="day in weekDays" :key="day.date" type="button" :disabled="!day.enabled||busy||!!form?.id" class="min-h-24 rounded-xl border p-3 text-left transition disabled:bg-gray-50 disabled:text-gray-300" :class="selectedDate===day.date?'border-zbb bg-blue-50 ring-2 ring-zbb':'hover:border-gray-400'" @click="selectDay(day)">
            <span class="block text-xs font-semibold uppercase">{{ formatDate(day.date,{weekday:'short'}) }}</span><span class="block text-lg font-semibold">{{ formatDate(day.date,{day:'2-digit',month:'2-digit'}) }}</span>
            <span v-if="day.enabled" class="mt-2 block text-xs">{{ day.tasks.length }} {{ day.tasks.length===1?'Aufgabe':'Aufgaben' }}</span><span v-if="day.enabled&&day.participants" class="block text-xs text-gray-500">{{day.participants}} {{day.participants===1?'Teilnehmer':'Teilnehmer'}}</span>
          </button>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
          <div><h3 class="font-semibold">{{ formatDate(selectedDate,{weekday:'long',day:'2-digit',month:'long',year:'numeric'}) }}</h3><p class="text-xs text-gray-500">{{ selectedTasks.length }} {{ selectedTasks.length===1?'dokumentierte Aufgabe':'dokumentierte Aufgaben' }}</p></div>
          <button v-if="data?.can_write&&!form" type="button" :disabled="busy||loading||!participants.length" class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50" @click="newTask">Aufgabe hinzufügen</button>
        </div>
        <p v-if="loading" role="status" class="my-3 text-sm"><i class="pi pi-spin pi-spinner mr-2"></i>Wochenübersicht wird geladen …</p>
        <p v-if="error" role="alert" class="my-3 rounded bg-red-50 p-3 text-sm text-red-800">{{ error }}</p><p v-if="message" role="status" class="my-3 text-sm text-green-800">{{ message }}</p>

        <form v-if="form" class="my-4 space-y-4 rounded-lg border border-blue-200 bg-blue-50 p-4" @submit.prevent="save">
          <h3 class="font-semibold">{{ form.id?'Aufgabe bearbeiten':'Neue Aufgabe' }} · {{ formatDate(selectedDate,{day:'2-digit',month:'2-digit',year:'numeric'}) }}</h3>
          <label v-if="data?.library?.length" class="block text-sm">Eigene Aufgabe wiederverwenden<select v-model="reuse" :disabled="busy" class="mt-1 w-full rounded border-gray-300" @change="form.description=reuse"><option value="">Bereits verwendete Aufgabe auswählen …</option><option v-for="item in data.library" :key="item.description" :value="item.description">{{item.description}}</option></select><span class="text-xs text-gray-600">Die letzten 100 unterschiedlichen Aufgaben aus diesem Projekt. Beobachtungen werden nicht kopiert.</span></label>
          <label class="block text-sm">Aufgabe / Tätigkeit<textarea v-model.trim="form.description" :disabled="busy" required maxlength="500" rows="2" placeholder="Zum Beispiel: Gemüse für das Mittagessen vorbereiten" class="mt-1 w-full rounded border-gray-300" /></label>
          <div class="flex flex-wrap gap-3 text-sm"><strong>Teilnehmer zuordnen</strong><button type="button" :disabled="busy" class="underline" @click="form.selected=participants.map(p=>p.participationId)">Alle auswählen</button><button type="button" :disabled="busy" class="underline" @click="form.selected=[]">Auswahl aufheben</button></div>
          <div class="grid max-h-80 gap-3 overflow-y-auto lg:grid-cols-2"><div v-for="person in participants" :key="person.id" class="rounded bg-white p-3"><label class="flex items-center gap-2 text-sm"><input v-model="form.selected" type="checkbox" :value="person.participationId" :disabled="busy" class="rounded">{{person.vorname}} {{person.nachname}}</label><label v-if="form.selected.includes(person.participationId)" class="mt-2 block text-xs text-gray-600">Individuelle Beobachtung (optional)<textarea v-model="form.notes[person.participationId]" :disabled="busy" maxlength="1000" rows="2" placeholder="Nur tatsächlich Beobachtetes, z. B. selbstständig gearbeitet oder Hilfe benötigt." class="mt-1 w-full rounded border-gray-300 text-sm" /></label></div></div>
          <div class="flex gap-3"><button :disabled="busy||!form.selected.length||!form.description" class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50">{{busy?'Speichert …':'Speichern'}}</button><button type="button" :disabled="busy" @click="form=null">Abbrechen</button></div>
        </form>

        <div v-if="!loading&&!form" class="mt-4 space-y-3"><article v-for="(task,index) in selectedTasks" :key="task.id" class="rounded-lg border p-4"><div class="flex items-start justify-between gap-3"><h3 class="font-semibold">{{index+1}}. {{task.description}}</h3><div v-if="task.can_edit" class="flex gap-3 text-xs"><button :disabled="busy" class="underline" @click="edit(task)">Bearbeiten</button><button :disabled="busy" class="text-red-700 underline" @click="remove(task)">Löschen</button></div></div><ul class="mt-2 space-y-1 text-sm"><li v-for="assignment in task.assignments" :key="assignment.project_person_id"><strong>{{participantName(assignment.project_person_id)}}</strong><span v-if="assignment.observation">: {{assignment.observation}}</span></li></ul></article></div>
        <p v-if="!loading&&!form&&data&&!selectedTasks.length" class="my-5 rounded-lg bg-gray-50 p-5 text-center text-sm text-gray-500">Für diesen Tag sind noch keine Aufgaben dokumentiert.</p>
        <p class="mt-4 text-xs text-gray-500">Die LuV bündelt die zugeordneten Tätigkeiten im gewählten Berichtszeitraum je Teilnehmer und fasst Wiederholungen zusammen.</p>
      </div>
    </section>
  </div>
</template>

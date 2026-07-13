<template>
  <Head title="Einteilung" />

  <app-layout>
    <template #header>Einteilung</template>

    <div class="px-4 pt-4">
      <div class="flex flex-col gap-4 rounded border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500">BOP Programm</p>
          <h1 class="text-lg font-bold text-gray-900">{{ partner.name }}</h1>
          <p class="text-sm text-gray-600">Schuljahr {{ schuljahr }} / Teil {{ teil }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
          <button v-if="can('einteilung.store')" type="button" @click="openCreateModal" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
            <i class="la la-plus mr-1"></i>Anlegen
          </button>
          <button v-if="can('einteilung.planning')" type="button" @click="openGruppenModal" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
            <i class="la la-refresh mr-1"></i>Gruppen generieren
          </button>
          <button v-if="can('einteilung.planning')" type="button" @click="openParameterModal" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
            <i class="la la-sliders-h mr-1"></i>Parameter
          </button>
          <button v-if="can('einteilung.planning')" type="button" @click="openSwitchModal" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
            <i class="la la-exchange-alt mr-1"></i>Runden tauschen
          </button>
          <button v-if="can('einteilung.store')" type="button" :disabled="isBusy" @click="submitEinteilen" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600 disabled:opacity-50">
            <i class="la la-arrows-alt mr-1"></i>Einteilen
          </button>
          <button v-if="can('einteilung.destroy')" type="button" :disabled="isBusy" @click="submitDestroy" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600 disabled:opacity-50">
            <i class="la la-trash mr-1"></i>Löschen
          </button>
          <button v-if="can('einteilung.export')" type="button" @click="openExportModal" class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
            <i class="la la-download mr-1"></i>Exportieren
          </button>
        </div>

      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 px-4 pt-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="card in statCards" :key="card.label" class="rounded border border-gray-200 bg-white px-4 py-3 text-center shadow-sm">
        <div class="text-sm text-gray-600">{{ card.label }}</div>
        <div class="text-xl font-bold text-gray-900">{{ card.value }}</div>
      </div>
    </div>

    <div v-if="statusMessage" class="mx-4 mt-4 rounded border px-4 py-3 text-sm" :class="statusType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'">
      {{ statusMessage }}
    </div>

    <div class="p-4 space-y-6 overflow-y-auto h-[80vh]">
        <div v-for="runde in runden" :key="runde">
            <h2 class="mb-2 text-sm font-bold uppercase text-gray-700">Runde {{ runde }}</h2>

            <div class="overflow-x-auto w-full">
            <table class="min-w-full border-collapse border border-gray-200 shadow-sm">
                <thead>
                <tr class="bg-gray-50">
                    <th
                    v-for="bereich in headerBereiche"
                    :key="normalizeKey(bereich)"
                    class="px-2 py-3 border border-gray-200 text-center uppercase font-bold text-xs tracking-wider whitespace-nowrap"
                    >
                    {{ bereich }}
                    </th>
                </tr>
                </thead>

                <tbody>
                <tr>
                    <td
                    v-for="bereich in bereiche"
                    :key="normalizeKey(bereich) + runde"
                    class="py-4 border border-gray-200 align-top "
                    >
                    <ul class="space-y-1 text-sm min-h-[300px] w-[200px]">
                        <li
                        v-for="schueler in results[bereich]?.[runde]"
                        :key="schueler.id"
                        @click="can('einteilung.update') && openEditModal(schueler)"
                        class="rounded transition group"
                        :class="can('einteilung.update') ? 'cursor-pointer hover:bg-gray-200' : 'cursor-default'"
                        >
                        <span class="group-hover:text-zbb font-medium text-xs">
                            {{ schueler.nachname }}, {{ schueler.vorname }}
                        </span>
                        <span class="text-gray-500 text-xs ml-1">
                            ({{ schueler.klasse }})
                        </span>
                        <span
                            :class="schueler.geschlecht === 'w' ? 'text-pink-500' : 'text-green-500'"
                            class="ml-1 text-[10px] font-bold"
                        >
                            ({{ schueler.geschlecht }})
                        </span>
                        </li>
                    </ul>

                    <div class="mt-4 pt-2 border-t border-orange-200 text-orange-600 font-bold italic text-xs">
                        Summe: {{ results[bereich]?.[runde]?.length || 0 }} / {{ capacityForBereich(bereich) }}
                    </div>
                    </td>
                </tr>
                </tbody>

            </table>
            </div>
        </div>

        <div v-if="updatedAt" class="mt-4 p-2 bg-blue-50 text-blue-800 rounded text-sm inline-block">
            Zuletzt geändert: {{ formatDate(updated_at) }}
        </div>
    </div>

    <Modal v-if="showModal" :show="showModal" @close="showModal = false">
    <template #header>Einteilung anpassen</template>

      <template #body>
        <div v-if="selectedSchueler?.id" class="space-y-6">
            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                <p class="text-sm text-gray-500 mb-2">Teilnehmer <span class="text-red-500">*</span> </p>
                <InputText disabled :value=" selectedSchueler.vorname + ' ' + selectedSchueler.nachname " v-model="teilnehmername"  size="small"  class="w-full" />
            </div>

          <div class="space-y-4">
            <div v-for="r in runden" :key="r" class="flex flex-col">
              <label :for="`runde-${r}`" class="text-sm font-semibold text-gray-700 mb-1">
                Bereich Runde {{ r }}
              </label>
              <select
                v-model="form['runde_' + r]"
                :id="`runde-${r}`"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-zbb focus:border-zbb sm:text-sm"
              >
                <option :value="null">-- Kein Bereich --</option>
                <option v-for="b in allBereiche" :key="b.id" :value="b.id">
                  {{ b.name }}
                </option>
              </select>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
         <button @click="submitUpdate" :disabled="form.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50" >
          {{ form.processing ? 'Speichert...' : 'Speichern' }}
        </button>
        <button @click="showModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50" >
          Abbrechen
        </button>

      </template>
    </Modal>

    <Modal v-if="showCreateModal" :show="showCreateModal" @close="showCreateModal = false">
      <template #header>Einteilung anlegen</template>
      <template #body>
        <div class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Teilnehmer</label>
            <select v-model="createForm.schueler_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
              <option :value="null">Teilnehmer waehlen</option>
              <option v-for="teilnehmer in teilnehmerOptions" :key="teilnehmer.id" :value="teilnehmer.id">
                {{ teilnehmer.name }} {{ teilnehmer.klasse ? '(' + teilnehmer.klasse + ')' : '' }}
              </option>
            </select>
          </div>
          <div v-for="r in runden" :key="`create-${r}`">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Runde {{ r }}</label>
            <select v-model="createForm['runde_' + r]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
              <option :value="null">Bereich waehlen</option>
              <option v-for="b in allBereiche" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="submitCreate" :disabled="createForm.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50">
          {{ createForm.processing ? 'Speichert...' : 'Speichern' }}
        </button>
        <button @click="showCreateModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Abbrechen
        </button>
      </template>
    </Modal>

    <Modal v-if="showParameterModal" :show="showParameterModal" @close="showParameterModal = false">
      <template #header>Einteilungs-Parameter</template>
      <template #body>
        <div class="space-y-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Runden</label>
              <select v-model.number="parameterForm.runden_anzahl" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
                <option v-for="count in [2, 3, 4, 5]" :key="count" :value="count">{{ count }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Standard-Kapazität</label>
              <input v-model.number="parameterForm.standard_kapazitaet" min="0" max="999" type="number" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
            </div>
          </div>

          <div class="overflow-x-auto border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Bereich</th>
                  <th class="w-40 px-3 py-2 text-left font-semibold text-gray-700">Plätze</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="bereich in allBereiche" :key="`kap-${bereich.id}`">
                  <td class="px-3 py-2 font-medium text-gray-800">{{ bereich.name }}</td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="parameterForm.kapazitaeten[bereich.id]"
                      min="0"
                      max="999"
                      type="number"
                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="submitParameter" :disabled="parameterForm.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50">
          {{ parameterForm.processing ? 'Speichert...' : 'Speichern' }}
        </button>
        <button @click="showParameterModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Abbrechen
        </button>
      </template>
    </Modal>

    <Modal v-if="showSwitchModal" :show="showSwitchModal" @close="showSwitchModal = false">
      <template #header>Runden tauschen</template>
      <template #body>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Erste Runde</label>
            <select
              v-model.number="switchForm.quelle_runde"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm"
              @change="ensureSwitchTarget"
            >
              <option v-for="runde in runden" :key="`quelle-${runde}`" :value="runde">Runde {{ runde }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Zweite Runde</label>
            <select v-model.number="switchForm.ziel_runde" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
              <option
                v-for="runde in runden"
                :key="`ziel-${runde}`"
                :value="runde"
                :disabled="runde === switchForm.quelle_runde"
              >
                Runde {{ runde }}
              </option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="submitSwitchRunden" :disabled="switchForm.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50">
          {{ switchForm.processing ? 'Tauscht...' : 'Tauschen' }}
        </button>
        <button @click="showSwitchModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Abbrechen
        </button>
      </template>
    </Modal>

    <Modal v-if="showGruppenModal" :show="showGruppenModal" @close="showGruppenModal = false">
      <template #header>Gruppen generieren</template>
      <template #body>
        <div class="space-y-5">
          <div class="grid grid-cols-2 gap-2">
            <label v-for="bereich in allBereiche" :key="bereich.id" class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="gruppenForm.bereiche" type="checkbox" :value="bereich.id" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
              <span>{{ bereich.name }}</span>
            </label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <template v-for="r in runden" :key="`runde-date-${r}`">
              <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Runde {{ r }} von</label>
                <input v-model="gruppenForm['runde' + r + 'von']" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Runde {{ r }} bis</label>
                <input v-model="gruppenForm['runde' + r + 'bis']" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
              </div>
            </template>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Startzeit</label>
              <input v-model="gruppenForm.startzeit" type="time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Endzeit</label>
              <input v-model="gruppenForm.endzeit" type="time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Raum</label>
              <select v-model="gruppenForm.raum_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
                <option :value="null">Raum waehlen</option>
                <option v-for="raum in raeume" :key="raum.id" :value="raum.id">{{ raum.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-gray-700">Betreuer</label>
              <select v-model="gruppenForm.betreuer_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm">
                <option :value="null">Betreuer waehlen</option>
                <option v-for="person in betreuer" :key="person.id" :value="person.id">{{ person.name }}</option>
              </select>
            </div>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="submitGruppen" :disabled="gruppenForm.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50">
          {{ gruppenForm.processing ? 'Generiert...' : 'Generieren' }}
        </button>
        <button @click="showGruppenModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Abbrechen
        </button>
      </template>
    </Modal>

    <Modal v-if="showExportModal" :show="showExportModal" @close="showExportModal = false">
      <template #header>Einteilung exportieren</template>
      <template #body>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Anfangsdatum</label>
            <input v-model="exportForm.eintritt" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Enddatum</label>
            <input v-model="exportForm.austritt" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="submitExport" :disabled="exportForm.processing" class="px-6 py-2 text-sm font-medium text-white bg-zbb border border-transparent rounded-md shadow-sm hover:bg-opacity-90 disabled:opacity-50">
          {{ exportForm.processing ? 'Exportiert...' : 'Exportieren' }}
        </button>
        <button @click="showExportModal = false" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Abbrechen
        </button>
      </template>
    </Modal>

  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/ModalForm.vue';
import { Head } from '@inertiajs/vue3'
import { ref, computed, reactive } from 'vue'
import InputText from 'primevue/inputtext';
import axios from 'axios'
import { usePermissions } from '@/utils/permissions'

const props = defineProps({
  results: Object,
  alle_bereiche: Array, // vom Controller
  updated_at: String,
  partner: Object,
  schuljahr: [String, Number],
  teil: [String, Number],
  klassen: Array,
  anzahlBereiche: Number,
  teilnehmerOptions: Array,
  raeume: Array,
  betreuer: Array,
  stats: Object,
  runden: Array,
  parameter: Object,
})
const { can } = usePermissions()

const teilnehmername = ref('');
const showModal = ref(false)
const showCreateModal = ref(false)
const showGruppenModal = ref(false)
const showExportModal = ref(false)
const showParameterModal = ref(false)
const showSwitchModal = ref(false)
const selectedSchueler = ref(null)
const results = ref(JSON.parse(JSON.stringify(props.results)));
const allBereiche = ref([...(props.alle_bereiche ?? [])])
const updatedAt = ref(props.updated_at)
const updated_at = computed(() => updatedAt.value)
const teilnehmerOptions = ref([...(props.teilnehmerOptions ?? [])])
const raeume = ref([...(props.raeume ?? [])])
const betreuer = ref([...(props.betreuer ?? [])])
const stats = ref({ ...(props.stats ?? {}) })
const statusMessage = ref('')
const statusType = ref('success')
const isBusy = ref(false)
const maxRoundNumbers = [1, 2, 3, 4, 5]
const runden = ref([...(props.runden?.length ? props.runden : [1, 2, 3])])

const normalizeParameter = (parameter = {}) => ({
  runden_anzahl: Number(parameter.runden_anzahl ?? 3),
  standard_kapazitaet: Number(parameter.standard_kapazitaet ?? 15),
  kapazitaeten: { ...(parameter.kapazitaeten ?? {}) },
})

const parameter = ref(normalizeParameter(props.parameter))

const statCards = computed(() => [
  { label: 'Schulen', value: stats.value.schulen ?? 0 },
  { label: 'Gruppen', value: stats.value.gruppen ?? 0 },
  { label: 'Teilnehmer', value: stats.value.teilnehmer ?? 0 },
  { label: 'Bereiche', value: stats.value.bereiche ?? 0 },
])

const contextPayload = () => ({
  partner_id: props.partner.id,
  schuljahr: props.schuljahr,
  teil: props.teil,
})

const replacePayload = (payload) => {
  if (!payload) return
  results.value = JSON.parse(JSON.stringify(payload.results ?? {}))
  allBereiche.value = [...(payload.alle_bereiche ?? [])]
  updatedAt.value = payload.updated_at ?? null
  teilnehmerOptions.value = [...(payload.teilnehmerOptions ?? [])]
  raeume.value = [...(payload.raeume ?? [])]
  betreuer.value = [...(payload.betreuer ?? [])]
  stats.value = { ...(payload.stats ?? {}) }
  runden.value = [...(payload.runden?.length ? payload.runden : [1, 2, 3])]
  parameter.value = normalizeParameter(payload.parameter)
}

const setStatus = (message, type = 'success') => {
  statusMessage.value = message
  statusType.value = type
}

const readError = async (error) => {
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
// Bereichsnamen für Tabellen-Header nur für die Anzeige
const headerBereiche = computed(() => {
  // Holen wir uns die Originalnamen aus alle_bereiche
  return allBereiche.value.map(b => b.name);
});

const form = reactive({
  schueler_id: null,
  runde_1: null,
  runde_2: null,
  runde_3: null,
  runde_4: null,
  runde_5: null,
  processing: false,
})
const createForm = reactive({
  schueler_id: null,
  runde_1: null,
  runde_2: null,
  runde_3: null,
  runde_4: null,
  runde_5: null,
  processing: false,
})
const gruppenForm = reactive({
  runde1von: '',
  runde1bis: '',
  runde2von: '',
  runde2bis: '',
  runde3von: '',
  runde3bis: '',
  runde4von: '',
  runde4bis: '',
  runde5von: '',
  runde5bis: '',
  startzeit: '08:00',
  endzeit: '15:00',
  raum_id: props.raeume?.[0]?.id ?? null,
  betreuer_id: props.betreuer?.[0]?.id ?? null,
  bereiche: (props.alle_bereiche ?? []).map(b => b.id),
  processing: false,
})
const exportForm = reactive({
  eintritt: '',
  austritt: '',
  processing: false,
})
const parameterForm = reactive({
  runden_anzahl: parameter.value.runden_anzahl,
  standard_kapazitaet: parameter.value.standard_kapazitaet,
  kapazitaeten: { ...parameter.value.kapazitaeten },
  processing: false,
})
const switchForm = reactive({
  quelle_runde: runden.value[0] ?? null,
  ziel_runde: runden.value[1] ?? null,
  processing: false,
})
const bereiche = computed(() => Object.keys(results.value || {}))

// === Bereichsnamen für Anzeige ===

// normalizeKey nur für Keys & interne Logik
function normalizeKey(str) {
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9]/g, '')
    .toLowerCase();
}

const resetRoundFields = (target) => {
  maxRoundNumbers.forEach((runde) => {
    target['runde_' + runde] = null
  })
}

const roundPayload = (target) => {
  const payload = {}
  runden.value.forEach((runde) => {
    payload['runde_' + runde] = target['runde_' + runde] ?? null
  })
  return payload
}

const gruppenDatePayload = () => {
  const payload = {}
  runden.value.forEach((runde) => {
    payload['runde' + runde + 'von'] = gruppenForm['runde' + runde + 'von']
    payload['runde' + runde + 'bis'] = gruppenForm['runde' + runde + 'bis']
  })
  return payload
}

const capacityForBereich = (bereichName) => {
  const bereich = allBereiche.value.find(b => b.name === bereichName)
  if (!bereich) return parameter.value.standard_kapazitaet ?? 0
  return parameter.value.kapazitaeten?.[bereich.id] ?? parameter.value.standard_kapazitaet ?? 0
}

const openParameterModal = () => {
  const current = normalizeParameter(parameter.value)
  parameterForm.runden_anzahl = current.runden_anzahl
  parameterForm.standard_kapazitaet = current.standard_kapazitaet
  parameterForm.kapazitaeten = { ...current.kapazitaeten }
  allBereiche.value.forEach((bereich) => {
    if (parameterForm.kapazitaeten[bereich.id] === undefined) {
      parameterForm.kapazitaeten[bereich.id] = current.standard_kapazitaet
    }
  })
  showParameterModal.value = true
}

const ensureSwitchTarget = () => {
  if (switchForm.quelle_runde !== switchForm.ziel_runde) return
  switchForm.ziel_runde = runden.value.find(r => r !== switchForm.quelle_runde) ?? null
}

const openSwitchModal = () => {
  switchForm.quelle_runde = runden.value[0] ?? null
  switchForm.ziel_runde = runden.value.find(r => r !== switchForm.quelle_runde) ?? null
  showSwitchModal.value = true
}

const openCreateModal = () => {
  createForm.schueler_id = null
  resetRoundFields(createForm)
  showCreateModal.value = true
}

const openGruppenModal = () => {
  if (!gruppenForm.bereiche.length) {
    gruppenForm.bereiche = allBereiche.value.map(b => b.id)
  }
  gruppenForm.raum_id = gruppenForm.raum_id ?? raeume.value[0]?.id ?? null
  gruppenForm.betreuer_id = gruppenForm.betreuer_id ?? betreuer.value[0]?.id ?? null
  showGruppenModal.value = true
}

const openExportModal = () => {
  showExportModal.value = true
}

const submitParameter = async () => {
  parameterForm.processing = true
  try {
    const response = await axios.post(route('einteilung.parameter.update'), {
      ...contextPayload(),
      runden_anzahl: parameterForm.runden_anzahl,
      standard_kapazitaet: parameterForm.standard_kapazitaet,
      kapazitaeten: parameterForm.kapazitaeten,
    })
    replacePayload(response.data.payload)
    showParameterModal.value = false
    setStatus(response.data.message)
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    parameterForm.processing = false
  }
}

const submitSwitchRunden = async () => {
  if (!switchForm.quelle_runde || !switchForm.ziel_runde || switchForm.quelle_runde === switchForm.ziel_runde) {
    setStatus('Bitte zwei unterschiedliche Runden auswählen.', 'error')
    return
  }

  if (!confirm(`Runde ${switchForm.quelle_runde} komplett mit Runde ${switchForm.ziel_runde} tauschen?`)) return

  switchForm.processing = true
  try {
    const response = await axios.post(route('einteilung.runden.switch'), {
      ...contextPayload(),
      quelle_runde: switchForm.quelle_runde,
      ziel_runde: switchForm.ziel_runde,
    })
    replacePayload(response.data.payload)
    showSwitchModal.value = false
    setStatus(response.data.message)
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    switchForm.processing = false
  }
}

const submitCreate = async () => {
  createForm.processing = true
  try {
    const response = await axios.post(route('einteilung.create'), {
      ...contextPayload(),
      schueler_id: createForm.schueler_id,
      ...roundPayload(createForm),
    })
    replacePayload(response.data.payload)
    showCreateModal.value = false
    setStatus(response.data.message)
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    createForm.processing = false
  }
}

const submitEinteilen = async () => {
  if (!confirm('Alle bestehenden Einteilungen für diese Schule neu generieren?')) return
  isBusy.value = true
  try {
    const response = await axios.post(route('einteilung.store'), contextPayload())
    replacePayload(response.data.payload)
    setStatus(response.data.message, response.data.teilnehmerOhneAuswahl?.length ? 'error' : 'success')
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    isBusy.value = false
  }
}

const submitDestroy = async () => {
  if (!confirm('Alle Einteilungen für diese Schule löschen?')) return
  isBusy.value = true
  try {
    const response = await axios.post(route('einteilung.destroy'), contextPayload())
    replacePayload(response.data.payload)
    setStatus(response.data.message)
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    isBusy.value = false
  }
}

const submitGruppen = async () => {
  gruppenForm.processing = true
  try {
    const response = await axios.post(route('gruppen.generieren'), {
      ...contextPayload(),
      ...gruppenDatePayload(),
      startzeit: gruppenForm.startzeit,
      endzeit: gruppenForm.endzeit,
      raum_id: gruppenForm.raum_id,
      betreuer_id: gruppenForm.betreuer_id,
      bereiche: gruppenForm.bereiche,
    })
    replacePayload(response.data.payload)
    showGruppenModal.value = false
    setStatus(response.data.message)
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    gruppenForm.processing = false
  }
}

const submitExport = async () => {
  exportForm.processing = true
  try {
    const response = await axios.post(route('einteilung.export.excel'), {
      ...contextPayload(),
      eintritt: exportForm.eintritt,
      austritt: exportForm.austritt,
    }, { responseType: 'blob' })

    const disposition = response.headers['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] || 'Einteilung.xlsx'
    const url = URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    showExportModal.value = false
    setStatus('Export wurde erstellt.')
  } catch (error) {
    setStatus(await readError(error), 'error')
  } finally {
    exportForm.processing = false
  }
}

// Modal öffnen
const openEditModal = (schueler) => {
  selectedSchueler.value = schueler
  teilnehmername.value = `${schueler.vorname} ${schueler.nachname}`
  form.schueler_id = schueler.id
  const einteilung = schueler.einteilung_ids || {}
  resetRoundFields(form)
  runden.value.forEach((runde) => {
    form['runde_' + runde] = einteilung[runde] || null
  })
  showModal.value = true
}

// Update via Axios
const submitUpdate = async () => {
  try {
    const response = await axios.post(route('einteilung.update'), {
      schueler_id: form.schueler_id,
      ...roundPayload(form),
      seite: 'schueler',
      ...contextPayload()
    });

    const data = response.data;
    showModal.value = false;
    if (data.payload) {
      replacePayload(data.payload)
      setStatus(data.message)
      return
    }

    const neueEinteilungen = data.einteilung_ids;

    runden.value.forEach(runde => {
      const zielBereichId = neueEinteilungen[runde] || null;

      Object.keys(results.value).forEach(bereichKey => {
        results.value[bereichKey][runde] = results.value[bereichKey][runde].filter(s => s.id !== data.schueler_id);
      });

      if (!zielBereichId) return;
      const bereichObj = allBereiche.value.find(b => b.id === zielBereichId);
      if (!bereichObj) return;

      // 🔹 Key im results-Objekt finden
      const zielBereichKey = Object.keys(results.value).find(k =>
        normalizeKey(k) === normalizeKey(bereichObj.name)
      );
      if (!zielBereichKey) return;

      // 🔹 Schüler hinzufügen
      const schuelerNeu = {
        id: selectedSchueler.value.id,
        vorname: selectedSchueler.value.vorname,
        nachname: selectedSchueler.value.nachname,
        klasse: selectedSchueler.value.klasse,
        geschlecht: selectedSchueler.value.geschlecht,
        einteilung_ids: { ...neueEinteilungen },
        _uuid: crypto.randomUUID() + '-' + runde
      };

      results.value[zielBereichKey][runde].push(schuelerNeu);
    });

  } catch (error) {
    console.error('Fehler:', error.response?.data || error);
    setStatus(await readError(error), 'error')
  }
};

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}
</script>

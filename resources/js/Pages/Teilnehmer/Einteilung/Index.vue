<template>
  <Head title="Einteilung" />

  <app-layout>
    <template #header>Einteilung</template>

    <div class="p-4 space-y-6 overflow-y-auto h-[80vh]">
        <div v-for="runde in [1, 2, 3]" :key="runde">

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
                        @click="openEditModal(schueler)"
                        class="cursor-pointer hover:bg-gray-200 rounded transition group"
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
                        Summe: {{ results[bereich]?.[runde]?.length || 0 }}
                    </div>
                    </td>
                </tr>
                </tbody>

            </table>
            </div>
        </div>

        <div v-if="updated_at" class="mt-4 p-2 bg-blue-50 text-blue-800 rounded text-sm inline-block">
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
            <div v-for="r in [1, 2, 3]" :key="r" class="flex flex-col">
              <label :for="`runde-${r}`" class="text-sm font-semibold text-gray-700 mb-1">
                Bereich Runde {{ r }}
              </label>
              <select
                v-model="form['runde_' + r]"
                :id="`runde-${r}`"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-zbb focus:border-zbb sm:text-sm"
              >
                <option :value="null">-- Kein Bereich --</option>
                <option v-for="b in alle_bereiche" :key="b.id" :value="b.id">
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
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/ModalForm.vue';
import { Head } from '@inertiajs/vue3'
import { ref, computed, reactive } from 'vue'
import InputText from 'primevue/inputtext';
import axios from 'axios'

const props = defineProps({
  results: Object,
  alle_bereiche: Array, // vom Controller
  updated_at: String,
})

const teilnehmername = ref('');
const showModal = ref(false)
const selectedSchueler = ref(null)
const results = ref(JSON.parse(JSON.stringify(props.results)));
// Bereichsnamen für Tabellen-Header nur für die Anzeige
const headerBereiche = computed(() => {
  // Holen wir uns die Originalnamen aus alle_bereiche
  return props.alle_bereiche.map(b => b.name);
});

const form = reactive({
  schueler_id: null,
  runde_1: null,
  runde_2: null,
  runde_3: null,
})
const bereiche = computed(() => Object.keys(props.results || {}))

// === Bereichsnamen für Anzeige ===

// normalizeKey nur für Keys & interne Logik
function normalizeKey(str) {
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9]/g, '')
    .toLowerCase();
}

// Modal öffnen
const openEditModal = (schueler) => {
  selectedSchueler.value = schueler
  teilnehmername.value = `${schueler.vorname} ${schueler.nachname}`
  form.schueler_id = schueler.id
  const einteilung = schueler.einteilung_ids || {}
  form.runde_1 = einteilung[1] || null
  form.runde_2 = einteilung[2] || null
  form.runde_3 = einteilung[3] || null
  showModal.value = true
}

// Update via Axios
const submitUpdate = async () => {
  try {
    const response = await axios.post(route('einteilung.update'), {
      schueler_id: form.schueler_id,
      runde_1: form.runde_1,
      runde_2: form.runde_2,
      runde_3: form.runde_3,
      seite: 'schueler'
    });

    const data = response.data;
    showModal.value = false;

    const neueEinteilungen = data.einteilung_ids;

    [1,2,3].forEach(runde => {
      const zielBereichId = neueEinteilungen[runde] || null;

      Object.keys(results.value).forEach(bereichKey => {
        results.value[bereichKey][runde] = results.value[bereichKey][runde].filter(s => s.id !== data.schueler_id);
      });

      if (!zielBereichId) return;
      const bereichObj = props.alle_bereiche.find(b => b.id === zielBereichId);
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

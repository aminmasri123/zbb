<script setup>
    import { ref, computed, onMounted } from 'vue'
    import { Head, router } from '@inertiajs/vue3'
    import AppLayout from '@/Layouts/AppLayout.vue'
    import InputText from 'primevue/inputtext'
    import FloatLabel from 'primevue/floatlabel'
    import Select from 'primevue/select'
    import MultiSelect from 'primevue/multiselect'
    import Dialog from 'primevue/dialog'
    import Button from 'primevue/button' // ✅ FEHLTE
    import Swal from 'sweetalert2'
    import axios from 'axios' // ✅ FEHLTE
    import { formatTime } from '@/utils/timeFormat'

    // --- Props ---
    const props = defineProps({
    gruppe: { type: Object, required: true },
    teilnehmer: { type: Array, required: true },
    anwesenheitsstatuten: { type: Array, required: true },
    })
    console.log('Props:', props.gruppe)
    // Modal-Steuerung + Auswahl
    const showTeilnehmerModal = ref(false)
    const selectedTeilnehmerIds = ref([])

    // --- Hilfsfunktion für Farben je nach Status ---
const statusFarbe = (statusName) => {
  if (!statusName) return { backgroundColor: '#d1d5db' } // grau fallback

  const item = props.anwesenheitsstatuten.find(
    s => s.status?.toLowerCase() === statusName.toLowerCase()
  )

  return item?.hex ? { backgroundColor: item.hex } : { backgroundColor: '#d1d5db' }
}


const zeitgeplantStart = ref();
const zeitgeplantEnd = ref();
const datumgeplantStart = ref();
const datumgeplantEnd = ref();

zeitgeplantStart.value = formatTime(props.gruppe.startzeit);
zeitgeplantEnd.value = formatTime(props.gruppe.endzeit);
datumgeplantStart.value = props.gruppe.anfangsdatum;
datumgeplantEnd.value =props.gruppe.enddatum;


// Funktion, um nach Klick auf „Übernehmen“ die ausgewählten Teilnehmer hinzuzufügen
const confirmTeilnehmer = async () => {
  if (selectedTeilnehmerIds.value.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Keine Auswahl',
      text: 'Bitte wähle mindestens einen Teilnehmer aus.',
    });
    return;
  }

  try {
    const response = await axios.post('/gruppehasteilnehmer/anlegen', {
      gruppe_id: props.gruppe.id,
      teilnehmer: selectedTeilnehmerIds.value,
      startzeit: zeitgeplantStart.value,
      endzeit: zeitgeplantEnd.value,
      startdatum: datumgeplantStart.value,
      enddatum: datumgeplantEnd.value,
    });

    const data = response.data;
    console.log('✅ RESPONSE:', data);

    // --- Modal zuerst schließen ---
    showTeilnehmerModal.value = false;
    selectedTeilnehmerIds.value = [];

    // --- Jetzt DOM-Update abwarten, bevor SweetAlert geöffnet wird ---
    await new Promise(resolve => setTimeout(resolve, 300));

    // --- SweetAlert anzeigen ---
    let message = data.message;
    if (data.added?.length) {
      message += `\n✅ Hinzugefügt: ${data.added.map(t => `${t.vorname} ${t.nachname}`).join(', ')}`;
    }
    if (data.already?.length) {
      message += `\n⚠️ Bereits vorhanden: ${data.already.map(t => `${t.vorname} ${t.nachname}`).join(', ')}`;
    }

    await Swal.fire({
      icon: 'success',
      title: 'Teilnehmer aktualisiert',
      text: message,
      confirmButtonText: 'OK',
    });

    // --- Tabelle sofort aktualisieren ---
    if (data.added?.length) {
      data.added.forEach(nt => {
        const existiert = gruppenTeilnehmer.value.some(t => t.id === nt.id);
        if (!existiert) {
          gruppenTeilnehmer.value.push({
            ...nt,
            anwesenheit: tage.value.map(() => 'unentschuldigt'),
            zeiten: tage.value.map(() => ({
              start: zeitgeplantStart.value,
              ende: zeitgeplantEnd.value,
            })),
          });
        }
      });
    }

  } catch (error) {
    console.error('❌ Fehler:', error);

    showTeilnehmerModal.value = false; // sicherheitshalber
    await new Promise(resolve => setTimeout(resolve, 300));

    await Swal.fire({
      icon: 'error',
      title: 'Fehler',
      text: error.response?.data?.message || 'Teilnehmer konnten nicht hinzugefügt werden.',
    });
  }
};



// --- Datumsbereich (inkl. Enddatum) ---
function generateDateRangeInclusive(start, end) {
  const result = []
  const startDate = new Date(start + 'T00:00:00')
  const endDate = new Date(end + 'T00:00:00')

  let current = new Date(startDate)
  let index = 1

  while (current <= endDate) {
    const weekday = current.toLocaleDateString('de-DE', { weekday: 'short' })
    const day = current.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })

    const localDate = [
      current.getFullYear(),
      String(current.getMonth() + 1).padStart(2, '0'),
      String(current.getDate()).padStart(2, '0'),
    ].join('-')

    result.push({
      label: `Tag ${index}`,
      datum: `${weekday}, ${day}.`,
      date: localDate,
    })

    current.setDate(current.getDate() + 1)
    index++
  }

  return result
}

const tage = computed(() =>
  props.gruppe?.anfangsdatum && props.gruppe?.enddatum
    ? generateDateRangeInclusive(props.gruppe.anfangsdatum, props.gruppe.enddatum)
    : []
)


// --- Teilnehmer vorbereiten ---
const gruppenTeilnehmer = ref([])
console.log(gruppenTeilnehmer);
const tag = ref([])

onMounted(() => {
  const gruppiert = {}
  props.gruppe.teilnehmer.forEach(t => {
    if (!gruppiert[t.id]) gruppiert[t.id] = []
    gruppiert[t.id].push(t)
  })

  gruppenTeilnehmer.value = Object.values(gruppiert).map(teilnehmerGruppe => {
    const basis = teilnehmerGruppe[0]

    return {
      ...basis,
      anwesenheit: tage.value.map(tag => {
        const eintrag = teilnehmerGruppe.find(tt => tt.pivot?.tag?.datum === tag.date)
        return eintrag?.pivot?.status?.status || 'unentschuldigt'
      }),
      zeiten: tage.value.map(tag => {
        const eintrag = teilnehmerGruppe.find(tt => tt.pivot?.tag?.datum === tag.date)
        return {
          start: eintrag?.pivot?.zeittatsaechlich?.startzeit || props.gruppe.startzeit,
          ende: eintrag?.pivot?.zeittatsaechlich?.endzeit || props.gruppe.endzeit,
        }
      }),
    }
  })
})




//Anwesenheit speichern
const speichernSofort = async (tID, ttag, statusName, startzeit, endzeit) => {
  try {
    const teilnehmerId = tID
    const tag = ttag.date

    const status = props.anwesenheitsstatuten.find(s => s.status === statusName)
    if (!status) return

    console.log('speichernSofort:', { teilnehmerId, tag, statusName, startzeit, endzeit })

    await axios.post('/anwesenheit/update', {
      personen_id: teilnehmerId,
      gruppe_id: props.gruppe.id,
      tag: tag,
      startzeit: startzeit,
      endzeit: endzeit,
      anwesenheitsstatuten_id: status.id,
      bemerkung: null,
    })

    Swal.fire({
      icon: 'success',
      title: 'Gespeichert',
      text: `Status: ${statusName}, Zeit: ${startzeit} – ${endzeit}`,
      timer: 1500,
      showConfirmButton: false,
    })
  } catch (error) {
    console.error(error)
    Swal.fire({
      icon: 'error',
      title: 'Fehler beim Speichern',
      text: error.response?.data?.message || 'Unbekannter Fehler.',
    })
  }
}


</script>

<template>
  <Head title="Teilnehmer verwalten" />

  <AppLayout >
    <template #header>
            Teilnehmerverwaltung | {{props.gruppe.bereich.name}} ({{ new Date(props.gruppe.anfangsdatum).toLocaleDateString('de-DE') }} – {{ new Date(props.gruppe.enddatum).toLocaleDateString('de-DE') }}),   von {{ formatTime(props.gruppe.startzeit) }} bis {{ formatTime(props.gruppe.endzeit) }}

    </template>

    <div class="p-6 space-y-8 bg-white rounded-lg shadow-sm ">
      <!-- Teilnehmer hinzufügen -->
      <div class="bg-gray-50 rounded-lg p-4 border shadow-sm">
        <h3 class="font-semibold mb-3 text-gray-700">Teilnehmer hinzufügen</h3>

        <Button
          label="➕ Teilnehmer hinzufügen"
          icon="pi pi-users"
          class="w-full !bg-orange-500 hover:!bg-orange-600 border-none"
          @click="showTeilnehmerModal = true"
        />

        <Dialog
          v-model:visible="showTeilnehmerModal"
          modal
          header="➕ Teilnehmer hinzufügen"
          :style="{ width: '700px', maxWidth: '95vw' }"
          :draggable="false"
          appendTo="body"
          dismissableMask
        >
          <div class="space-y-4">
             <div class="flex gap-2">
                <div class="w-full">
                    <label for="abteilungDelete">Von*</label>
                    <InputText type="time" v-model="zeitgeplantStart" class="w-full" />
                </div>

                <div class="w-full">
                    <label for="abteilungDelete">Bis*</label>
                    <InputText type="time" v-model="zeitgeplantEnd" class="w-full" />
                </div>
            </div>


            <div class="flex gap-2">
                <div class="w-full">
                    <label for="abteilungDelete">Von*</label>
                    <InputText type="date" v-model="datumgeplantStart" class="w-full" />
                </div>

                <div class="w-full">
                    <label for="abteilungDelete">Bis*</label>
                    <InputText type="date" v-model="datumgeplantEnd" class="w-full" />
                </div>
            </div>
            <FloatLabel variant="on">
              <MultiSelect
                v-model="selectedTeilnehmerIds"
                :options="props.teilnehmer"
                :filter="true"
                display="chip"
                optionValue="id"
                :optionLabel="(t) => `${t.vorname} ${t.nachname}`"
                placeholder="Teilnehmer auswählen"
                class="w-full"
                appendTo="body"
                panelClass="z-[9999]"
              />
            </FloatLabel>



            <div class="flex justify-end gap-2 pt-2">
              <Button
                label="Abbrechen"
                class="p-button-text hover:!bg-zbbTrp !text-zbb"
                @click="showTeilnehmerModal = false"
              />
              <Button
                label="Übernehmen"
                icon="pi pi-check"
                class="!bg-zbb hover:!bg-zbb/80 border-none"
                @click="confirmTeilnehmer"
              />
            </div>
          </div>
        </Dialog>
      </div>

      <!-- Anwesenheit -->
      <div class="space-y-4">
        <h3 class="font-semibold text-gray-700">Anwesenheit verwalten</h3>

        <!-- Anwesenheitsstatuten Agenda-->
        <div class="flex items-center gap-6 bg-zbbTrp border p-3 rounded">
          <div
            v-for="s in props.anwesenheitsstatuten"
            :key="s.status"
            class="flex items-center gap-2 text-sm"
          >
            <span class="w-3 h-3 rounded-full " :style="statusFarbe(s.status)"></span>
            {{ s.status }}
          </div>
        </div>

        <!-- Tabelle -->
        <div class="overflow-x-auto max-w-full">
          <table class="w-full table-fixed text-sm border-collapse border shadow-sm ">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="border px-3 py-2 text-left">Teilnehmer</th>
                <th
                  v-for="tag in tage"
                  :key="tag.label"
                  class="border px-3 py-2 text-center"
                >
                  <div class="flex flex-col items-center">
                    <span class="font-semibold">{{ tag.label }}</span>
                    <span class="text-xs text-gray-500">{{ tag.datum }}</span>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(t, tIndex) in gruppenTeilnehmer"
                :key="t.id"
                class="hover:bg-gray-50"
              >
                <td class="border px-4 py-3 font-medium text-gray-800">
                  <p>{{ t.vorname }} {{ t.nachname }}</p>
                  <span class="text-sm text-zbb">{{ formatTime(t.pivot?.zeitgeplant?.startzeit) }} - {{formatTime(t.pivot?.zeitgeplant?.endzeit)}}</span>
                </td>
                <td v-for="(tttag, dayIndex) in tage" :key="dayIndex" class="border px-4 py-3 text-center">
                    <div class="flex flex-col gap-1 items-center">

                        <!-- Anwesenheitsstatus -->

                        <Select
                        v-model="gruppenTeilnehmer[tIndex].anwesenheit[dayIndex]"
                        :options="props.anwesenheitsstatuten"
                        optionLabel="status"
                        optionValue="status"
                        class="text-sm"
                       @change="speichernSofort(
                            t.id,
                            tttag,
                            gruppenTeilnehmer[tIndex].anwesenheit[dayIndex],
                            t.pivot.zeittatsaechlich.startzeit,
                            t.pivot.zeittatsaechlich.endzeit
                        )"
                    >
                    <template #value="slotProps">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full inline-block" :style="statusFarbe(slotProps.value)" >

                            </span> {{ slotProps.value }}
                        </div>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full inline-block" :style="statusFarbe(slotProps.option.status)" ></span>
                            {{ slotProps.option.status }}
                        </div>
                    </template>
                </Select>


                        <!-- Zeiten -->
                        <div class="flex gap-1 justify-center mt-1">
                        <InputText
                            type="time"
                            v-model="t.zeiten[dayIndex].start"
                            @blur="speichernSofort(
                                t.id,
                                tttag,
                                gruppenTeilnehmer[tIndex].anwesenheit[dayIndex],
                                t.zeiten[dayIndex].start,
                                t.zeiten[dayIndex].ende
                            )"
                        />

                        <InputText
                            type="time"
                            v-model="t.zeiten[dayIndex].ende"
                            @blur="speichernSofort(
                                t.id,
                                tttag,
                                gruppenTeilnehmer[tIndex].anwesenheit[dayIndex],
                                t.zeiten[dayIndex].start,
                                t.zeiten[dayIndex].ende
                            )"
                        />

                        </div>
                    </div>
                </td>

              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

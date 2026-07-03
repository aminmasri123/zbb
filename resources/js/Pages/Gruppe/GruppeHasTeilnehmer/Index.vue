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
    bopLegacyExporte: { type: Array, default: () => [] },
    })
    console.log('Props:', props.gruppe    )
    // Modal-Steuerung + Auswahl
    const showTeilnehmerModal = ref(false)
    const showExportDialog = ref(false)
    const exportSuche = ref('')
    const legacyExportLoading = ref(null)
    const selectedTeilnehmerIds = ref([])

    // --- Hilfsfunktion für Farben je nach Status ---
const statusFarbe = (statusName) => {
  if (!statusName) return { backgroundColor: '#d1d5db' } // grau fallback

  const item = props.anwesenheitsstatuten.find(
    s => s.status?.toLowerCase() === statusName.toLowerCase()
  );

  return item?.farben
    ? { backgroundColor: item.farben }  // ← HEX Wert aus DB!
    : { backgroundColor: '#d1d5db' };
}




const zeitgeplantStart = ref();
const zeitgeplantEnd = ref();
const tatstartTime  = ref();
const tatendTime = ref();
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

const exportVorlagen = computed(() =>
  (props.gruppe?.projekt?.dokumente || []).filter((dokument) =>
    dokument.dateipfad &&
    dokument.pivot?.gruppen_export &&
    dokument.pivot?.serienbrief
  )
)

const exportFormate = (dokument) => {
  if (Array.isArray(dokument.ausgabeformate) && dokument.ausgabeformate.length) {
    return dokument.ausgabeformate
  }

  if (dokument.typ === 'excel') return ['xlsx', 'pdf']
  if (dokument.typ === 'pdf') return ['pdf']
  return ['docx', 'pdf']
}

const gefilterteExportVorlagen = computed(() => {
  const suche = exportSuche.value.trim().toLowerCase()
  if (!suche) return exportVorlagen.value

  return exportVorlagen.value.filter((dokument) =>
    [dokument.name, dokument.typ, dokument.kontext, ...(dokument.ausgabeformate || [])]
      .filter(Boolean)
      .some((wert) => String(wert).toLowerCase().includes(suche))
  )
})

const bopLegacyExporte = computed(() => props.bopLegacyExporte || [])

const gefilterteBopLegacyExporte = computed(() => {
  const suche = exportSuche.value.trim().toLowerCase()
  if (!suche) return bopLegacyExporte.value

  return bopLegacyExporte.value.filter((item) =>
    [item.name, item.typ, item.format]
      .filter(Boolean)
      .some((wert) => String(wert).toLowerCase().includes(suche))
  )
})

const exportTreffer = computed(() => gefilterteExportVorlagen.value.length + gefilterteBopLegacyExporte.value.length)
const exportGesamt = computed(() => exportVorlagen.value.length + bopLegacyExporte.value.length)

const formatLabel = (format) => String(format).toUpperCase()

const exportHref = (dokument, format) =>
  route('gruppe.export.serienbrief', {
    gruppe: props.gruppe.id,
    dokument: dokument.id,
    format,
  })

const fileNameFromResponse = (response, fallback) => {
  const disposition = response.headers?.['content-disposition'] || ''
  const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1]
  if (encoded) return decodeURIComponent(encoded)

  const plain = disposition.match(/filename="?([^";]+)"?/i)?.[1]
  return plain || fallback || 'export'
}

const downloadBlob = (response, fallbackName) => {
  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', fileNameFromResponse(response, fallbackName))
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

const startBopLegacyExport = async (item) => {
  if (!item?.url) return

  if ((item.method || 'get').toLowerCase() !== 'post') {
    window.location.href = item.url
    return
  }

  legacyExportLoading.value = item.id

  try {
    const response = await axios.post(item.url, item.payload || {}, {
      responseType: 'blob',
    })
    downloadBlob(response, item.fileName || `${item.name}.${String(item.format || 'docx').toLowerCase()}`)
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Export fehlgeschlagen.', 'error')
  } finally {
    legacyExportLoading.value = null
  }
}


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
const speichernSofort = async (tID, ttag, statusName, tatstartTime, tatendTime) => {
  try {
    const teilnehmerId = tID
    const tag = ttag.date

    const status = props.anwesenheitsstatuten.find(s => s.status === statusName)
    if (!status) return

    console.log('speichernSofort:', { teilnehmerId, tag, statusName, tatstartTime , tatendTime })

    //await axios.post('/anwesenheit/update', {
    router.post('/anwesenheit/update', {
      personen_id: teilnehmerId,
      gruppe_id: props.gruppe.id,
      tag: tag,
      tatstartTime: formatTime(tatstartTime),
      tatendTime: formatTime(tatendTime),
      anwesenheitsstatuten_id: status.id,
      bemerkung: null,
    })

    Swal.fire({
      icon: 'success',
      title: 'Gespeichert',
      text: `Status: ${statusName}, Zeit: ${tatstartTime} – ${tatendTime}`,
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
const exportMitTag = async () => {
    showExportDialog.value = false;

    const options = tage.value.map(t => ({
        value: t.date,
        label: `${t.label} (${t.datum})`
    }));

    const { value: ausgewahlt } = await Swal.fire({
        title: "Welchen Tag exportieren?",
        input: "select",
        inputOptions: options.reduce((acc, t) => {
            acc[t.value] = t.label;
            return acc;
        }, {}),
        inputPlaceholder: "Bitte einen Tag auswählen",
        showCancelButton: true,
        confirmButtonText: "Export starten",
        cancelButtonText: "Abbrechen",
    });

    if (!ausgewahlt) return;

    // Direkt Download starten
    window.location.href = route("export.anwesenheitslite_V1", {
        id: props.gruppe.id,
        tag: ausgewahlt
    });
};

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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold text-gray-700">Anwesenheit verwalten</h3>
            <Button
              label="Exportieren"
              icon="pi pi-download"
              class="!bg-zbb hover:!bg-zbb/80 border-none"
              @click="showExportDialog = true"
            />
        </div>

        <Dialog
          v-model:visible="showExportDialog"
          modal
          header="Exportieren"
          :style="{ width: '760px', maxWidth: '94vw' }"
          :draggable="false"
          appendTo="body"
          dismissableMask
        >
          <div class="space-y-4">
            <button
              type="button"
              class="flex w-full items-center justify-between rounded border border-gray-200 bg-white px-4 py-3 text-left text-sm hover:border-zbb hover:bg-zbbTrp"
              @click="exportMitTag"
            >
              <span>
                <span class="block font-semibold text-gray-800">Anwesenheitsliste</span>
                <span class="block text-xs text-gray-500">{{ tage.length }} Tage</span>
              </span>
              <i class="las la-file-export text-xl text-zbb"></i>
            </button>

            <div class="flex items-center gap-2">
              <InputText
                v-model="exportSuche"
                class="w-full"
                placeholder="Vorlage suchen"
              />
              <span class="shrink-0 rounded bg-gray-100 px-3 py-2 text-xs text-gray-600">
                {{ exportTreffer }} / {{ exportGesamt }}
              </span>
            </div>

            <div v-if="gefilterteBopLegacyExporte.length" class="rounded border border-gray-200 bg-white">
              <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                BOP-Funktionen
              </div>
              <div
                v-for="item in gefilterteBopLegacyExporte"
                :key="item.id"
                class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 hover:bg-gray-50"
              >
                <div class="min-w-0">
                  <div class="truncate font-medium text-gray-800" :title="item.name">{{ item.name }}</div>
                  <div class="mt-1 flex flex-wrap gap-1 text-xs text-gray-500">
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ item.typ }}</span>
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ item.format }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  class="inline-flex h-9 min-w-20 items-center justify-center rounded border border-zbb/30 px-3 text-xs font-semibold text-zbb hover:bg-zbb hover:text-white disabled:opacity-60"
                  :disabled="legacyExportLoading === item.id"
                  @click="startBopLegacyExport(item)"
                >
                  {{ legacyExportLoading === item.id ? 'Lädt...' : item.format }}
                </button>
              </div>
            </div>

            <div class="max-h-[52vh] overflow-y-auto rounded border border-gray-200 bg-white">
              <div v-if="gefilterteExportVorlagen.length" class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                Vorlagen
              </div>
              <div
                v-for="dok in gefilterteExportVorlagen"
                :key="dok.id"
                class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 hover:bg-gray-50"
              >
                <div class="min-w-0">
                  <div class="truncate font-medium text-gray-800" :title="dok.name">{{ dok.name }}</div>
                  <div class="mt-1 flex flex-wrap gap-1 text-xs text-gray-500">
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ dok.typ?.toUpperCase() }}</span>
                    <span v-if="dok.kontext" class="rounded bg-gray-100 px-2 py-0.5">{{ dok.kontext }}</span>
                  </div>
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-2">
                  <a
                    v-for="format in exportFormate(dok)"
                    :key="dok.id + '-' + format"
                    class="inline-flex h-9 min-w-16 items-center justify-center rounded border border-zbb/30 px-3 text-xs font-semibold text-zbb hover:bg-zbb hover:text-white"
                    :href="exportHref(dok, format)"
                  >
                    {{ formatLabel(format) }}
                  </a>
                </div>
              </div>

              <div v-if="exportTreffer === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                Keine Export-Vorlagen
              </div>
            </div>
          </div>
        </Dialog>

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

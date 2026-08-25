<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Dialog from 'primevue/dialog';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import { usePermissions } from '@/utils/permissions';

const props = defineProps({
  dokumente: { type: Array, default: () => [] },
  projekte: { type: Array, default: () => [] },
  kategorien: { type: Array, default: () => [] },
  bereiche: { type: Array, default: () => [] },
  platzhalter: { type: Array, default: () => [] },
});

const dokumentListe = ref([...(props.dokumente || [])]);
const projektListe = ref([...(props.projekte || [])]);
const kategorieListe = ref([...(props.kategorien || [])]);
const bereichListe = ref([...(props.bereiche || [])]);
const saving = ref(false);
const categorySaving = ref(false);
const search = ref('');
const editingDokument = ref(null);
const showDokumentModal = ref(false);
const fileInputKey = ref(0);
const { can } = usePermissions();
const canStoreDokument = computed(() => can('dokumente.store'));
const canUpdateDokument = computed(() => can('dokumente.update'));
const canDownloadDokument = computed(() => can('dokumente.download'));
const canStoreKategorie = computed(() => can('dokumente.kategorien.store'));
const canUpdateProjektKategorien = computed(() => can('dokumente.projekt-kategorien.update'));
const canSubmitDokument = computed(() => editingDokument.value ? canUpdateDokument.value : canStoreDokument.value);
const canUseDokumentActions = computed(() => canUpdateDokument.value || canDownloadDokument.value);

const defaultForm = () => ({
  name: '',
  typ: 'word',
  kontext: 'teilnehmer',
  einsatzbereich: 'gruppe',
  version: '',
  beschreibung: '',
  datei: null,
  ausgabeformate: ['docx', 'pdf'],
  projekt_ids: [],
  kategorie_ids: [],
  bereich_ids: [],
  gruppen_export: true,
  serienbrief: true,
  gruppen_export_modus: 'einzelne_dateien',
});

const form = ref(defaultForm());

const neueKategorie = ref({
  name: '',
  beschreibung: '',
});

const formatOptionen = computed(() => {
  if (form.value.typ === 'excel') return ['xlsx', 'pdf'];
  if (form.value.typ === 'pdf') return ['pdf'];
  return ['docx', 'pdf'];
});

const filteredDokumente = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return dokumentListe.value;

  return dokumentListe.value.filter((dokument) =>
    [
      dokument.name,
      dokument.typ,
      dokument.kontext,
      dokument.einsatzbereich,
      dokument.version,
      dokument.beschreibung,
      dokument.export_permission,
      dokument.gruppen_export_modus,
      ...(dokument.kategorien || []).map((kategorie) => kategorie.name),
      ...(dokument.projekte || []).map((projekt) => projekt.name),
      ...(dokument.bereiche || []).map((bereich) => bereich.name),
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(term))
  );
});

const setTyp = (typ) => {
  form.value.typ = typ;
  form.value.ausgabeformate = typ === 'excel' ? ['xlsx', 'pdf'] : (typ === 'pdf' ? ['pdf'] : ['docx', 'pdf']);
  form.value.kontext = form.value.einsatzbereich === 'teilnehmer'
    ? 'teilnehmer'
    : (form.value.einsatzbereich === 'partner' ? 'partner' : (typ === 'word' ? 'teilnehmer' : 'gruppe'));
  form.value.gruppen_export_modus = typ === 'word' && form.value.kontext === 'gruppe' ? 'eine_datei' : 'einzelne_dateien';
};

const setKontext = (event) => {
  form.value.kontext = event.target.value;
  if (form.value.einsatzbereich === 'teilnehmer' && form.value.kontext !== 'teilnehmer') {
    form.value.einsatzbereich = form.value.kontext === 'partner' ? 'partner' : 'gruppe';
    form.value.gruppen_export = form.value.einsatzbereich === 'gruppe';
  }
  if (form.value.typ === 'word') {
    form.value.gruppen_export_modus = form.value.kontext === 'gruppe' ? 'eine_datei' : 'einzelne_dateien';
  }
};

const setEinsatzbereich = (event) => {
  form.value.einsatzbereich = event.target.value;

  if (form.value.einsatzbereich === 'teilnehmer') {
    form.value.kontext = 'teilnehmer';
    form.value.gruppen_export = false;
    form.value.serienbrief = true;
    form.value.bereich_ids = [];
  } else if (form.value.einsatzbereich === 'gruppe') {
    form.value.gruppen_export = true;
  } else if (form.value.einsatzbereich === 'partner') {
    form.value.kontext = 'partner';
    form.value.gruppen_export = false;
    form.value.bereich_ids = [];
  }
};

const einsatzbereichLabel = (einsatzbereich) => ({
  partner: 'Partner / Schule',
  teilnehmer: 'Teilnehmerseite',
  gruppe: 'Gruppe',
})[einsatzbereich] || 'Gruppe';

const gruppenExportModusLabel = (modus) => ({
  kopf: 'Nur Kopf',
  eine_datei: 'Eine Datei',
  einzelne_dateien: 'Einzeln/ZIP',
})[modus] || 'Einzeln/ZIP';

const toggleArrayValue = (target, id) => {
  const value = Number(id);
  const index = target.indexOf(value);
  if (index >= 0) {
    target.splice(index, 1);
  } else {
    target.push(value);
  }
};

const onFileChange = (event) => {
  form.value.datei = event.target.files?.[0] || null;
  if (!form.value.name && form.value.datei?.name) {
    form.value.name = form.value.datei.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
  }
};

const resetForm = () => {
  editingDokument.value = null;
  form.value = defaultForm();
  fileInputKey.value += 1;
};

const openCreateDokument = () => {
  resetForm();
  showDokumentModal.value = true;
};

const closeDokumentModal = () => {
  showDokumentModal.value = false;
  resetForm();
};

const refreshFromPage = (page) => {
  dokumentListe.value = [...(page.props.dokumente || [])];
  projektListe.value = [...(page.props.projekte || [])];
  kategorieListe.value = [...(page.props.kategorien || [])];
  bereichListe.value = [...(page.props.bereiche || [])];
};

const editDokument = (dokument) => {
  if (!canUpdateDokument.value) return;

  editingDokument.value = dokument;
  form.value = {
    name: dokument.name || '',
    typ: dokument.typ || 'word',
    kontext: dokument.kontext || 'teilnehmer',
    einsatzbereich: dokument.einsatzbereich || 'gruppe',
    version: dokument.version || '',
    beschreibung: dokument.beschreibung || '',
    datei: null,
    ausgabeformate: [...(dokument.ausgabeformate || [])],
    projekt_ids: (dokument.projekte || []).map((projekt) => Number(projekt.id)),
    kategorie_ids: (dokument.kategorien || []).map((kategorie) => Number(kategorie.id)),
    bereich_ids: (dokument.bereiche || []).map((bereich) => Number(bereich.id)),
    gruppen_export: Boolean(dokument.projekte?.[0]?.pivot?.gruppen_export ?? dokument.kategorien?.[0]?.pivot?.gruppen_export ?? true),
    serienbrief: Boolean(dokument.projekte?.[0]?.pivot?.serienbrief ?? dokument.kategorien?.[0]?.pivot?.serienbrief ?? true),
    gruppen_export_modus: dokument.gruppen_export_modus || (dokument.kontext === 'gruppe' ? 'eine_datei' : 'einzelne_dateien'),
  };
  fileInputKey.value += 1;
  showDokumentModal.value = true;
};

const submit = () => {
  if (!canSubmitDokument.value) return;

  saving.value = true;

  const payload = {
    ...form.value,
  };
  if (editingDokument.value) {
    payload._method = 'put';
  }
  const target = editingDokument.value
    ? route('dokumente.update', editingDokument.value.id)
    : route('dokumente.store');

  router.post(target, payload, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: (page) => {
      Swal.fire('Erfolg', editingDokument.value ? 'Export-Vorlage wurde aktualisiert.' : 'Export-Vorlage wurde angelegt.', 'success');
      refreshFromPage(page);
      closeDokumentModal();
    },
    onError: (errors) => {
      Swal.fire('Fehler', Object.values(errors)[0] || 'Vorlage konnte nicht gespeichert werden.', 'error');
    },
    onFinish: () => {
      saving.value = false;
    },
  });
};

const createKategorie = async () => {
  if (!canStoreKategorie.value) return;

  categorySaving.value = true;

  try {
    const response = await axios.post(route('dokumente.kategorien.store'), neueKategorie.value);
    kategorieListe.value.push(response.data.kategorie);
    kategorieListe.value.sort((a, b) => a.name.localeCompare(b.name));
    neueKategorie.value = { name: '', beschreibung: '' };
    Swal.fire('Erfolg', 'Kategorie wurde angelegt.', 'success');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Kategorie konnte nicht gespeichert werden.', 'error');
  } finally {
    categorySaving.value = false;
  }
};

const projektKategorieIds = (projekt) => (projekt.dokument_kategorien || []).map((kategorie) => Number(kategorie.id));

const toggleProjektKategorie = (projekt, kategorieId) => {
  if (!canUpdateProjektKategorien.value) return;

  const ids = projektKategorieIds(projekt);
  projekt.dokument_kategorien = ids.includes(Number(kategorieId))
    ? (projekt.dokument_kategorien || []).filter((kategorie) => Number(kategorie.id) !== Number(kategorieId))
    : [...(projekt.dokument_kategorien || []), kategorieListe.value.find((kategorie) => Number(kategorie.id) === Number(kategorieId))];
};

const saveProjektKategorien = async (projekt) => {
  if (!canUpdateProjektKategorien.value) return;

  try {
    const response = await axios.put(route('dokumente.projekt-kategorien.update', projekt.id), {
      kategorie_ids: projektKategorieIds(projekt),
    });
    const index = projektListe.value.findIndex((entry) => entry.id === projekt.id);
    if (index !== -1) projektListe.value[index] = response.data.projekt;
    Swal.fire('Erfolg', 'Projekt-Kategorien wurden gespeichert.', 'success');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Projekt-Kategorien konnten nicht gespeichert werden.', 'error');
  }
};

const token = (key) => '${' + key + '}';
</script>

<template>
  <Head title="Dokumentenmanager" />

  <AppLayout>
    <template #header>Dokumentenmanager</template>

    <div class="space-y-6 p-4">
      <section class="space-y-4">
        <Dialog
          v-model:visible="showDokumentModal"
          modal
          :header="editingDokument ? 'Export-Vorlage bearbeiten' : 'Export-Vorlage anlegen'"
          :style="{ width: '720px', maxWidth: '94vw' }"
          :contentStyle="{ maxHeight: '76vh', overflowY: 'auto' }"
          :draggable="false"
          appendTo="body"
          dismissableMask
          @hide="resetForm"
        >
        <form v-if="canStoreDokument || editingDokument" class="space-y-4" @submit.prevent="submit">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p v-if="editingDokument" class="mt-1 text-xs text-gray-500">
                {{ editingDokument.dateipfadName || editingDokument.dateipfad }}
              </p>
            </div>
            <button
              v-if="editingDokument && canStoreDokument"
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:border-zbb hover:text-zbb"
              @click="resetForm"
            >
              Neu
            </button>
          </div>

          <div class="space-y-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase text-gray-500">Bezeichnung</span>
              <input v-model="form.name" class="w-full rounded border-gray-300 text-sm" placeholder="z.B. Toilettennutzungsliste BOP" />
            </label>

            <div>
              <div class="mb-1 text-xs font-semibold uppercase text-gray-500">Vorlagentyp</div>
              <div class="grid grid-cols-3 gap-2">
              <button
                v-for="typ in ['word', 'excel', 'pdf']"
                :key="typ"
                type="button"
                class="rounded border px-3 py-2 text-sm"
                :class="form.typ === typ ? 'border-zbb bg-zbb text-white' : 'border-gray-300 bg-white text-gray-700'"
                @click="setTyp(typ)"
              >
                {{ typ.toUpperCase() }}
              </button>
              </div>
            </div>

            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase text-gray-500">Datenbezug</span>
              <select :value="form.kontext" class="w-full rounded border-gray-300 text-sm" @change="setKontext">
                <option value="teilnehmer">Teilnehmer</option>
                <option value="gruppe">Gruppe</option>
                <option value="partner">Partner / Schule</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase text-gray-500">Anzeigeort</span>
              <select :value="form.einsatzbereich" class="w-full rounded border-gray-300 text-sm" @change="setEinsatzbereich">
                <option value="gruppe">Gruppe</option>
                <option value="teilnehmer">Teilnehmerseite</option>
                <option value="partner">Partner / Schule</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase text-gray-500">Version / Programm</span>
              <input v-model="form.version" class="w-full rounded border-gray-300 text-sm" placeholder="z.B. BOP-Programm" />
            </label>
            <div class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">
                {{ editingDokument ? 'Datei ersetzen' : 'Datei' }}
              </div>
              <input
                :key="fileInputKey"
                type="file"
                class="w-full rounded border border-gray-300 p-2 text-sm"
                @change="onFileChange"
              />
              <div v-if="editingDokument" class="mt-2 flex flex-wrap gap-2">
                <a
                  v-if="canDownloadDokument"
                  class="inline-flex rounded border border-zbb/30 px-3 py-1 text-xs font-semibold text-zbb hover:bg-zbb hover:text-white"
                  :href="route('dokumente.download', editingDokument.id)"
                >
                  Aktuelle Datei herunterladen
                </a>
                <span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">
                  Ohne neue Datei bleiben Word/Excel/PDF unverändert.
                </span>
              </div>
            </div>

            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase text-gray-500">Beschreibung</span>
              <textarea
                v-model="form.beschreibung"
                rows="3"
                class="w-full rounded border-gray-300 text-sm"
                placeholder="Beschreibung / interne Notiz"
              ></textarea>
            </label>

            <div class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Ausgabe</div>
              <label v-for="format in formatOptionen" :key="format" class="mr-4 inline-flex items-center gap-2 text-sm">
                <input v-model="form.ausgabeformate" type="checkbox" :value="format" class="rounded border-gray-300 text-zbb" />
                {{ format.toUpperCase() }}
              </label>
            </div>

            <div class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Kategorien</div>
              <label v-for="kategorie in kategorieListe" :key="kategorie.id" class="mr-4 inline-flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-zbb"
                  :checked="form.kategorie_ids.includes(kategorie.id)"
                  @change="toggleArrayValue(form.kategorie_ids, kategorie.id)"
                />
                {{ kategorie.name }}
              </label>
            </div>

            <div v-if="form.einsatzbereich === 'gruppe'" class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Bereiche</div>
              <label v-for="bereich in bereichListe" :key="bereich.id" class="mb-1 mr-4 inline-flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-zbb"
                  :checked="form.bereich_ids.includes(bereich.id)"
                  @change="toggleArrayValue(form.bereich_ids, bereich.id)"
                />
                {{ bereich.name }}
              </label>
              <div v-if="form.bereich_ids.length === 0" class="mt-2 inline-flex rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">
                Alle Bereiche
              </div>
            </div>

            <div class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Direkte Projekte</div>
              <div class="max-h-36 overflow-y-auto">
                <label v-for="projekt in projektListe" :key="projekt.id" class="mb-1 flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300 text-zbb"
                    :checked="form.projekt_ids.includes(projekt.id)"
                    @change="toggleArrayValue(form.projekt_ids, projekt.id)"
                  />
                  {{ projekt.name }}
                </label>
              </div>
            </div>

            <div v-if="form.einsatzbereich === 'gruppe'" class="flex flex-wrap gap-4 text-sm">
              <label class="inline-flex items-center gap-2">
                <input v-model="form.gruppen_export" type="checkbox" class="rounded border-gray-300 text-zbb" />
                Gruppen-Export
              </label>
              <label class="inline-flex items-center gap-2">
                <input v-model="form.serienbrief" type="checkbox" class="rounded border-gray-300 text-zbb" />
                Platzhalter füllen
              </label>
            </div>

            <div v-if="form.einsatzbereich === 'gruppe' && form.typ === 'word' && form.gruppen_export" class="rounded border border-gray-200 p-3">
              <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Gruppenexport-Art</div>
              <div class="grid gap-2">
                <label class="flex cursor-pointer gap-3 rounded border p-3 text-sm" :class="form.gruppen_export_modus === 'kopf' ? 'border-zbb bg-zbbTrp' : 'border-gray-200'">
                  <input v-model="form.gruppen_export_modus" type="radio" value="kopf" class="mt-1 border-gray-300 text-zbb" />
                  <span>
                    <span class="block font-medium text-gray-800">Nur Kopf fuellen</span>
                    <span class="text-xs text-gray-500">Eine Datei, nur Gruppenwerte wie Datum, Bereich, Raum und Betreuer. Teilnehmerfelder bleiben leer.</span>
                  </span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded border p-3 text-sm" :class="form.gruppen_export_modus === 'eine_datei' ? 'border-zbb bg-zbbTrp' : 'border-gray-200'">
                  <input v-model="form.gruppen_export_modus" type="radio" value="eine_datei" class="mt-1 border-gray-300 text-zbb" />
                  <span>
                    <span class="block font-medium text-gray-800">Serienbrief in eine Datei</span>
                    <span class="text-xs text-gray-500">Eine DOCX/PDF mit allen Teilnehmern, passend fuer Listen wie Toilettennutzungsliste.</span>
                  </span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded border p-3 text-sm" :class="form.gruppen_export_modus === 'einzelne_dateien' ? 'border-zbb bg-zbbTrp' : 'border-gray-200'">
                  <input v-model="form.gruppen_export_modus" type="radio" value="einzelne_dateien" class="mt-1 border-gray-300 text-zbb" />
                  <span>
                    <span class="block font-medium text-gray-800">Serienbrief einzeln</span>
                    <span class="text-xs text-gray-500">Eine Datei pro Teilnehmer, als ZIP. Sinnvoll fuer Zertifikate oder Einzelbriefe.</span>
                  </span>
                </label>
              </div>
            </div>

            <div class="flex gap-2">
              <button v-if="canSubmitDokument" type="submit" class="w-full rounded bg-zbb px-4 py-2 text-white disabled:opacity-60" :disabled="saving">
                {{ saving ? 'Speichert...' : (editingDokument ? 'Aktualisieren' : 'Speichern') }}
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-4 py-2 text-gray-600 hover:border-zbb hover:text-zbb"
                @click="closeDokumentModal"
              >
                Abbrechen
              </button>
            </div>
          </div>
        </form>
        </Dialog>

        <div class="rounded border bg-white p-4 shadow-sm">
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <input v-model="search" class="w-full rounded border-gray-300 text-sm" placeholder="Vorlagen suchen" />
            <button
              v-if="canStoreDokument"
              type="button"
              class="inline-flex h-10 shrink-0 items-center justify-center rounded bg-zbb px-4 text-sm font-semibold text-white hover:bg-zbb/80"
              @click="openCreateDokument"
            >
              <i class="las la-plus mr-2"></i>
              Neue Vorlage
            </button>
          </div>

          <div class="overflow-visible">
            <table class="w-full table-fixed text-sm">
              <colgroup>
                <col class="w-[30%]" />
                <col class="w-[9%]" />
                <col class="w-[10%]" />
                <col class="w-[13%]" />
                <col class="w-[9%]" />
                <col class="w-[10%]" />
                <col class="w-[14%]" />
                <col v-if="canUseDokumentActions" class="w-[5%]" />
              </colgroup>
              <thead class="bg-gray-100 text-left text-gray-600">
                <tr>
                  <th class="px-3 py-2">Vorlage</th>
                  <th class="px-3 py-2">Typ</th>
                  <th class="px-3 py-2">Anzeigeort</th>
                  <th class="px-3 py-2">Bereiche</th>
                  <th class="px-3 py-2">Ausgabe</th>
                  <th class="px-3 py-2">Kategorien</th>
                  <th class="px-3 py-2">Projekte</th>
                  <th v-if="canUseDokumentActions" class="px-3 py-2 text-center">
                    <span class="sr-only">Aktionen</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="dokument in filteredDokumente" :key="dokument.id" class="border-t">
                  <td class="px-3 py-2 align-top">
                    <div class="font-medium text-gray-800">{{ dokument.name }}</div>
                    <div class="break-words text-xs text-gray-500">{{ dokument.dateipfadName || dokument.dateipfad }}</div>
                    <div v-if="dokument.export_permission" class="mt-1 break-words text-[11px] font-medium text-zbb">
                      {{ dokument.export_permission }}
                    </div>
                  </td>
                  <td class="px-3 py-2 align-top">{{ dokument.typ }} / {{ dokument.kontext }}</td>
                  <td class="px-3 py-2 align-top">
                    <span class="rounded bg-gray-100 px-2 py-1 text-xs">
                      {{ einsatzbereichLabel(dokument.einsatzbereich) }}
                    </span>
                    <span v-if="dokument.einsatzbereich === 'gruppe' && dokument.typ === 'word'" class="mt-1 inline-flex rounded bg-zbbTrp px-2 py-1 text-xs">
                      {{ gruppenExportModusLabel(dokument.gruppen_export_modus) }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span v-if="!dokument.bereiche?.length" class="rounded bg-gray-100 px-2 py-1 text-xs">Alle Bereiche</span>
                    <span v-for="bereich in dokument.bereiche" :key="bereich.id" class="mr-1 rounded bg-gray-100 px-2 py-1 text-xs">
                      {{ bereich.name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span v-for="format in (dokument.ausgabeformate || [])" :key="format" class="mr-1 rounded bg-gray-100 px-2 py-1 text-xs">
                      {{ format.toUpperCase() }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span v-for="kategorie in dokument.kategorien" :key="kategorie.id" class="mr-1 rounded bg-zbbTrp px-2 py-1 text-xs">
                      {{ kategorie.name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span v-for="projekt in dokument.projekte" :key="projekt.id" class="mr-1 rounded bg-gray-100 px-2 py-1 text-xs">
                      {{ projekt.name }}
                    </span>
                  </td>
                  <td v-if="canUseDokumentActions" class="px-3 py-2 align-top text-center">
                    <Dropdown align="right" width="48" :content-classes="['bg-white', 'py-1']">
                      <template #trigger>
                        <button
                          v-if="canUpdateDokument"
                          type="button"
                          class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 hover:border-zbb hover:bg-zbbTrp hover:text-zbb"
                          aria-label="Aktionen anzeigen"
                        >
                          <i class="la la-ellipsis-v la-lg"></i>
                        </button>
                      </template>
                      <template #content>
                        <button
                          type="button"
                          class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm text-gray-700 hover:bg-zbbTrp hover:text-zbb"
                          @click="editDokument(dokument)"
                        >
                          <span>Bearbeiten</span>
                          <i class="las la-edit"></i>
                        </button>
                        <a
                          v-if="canDownloadDokument"
                          class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm text-gray-700 hover:bg-zbbTrp hover:text-zbb"
                          :href="route('dokumente.download', dokument.id)"
                        >
                          <span>Datei</span>
                          <i class="las la-download"></i>
                        </a>
                      </template>
                    </Dropdown>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-[minmax(360px,430px)_1fr]">
        <div v-if="canStoreKategorie" class="rounded border bg-white p-4 shadow-sm">
          <h2 class="mb-4 text-base font-semibold text-gray-700">Kategorie anlegen</h2>
          <div class="space-y-3">
            <input v-model="neueKategorie.name" class="w-full rounded border-gray-300 text-sm" placeholder="Name" />
            <textarea v-model="neueKategorie.beschreibung" rows="2" class="w-full rounded border-gray-300 text-sm" placeholder="Beschreibung"></textarea>
            <button class="w-full rounded bg-zbb px-4 py-2 text-white disabled:opacity-60" :disabled="categorySaving" @click="createKategorie">
              {{ categorySaving ? 'Speichert...' : 'Kategorie speichern' }}
            </button>
          </div>
        </div>

        <div v-if="canUpdateProjektKategorien" class="rounded border bg-white p-4 shadow-sm">
          <h2 class="mb-4 text-base font-semibold text-gray-700">Projekt-Kategorien</h2>
          <div class="max-h-[420px] overflow-y-auto">
            <div v-for="projekt in projektListe" :key="projekt.id" class="mb-3 rounded border border-gray-200 p-3">
              <div class="mb-2 flex items-center justify-between gap-3">
                <div class="font-medium text-gray-800">{{ projekt.name }}</div>
                <button class="rounded border border-zbb px-3 py-1 text-sm text-zbb" @click="saveProjektKategorien(projekt)">Speichern</button>
              </div>
              <label v-for="kategorie in kategorieListe" :key="kategorie.id" class="mr-4 inline-flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-zbb"
                  :checked="projektKategorieIds(projekt).includes(kategorie.id)"
                  @change="toggleProjektKategorie(projekt, kategorie.id)"
                />
                {{ kategorie.name }}
              </label>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded border bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-gray-700">Platzhalter</h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div v-for="gruppe in platzhalter" :key="gruppe.gruppe" class="rounded border border-gray-200 p-3">
            <div class="mb-2 font-medium text-gray-700">{{ gruppe.gruppe }}</div>
            <div v-for="eintrag in gruppe.werte" :key="eintrag.key" class="mb-2">
              <code class="rounded bg-gray-100 px-2 py-1 text-xs text-zbb">{{ token(eintrag.key) }}</code>
              <div class="mt-1 text-xs text-gray-500">{{ eintrag.label }}</div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

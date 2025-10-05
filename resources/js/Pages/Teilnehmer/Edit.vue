<template>
  <Head title="Teilnehmer" />
  <AppLayout>
    <!-- ================= HEADER ================= -->
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <span>{{ $t("Teilnehmerprofil") }}: </span>
          <span class="text-zbb text-md font-semibold">
            {{ teilnehmer.vorname }} {{ teilnehmer.nachname }}
          </span>
          <span class="text-danger text-xs font-semibold">
            - {{ alter ? `${alter} Jahre` : "" }}
          </span>
          <p class="text-gray-500 text-xs">{{ $t("Verwaltung & Übersicht") }}</p>
        </div>
        <div>
          <p class="bg-zbb text-white py-1 px-2 rounded-lg text-sm">
            ID: {{ teilnehmer.id }}
          </p>
        </div>
      </div>
    </template>

    <!-- ================= INHALT ================= -->
    <div class="bg-gray-50 ">
      <div class="bg-gray-100  space-y-6 -mt-6">
        <!-- =================== TABS =================== -->
        <div class="bg-white shadow-md rounded-xl p-6 border border-gray-200">
          <nav class="flex flex-wrap gap-2 border-b pb-2 mb-4 justify-center">
            <button
              v-for="tab in tabs"
              :key="tab"
              @click="activeTab = activeTab === tab ? '' : tab"
              :class="[
                'px-3 py-1 text-sm font-medium rounded-t-md',
                activeTab === tab
                  ? 'bg-zbbTrp text-zbb border-b-2 border-zbb'
                  : 'text-gray-600 hover:text-zbb',
              ]"
            >
              {{ tab }}
            </button>
          </nav>







          <!-- ================= STAMMDATEN ================= -->
          <div v-if="activeTab === 'Stammdaten'">
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label>Vorname</label>
                <input v-model="teilnehmer.vorname" class="input" />
              </div>
              <div>
                <label>Nachname</label>
                <input v-model="teilnehmer.nachname" class="input" />
              </div>
              <div>
                <label>Geschlecht</label>
                <select v-model="form.geschlecht" class="input">
                  <option value="m">m</option>
                  <option value="w">w</option>
                  <option value="d">divers</option>
                </select>
              </div>
              <div>
                <label>Geburtsdatum</label>
                <input type="date" v-model="form.geburtsdatum" class="input" />
              </div>
              <div>
                <label>Betreuer</label>
                <select v-model="form.betreuer" class="input">
                  <option v-for="m in mitarbeiterListe" :key="m">{{ m }}</option>
                </select>
              </div>
              <div class="md:col-span-3">
                <label>Bemerkungen</label>
                <textarea v-model="form.bemerkungen" rows="2" class="input"></textarea>
              </div>
            </div>
          </div>

          <!-- ================= ADRESSEN ================= -->
          <div v-else-if="activeTab === 'Adresse'">
            <div v-if="teilnehmer.adresses && teilnehmer.adresses.length">
              <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-700">
                  <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Land</th>
                    <th class="px-3 py-2 text-left">Straße</th>
                    <th class="px-3 py-2 text-left">Hausnummer</th>
                    <th class="px-3 py-2 text-left">PLZ</th>
                    <th class="px-3 py-2 text-left">Ort</th>
                    <th class="px-3 py-2 text-left">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(adresse, index) in teilnehmer.adresses"
                    :key="adresse.id || index"
                    class="border-t hover:bg-gray-50 transition"
                  >
                    <td class="px-3 py-2">{{ index + 1 }}</td>
                    <td class="px-3 py-2">{{ adresse.land }}</td>
                    <td class="px-3 py-2">{{ adresse.strasse }}</td>
                    <td class="px-3 py-2">{{ adresse.hausnummer }}</td>
                    <td class="px-3 py-2">{{ adresse.plz }}</td>
                    <td class="px-3 py-2">{{ adresse.stadt }}</td>
                    <td class="px-3 py-2">
                      <button
                        @click="confirmDeleteAdresse(adresse)"
                        class="text-red-500 hover:text-red-700 text-sm"
                      >
                        <i class="la la-trash"></i> Löschen
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-gray-500 italic">Keine Adressdaten vorhanden.</p>
            <div class="mt-4">
              <button
                @click="showModalAdresse = true"
                class="bg-zbb text-white px-3 py-2 rounded-md text-sm hover:bg-zbb/80 transition"
              >
                ➕ Neue Adresse hinzufügen
              </button>
            </div>
          </div>

          <!-- ================= KONTAKTDATEN ================= -->
          <div v-else-if="activeTab === 'Kontaktdaten'">
            <div v-if="teilnehmer.kontaktes && teilnehmer.kontaktes.length" class="space-y-3 mb-6">
              <div
                v-for="kontakt in teilnehmer.kontaktes"
                :key="kontakt.id"
                class="flex justify-between items-center bg-gray-50 border rounded-lg px-4 py-2"
              >
                <div>
                  <p class="text-sm font-semibold text-gray-800">
                    {{ kontakt.kontakttyp?.name || "Unbekannt" }}
                  </p>
                  <p class="text-gray-600 text-sm">{{ kontakt.wert }}</p>
                </div>
                <button
                  @click="confirmDeleteKontakt(kontakt)"
                  class="text-red-500 hover:text-red-700 text-sm"
                >
                  Entfernen
                </button>
              </div>
            </div>
            <p v-else class="text-gray-400 italic mb-6">
              Noch keine Kontaktdaten vorhanden.
            </p>
            <button
              @click="showModalCreateKontakt = true"
              class="bg-zbb text-white px-3 py-2 rounded-md text-sm hover:bg-zbb/80 transition"
            >
              ➕ Kontakt hinzufügen
            </button>
          </div>

          <!-- =================== MASSNAHMENVERLAUF =================== -->
          <div v-else-if="activeTab === 'Maßnahmenverlauf'">
          <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="th">Maßnahme</th>
                <th class="th">Von</th>
                <th class="th">Bis</th>
                <th class="th">Eintritt</th>
                <th class="th">Ende</th>
                <th class="th">ESF</th>
                <th class="th">JC-Mitarbeiter</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(m, i) in form.massnahmen" :key="i" class="border-t">
                <td><input v-model="m.name" class="input w-full" /></td>
                <td><input type="date" v-model="m.von" class="input w-full" /></td>
                <td><input type="date" v-model="m.bis" class="input w-full" /></td>
                <td><input v-model="m.eintritt" class="input w-full" /></td>
                <td><input v-model="m.ende" class="input w-full" /></td>
                <td class="text-center"><input type="checkbox" v-model="m.esf" /></td>
                <td><input v-model="m.jc_mitarbeiter" class="input w-full" /></td>
              </tr>
            </tbody>
          </table>
     

    
  <!-- Projekt hinzufügen -->
  <div class="flex items-end gap-3">
    <div class="flex-1">
      <label class="text-sm text-gray-600">Projekt auswählen</label>
      <select v-model="neuesProjektId" class="input mt-1">
        <option disabled value="">-- Projekt auswählen --</option>
        <option v-for="projekt in alleProjekte" :key="projekt.id" :value="projekt.id">
          {{ projekt.name }}
        </option>
      </select>
    </div>
    <button
      @click="addProjekt"
      :disabled="!neuesProjektId || loadingProjekt"
      class="bg-zbb text-white px-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition"
    >
      <span v-if="!loadingProjekt">➕ Zuweisen</span>
      <span v-else>...</span>
    </button>
  </div>

          </div>

          <!-- ================= BRIEFE ================= -->
          <div v-else-if="activeTab === 'Briefe'">
            <div class="grid grid-cols-3 gap-4">
              <div><label>Datum</label><input type="date" v-model="brief.datum" class="input" /></div>
              <div><label>Betreff</label><input v-model="brief.betreff" class="input" /></div>
              <div><label>Unterschrift</label><input v-model="brief.unterschrift1" class="input" /></div>
              <div class="col-span-2">
                <label>Inhalt</label>
                <textarea v-model="brief.inhalt" rows="6" class="input"></textarea>
              </div>
              <div>
                <label>Vorlagen</label>
                <ul class="border border-gray-200 rounded p-2 text-sm">
                  <li
                    v-for="v in vorlagen"
                    :key="v"
                    class="cursor-pointer hover:text-zbb"
                  >
                    {{ v }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- ================= AKTENNOTIZ ================= -->
          <div v-else-if="activeTab === 'Aktennotiz'">
            <textarea v-model="form.aktennotiz" rows="8" class="input"></textarea>
          </div>

          <!-- ================= NOTIZEN ================= -->
          <div v-else-if="activeTab === 'Notizen'">
            <textarea v-model="form.notizen" rows="8" class="input"></textarea>
          </div>

          
          <!-- ================= KINDER ================= -->
          <div v-else-if="activeTab === 'Kinder'">
            <p class="text-gray-500">Informationen zu Kindern können hier ergänzt werden.</p>
          </div>

          <!-- ================= BANK ================= -->
          <div v-else-if="activeTab === 'Bank'">
            <div class="grid grid-cols-2 gap-4">
              <div><label>Bankname</label><input v-model="form.bankname" class="input" /></div>
              <div><label>IBAN</label><input v-model="form.iban" class="input" /></div>
            </div>
          </div>

          <!-- ================= NETZWERKE ================= -->
          <div v-else-if="activeTab === 'Netzwerke'">
            <p class="text-gray-500">Hier kannst du Netzwerkverbindungen pflegen.</p>
          </div>

          <!-- ================= VERMITTLUNG ================= -->
          <div v-else-if="activeTab === 'Vermittlung'">
              <p class="text-gray-500">Hier kannst du Netzwerkverbindungen pflegen.</p>
                <textarea v-model="form.vermittlung" rows="6" class="input"></textarea>
          </div>    
          
          
          <!-- ================= Projekte ================= -->
          <div v-else-if="activeTab === 'Projekte'">
              <p class="text-gray-500">Hier kannst du Projekte verwalten.</p>
                <textarea v-model="form.projekte" rows="6" class="input"></textarea>
          </div>  
        </div>
      </div>
    </div>




  








        <!-- MODAL: Adresse hinzufügen -->
            <transition name="fade">
              <div
                v-if="showModalAdresse"
                class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
              >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button
                    @click="showModalAdresse = false"
                    class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                  >
                    ✕
                  </button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Neue Adresse</h3>

                  <div class="space-y-3">
                    <div><label>Land</label><input v-model="neueAdresse.land" class="input" /></div>
                    <div><label>Straße</label><input v-model="neueAdresse.strasse" class="input" /></div>
                    <div><label>Hausnummer</label><input v-model="neueAdresse.hausnummer" class="input" /></div>
                    <div><label>PLZ</label><input v-model="neueAdresse.plz" class="input" /></div>
                    <div><label>Ort</label><input v-model="neueAdresse.stadt" class="input" /></div>
                  </div>

                  <div class="mt-6 flex justify-end space-x-3">
                    <button
                      @click="showModalAdresse = false"
                      class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                    >
                      Abbrechen
                    </button>
                    <button
                      @click="addAdresse"
                      :disabled="loadingAdresse"
                      class="px-4 py-2 rounded-md text-sm text-white transition"
                      :class="loadingAdresse ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                    >
                      <span v-if="!loadingAdresse">Speichern</span>
                      <span v-else>Speichern...</span>
                    </button>
                  </div>
                </div>
              </div>
            </transition>

        <!-- MODAL: Kontakt hinzufügen -->
            <transition name="fade">
              <div v-if="showModalCreateKontakt" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button @click="showModalCreateKontakt = false" class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl">✕</button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Neuen Kontakt hinzufügen</h3>

                  <div class="space-y-4">
                    <div>
                      <label class="text-sm text-gray-600">Kontakttyp</label>
                      <select v-model="neuerKontakt.kontakttyp_id" class="input mt-1">
                        <option disabled value="">-- auswählen --</option>
                        <option v-for="kt in props.kontakttypen" :key="kt.id" :value="kt.id">{{ kt.name }}</option>
                      </select>
                    </div>

                    <!-- Dynamische Eingabe je nach Typ -->
                    <div v-if="selectedTyp?.name === 'Telefon'">
                      <label class="text-sm text-gray-600">Telefonnummer</label>
                      <input v-model="neuerKontakt.wert" class="input mt-1" placeholder="+49 ..." />
                    </div>

                    <div v-else-if="selectedTyp?.name === 'E-Mail'">
                      <label class="text-sm text-gray-600">E-Mail</label>
                      <input v-model="neuerKontakt.wert" type="email" class="input mt-1" placeholder="beispiel@mail.de" />
                    </div>

                    <div v-else-if="selectedTyp?.name === 'Adresse'">
                      <label class="text-sm text-gray-600">Adresse</label>
                      <input v-model="neuerKontakt.wert" class="input mt-1" placeholder="Straße, Ort ..." />
                    </div>

                    <div v-else-if="selectedTyp">
                      <label class="text-sm text-gray-600">Wert</label>
                      <input v-model="neuerKontakt.wert" class="input mt-1" />
                    </div>
                  </div>

                  <div class="mt-6 flex justify-end space-x-3">
                    <button @click="showModalKontakt = false" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
                    <button
                      @click="addKontakt"
                      :disabled="loadingKontakt"
                      class="px-4 py-2 rounded-md text-sm text-white transition"
                      :class="loadingKontakt ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                    >
                      <span v-if="!loadingKontakt">Speichern</span>
                      <span v-else>Speichern...</span>
                    </button>
                  </div>
                </div>
              </div>
            </transition>










    <!-- ================= MODAL LÖSCHEN ================= -->
    <ModalDestroy
      v-if="showModalLöschen"
      @close="showModalLöschen = false"
      @confirm="deleteItem"
      :seite="seite"
      :toDelete="toDeleteItem"
    />
  </AppLayout>
</template>

<script setup>
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, computed } from "vue";
import ModalDestroy from "@/Components/ModalDestroyForm.vue";


const props = defineProps({
  teilnehmer: Object,
  kontakttypen: Array,
});

// Lokale Kopie der Teilnehmerdaten
const teilnehmer = ref(JSON.parse(JSON.stringify(props.teilnehmer)));

// Tabs
const tabs = [
  "Stammdaten",
  "Adresse",
  "Kontaktdaten",
  "Maßnahmenverlauf",
  "Briefe",
  "Aktennotiz",
  "Notizen",
  "Kinder",
  "Bank",
  "Netzwerke",
  "Vermittlung",
  
  "Projekte",
];
const activeTab = ref("");

// Formulare & Variablen
const mitarbeiterListe = ["Test Admin", "Mitarbeiter Test Amin Masri"];
const showModalAdresse = ref(false);
const showModalCreateKontakt = ref(false);
const showModalLöschen = ref(false);
const seite = ref("");
const toDeleteItem = ref(null);
const form = ref({
  geschlecht: "m",
  geburtsdatum: "1997-05-17",
  betreuer: "",
  bemerkungen: "",
  aktennotiz: "",
  notizen: "",
  vermittlung: "",
  bankname: "",
  iban: "",
});
const alter = computed(() => {
  const geb = form.value.geburtsdatum;
  if (!geb) return "";
  const diff = Date.now() - new Date(geb).getTime();
  return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
});

// ======= ADRESSEN =======
const neueAdresse = ref({ land: "", strasse: "", hausnummer: "", plz: "", stadt: "" });
const loadingAdresse = ref(false);
const addAdresse = () => {
  if (!neueAdresse.value.land || !neueAdresse.value.strasse) return;
  loadingAdresse.value = true;
  router.post(
    route("adresse.store"),
    {
      ...neueAdresse.value,
      model_type: "App\\Models\\Teilnehmer",
      model_id: teilnehmer.value.id,
    },
    {
      onFinish: () => (loadingAdresse.value = false),
      onSuccess: () => {
        teilnehmer.value.adresses.push({ ...neueAdresse.value, id: Date.now() });
        showModalAdresse.value = false;
        neueAdresse.value = { land: "", strasse: "", hausnummer: "", plz: "", stadt: "" };
      },
    }
  );
};

// ======= KONTAKTE =======
const neuerKontakt = ref({ kontakttyp_id: "", wert: "" });
const loadingKontakt = ref(false);
const selectedTyp = computed(() =>
  props.kontakttypen.find((t) => t.id === neuerKontakt.value.kontakttyp_id)
);
const addKontakt = () => {
  if (!neuerKontakt.value.kontakttyp_id || !neuerKontakt.value.wert) return;
  loadingKontakt.value = true;
  router.post(
    route("kontakt.store"),
    {
      kontakttyp_id: neuerKontakt.value.kontakttyp_id,
      wert: neuerKontakt.value.wert,
      model_type: "App\\Models\\Teilnehmer",
      model_id: teilnehmer.value.id,
    },
    {
      onFinish: () => (loadingKontakt.value = false),
      onSuccess: () => {
        teilnehmer.value.kontaktes.push({
          id: Date.now(),
          wert: neuerKontakt.value.wert,
          kontakttyp: selectedTyp.value,
        });
        neuerKontakt.value = { kontakttyp_id: "", wert: "" };
        showModalCreateKontakt.value = false;
      },
    }
  );
};

// ======= LÖSCHEN =======
const confirmDeleteKontakt = (kontakt) => {
  toDeleteItem.value = { name: kontakt.wert, id: kontakt.id };
  seite.value = "kontakt";
  showModalLöschen.value = true;
};
const confirmDeleteAdresse = (adresse) => {
  toDeleteItem.value = { name: `${adresse.strasse} ${adresse.hausnummer}`, id: adresse.id };
  seite.value = "adresse";
  showModalLöschen.value = true;
};
const deleteItem = () => {
  if (!toDeleteItem.value?.id || !seite.value) return;
  router.delete(route(`${seite.value}.destroy`, toDeleteItem.value.id), {
    onSuccess: () => {
      if (seite.value === "adresse")
        teilnehmer.value.adresses = teilnehmer.value.adresses.filter(
          (a) => a.id !== toDeleteItem.value.id
        );
      if (seite.value === "kontakt")
        teilnehmer.value.kontaktes = teilnehmer.value.kontaktes.filter(
          (k) => k.id !== toDeleteItem.value.id
        );
      showModalLöschen.value = false;
    },
  });
};

// ======= BRIEFE =======
const brief = ref({
  datum: new Date().toISOString().split("T")[0],
  betreff: "",
  unterschrift1: "",
  inhalt: "",
});
const vorlagen = ["Einladung", "Abmahnung", "Vertragsangebot"];
</script>

<style scoped>
.input {
  @apply mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm;
}
.th {
  @apply px-2 py-1 text-left text-gray-600 font-medium;
}
</style>

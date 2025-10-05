<template>
  <Head title="Teilnehmer" />
  <AppLayout>
    <template #header>
    <div class="flex justify-between items-center">
        <div>
            <span>{{ $t("Teilnehmerprofil") }}: </span>
            <span class="text-zbb text-md font-semibold">
                {{ form.vorname }} {{ form.nachname }}
            </span>
            <span class="text-danger text-xs font-semibold">
                - {{ alter ? `${alter} Jahre` : '' }}
            </span>
            <p class="text-gray-500 text-xs">Verwaltung & Übersicht</p>
        </div>

        <div>
            <p class="bg-zbb text-white py-1  px-2 rounded-lg text-sm">ID: {{ form.id }}</p>
        </div>
    </div>
    </template>

    <div class="bg-gray-50 min-h-screen">
      <div class=" bg-gray-100 min-h-screen space-y-6 -mt-6">
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

          <!-- STAMMDATEN -->
          <div v-if="activeTab === 'Stammdaten'">
            <h3 class="font-medium mb-2">Stammdaten</h3>
            <div class="grid grid-cols-3 gap-4">
                <div><label>Vorname</label><input v-model="form.vorname" class="input" /></div>
                <div><label>Nachname</label><input v-model="form.nachname" class="input" /></div>
                <div><label>Geschlecht</label>
                    <select v-model="form.geschlecht" class="input">
                    <option value="m">m</option>
                    <option value="w">w</option>
                    <option value="d">divers</option>
                    </select>
                </div>
                <div><label>Geburtsdatum</label><input type="date" v-model="form.geburtsdatum" class="input" /></div>
                <div><label>Betreuer</label>
                    <select v-model="form.betreuer" class="input">
                    <option v-for="m in mitarbeiterListe" :key="m">{{ m }}</option>
                    </select>
                </div>
                <div class="md:col-span-3"><label>Bemerkungen</label><textarea v-model="form.bemerkungen" rows="2" class="input"></textarea></div>
            </div>
          </div>
          <!-- ADRESSE -->
          <div v-if="activeTab === 'Adresse'">
            <h3 class="font-medium mb-2">Adresse</h3>
            <div class="grid grid-cols-3 gap-4">
              <div><label>Land</label><input v-model="form.land" class="input" /></div>
              <div><label>Straße</label><input v-model="form.strasse" class="input" /></div>
              <div><label>Hausnummer</label><input v-model="form.hausnummer" class="input" /></div>
              <div><label>PLZ</label><input v-model="form.plz" class="input" /></div>
              <div><label>Ort</label><input v-model="form.ort" class="input" /></div>
            </div>
          </div>

          <!-- KONTAKTDATEN -->
          <div v-else-if="activeTab === 'Kontaktdaten'">
            <h3 class="font-medium mb-2">Kontaktdaten</h3>
            <div class="grid grid-cols-3 gap-4">
              <div><label>Telefon</label><input v-model="form.telefon" class="input" /></div>
              <div><label>Mobil</label><input v-model="form.mobil" class="input" /></div>
              <div><label>E-Mail</label><input v-model="form.email" class="input" /></div>
            </div>
          </div>

          <!-- BRIEFE -->
          <div v-else-if="activeTab === 'Briefe'">
            <h3 class="font-medium mb-2">Briefverwaltung</h3>
            <div class="grid grid-cols-3 gap-4">
              <div><label>Datum</label><input type="date" v-model="brief.datum" class="input" /></div>
              <div><label>Betreff</label><input v-model="brief.betreff" class="input" /></div>
              <div><label>Unterschrift 1</label><input v-model="brief.unterschrift1" class="input" /></div>
              <div class="col-span-2"><label>Inhalt</label><textarea v-model="brief.inhalt" rows="6" class="input"></textarea></div>
              <div><label>Vorlagen</label>
                <ul class="border border-gray-200 rounded p-2 text-sm">
                  <li v-for="v in vorlagen" :key="v" class="cursor-pointer hover:text-zbb">
                    {{ v }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- AKTENNOTIZ -->
          <div v-else-if="activeTab === 'Aktennotiz'">
            <h3 class="font-medium mb-2">Aktennotiz</h3>
            <textarea v-model="form.aktennotiz" rows="8" class="input"></textarea>
          </div>

          <!-- NOTIZEN -->
          <div v-else-if="activeTab === 'Notizen'">
            <h3 class="font-medium mb-2">Notizen</h3>
            <textarea v-model="form.notizen" rows="8" class="input"></textarea>
          </div>

          <!-- BANK -->
          <div v-else-if="activeTab === 'Bank'">
            <h3 class="font-medium mb-2">Bankdaten</h3>
            <div class="grid grid-cols-2 gap-4">
              <div><label>Bankname</label><input v-model="form.bankname" class="input" /></div>
              <div><label>IBAN</label><input v-model="form.iban" class="input" /></div>
            </div>
          </div>

          <!-- VERMITTLUNG -->
          <div v-else-if="activeTab === 'Vermittlung'">
            <h3 class="font-medium mb-2">Vermittlung / Maßnahme</h3>
            <textarea v-model="form.vermittlung" rows="6" class="input"></textarea>
          </div>
        </div>


         <!-- =================== MASSNAHMENVERLAUF =================== -->
        <div class="bg-white shadow-md rounded-xl p-6 border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Maßnahmenverlauf</h2>
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
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, computed, watch } from "vue";

const activeTab = ref("");
const tabs = [
    "Stammdaten",
    "Adresse",
    "Kontaktdaten",
    "Briefe",
    "Aktennotiz",
    "Notizen",
    "Gruppen",
    "Kinder",
    "Bank",
    "Netzwerke",
    "Vermittlung",
];

const mitarbeiterListe = ["Test Admin", "Mitarbeiter Test Amin Masri"];

const form = ref({
  id: 54,
  vorname: "Amin",
  nachname: "Masri",
  geschlecht: "m",
  geburtsdatum: "1997-05-17",
  strasse: "Musterstraße 1",
  plz: "66271",
  ort: "Kleinblittersdorf",
  telefon: "",
  mobil: "",
  email: "",
  betreuer: "Mitarbeiter Test Amin Masri",
  bemerkungen: "",
  aktennotiz: "",
  notizen: "",
  vermittlung: "",
  bankname: "",
  iban: "",
  massnahmen: [{ name: "", von: "", bis: "", eintritt: "", ende: "", esf: false, jc_mitarbeiter: "" }],
});

const brief = ref({
  datum: new Date().toISOString().split("T")[0],
  betreff: "",
  unterschrift1: "",
  inhalt: "",
});

const vorlagen = ["Test 1 Baustein", "Test 2 Briefbausteine Masri"];

const alter = computed(() => {
  const geb = form.value.geburtsdatum;
  if (!geb) return "";
  const diff = Date.now() - new Date(geb).getTime();
  return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
});

watch(() => form.value.geburtsdatum, (neu) => {
  console.log("Geburtsdatum geändert:", neu);
  console.log("Aktuelles Alter:", alter.value);
});
</script>

<style scoped>
.input {
  @apply mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm;
}
.label {
  @apply text-sm text-gray-600;
}
.th {
  @apply px-2 py-1 text-left text-gray-600 font-medium;
}
</style>

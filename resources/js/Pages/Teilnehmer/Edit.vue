<template>
  <Head title="Teilnehmer" />
  <AppLayout>
    <Alert/>
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
            <Stammdaten v-if="activeTab === 'Stammdaten'" :teilnehmer="teilnehmer" :betreuer="props.betreuer" />


          <!-- ================= Sozialdaten ================= -->
          <div v-if="activeTab === 'Sozialdaten'">
            <button @click="saveSozialdaten"
                class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full">
                ➕ Speichern
              </button>

                <div class="space-y-5  w-96 mx-auto">
                    <Toggle v-model="drittstaatsangehoerig" label="Drittstaatsangehörige?" hint="Nicht-EU/EWR/Schweiz" />

                    <Toggle v-model="behinderung" label="Liegt eine Behinderung vor?" hint="Nach §2 SGB IX" />

                    <Toggle v-model="gefluechtet" label="Teilnehmer ist geflüchtet?" />

                    <div class="flex items-center w-96   justify-between gap-4">
                        <label class="block text-sm font-medium text-gray-800 leading-6">Leistungsbezug nach SGB</label>
                        <select v-model="leistungsbezug_id" class="input w-64" >
                            <option :value="null" disabled>— auswählen —</option>
                            <option v-for="m in props.leistungsbezuege" :key="m.id" :value="m.id" >
                                {{ m.bezeichnung }}
                            </option>
                        </select>
                    </div>

                    <Toggle v-model="migrationshintergrund" label="Liegt ein Migrationshintergrund vor?" />

                    wohnsitz_stabil
                    <Toggle v-model="wohnsitz_stabil" label="Wohnsitz stabil?" />
                </div>
          </div>

          <!-- ================= ADRESSEN ================= -->
          <div v-else-if="activeTab === 'Adresse'">
              <button @click="showModalAdresse = true"
                class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full">
                ➕ Neue Adresse hinzufügen
              </button>

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
                        @click="confirmDelete(adresse, 'adresse')"
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

          </div>

          <!-- ================= KONTAKTDATEN ================= -->
          <div v-else-if="activeTab === 'Kontaktdaten'">
            <button @click="showModalCreateKontakt = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                <span v-if="!loadingKontakt">➕ Kontakt hinzufügen</span>
                <span v-else>...</span>
            </button>

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
                  @click="confirmDelete(kontakt, 'kontakt')"
                  class="text-red-500 hover:text-red-700 text-sm"
                >
                  Entfernen
                </button>
              </div>
            </div>
            <p v-else class="text-gray-400 italic mb-6">
              Noch keine Kontaktdaten vorhanden.
            </p>

          </div>

          <!-- =================== MASSNAHMENVERLAUF =================== -->
        <div v-else-if="activeTab === 'Projektverlauf'">
             <!-- Projekt hinzufügen -->
            <button @click="showModalProjektzuweisen = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                <span v-if="!loadingProjekt">➕ Zuweisen</span>
                <span v-else>...</span>
            </button>
            <table class="min-w-full border border-gray-300 text-sm text-center">
                <thead class="bg-gray-100">
                    <tr>
                    <th class="px-4 py-2 border">Projekte</th>
                    <th class="px-4 py-2 border">Antragsdatum</th>
                    <th class="px-4 py-2 border">Anfangsdatum</th>
                    <th class="px-4 py-2 border">Enddatum</th>
                    <th class="px-4 py-2 border">Starttermin</th>
                    <th class="px-4 py-2 border">Endtermin</th>
                    <th  class="px-4 py-2 border">*</th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Schleife über alle Projekte -->
                    <template v-for="(projekt, i) in teilnehmer.projekte" :key="i">

                    <!-- Wenn das Projekt mehrere Zeiträume hat -->
                    <tr
                        v-for="(zeit, z) in projekt.pivot_model?.zeitraume || []"
                        :key="zeit.id"
                        class="hover:bg-gray-50"
                    >
                        <!-- Projektname nur einmal pro Gruppe -->
                        <td
                            v-if="z === 0"
                            :rowspan="projekt.pivot_model?.zeitraume?.length || 1"
                            class="border px-4 py-2 align-middle font-medium bg-gray-50"
                            >
                            {{ projekt.name }}
                        </td>

                        <td class="border px-4 py-2">{{ $formatDate(zeit.antragsdatum) || '-' }}</td>
                        <td class="border px-4 py-2">{{ $formatDate(zeit.anfangsdatum) || '-' }}</td>
                        <td class="border px-4 py-2">{{ $formatDate(zeit.enddatum) || '-'  }}</td>
                        <td class="border px-4 py-2">{{ $formatDate(zeit.starttermin) || '-' }}</td>
                        <td class="border px-4 py-2">{{ $formatDate(zeit.endtermin) || '-' }}</td>
                        <td class="border px-6 py-4 text-center">
                            <!-- Dropdown für Aktion -->
                            <Dropdown>
                                <template #trigger>
                                    <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        <span class="cursor-pointer">
                                            <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                        </span>
                                    </button>
                                </template>

                                <template #content >
                                    <!-- Gefilterte Projektauswahl -->
                                    <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100 " @click="confirmDelete(projekt, 'projekt')">
                                          {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                    <Link class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" :href="route('teilnehmer.edit', teilnehmer.id)">
                                       {{ $t('Bearbeiten') }}  <i class="las la-edit"></i>
                                    </Link>
                                    <a  target="_blank" :href="route('export.excel.esfStammblatt', [props.teilnehmer.id, projekt.id])" class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" >ESF <i class="las la-file-download"></i></a>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>

                    <!-- Falls keine Zeiträume vorhanden sind -->
                    <tr v-if="!projekt.pivot_model?.zeitraume?.length" class="hover:bg-gray-50">
                        <td class="border px-4 py-2 font-medium bg-gray-50">{{ projekt.name }}</td>
                        <td colspan="5" class="border px-4 py-2 text-gray-500 italic">
                        Keine Zeiträume vorhanden
                        </td>
                        <td class="border px-6 py-4 text-center">
                            <!-- Dropdown für Aktion -->
                            <Dropdown>
                                <template #trigger>
                                    <button class=" items-center  text-sm leading-4 font-medium text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        <span class="cursor-pointer">
                                            <i class="transform transition-transform duration-300  la la-ellipsis-v la-lg"></i>
                                        </span>
                                    </button>
                                </template>

                                <template #content >
                                    <!-- Gefilterte Projektauswahl -->
                                    <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100 " @click="confirmDelete(projekt, 'projekt')">
                                          {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                                    </span>
                                    <Link class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" :href="route('teilnehmer.edit', teilnehmer.id)">
                                       {{ $t('Bearbeiten') }}  <i class="las la-edit"></i>
                                    </Link>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>

                    </template>


                </tbody>
            </table>
          </div>

            <!-- =================== Anwesenheit =================== -->
            <div v-else-if="activeTab === 'Anwesenheit'">
                 <!-- Anwesenheit hinzufügen -->
                <div class="flex gap-4">
                    <button @click="showModalAnwesenheit = true" class="bg-zbb text-white px-4 mb-6 mt-4   py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                    <span v-if="!loadingProjekt">➕ Anwesenheit</span>
                        <span v-else>...</span>
                    </button>
                    <div class="mb-6 mt-4">
                        <select
                            v-model="selectedMonth"
                            class="border-zbb rounded-md text-sm focus:ring-zbb focus:border-zbb"
                        >
                            <option value="">Alle Monate</option>
                            <option v-for="monat in verfuegbareMonate" :key="monat" :value="monat">
                                {{ monat }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Tabelle -->
                <div class="bg-white rounded-2xl shadow-md border mt-8 p-8 w-5/6 mx-auto">
                    <!-- Wenn keine Anwesenheit -->
                    <div v-if="props.teilnehmer.gruppen === 0" class="text-gray-500 italic text-sm">
                        <div class="p-8 text-center text-gray-500">
                            <div class="mb-4 flex justify-center text-6xl text not-italic">
                                🕒
                            </div>
                            <p class="text-lg font-medium">Noch keine Anwesenheit verfasst </p>
                            <p class="text-sm">Klicken Sie auf "Anwesenheit erfassen" um zu beginnen</p>
                        </div>
                    </div>

                    <!-- Karten -->
                    <div v-else class="space-y-3">
                        <!-- Gruppierte Ausgabe -->
                        <div v-for="(gruppen, monat) in gruppenNachMonat" :key="monat">
                        <div
                            v-if="!selectedMonth || selectedMonth === monat"
                            class="mt-8"
                        >
                            <h4 class="text-lg font-semibold text-zbb border-b pb-1 mb-3">
                                📆 {{ monat }}
                            </h4>

                            <div
                            v-for="gruppe in gruppen"
                            :key="gruppe.id"
                            class="flex flex-col sm:flex-row justify-between sm:items-center bg-white border border-gray-100 rounded-xl px-5 py-4 shadow-sm hover:shadow-md transition-all duration-200 mb-2"
                            >
                            <div class="flex">
                                <div class="items-center gap-6 w-96">
                                <div class="font-semibold">
                                    <p class="text-zbb mr-8"><span class="text-lg ml-8">🎨</span> {{ gruppe.bereich.name }}</p>
                                    <p class="text-zbb mr-8"><span class="text-lg ml-8">📅</span> {{ formatDate(gruppe.pivot.tag.datum) }}</p>
                                    <p><span class="text-lg ml-8">🕒</span> {{ formatTime(gruppe.startzeit) }} - {{ formatTime(gruppe.endzeit) }}</p>
                                </div>
                                </div>

                                <div class="items-center gap-6 w-64 mt-8">
                                <div class="font-semibold">
                                    <p class="ml-8 mr-8">🗓️ Geplante Arbeitszeit</p>
                                    <p><span class="text-lg ml-8">⏰</span> {{ formatTime(gruppe.pivot.zeitgeplant.startzeit) }} - {{ formatTime(gruppe.pivot.zeitgeplant.endzeit) }}</p>
                                </div>
                                </div>

                                <div class="items-center gap-6 w-96 mt-8">
                                <div class="font-semibold">
                                    <p class="ml-8 mr-8">💼 Tatsächliche Arbeitszeit</p>
                                    <p><span class="text-lg ml-8">⌛</span> {{ formatTime(gruppe.pivot?.zeittatsaechlich?.startzeit) }} - {{ formatTime(gruppe.pivot?.zeittatsaechlich?.endzeit) }}</p>
                                </div>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4 sm:mt-0">
                                <button
                                @click="openModalEdit(gruppe)"
                                class="px-4 py-2 text-sm font-medium rounded-md bg-zbb text-white shadow-sm hover:bg-zbb/90 transition"
                                >
                                Verwalten
                                </button>
                                <button
                                @click="confirmDelete(gruppe.pivot, 'gruppeHasPersonen')"
                                class="px-4 py-2 text-sm font-medium rounded-md bg-red-600 text-white shadow-sm hover:bg-red-700 transition"
                                >
                                Löschen
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>

                    </div>
                </div>

            </div>


            <!-- ================= BANK ================= -->
            <div v-else-if="activeTab === 'Bank'">
                <button @click="showModalBank = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                    <span v-if="!loadingBank">➕ Hinzufügen</span>
                    <span v-else>...</span>
                </button>
                <div v-if="teilnehmer.baenke && teilnehmer.baenke.length">
                    <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Bankname</th>
                        <th class="px-3 py-2 text-left">IBAN</th>
                        <th class="px-3 py-2 text-left">BLZ</th>
                        <th class="px-3 py-2 text-center">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                        v-for="(bank, index) in teilnehmer.baenke"
                        :key="bank.id || index"
                        class="border-t hover:bg-gray-50 transition"
                        >
                        <td class="px-3 py-2">{{ index + 1 }}</td>
                        <td class="px-3 py-2">{{ bank.name }}</td>
                        <td class="px-3 py-2 font-mono">{{ bank.iban }}</td>
                        <td class="px-3 py-2">{{ bank.blz }}</td>
                        <td class="px-3 py-2 text-center">
                            <button
                            @click="confirmDelete(bank, 'bank')"
                            class="text-red-500 hover:text-red-700 text-sm"
                            >
                            <i class="la la-trash"></i> Löschen
                            </button>
                        </td>
                        </tr>
                    </tbody>
                    </table>
                </div>
                <!-- Falls keine Bankdaten -->
                <p v-else class="text-gray-500 italic">Keine Bankdaten vorhanden.</p>
            </div>

            <!-- =================  Schule/Beruf ================= -->

            <div v-else-if="activeTab === 'Schule/Beruf'">
                <!-- Button -->
                <button
                    @click="showModalCreateAbschluss = true"
                    class="bg-zbb text-white px-4 mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full"
                >
                    <span v-if="!loadingAbschluss">➕ Abschluss hinzufügen</span>
                    <span v-else>...</span>
                </button>

                <!-- Bestehende Abschlüsse -->
                <div v-if="teilnehmer.abschluesse && teilnehmer.abschluesse.length" class="space-y-3 mb-6">
                    <div
                    v-for="eintrag in teilnehmer.abschluesse"
                    :key="eintrag.id"
                    class="flex justify-between items-center bg-gray-50 border rounded-lg px-4 py-2"
                    >
                    <div>
                        <p class="text-sm font-bold text-zbb uppercase">
                        {{ eintrag.typ }}
                        </p>
                        <p class="text-gray-600 text-sm">
                        🎓 <span class="font-bold">{{ eintrag.bezeichnung }}:</span> {{ eintrag.pivot_model.bezeichnung }}
                        </p>
                        <p class="text-gray-600 text-sm">
                    📆 {{ formatDate(eintrag.pivot_model.start) }} - {{ formatDate(eintrag.pivot_model.end) }}
                        </p>
                    </div>
                    <button
                        @click="confirmDelete(eintrag.pivot_model, 'abschluss')"
                        class="text-red-500 hover:text-red-700 text-sm"
                    >
                        Entfernen
                    </button>
                    </div>
                </div>

                <p v-else class="text-gray-400 italic mb-6">
                    Noch keine Schul- oder Berufsabschlüsse vorhanden.
                </p>
            </div>
          <!-- ================= BRIEFE ================= -->
          <div v-else-if="activeTab === 'Briefe'">
            <button @click="showModalBrief = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                <span v-if="!loadingBrief">➕ Vorlage erstellen</span>
                <span v-else>...</span>
            </button>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-1 border p-4 rounded space-y-4 shadow-sm">
                    <div><label>Datum</label><input type="date" v-model="brief.datum" class="input" /></div>
                    <div><label>Betreff</label><input v-model="brief.betreff" class="input" /></div>
                    <label>Inhalt</label>
                    <textarea v-model="brief.inhalt" rows="6" class="input"></textarea>
                </div>
                <div class="col-span-1 border p-4 rounded space-y-4 shadow-sm">
                    <div>
                        <label>Meine ✍️</label>
                       <ul class="border border-gray-200 rounded-lg divide-y divide-gray-100 shadow-sm bg-white text-sm">
                            <template v-if="meineBriefe && meineBriefe.length > 0">
                                <li
                                v-for="v in meineBriefe" :key="v.id"
                                @click="setBriefVorlage(v)"
                                class="flex items-center justify-between px-3 py-2 cursor-pointer hover:bg-blue-50 transition-all rounded-md"
                                >
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="font-medium">{{ v.name }}</span>
                                </div>
                                <div class="flex space-x-2">
                                    <button
                                    @click="confirmDelete(v, 'brief')"
                                    class="flex items-center gap-1 text-blue-600 hover:text-red-800 bg-red-100 hover:bg-red-200 px-2 py-1 rounded-md text-xs font-medium transition"
                                >
                                    <i class="la la-trash"></i> <span>Löschen</span>
                                </button>
                                <button
                                    @click.stop="() => openFreigabeModal(v)"
                                    class="flex items-center gap-1 text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 px-2 py-1 rounded-md text-xs font-medium transition"
                                >
                                    🔄 <span>Freigeben</span>
                                </button>
                                </div>


                                </li>
                            </template>

                            <template v-else>
                                <li class="text-center text-gray-500 italic py-4">
                                <div class="flex flex-col items-center justify-center space-y-1">
                                    <span class="text-2xl">📭</span>
                                    <span> keine Vorlage vorhanden</span>
                                </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <label>Shared ↩️</label>
                        <ul class="border border-gray-200 rounded-lg divide-y divide-gray-100 shadow-sm bg-white text-sm">
                            <template v-if="erhalteneBriefe && erhalteneBriefe.length > 0">
                                <li
                                v-for="erhalteneBrief in erhalteneBriefe"
                                :key="erhalteneBrief.id"
                                @click="setBriefVorlage(erhalteneBrief)"
                                class="flex items-center justify-between px-3 py-2 cursor-pointer hover:bg-green-50 transition-all rounded-md"
                                >
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="font-medium">{{ erhalteneBrief.name }}</span>
                                </div>

                               <div class="flex space-x-2">
                                    <button
                                    @click="confirmDelete(erhalteneBrief, 'briefShared')"
                                    class="flex items-center gap-1 text-blue-600 hover:text-red-800 bg-red-100 hover:bg-red-200 px-2 py-1 rounded-md text-xs font-medium transition"
                                >
                                    <i class="la la-trash"></i> <span>Löschen</span>
                                </button>
                                <button
                                    @click.stop="() => openFreigabeModal(v)"
                                    class="flex items-center gap-1 text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 px-2 py-1 rounded-md text-xs font-medium transition"
                                >
                                    🔄 <span>Freigeben</span>
                                </button>
                                </div>
                                </li>
                            </template>

                            <template v-else>
                                <li class="text-center text-gray-500 italic py-10">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <span class="text-2xl">📧</span>
                                    <span>Keine freigegebene Briefe vorhanden‼️</span>
                                </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
          </div>

        <!-- ================= NOTIZEN ================= -->
        <div v-else-if="activeTab === 'Notizen'">
            <button @click="showModalNotiz = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                <span v-if="!loadingNotiz">➕ Notiz erstellen</span>
                <span v-else>...</span>
            </button>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <form class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <!-- Suche -->
                <input
                    type="text"
                    v-model="filter.suche"
                    placeholder="Inhalt suchen..."
                    class="w-full rounded-md border-gray-300 text-sm focus:border-zbb focus:ring-zbb"
                />

                <!-- Notiztyp -->
                <select v-model="filter.typ" class="w-full rounded-md border-gray-300 text-sm focus:border-zbb focus:ring-zbb">
                    <option value="">Alle Typen</option>
                    <option v-for="t in props.notiztypen" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>

                <!-- Priorität -->
                <select v-model="filter.prioritaet" class="w-full rounded-md border-gray-300 text-sm focus:border-zbb focus:ring-zbb">
                    <option value="">Alle Prioritäten</option>
                    <option v-for="p in props.notizprioritaet" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>

                <!-- Kategorie -->
                <select v-model="filter.kategorie" class="w-full rounded-md border-gray-300 text-sm focus:border-zbb focus:ring-zbb">
                    <option value="">Alle Kategorien</option>
                    <option v-for="k in props.notizkategorie" :key="k.id" :value="k.id">{{ k.name }}</option>
                </select>

                </form>

            </div>


            <template  v-if="props.teilnehmer && props.teilnehmer.notizen.length > 0">
                <div v-for="notiz in [...gefilterteNotizen].reverse()" :key="notiz.id" class="bg-white mt-6 rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col gap-4 hover:bg-gray-50">
                    <!-- Header -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span v-if="notiz.notiztyp.name === 'Telefonnotiz'" class="text-purple-400 text-2xl">📞</span>
                            <span v-if="notiz.notiztyp.name === 'Verlaufsnotiz'" class="text-purple-400 text-2xl">📈</span>
                            <span v-if="notiz.notiztyp.name === 'Beratungsprotokoll'" class="text-purple-400 text-2xl">💬</span>
                            <span v-if="notiz.notiztyp.name === 'Aktennotiz'" class="text-purple-400 text-2xl">📋</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">{{notiz.titel}}</h3>
                                <p class="text-xs text-gray-500">{{props.teilnehmer.vorname}} {{props.teilnehmer.nachname}}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span
                                class="px-2 py-0.5 text-xs rounded-full"
                                :class="{
                                    'bg-green-100 text-green-600': notiz.notizprioritaet.name === 'Niedrig',
                                    'bg-orange-100 text-orange-600': notiz.notizprioritaet.name === 'Mittel',
                                    'bg-red-100 text-red-600': notiz.notizprioritaet.name === 'Hoch'
                                }"
                            >
                                {{ notiz.notizprioritaet.name }}
                            </span>
                            <button @click="confirmDelete(notiz, 'notizen')" class="text-gray-400 hover:text-red-500 transition">
                                <i class="las la-trash text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Inhalt -->
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                        {{notiz.notizinhalt}}
                    </p>

                    <hr>

                    <!-- Footer -->
                    <div class="flex justify-between items-center text-xs text-gray-500">
                        <div class="flex gap-4">
                            <span>{{notiz.notiztyp.name}}</span>
                            <span>{{notiz.notizkategorie.name}}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span>{{notiz.user.vorname}} {{notiz.user.nachname}}</span>
                            <span>•</span>
                            <span>{{formatDateTime(notiz.created_at)}}</span>
                        </div>
                    </div>
                </div>
            </template>

        </div>


          <!-- ================= KINDER ================= -->
          <div v-else-if="activeTab === 'Kinder'">
            <p class="text-gray-500">Informationen zu Kindern können hier ergänzt werden.</p>
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

          <!-- ================= Praktika ================= -->
          <div v-else-if="activeTab === 'Praktika'">
              <p class="text-gray-500">Hier kannst du Praktika verwalten.</p>
                <textarea v-model="form.projekte" rows="6" class="input"></textarea>
          </div>

        <!-- ================= Fahrtkosten ================= -->
          <div v-else-if="activeTab === 'Fahrtkosten'">
              <button @click="showModalFahrtkosten = true"
                class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full">
                ➕ Fahrtkosten anlegen
              </button>

            <div v-if="teilnehmer.fahrtabrechnungen && teilnehmer.fahrtabrechnungen.length">
              <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-700">
                  <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Fahrtarten</th>
                    <th class="px-3 py-2 text-left">Tag</th>
                    <th class="px-3 py-2 text-left">Start</th>
                    <th class="px-3 py-2 text-left">Ziel</th>
                    <th class="px-3 py-2 text-left">Entfernung</th>
                    <th class="px-3 py-2 text-left">Kostenberechnet</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Erstellen am</th>
                    <th class="px-3 py-2 text-left">Personal</th>
                    <th class="px-3 py-2 text-left">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(fahrtabrechnung, index) in teilnehmer.fahrtabrechnungen"
                    :key="fahrtabrechnung.id || index"
                    class="border-t hover:bg-gray-50 transition"
                  >
                    <td class="px-3 py-2">{{ index + 1 }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.fahrtarten.name }}</td>
                    <td class="px-3 py-2">{{ formatDate(fahrtabrechnung.datum) }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.start }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.ziel }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.entfernung_km }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.kosten_berechnet }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.status }}</td>
                    <td class="px-3 py-2">{{ formatDateTime(fahrtabrechnung.created_at) }}</td>
                    <td class="px-3 py-2">{{ fahrtabrechnung.personal.vorname }} {{ fahrtabrechnung.personal.nachname }}</td>

                    <td class="px-3 py-2">
                        <button @click="confirmDelete(fahrtabrechnung, 'fahrtkostenAbrechnung')" class="text-red-500 hover:text-red-700 text-sm" >
                            <i class="la la-trash"></i> Löschen
                        </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-gray-500 italic">Keine Fahrtkosten vorhanden.</p>

          </div>


          <!-- ================= Exportieren ================= -->
          <div v-else-if="activeTab === 'Exportieren'">
                <div class="max-w-7xl  mx-auto px-4  flex gap-6 my-24 justify-center">

                    <a :href="route('export.excel.esfStammblatt', props.teilnehmer.id)" class="cursor-pointer" >
                    <div class="rounded-lg shadow py-6 px-8 flex items-center gap-4 bg-zbb">
                            <span class="text-5xl">📑</span>
                            <div>
                                <div class="text-2xl font-bold">ESF</div>
                                <div class="text-sm">Stammblatt</div>
                            </div>
                        </div>
                    </a>
                    <div class="rounded-lg shadow py-6 px-8 flex items-center gap-4 bg-zbb">
                        <span class="text-5xl">📑</span>
                        <div>
                            <div class="text-2xl font-bold">ESF</div>
                            <div class="text-sm">Stammblatt</div>
                        </div>
                    </div>

                    <div class="rounded-lg shadow py-6 px-8 flex items-center gap-4 bg-zbb">
                        <span class="text-5xl">📑</span>
                        <div>
                            <div class="text-2xl font-bold">ESF</div>
                            <div class="text-sm">Stammblatt</div>
                        </div>
                    </div>
                </div>


          </div>
        </div>
      </div>
    </div>


            <!-- MODAL: Brief hinzufügen -->
             <transition name="fade">
              <div
                v-if="showModalBriefFreigeben"
                class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
              >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button
                    @click="showModalBriefFreigeben = false"
                    class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                  >
                    ✕
                  </button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Brief freigeben</h3>

                  <Multiselect
                        required
                        v-model="neuesBriefFreigeben"
                        :options="props.betreuer.map(p => ({ value: p.id, label: `${p.vorname} ${p.nachname}` }))"
                        placeholder="Betreuer auswählen"
                        searchable
                        noOptionsText="Keine Person gefunden"
                        class="input-auto"
                        mode="tags"
                    />





                  <div class="mt-6 flex justify-end space-x-3">
                    <button
                      @click="showModalBriefFreigeben = false"
                      class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                    >
                      Abbrechen
                    </button>
                    <button
                        @click="briefFreigeben"
                        :disabled="loadingBriefFreigabe"
                        class="px-4 py-2 rounded-md text-sm text-white transition"
                        :class="loadingBriefFreigabe ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                        >
                        <span v-if="!loadingBriefFreigabe">Freigeben</span>
                        <span v-else>Freigabe läuft...</span>
                    </button>

                  </div>
                </div>
              </div>
            </transition>
            <transition name="fade">
              <div
                v-if="showModalBrief"
                class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
              >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button
                    @click="showModalBrief = false"
                    class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                  >
                    ✕
                  </button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Neuer Brief</h3>

                  <div class="space-y-3">
                    <div><label>Name</label><input v-model="neuerBrief.name" class="input" /></div>
                    <div><label>Titel</label><input v-model="neuerBrief.titel" class="input" /></div>
                    <div><label>Content</label></div><textarea v-model="neuerBrief.content" rows="2" class="input"></textarea>
                  </div>

                  <div class="mt-6 flex justify-end space-x-3">
                    <button
                      @click="showModalBrief = false"
                      class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                    >
                      Abbrechen
                    </button>
                    <button
                      @click="addBrief"
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

             <!-- MODAL: Fahrtkosten hinzufügen -->
            <transition name="fade">
              <div
                v-if="showModalFahrtkosten"
                class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
              >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button
                    @click="showModalFahrtkosten = false"
                    class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                  >
                    ✕
                  </button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Neue Fahrtkosten</h3>

                  <div class="space-y-3">
                    <div>
                        <label for="startDate">
                            Fahrtarten <span class="text-red-500">*</span>
                        </label>
                      <Select
                            v-model="neueFahrtkosten.fahrtarten_id"
                            :options="props.fahrtarten"
                            optionLabel="name"
                            optionValue="id"
                            class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                            >
                        </Select>
                    </div>
                    <div>
                        <label for="startDate">
                            Fahrtarten <span class="text-red-500">*</span>
                        </label>
                        <Select
                            v-model="neueFahrtkosten.status"
                            :options="fahrtkostenStatus"
                            class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                            >
                        </Select>
                    </div>
                    <div><label>Tag<span class="text-red-500">*</span></label><input type="date" v-model="neueFahrtkosten.tag" class="input" /></div>
                    <div><label>Start</label><input v-model="neueFahrtkosten.start" class="input" /></div>
                    <div><label>Ziel</label><input v-model="neueFahrtkosten.ziel" class="input" /></div>
                    <div><label>Entfernung</label><input v-model="neueFahrtkosten.entfernung" class="input" /></div>
                  </div>

                  <div class="mt-6 flex justify-end space-x-3">
                    <button
                      @click="showModalFahrtkosten = false"
                      class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                    >
                      Abbrechen
                    </button>
                    <button
                      @click="addFahrtkosten"
                      :disabled="loadingFahrtkosten"
                      class="px-4 py-2 rounded-md text-sm text-white transition"
                      :class="loadingFahrtkosten ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                    >
                      <span v-if="!loadingFahrtkosten">Speichern</span>
                      <span v-else>Speichern...</span>
                    </button>
                  </div>
                </div>
              </div>
            </transition>

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
                    <button @click="showModalCreateKontakt = false" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
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

          <!-- MODAL: Projekt zuweisen -->
            <transition name="fade">
                <div
                    v-if="showModalProjektzuweisen"
                    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
                >
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                    <button
                        @click="showModalProjektzuweisen = false"
                        class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                    >
                        ✕
                    </button>

                    <h3 class="text-lg font-semibold mb-4 text-zbb">Projekt zuweisen</h3>

                    <div class="space-y-3">
                        <!-- Projekt-Auswahl -->
                        <div>
                        <label class="text-sm text-gray-600">Projekt auswählen</label>
                        <Multiselect
                            v-model="neuesProjektId"
                            :options="props.projekte.map(p => ({ value: p.id, label: p.name }))"
                            placeholder="Projekt suchen..."
                            searchable
                            noOptionsText="Keine Projekte gefunden"
                            class="input-auto"
                        />
                        </div>

                        <!-- Zeiträume -->
                        <div>
                            <label>Starttermin</label>
                            <input type="date" v-model="neuesProjekt.antragsdatum" class="input" />
                        </div>
                        <div>
                            <label>Starttermin</label>
                            <input type="date" v-model="neuesProjekt.starttermin" class="input" />
                        </div>
                        <div>
                            <label>Endtermin</label>
                            <input type="date" v-model="neuesProjekt.endtermin" class="input" />
                        </div>
                        <div>
                            <label>Anfangsdatum</label>
                            <input type="date" v-model="neuesProjekt.anfangsdatum" class="input" />
                        </div>
                        <div>
                            <label>Enddatum</label>
                            <input type="date" v-model="neuesProjekt.enddatum" class="input" />
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                        @click="showModalProjektzuweisen = false"
                        class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                        >
                        Abbrechen
                        </button>
                        <button
                        @click="addProjekt"
                        :disabled="loadingProjekt"
                        class="px-4 py-2 rounded-md text-sm text-white transition"
                        :class="loadingProjekt ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                        >
                        <span v-if="!loadingProjekt">Speichern</span>
                        <span v-else>Speichern...</span>
                        </button>
                    </div>
                    </div>
                </div>
            </transition>

            <!-- MODAL: Anwesenheit -->
            <transition name="fade">
                <div v-if="showModalAnwesenheit" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                        <button @click="showModalAnwesenheit = false" class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl">✕</button>
                            <h3 class="text-lg font-semibold mb-4 text-zbb">
                            {{ editMode ? 'Anwesenheit bearbeiten' : 'Anwesenheit erfassen' }}
                            </h3>
                        <div class="space-y-4">
                            <!-- Datum -->
                            <div>
                                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                    Datum <span class="text-red-500">*</span>
                                </label>
                                <input v-model="neueAnwesenheit.dateAnwesenheit" type="date" id="dateAnwesenheit" name="dateAnwesenheit" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                            </div>
                            <!-- Zeitraum geplant-->
                            <div class="flex space-x-4">
                                <div class="w-1/2">
                                    <label for="startTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                        geplante Startzeit <span class="text-red-500">*</span>
                                    </label>
                                    <input v-model="neueAnwesenheit.startTime" type="time" id="startTime" name="startTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                                </div>
                                <div class="w-1/2">
                                    <label for="endTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                        geplante Endzeit <span class="text-red-500">*</span>
                                    </label>
                                    <input v-model="neueAnwesenheit.endTime" type="time" id="endTime" name="endTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                                </div>
                            </div>

                            <!-- Zeitraum tatsächlich-->
                            <div class="flex space-x-4">
                                <div class="w-1/2">
                                    <label for="startTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                        tatsächliche Startzeit
                                    </label>
                                    <input v-model="neueAnwesenheit.tatstartTime" type="time" id="startTime" name="startTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                                </div>
                                <div class="w-1/2">
                                    <label for="endTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                        tatsächliche Endzeit
                                    </label>
                                    <input v-model="neueAnwesenheit.tatendTime" type="time" id="endTime" name="endTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                                </div>
                            </div>
                            <div>
                                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Gruppen <span class="text-red-500">*</span>
                                </label>
                               <Select
                                    v-model="neueAnwesenheit.gruppe"
                                    :options="props.gruppen"
                                    :optionLabel="(g) => `${g.bereich.name} (${new Date(g.anfangsdatum).toLocaleDateString('de-DE')} – ${new Date(g.enddatum).toLocaleDateString('de-DE')}) ${g.betreuer.vorname} ${g.betreuer.nachname} `"
                                    optionValue="id"
                                    class="w-full text-sm px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                                    />

                            </div>
                             <div>
                                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Anwesenheitsstatuten <span class="text-red-500">*</span>
                                </label>
                               <Select
                                    v-model="neueAnwesenheit.anwesenheitsstatus"
                                    :options="props.anwesenheitsstatuten"
                                    optionLabel="status"
                                    optionValue="id"
                                    class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                                    >
                                    <template #option="slotProps">
                                        <div class="flex items-center space-x-2">
                                        <span :class="['w-4 h-4 rounded-full', slotProps.option.farben]"></span>
                                        <span>{{ slotProps.option.status }}</span>
                                        </div>
                                    </template>

                                    <template #value="slotProps">
                                        <div class="flex items-center space-x-2">
                                        <span
                                            :class="[
                                            'w-3 h-3 rounded-full',
                                            props.anwesenheitsstatuten.find(s => s.id === slotProps.value)?.farben || 'bg-gray-300'
                                            ]"
                                        ></span>
                                        <span>
                                            {{ props.anwesenheitsstatuten.find(s => s.id === slotProps.value)?.status || '–' }}
                                        </span>


                                        </div>
                                    </template>
                                </Select>
                            </div>
                            <div>
                                <label for="bemerkungen" class="block text-sm font-medium text-gray-700 mb-2" >
                                    Anwesenheitsstatuten <span class="text-red-500">*</span>
                                </label>
                                <textarea v-model="neueAnwesenheit.bemerkungen" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors py-3"></textarea>

                            </div>


                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button @click="showModalAnwesenheit = false" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
                            <button
                                @click="addAnwesenheit"
                                :disabled="loadingAnwesenheit"
                                class="px-4 py-2 rounded-md text-sm text-white transition"
                                :class="loadingAnwesenheit ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                                >
                                <span v-if="!loadingAnwesenheit">
                                    {{ editMode ? 'Änderungen speichern' : 'Speichern' }}
                                </span>
                                <span v-else>Speichern...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- End MODAL: Anwesenheit -->

            <!-- MODAL: Bank hinzufügen -->
            <transition name="fade">
              <div v-if="showModalBank" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                  <button @click="showModalBank = false" class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl">✕</button>
                  <h3 class="text-lg font-semibold mb-4 text-zbb">Neue Bank hinzufügen</h3>

                  <div class="space-y-4">

                    <!-- Dynamische Eingabe je nach Typ -->
                    <div>
                      <label class="text-sm text-gray-600">Name<span class="text-danger text-md">*</span> </label>
                      <input v-model="neueBank.name" class="input mt-1"/>
                    </div>
                    <div>
                      <label class="text-sm text-gray-600">IBAN<span class="text-danger text-md">*</span> </label>
                      <input v-model="neueBank.iban" class="input mt-1"/>
                    </div>
                    <div>
                      <label class="text-sm text-gray-600">BLZ<span class="text-danger text-md">*</span> </label>
                      <input v-model="neueBank.blz" class="input mt-1"/>
                    </div>
                  </div>

                  <div class="mt-6 flex justify-end space-x-3">
                    <button @click="showModalBank = false" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
                    <button
                      @click="addBank"
                      :disabled="loadingBank"
                      class="px-4 py-2 rounded-md text-sm text-white transition"
                      :class="loadingBank ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                    >
                      <span v-if="!loadingBank">Speichern</span>
                      <span v-else>Speichern...</span>
                    </button>
                  </div>
                </div>
              </div>
            </transition>

            <!-- MODAL: Notiz -->
            <transition name="fade">
                <div v-if="showModalNotiz" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-xl w-1/3  p-6 relative">
                        <button @click="showModalNotiz = false" class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl">✕</button>
                        <h3 class="text-lg font-semibold mb-4 text-zbb">Neue Notiz erstellen</h3>
                        <div class="space-y-4">
                            <div class="flex space-x-4">
                                <div class="w-1/2">
                                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Notiztyp <span class="text-red-500">*</span>
                                    </label>
                                     <Select
                                        v-model="neueNotiz.typ"
                                        :options="props.notiztypen"
                                        optionLabel="name"
                                        optionValue="id"
                                        class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                                        >
                                    </Select>
                                </div>

                                <div class="w-1/2">
                                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Priorität <span class="text-red-500">*</span>
                                    </label>
                                     <Select
                                        v-model="neueNotiz.prioritaet"
                                        :options="props.notizprioritaet"
                                        optionLabel="name"
                                        optionValue="id"
                                        class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                                        >
                                    </Select>
                                </div>
                            </div>

                             <div>
                                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Kategorie <span class="text-red-500">*</span>
                                    </label>
                                     <Select
                                        v-model="neueNotiz.kategorie"
                                        :options="props.notizkategorie"
                                        optionLabel="name"
                                        optionValue="id"
                                        class="w-[200px] text-sm w-full px-4 py-1 border !border-gray-300 rounded-lg focus:!ring-1 focus:!ring-zbb focus:!border-zbb transition-colors"
                                        >
                                    </Select>
                                </div>
                                <div>
                                    <label for="titel" class="block text-sm font-medium text-gray-700 mb-2" >
                                        Notiztitel <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" v-model="neueNotiz.titel" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors py-3"/>
                                </div>

                            <div>
                                <label for="inhalt" class="block text-sm font-medium text-gray-700 mb-2" >
                                    Notizinhalt <span class="text-red-500">*</span>
                                </label>
                                <textarea v-model="neueNotiz.inhalt" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors py-3"></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button @click="showModalNotiz = false" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
                            <button
                                @click="addNotiz"
                                :disabled="loadingNotiz"
                                class="px-4 py-2 rounded-md text-sm text-white transition"
                                :class="loadingNotiz ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                                >
                                <span v-if="!loadingNotiz">
                                    Speichern
                                </span>
                                <span v-else>Speichern...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- End MODAL: Notiz -->


            <!-- MODAL Abschluss-->
            <transition name="fade">
                <div
                    v-if="showModalCreateAbschluss"
                    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
                >
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
                    <button
                        @click="showModalCreateAbschluss = false"
                        class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
                    >
                        ✕
                    </button>
                    <h3 class="text-lg font-semibold mb-4 text-zbb">
                        Neuen Abschluss hinzufügen
                    </h3>

                    <div class="space-y-4">
                        <!-- Typauswahl -->
                        <div>
                            <label class="text-sm text-gray-600">Abschluss-Typ</label>
                            <select v-model="neuerAbschluss.typ" class="input mt-1">
                                <option disabled value="">-- auswählen --</option>
                                <option value="schule">Schulabschluss</option>
                                <option value="beruf">Berufsabschluss</option>
                                <option value="hochschule">Hochschulabschluss</option>
                                <option value="weiterbildung">Weiterbildung</option>
                            </select>
                        </div>

                        <!-- Bezeichnung -->
                        <div v-if="neuerAbschluss.typ">
                            <label class="text-sm text-gray-600">Abschluss</label>
                            <select v-model="neuerAbschluss.abschluss_id" class="input mt-1">
                                <option disabled value="">-- auswählen --</option>
                                <option
                                v-for="a in props.abschluesse.filter(a => a.typ === neuerAbschluss.typ)"
                                :key="a.id"
                                :value="a.id"
                                >
                                {{ a.bezeichnung }}
                                </option>
                            </select>
                        </div>
                        <div v-if="neuerAbschluss.typ">

                            <label class="text-sm text-gray-600">Bezeichnung</label>
                            <input v-model="neuerAbschluss.bezeichnung" class="input" />
                        </div>
                        <!-- Startdatum -->
                        <div>
                            <label class="text-sm text-gray-600">Startdatum</label>
                            <input type="date" v-model="neuerAbschluss.start" class="input" />
                        </div>

                        <!-- Enddatum -->
                        <div>
                            <label class="text-sm text-gray-600">Enddatum</label>
                            <input type="date" v-model="neuerAbschluss.end" class="input" />
                        </div>


                    </div>

                    <!-- Buttons -->
                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                        @click="showModalCreateAbschluss = false"
                        class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-100"
                        >
                        Abbrechen
                        </button>
                        <button
                        @click="addAbschluss"
                        :disabled="loadingAbschluss"
                        class="px-4 py-2 rounded-md text-sm text-white transition"
                        :class="loadingAbschluss ? 'bg-gray-400 cursor-not-allowed' : 'bg-zbb hover:bg-zbb/80'"
                        >
                        <span v-if="!loadingAbschluss">Speichern</span>
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
            @delete="handleDelete"
            :seite="seite"
            :toDelete="toDeleteItem"
        />
  </AppLayout>
</template>

<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, computed, onMounted, watch, watchEffect } from 'vue';
    import { router, Head, Link, usePage } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import Multiselect from '@vueform/multiselect';
    import '@vueform/multiselect/themes/default.css';
    import { formatDate } from '@/utils/dateFormat';
    import { formatTime } from '@/utils/timeFormat';
    import {formatDateTime} from '@/utils/dateFormat';
    import Select from 'primevue/select';
    import Swal from 'sweetalert2'
    import Toggle from '@/Components/Toggle.vue';
    import Alert from '@/Components/Utils/SweetalertSuccessError.vue'
    const { flash } = usePage().props
    import Stammdaten from '@/Pages/Teilnehmer/Tabs/StammdatenSection.vue';

    const props = defineProps({
        teilnehmer: Object,
        gruppen: Array,
        kontakttypen: Array,
        projekte: Array,
        betreuer: Array,
        erhalteneBriefe: Array,
        meineBriefe: Array,
        anwesenheitsstatuten: Array,
        abschluesse: Array, // enthält ALLE Abschlüsse aus Seeder
        leistungsbezuege: Array,
        notizprioritaet: Array,
        notizkategorie: Array,
        notiztypen:Array,
        fahrtarten: Array,
    });


    console.log(props.gruppen);

watchEffect(() => {

  if (flash?.error) {
    Swal.fire({
      icon: "error",
      title: "Fehler",
      text: flash.error,
    });
  }
});

    // Tabs
    const tabs = [
        "Stammdaten",
        "Sozialdaten",
        "Adresse",
        "Kontaktdaten",
        "Projektverlauf",
        "Anwesenheit",
        "Bank",
        "Schule/Beruf",
        "Briefe",
        "Notizen",
        "Kinder",
        "Netzwerke",
        "Vermittlung",
        "Praktika",
        "Fahrtkosten",
        "Exportieren"

    ];

    // Lokale Kopie der Teilnehmerdaten
    const teilnehmer = ref(JSON.parse(JSON.stringify(props.teilnehmer)));
    const neuesProjektId = ref('');
    const loadingProjekt = ref(false);
    const neuesBriefFreigeben = ref([]);


    const neuesProjekt = ref({
        antragsdatum: '',
        starttermin: '',
        endtermin: '',
        anfangsdatum: '',
        enddatum: '',
    });


const loadingBank = ref(false)
const loadingBriefFreigabe = ref(false)
const loadingAnwesenheit = ref(false)
const neueBank = ref({ name: '', iban: '', blz: '' })



const form = ref({
    geschlecht: "m",
    geburtsdatum: "1997-05-17",
    betreuer: "",
    bemerkungen: "",
    notizen: "",
    vermittlung: "",
    bankname: "",
    iban: "",


});
const neueAnwesenheit = ref({
    anwesenheitsstatus: null,
    dateAnwesenheit: '',
    startTime: '',
    endTime: '',
    bemerkungen: '',
});



const activeTab = ref("");

// Formulare & Variablen
const showModalAdresse = ref(false);
const showModalBank = ref(false);
const showModalAnwesenheit = ref(false);
const showModalBrief = ref(false);

const showModalBriefFreigeben = ref("");

const showModalProjektzuweisen = ref(false);
const showModalCreateKontakt = ref(false);
const showModalLöschen = ref(false);
const seite = ref("");
const toDeleteItem = ref(null);

const alter = computed(() => {
  const geb = form.value.geburtsdatum;
  if (!geb) return "";
  const diff = Date.now() - new Date(geb).getTime();
  return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
});

onMounted(() => {
  // Finde den Eintrag "anwesend" aus der Liste
  const standard = props.anwesenheitsstatuten.find(s => s.status === 'anwesend')
  if (standard) {
    neueAnwesenheit.value.anwesenheitsstatus = standard.id
  }
})

// =======  Sozialdaten  =======
// script setup (Ausschnitt)
const drittstaatsangehoerig  = ref(!!props.teilnehmer.sozialedaten?.drittstaatsangehoerig);
const behinderung            = ref(!!props.teilnehmer.sozialedaten?.behinderung);
const gefluechtet            = ref(!!props.teilnehmer.sozialedaten?.gefluechtet);
const migrationshintergrund  = ref(!!props.teilnehmer.sozialedaten?.migrationshintergrund);
const leistungsbezug_id      = ref(props.teilnehmer.sozialedaten?.leistungsbezug_id);
const wohnsitz_stabil        = ref(!!props.teilnehmer.sozialedaten?.wohnsitz_stabil);


const saveSozialdaten = () => {
  router.patch(
    route('person.sozialdaten.update', props.teilnehmer.id),
    {
      ist_drittstaatsangehoerig: drittstaatsangehoerig.value,
      hat_behinderung:          behinderung.value,
      ist_gefluechtet:          gefluechtet.value,
      hat_migrationshintergrund:    migrationshintergrund.value,
      leistungsbezug_id:        leistungsbezug_id.value,
      ist_wohnsitz_stabil: wohnsitz_stabil.value,
      teilnehmer_id: props.teilnehmer.id,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        // Optional: nur bestimmte Props neu laden (spart Full-Reload)
        router.reload({ only: ['teilnehmer'] });
        Swal.fire({
        icon: 'success',
        title: 'Gespeichert!',
        text: 'Abschluss erfolgreich hinzugefügt.',
        timer: 1500,
        showConfirmButton: false,
      });
      },
    }
  );
};
// =======  Stammdaten  =======
const loadingSave = ref(false);

const saveStammdaten = () => {

    // einfache Validierung
    if (!teilnehmer.value.vorname || !teilnehmer.value.nachname) return;

    loadingSave.value = true;
    const payload = {
        vorname: teilnehmer.value.vorname,
        nachname: teilnehmer.value.nachname,
        geschlecht: form.value.geschlecht,
        geburtsdatum: form.value.geburtsdatum,
        //betreuer: form.value.betreuer,
        bemerkungen: form.value.bemerkungen,
    };

    router.patch(
        route('teilnehmer.update', teilnehmer.value.id),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => (loadingSave.value = false),
            onSuccess: () => {
                // lokale Kopie mit bestätigten Werten aktualisieren
                Object.assign(teilnehmer.value, {
                    vorname: payload.vorname,
                    nachname: payload.nachname,
                    geschlecht: payload.geschlecht,
                    geburtsdatum: payload.geburtsdatum,
                    betreuer: payload.betreuer,
                    bemerkungen: payload.bemerkungen,
                });
            },
        }
    );
};

//======= Fahrtkosten abrechnen
        const showModalFahrtkosten = ref(false);
        const loadingFahrtkosten = ref(false);
        const fahrtkostenStatus = ['offen','in bearbeitung', 'abgerechnet', 'bezahlt', 'storniert'];
        const neueFahrtkosten = ref({
            fahrtarten_id: '',
            tag: '',
            start: '',
            ziel: '',
            entfernung: '',
            kosten: '',
            status: '',
        });

  const addFahrtkosten = () => {
  if (!neueFahrtkosten.value.fahrtarten_id || !neueFahrtkosten.value.tag) {
    Swal.fire({
      icon: "warning",
      title: "Fehlende Daten",
      text: "Bitte füllen Sie alle Pflichtfelder aus.",
      timer: 1500,
      showConfirmButton: false,
    });
    return;
  }

  loadingFahrtkosten.value = true;

  axios.post(route("fahrtkostenAbrechnung.store"), {
    teilnehmer_id: props.teilnehmer.id,
    ...neueFahrtkosten.value,
  })
  .then((response) => {
    const fahrtabrechnung = response.data.fahrtabrechnung;

    // ✅ Neues Objekt aus Backend übernehmen (berechnete Werte + Relationen)
    if (!teilnehmer.value.fahrtabrechnungen) {
      teilnehmer.value.fahrtabrechnungen = [];
    }
    teilnehmer.value.fahrtabrechnungen.unshift(fahrtabrechnung);

    // ✅ Formular schließen & zurücksetzen
    showModalFahrtkosten.value = false;
    neueFahrtkosten.value = {
      fahrtarten_id: "",
      tag: "",
      start: "",
      ziel: "",
      entfernung: "",
      kosten: "",
      status: "",
    };

    Swal.fire({
      icon: "success",
      title: "Gespeichert!",
      text: response.data.message || "Fahrtkosten erfolgreich hinzugefügt.",
      timer: 1500,
      showConfirmButton: false,
    });
  })
  .catch((error) => {
    Swal.fire({
      icon: "error",
      title: "Fehler",
      text: error.response?.data?.message || "Die Fahrtkosten konnten nicht gespeichert werden.",
    });
  })
  .finally(() => {
    loadingFahrtkosten.value = false;
  });
};


// ======= End Fahrtkosten abrechnen






// ======= Notizen
        const showModalNotiz = ref(false);
        const loadingNotiz = ref(false);
        const neueNotiz = ref({
            typ: '',
            kategorie: '',
            prioritaet: '',
            titel: '',
            inhalt: '',
        });
        const filter = ref({
        suche: "",
        typ: "",
        prioritaet: "",
        kategorie: ""
        });
        const gefilterteNotizen = computed(() => {
        return props.teilnehmer.notizen.filter((n) => {
            const matchSuche =
            filter.value.suche === "" ||
            n.notizinhalt.toLowerCase().includes(filter.value.suche.toLowerCase()) ||
            n.titel.toLowerCase().includes(filter.value.suche.toLowerCase());

            const matchTyp =
            filter.value.typ === "" || n.notiztyp_id === filter.value.typ;

            const matchPrioritaet =
            filter.value.prioritaet === "" ||
            n.prioritaet_id === filter.value.prioritaet;

            const matchKategorie =
            filter.value.kategorie === "" ||
            n.kategorie_id === filter.value.kategorie;

            return matchSuche && matchTyp && matchPrioritaet && matchKategorie;
        });
        });

        const addNotiz = () => {
        if (!neueNotiz.value.typ || !neueNotiz.value.kategorie || !neueNotiz.value.prioritaet || !neueNotiz.value.titel || !neueNotiz.value.inhalt) {
            Swal.fire({
            icon: 'warning',
            title: 'Fehldende Daten',
            text: 'Bitte füllen Sie alle Pflichfelder aus.',
            timer: 1500,
            showConfirmButton: false,
            });
            return;
        }

        loadingNotiz.value = true;

        const payload = {
                person_id: props.teilnehmer.id,
                notiztyp: neueNotiz.value.typ,
                notizkategorie: neueNotiz.value.kategorie,
                prioritaet: neueNotiz.value.prioritaet,
                titel: neueNotiz.value.titel,
                inhalt: neueNotiz.value.inhalt,
        };

        router.post(route('notizen.store'), payload, {
            onSuccess: () => {
            // Lokale Liste sofort aktualisieren (Frontend ohne Reload)
            const selected = props.abschluesse.find(a => a.id === payload.abschluss_id);
            if (selected) {
                if (!props.teilnehmer.notizen) props.teilnehmer.notizen = [];
                    teilnehmer.value.notizen.unshift({
                    ...selected,

                });
            }

            showModalNotiz.value = false;
                loadingNotiz.value = false;

            neueNotiz.value = { notiztyp: '', notizkategorie: '', prioritaet: '', titel: '', inhalt: '' };

            Swal.fire({
                icon: 'success',
                title: 'Gespeichert!',
                text: 'Abschluss erfolgreich hinzugefügt.',
                timer: 1500,
                showConfirmButton: false,
            });
            },
            onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Fehler',
                text: 'Der Abschluss konnte nicht gespeichert werden.',
            });
            },
            onFinish: () => (loadingAbschluss.value = false),
        });
    };
// ======= End Notizen

// ======= Schule/Beruf

// 🔧 Reaktive Zustände
const showModalCreateAbschluss = ref(false);
const loadingAbschluss = ref(false);

// Neues Abschlussobjekt (wird im Modal befüllt)
const neuerAbschluss = ref({
  typ: '',
  abschluss_id: '',
  bezeichnung: '',
});

// 🧠 Funktion: Abschluss hinzufügen
const addAbschluss = () => {
  if (!neuerAbschluss.value.abschluss_id) {
    Swal.fire({
      icon: 'warning',
      title: 'Bitte auswählen',
      text: 'Wählen Sie einen Abschluss aus.',
      timer: 1500,
      showConfirmButton: false,
    });
    return;
  }

  loadingAbschluss.value = true;

  const payload = {
        person_id: props.teilnehmer.id,
        abschluss_id: neuerAbschluss.value.abschluss_id,
        abschluss_typ: neuerAbschluss.value.typ,
        bezeichnung: neuerAbschluss.value.bezeichnung,
        start: neuerAbschluss.value.start,
        end: neuerAbschluss.value.end,
  };

  router.post(route('abschluss.store'), payload, {
    onSuccess: () => {
      // Lokale Liste sofort aktualisieren (Frontend ohne Reload)
      const selected = props.abschluesse.find(a => a.id === payload.abschluss_id);
      if (selected) {
        if (!props.teilnehmer.abschluesse) props.teilnehmer.abschluesse = [];
            teilnehmer.value.abschluesse.unshift({
            ...selected,
            pivot_model: {
                bezeichnung: payload.bezeichnung,
                start: payload.start,
                end: payload.end,
            },
        });
      }

      showModalCreateAbschluss.value = false;
      neuerAbschluss.value = { typ: '', abschluss_id: '', bezeichnung: '', start: '', end: '' };

      Swal.fire({
        icon: 'success',
        title: 'Gespeichert!',
        text: 'Abschluss erfolgreich hinzugefügt.',
        timer: 1500,
        showConfirmButton: false,
      });
    },
    onError: () => {
      Swal.fire({
        icon: 'error',
        title: 'Fehler',
        text: 'Der Abschluss konnte nicht gespeichert werden.',
      });
    },
    onFinish: () => (loadingAbschluss.value = false),
  });
};

// ======= Brief =======
const openFreigabeModal = (briefData) => {
  brief.value = briefData; // speichert den ausgewählten Brief für die Freigabe
  showModalBriefFreigeben.value = true;
};

    const neuerBrief = ref({name: "", titel: "", content: ""});
    const brief = ref({
        datum: new Date().toISOString().split("T")[0],
        betreff: "",
        inhalt: "",
    });
    // 🔹 Funktion: generiert automatisch die passende Anrede
    const generiereAnrede = (teilnehmer) => {
    if (!teilnehmer) return "";

    if (teilnehmer.geschlecht === "m") {
        return `Sehr geehrter Herr ${teilnehmer.nachname},\n\n`;
    } else if (teilnehmer.geschlecht === "w") {
        return `Sehr geehrte Frau ${teilnehmer.nachname},\n\n`;
    } else {
        // neutral / divers / unbekannt
        return `Sehr geehrte*r ${teilnehmer.vorname} ${teilnehmer.nachname},\n\n`;
    }
    };
    const loadingBrief = ref(false);
     const setBriefVorlage = (vorlage) => {
        const anrede = generiereAnrede(props.teilnehmer);

        brief.value.betreff = vorlage.title || '';
        brief.value.inhalt = anrede + (vorlage.content || '');
    };

const briefFreigeben = async () => {
  // Prüfen, ob ein Betreuer ausgewählt wurde
  if (!neuesBriefFreigeben.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Keine Person ausgewählt',
      text: 'Bitte wähle mindestens eine Person aus, für die der Brief freigegeben werden soll.',
    });
    return;
  }

  // Prüfen, ob auch ein Brief existiert
  if (!brief.value || !brief.value.id) {
    Swal.fire({
      icon: 'warning',
      title: 'Kein Brief ausgewählt',
      text: 'Bitte wähle zuerst den Brief aus, den du freigeben möchtest.',
    });
    return;
  }

  loadingBriefFreigabe.value = true;

  try {
    await router.post(
      route('brief.share'),
      {
        brief_id: brief.value.id, // <-- Hier wird jetzt korrekt die Brief-ID gesendet!
        betreuer_ids: Array.isArray(neuesBriefFreigeben.value)
          ? neuesBriefFreigeben.value
          : [neuesBriefFreigeben.value],
      },
      {
        preserveScroll: true,
        onFinish: () => (loadingBriefFreigabe.value = false),
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Freigabe erfolgreich',
            text: 'Der Brief wurde erfolgreich freigegeben.',
            timer: 2000,
            showConfirmButton: false,
          });
          showModalBriefFreigeben.value = false;
            neuesBriefFreigeben.value = [];
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Fehler',
            text: 'Beim Freigeben des Briefes ist ein Fehler aufgetreten.',
          });
          console.error(errors);
        },
      }
    );
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Fehler',
      text: e.message || 'Freigabe konnte nicht abgeschlossen werden.',
    });
  }
};

const addBrief = () => {
    if (!neuerBrief.value.name || !neuerBrief.value.titel || !neuerBrief.value.content) return;

    loadingBrief.value = true;

    router.post(
        route("brief.store"),
        {
            name: neuerBrief.value.name,
            titel: neuerBrief.value.titel,
            content: neuerBrief.value.content,
        },
        {
            preserveScroll: true,
    onFinish: () => (loadingBrief.value = false),
    onSuccess: () => {
        showModalBrief.value = false;
        neuerBrief.value = { name: "", titel: "", content: "" };
    },
        }
    );
};







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
        model_type: "App\\Models\\Personen",
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
      model_type: "App\\Models\\Personen",
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



// =======  ANWESENHEIT =======
const selectedMonth = ref("");
// 🧠 Gruppiere Anwesenheiten (bzw. Gruppen) nach Monat
const gruppenNachMonat = computed(() => {
  if (!teilnehmer.value?.gruppen) return {}

  const gruppiert = {}

  teilnehmer.value.gruppen.forEach((g) => {
    const datum = new Date(g.pivot.tag.datum)
    const monat = datum.toLocaleString("de-DE", { month: "long", year: "numeric" })
    if (!gruppiert[monat]) gruppiert[monat] = []
    gruppiert[monat].push(g)
  })

  for (const key in gruppiert) {
    gruppiert[key].sort((a, b) => new Date(b.pivot.tag.datum) - new Date(a.pivot.tag.datum))
  }

  return gruppiert
})


// 🧮 Liste aller verfügbaren Monate für Filter
const verfuegbareMonate = computed(() => Object.keys(gruppenNachMonat.value))

 const editMode = ref(false);
    const aktuelleAnwesenheit = ref(null);
const openModalEdit = (anwesenheit) => {
  editMode.value = true;
  aktuelleAnwesenheit.value = anwesenheit;
  showModalAnwesenheit.value = true;

  neueAnwesenheit.value = {
    dateAnwesenheit: anwesenheit.tag?.datum || '',
    startTime: anwesenheit.zeit?.startzeit || '',
    endTime: anwesenheit.zeit?.endzeit || '',
    anwesenheitsstatus: props.anwesenheitsstatuten.find(
      s => s.status === anwesenheit.status?.status
    )?.id || null,
    bemerkungen: anwesenheit.bemerkung || ''
  };
};

const addAnwesenheit = () => {
  // 🧩 Validierung
  if (
    !neueAnwesenheit.value.dateAnwesenheit ||
    !neueAnwesenheit.value.startTime ||
    !neueAnwesenheit.value.endTime ||
    !neueAnwesenheit.value.gruppe
  ) {
    Swal.fire({
      icon: 'warning',
      title: 'Fehlende Angaben',
      text: 'Bitte füllen Sie alle Pflichtfelder aus.',
    });
    return;
  }

  const payload = {
    id: aktuelleAnwesenheit.value?.id || null,
    personen_id: props.teilnehmer.id,
    tag: neueAnwesenheit.value.dateAnwesenheit,
    startzeit: neueAnwesenheit.value.startTime,
    endzeit: neueAnwesenheit.value.endTime,
    anwesenheitsstatuten_id: neueAnwesenheit.value.anwesenheitsstatus,
    bemerkung: neueAnwesenheit.value.bemerkungen,
    gruppe_id : neueAnwesenheit.value.gruppe,
    tatstartTime: neueAnwesenheit.value.tatstartTime,
    tatendTime: neueAnwesenheit.value.tatendTime,
  };

  loadingAnwesenheit.value = true;

  router.post(
    editMode.value
      ? route('anwesenheit.update')
      : route('anwesenheit.store'),
    payload,
    {
      preserveScroll: true,
      onSuccess: () => {
        const statusObj = props.anwesenheitsstatuten.find(
          s => s.id === payload.anwesenheitsstatuten_id
        );

        if (editMode.value && aktuelleAnwesenheit.value) {
          // 🔄 Lokale Aktualisierung (Update)
          Object.assign(aktuelleAnwesenheit.value, {
            tag: { datum: payload.tag },
            zeit: { startzeit: payload.startzeit, endzeit: payload.endzeit },
            status: statusObj,
            bemerkung: payload.bemerkung,
          });
        } else {
          // ➕ Neue Anwesenheit hinzufügen (Create)
            teilnehmer.value.gruppen.unshift({
                id: payload.gruppe_id,
                bereich: props.gruppen.find(g => g.id === payload.gruppe_id)?.bereich || { name: 'Unbekannt' },
                betreuer: props.gruppen.find(g => g.id === payload.gruppe_id)?.betreuer || {},
                pivot: {
                    id: Date.now(), // temporäre ID bis zum Reload
                    tag: { datum: payload.tag },
                    zeitgeplant: { startzeit: payload.startzeit, endzeit: payload.endzeit },
                    zeittatsaechlich: null,
                    anwesenheitsstatuten_id: payload.anwesenheitsstatuten_id,
                    bemerkung: payload.bemerkung,
                },
            });
        }

        // ✅ Modal schließen & zurücksetzen
        showModalAnwesenheit.value = false;
        editMode.value = false;
        aktuelleAnwesenheit.value = null;
        neueAnwesenheit.value = {
          dateAnwesenheit: '',
          startTime: '',
          endTime: '',
          tatstartTime: '',
          tatendTime: '',
          anwesenheitsstatus: null,
          bemerkungen: '',
        };

        // ✅ Erfolgsmeldung
        Swal.fire({
          icon: 'success',
          title: 'Gespeichert!',
          text: editMode.value
            ? 'Die Anwesenheit wurde aktualisiert.'
            : 'Neue Anwesenheit wurde hinzugefügt.',
          timer: 1800,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({
          icon: 'error',
          title: 'Fehler',
          text: 'Beim Speichern ist ein Fehler aufgetreten.',
        });
      },
      onFinish: () => {
        loadingAnwesenheit.value = false;
      },
    }
  );
};



watch(showModalAnwesenheit, (val) => {
  if (!val) {
    editMode.value = false;
    aktuelleAnwesenheit.value = null;
    neueAnwesenheit.value = {
      anwesenheitsstatus: null,
      dateAnwesenheit: '',
      startTime: '',
      endTime: '',
      tatstartTime: '',
      tatendTime: '',
      bemerkungen: '',
      gruppe:'',
    };
  }
});



// ======= PROJEKTE ZUWEISEN =======
const addProjekt = () => {
  if (!neuesProjektId.value) return; // Sicherheitsabfrage
  loadingProjekt.value = true;

  router.post(
    route("projekthasteilnehmer.store"),
    {
      teilnehmer_id: props.teilnehmer.id,
      projekt_id: neuesProjektId.value,
      model_type: 'App\\Models\\ProjektHasPersonen',
      ...neuesProjekt.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => (loadingProjekt.value = false),
      onSuccess: () => {
        // Projekt aus den Props suchen
        const projekt = props.projekte.find(
          (p) => p.id === neuesProjektId.value
        );

        if (projekt) {
          // 🔥 Neues Projekt ganz oben einfügen (nicht unten)
          teilnehmer.value.projekte.unshift({
            ...projekt,
            pivot_model: {
              zeitraume: [
                {
                  antragsdatum: neuesProjekt.value.antragsdatum || null,
                  starttermin: neuesProjekt.value.starttermin || null,
                  endtermin: neuesProjekt.value.endtermin || null,
                  anfangsdatum: neuesProjekt.value.anfangsdatum || null,
                  enddatum: neuesProjekt.value.enddatum || null,
                },
              ],
            },
            esf: false,
            jc_mitarbeiter: "",
          });
        }

        // Modal schließen & Eingaben zurücksetzen
        neuesProjektId.value = "";
        neuesProjekt.value = {
          antragsdatum: "",
          starttermin: "",
          endtermin: "",
          anfangsdatum: "",
          enddatum: "",
        };
        showModalProjektzuweisen.value = false; // ✅ korrekt schließen
      },
    }
  );
};

const addBank = () => {
  if (!neueBank.value.name || !neueBank.value.iban || !neueBank.value.blz) return
  loadingBank.value = true

  router.post(
    route('bank.store'),
    {
      ...neueBank.value,
      model_type: 'App\\Models\\Personen',
      model_id: teilnehmer.value.id,
    },
    {
      preserveState: true, //
      onFinish: () => (loadingBank.value = false),
      onSuccess: () => {
        // Tabelle sofort aktualisieren
        teilnehmer.value.baenke.unshift({ ...neueBank.value, id: Date.now() });

        // Eingaben zurücksetzen
        neueBank.value = { name: '', iban: '', blz: '' };

        // Modal schließen
        showModalBank.value = false;

      },
    }
  )
}



// ====================== LÖSCHEN ======================
// Lokale Kopien der Brief-Arrays (reaktiv)
const meineBriefe = ref([...props.meineBriefe]);
const erhalteneBriefe = ref([...props.erhalteneBriefe]);

const confirmDelete = (item, type) => {
    console.log(item.id);
  toDeleteItem.value = { id: item.id, name: item.name || item.wert || item.strasse || item.bezeichnung || item.titel };
  seite.value = type;
  showModalLöschen.value = true;
};

const handleDelete = (id) => {
  if (seite.value === 'adresse') {
    teilnehmer.value.adresses = teilnehmer.value.adresses.filter((a) => a.id !== id);
  }
  if (seite.value === 'kontakt') {
    teilnehmer.value.kontaktes = teilnehmer.value.kontaktes.filter((k) => k.id !== id);
  }
  if (seite.value === 'bank') {
    teilnehmer.value.baenke = teilnehmer.value.baenke.filter((b) => b.id !== id);
  }

  if (seite.value === 'projekt') {
    teilnehmer.value.projekte = teilnehmer.value.projekte.filter((p) => p.id !== id);
  }
  if (seite.value === 'gruppeHasPersonen') {
   teilnehmer.value.gruppen = teilnehmer.value.gruppen.filter(
        (g) => g.pivot.id !== id
      );
}




  if (seite.value === 'brief') {
    meineBriefe.value = meineBriefe.value.filter((b) => b.id !== id);
 }
 if (seite.value === 'briefShared') {
    erhalteneBriefe.value = erhalteneBriefe.value.filter((b) => b.id !== id);
 }
if (seite.value === 'abschluss') {
  window.location.reload();
}
if (seite.value === 'notizen') {
  props.teilnehmer.notizen = props.teilnehmer.notizen.filter((n) => n.id !== id);
}
if (seite.value === 'fahrtkostenAbrechnung') {
  teilnehmer.value.fahrtabrechnungen = teilnehmer.value.fahrtabrechnungen.filter(
    (n) => n.id !== id
  );
}

  showModalLöschen.value = false;
};

</script>

<style scoped>
.input {
  @apply mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-zbbTrp focus:border-zbb text-sm;
}

.input-auto {
  @apply mt-1 w-auto border-gray-300 rounded-md shadow-sm focus:ring-zbbTrp focus:border-zbb text-sm;
}
.th {
  @apply px-2 py-1  text-gray-600 font-medium;
}
</style>

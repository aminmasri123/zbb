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
            <button @click="saveStammdaten"
                class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full">
                ➕ Speichern
              </button>


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
                <select v-model="teilnehmer.geschlecht" class="input">
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
                  <option v-for="m in props.betreuer" :key="m">{{ m.nachname }} - {{ m.vorname }}</option>
                </select>
              </div>
              <div class="md:col-span-3">
                <label>Bemerkungen</label>
                <textarea v-model="teilnehmer.bemerkungen" rows="2" class="input"></textarea>
              </div>
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
          <!-- ================= BRIEFE ================= -->
          <div v-else-if="activeTab === 'Briefe'">
            <button @click="showModalBrief = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                <span v-if="!loadingBrief">➕ Vorage erstellen</span>
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
                        <label>Meine</label>
                        <ul class="border border-gray-200 rounded p-2 text-sm">
                            <li
                                v-for="v in props.meineBriefe"
                                :key="v"
                                class="cursor-pointer hover:text-zbb"
                                 @click="setBriefVorlage(v)">
                                {{ v?.name }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <label>Shared</label>
                        <ul class="border border-gray-200 rounded p-2 text-sm">
                        <li
                            v-for="erhalteneBrief in props.erhalteneBriefe"
                            :key="erhalteneBrief"
                            class="cursor-pointer hover:text-zbb"
                             @click="setBriefVorlage(erhalteneBrief)">
                            {{ erhalteneBrief.name }}
                        </li>
                        </ul>
                    </div>
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
        </div>
      </div>
    </div>


            <!-- MODAL: Brief hinzufügen -->
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
    import { ref, defineProps, computed } from 'vue';
    import { router, Head, Link } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import Multiselect from '@vueform/multiselect';
    import '@vueform/multiselect/themes/default.css';

    const props = defineProps({
        teilnehmer: Object,
        kontakttypen: Array,
        projekte: Array,
        betreuer: Array,
        erhalteneBriefe: Array,
        meineBriefe: Array,
    });
    console.log(props.meineBriefe);
    // Lokale Kopie der Teilnehmerdaten
    const teilnehmer = ref(JSON.parse(JSON.stringify(props.teilnehmer)));
    const neuesProjektId = ref('');
    const loadingProjekt = ref(false);

    const neuesProjekt = ref({
        antragsdatum: '',
        starttermin: '',
        endtermin: '',
        anfangsdatum: '',
        enddatum: '',
    });


const loadingBank = ref(false)
const neueBank = ref({ name: '', iban: '', blz: '' })



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


// Tabs
const tabs = [
    "Stammdaten",
    "Adresse",
    "Kontaktdaten",
    "Projektverlauf",
    "Bank",
    "Briefe",
    "Aktennotiz",
    "Notizen",
    "Kinder",
    "Netzwerke",
    "Vermittlung",
    "Praktika",

];
const activeTab = ref("");

// Formulare & Variablen
const showModalAdresse = ref(false);
const showModalBank = ref(false);
const showModalBrief = ref(false);

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

// =======  Stammdaten  =======
const loadingSave = ref(false);

const saveStammdaten = () => {

    // einfache Validierung
    if (!teilnehmer.value.vorname || !teilnehmer.value.nachname) return;

    loadingSave.value = true;
    console.log("Speichern Stammdaten:", teilnehmer.value);
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


// ======= Brief =======
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

        console.log("Vorlage gewählt:", vorlage);
        brief.value.betreff = vorlage.title || '';
        brief.value.inhalt = anrede + (vorlage.content || '');
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

// ====================== LÖSCHEN ======================
const confirmDelete = (item, type) => {
  toDeleteItem.value = { id: item.id, name: item.name || item.wert || item.strasse };
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
  showModalLöschen.value = false;
};





// ======= PROJEKTE ZUWEISEN =======
const addProjekt = () => {
  if (!neuesProjektId.value) return; // Sicherheitsabfrage
    console.log("Zuweisen Projekt ID:", neuesProjekt.value);
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

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
                <nav class="flex flex-wrap gap-1 border-b pb-2 mb-4 justify-center">
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


                            <FloatLabel variant="on">
                                <InputText  v-model="kundennummer" label="kundennummer?"  size="small"  class="w-full" />
                                <label for="abteilungDelete">Kundennummer*</label>
                            </FloatLabel>
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
                        <span v-if="!loadingProjekt">Zeitraum hinzufügen</span>
                        <span v-else>...</span>
                    </button>
                    <table class="min-w-full border border-gray-300 text-sm text-center">
                        <thead class="bg-gray-100">
                            <tr>
                            <th class="px-4 py-2 border">Projekte</th>
                            <th class="px-4 py-2 border">Teilnahmestatus</th>
                            <th class="px-4 py-2 border">Standort</th>
                            <th class="px-4 py-2 border">Betreuer</th>
                            <th class="px-4 py-2 border">Projektbegleiter</th>
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
                                    <td
                                        v-if="z === 0"
                                        :rowspan="projekt.pivot_model?.zeitraume?.length || 1"
                                        class="border px-4 py-2 align-middle bg-gray-50"
                                    >
                                        {{ participationStatusLabel(projekt.pivot_model?.status) }}
                                    </td>
                                    <td
                                        v-if="z === 0"
                                        :rowspan="projekt.pivot_model?.zeitraume?.length || 1"
                                        class="border px-4 py-2 align-middle bg-gray-50"
                                    >
                                        {{ getProjektStandortName(projekt) }}
                                    </td>
                                    <td class="border px-4 py-2">{{ projekt.pivot_model.meta?.betreuer?.geschlecht == 'w' ? 'Frau' : (projekt.pivot_model.meta?.betreuer?.geschlecht == 'm' ? 'Herr' : '') }} {{ projekt.pivot_model.meta?.betreuer?.vorname }} {{ projekt.pivot_model.meta?.betreuer?.nachname }}</td>
                                    <td class="border px-4 py-2">{{ projekt.pivot_model.meta?.projektbegleiter?.geschlecht == 'w' ? 'Frau' : (projekt.pivot_model.meta?.projektbegleiter?.geschlecht == 'm' ? 'Herr' : '') }} {{ projekt.pivot_model.meta?.projektbegleiter?.vorname }} {{ projekt.pivot_model.meta?.projektbegleiter?.nachname }}</td>
                                    <td class="border px-4 py-2">{{ formatDate(zeit.antragsdatum)  || '-' }}</td>
                                    <td class="border px-4 py-2">{{ formatDate(zeit.anfangsdatum)  || '-' }}</td>
                                    <td class="border px-4 py-2">{{ formatDate(zeit.enddatum) || '-'  }}</td>
                                    <td class="border px-4 py-2">{{ formatDate(zeit.starttermin)  || '-' }}</td>
                                    <td class="border px-4 py-2">{{ formatDate(zeit.endtermin)  || '-' }}</td>
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
                                                <span
                                                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                                                    @click="openProjektEdit(zeit, projekt)"
                                                    >
                                                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                                </span>
                                                <a  target="_blank" :href="route('export.excel.esfStammblatt', [props.teilnehmer.id, projekt.id])" class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100" >ESF <i class="las la-file-download"></i></a>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>

                                <!-- Falls keine Zeiträume vorhanden sind -->
                                <tr v-if="!projekt.pivot_model?.zeitraume?.length" class="hover:bg-gray-50">
                                    <td class="border px-4 py-2 font-medium bg-gray-50">{{ projekt.name }}</td>
                                    <td class="border px-4 py-2 bg-gray-50">{{ participationStatusLabel(projekt.pivot_model?.status) }}</td>
                                    <td class="border px-4 py-2 bg-gray-50">{{ getProjektStandortName(projekt) }}</td>
                                    <td class="border px-4 py-2">{{ projekt.pivot_model.meta?.betreuer?.geschlecht == 'w' ? 'Frau' : (projekt.pivot_model.meta?.betreuer?.geschlecht == 'm' ? 'Herr' : '------') }} {{ projekt.pivot_model.meta?.betreuer?.vorname }} {{ projekt.pivot_model.meta?.betreuer?.nachname }}</td>
                                    <td class="border px-4 py-2 font-medium bg-gray-50">{{ projekt.pivot_model.meta?.projektbegleiter?.geschlecht == 'w' ? 'Frau' : 'Herr' }} {{ projekt.pivot_model.meta?.projektbegleiter?.vorname }} {{ projekt.pivot_model.meta?.projektbegleiter?.nachname }}</td>
                                    <td colspan="5" class="border px-4 py-2 text-gray-500 italic"> Keine Zeiträume vorhanden</td>
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
                                                <span
                                                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                                                    @click="openProjektEdit(null, projekt)"
                                                    >
                                                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                                                </span>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- =================== Aufnahme =================== -->
                <div v-else-if="activeTab === 'Aufnahme'" class="mx-auto mt-6 max-w-5xl">
                    <div class="rounded-2xl border bg-white p-6 shadow-sm">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-zbb">Aufnahmecheckliste</h3>
                                <p class="text-sm text-gray-500">Prüfstand der Teilnahme im aktuell gewählten Projekt.</p>
                            </div>
                            <div class="flex items-center gap-3 text-right">
                                <div v-if="$page.props.enabledModules?.participant_portal">
                                    <span v-if="props.portalAccess?.account" class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Portal aktiv · {{ props.portalAccess.account.email }}</span>
                                    <button v-else type="button" class="rounded border border-zbb px-3 py-2 text-xs font-semibold text-zbb" @click="createPortalInvitation">Portalzugang einladen</button>
                                </div>
                                <div>
                                <p class="text-2xl font-semibold text-zbb">{{ intakeProgress.completed }}/{{ intakeProgress.total }}</p>
                                <p class="text-xs text-gray-500">{{ intakeProgress.percent }} % abgeschlossen</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full bg-zbb transition-all" :style="{ width: `${intakeProgress.percent}%` }"></div>
                        </div>

                        <div v-if="intakeChecklistItems.length" class="space-y-3">
                            <label v-for="item in intakeChecklistItems" :key="item.id" class="flex items-start gap-4 rounded-xl border p-4" :class="item.completions?.[0]?.completed ? 'border-green-200 bg-green-50' : 'border-gray-200'">
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb"
                                    :checked="Boolean(item.completions?.[0]?.completed)"
                                    :disabled="intakeSavingItemId === item.id || !props.activeParticipationId"
                                    @change="updateIntakeCompletion(item, $event.target.checked)"
                                />
                                <span class="flex-1">
                                    <span class="font-semibold text-gray-800">{{ item.label }}</span>
                                    <span v-if="item.required" class="ml-2 rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">Pflicht</span>
                                    <span v-if="item.description" class="mt-1 block text-sm text-gray-500">{{ item.description }}</span>
                                    <span v-if="item.completions?.[0]?.completed_at" class="mt-1 block text-xs text-green-700">
                                        Erledigt am {{ formatDateTime(item.completions[0].completed_at) }}
                                        <template v-if="item.completions[0].completed_by?.name"> durch {{ item.completions[0].completed_by.name }}</template>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p v-else class="rounded border border-dashed p-6 text-center text-sm text-gray-500">
                            Für dieses Projekt wurde noch keine Aufnahmecheckliste konfiguriert.
                        </p>
                    </div>
                </div>

                <!-- =================== Aufgaben =================== -->
                <div v-else-if="activeTab === 'Aufgaben'" class="mx-auto mt-6 max-w-6xl">
                    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
                        <form class="rounded-2xl border bg-white p-5 shadow-sm" @submit.prevent="createParticipationTask">
                            <h3 class="text-lg font-semibold text-zbb">Neue Aufgabe</h3>
                            <p class="mb-4 text-sm text-gray-500">Aufgabe und Frist für diese Projektteilnahme.</p>
                            <div class="space-y-3">
                                <input v-model="taskForm.title" required maxlength="255" placeholder="Aufgabe" class="w-full rounded border-gray-300 text-sm" />
                                <textarea v-model="taskForm.description" maxlength="2000" rows="3" placeholder="Beschreibung" class="w-full rounded border-gray-300 text-sm"></textarea>
                                <select v-model="taskForm.assignee_person_id" class="w-full rounded border-gray-300 text-sm">
                                    <option value="">Keine verantwortliche Person</option>
                                    <option v-for="person in props.betreuer" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option>
                                </select>
                                <div class="grid grid-cols-2 gap-3">
                                    <select v-model="taskForm.priority" class="rounded border-gray-300 text-sm">
                                        <option value="low">Niedrig</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">Hoch</option>
                                    </select>
                                    <input v-model="taskForm.due_at" type="date" class="rounded border-gray-300 text-sm" />
                                </div>
                                <label class="flex items-start gap-2 text-sm text-gray-600">
                                    <input v-model="taskForm.visible_to_participant" type="checkbox" class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb" />
                                    Im Teilnehmerportal anzeigen
                                </label>
                                <button type="submit" class="w-full rounded bg-zbb px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="taskSaving || !props.activeParticipationId">
                                    {{ taskSaving ? 'Speichert …' : 'Aufgabe anlegen' }}
                                </button>
                            </div>
                        </form>

                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-zbb">Aufgaben und Fristen</h3>
                                    <p class="text-sm text-gray-500">{{ openParticipationTasks }} offen, {{ overdueParticipationTasks }} überfällig</p>
                                </div>
                            </div>
                            <div v-if="participationTaskItems.length" class="space-y-3">
                                <article v-for="task in participationTaskItems" :key="task.id" class="rounded-xl border p-4" :class="task.status === 'done' ? 'bg-green-50 border-green-200' : isTaskOverdue(task) ? 'bg-red-50 border-red-200' : 'border-gray-200'">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-800">{{ task.title }}</p>
                                            <p v-if="task.description" class="mt-1 text-sm text-gray-500">{{ task.description }}</p>
                                            <p class="mt-2 text-xs text-gray-500">
                                                Verantwortlich: {{ task.assignee ? `${task.assignee.vorname} ${task.assignee.nachname}` : 'nicht zugewiesen' }}
                                                <span v-if="task.due_at"> · Fällig: {{ formatDate(task.due_at) }}</span>
                                            </p>
                                            <p v-if="task.visible_to_participant" class="mt-1 text-xs font-medium text-blue-600">Für Teilnehmer sichtbar</p>
                                        </div>
                                        <span class="rounded px-2 py-1 text-xs font-semibold" :class="task.priority === 'high' ? 'bg-red-100 text-red-700' : task.priority === 'low' ? 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-700'">
                                            {{ task.priority === 'high' ? 'Hoch' : task.priority === 'low' ? 'Niedrig' : 'Normal' }}
                                        </span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <select v-model="task.status" class="rounded border-gray-300 py-1 text-xs" @change="saveParticipationTask(task)">
                                            <option value="open">Offen</option>
                                            <option value="progress">In Arbeit</option>
                                            <option value="done">Erledigt</option>
                                        </select>
                                        <button type="button" class="text-xs text-red-600" @click="deleteParticipationTask(task)">Löschen</button>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="rounded border border-dashed p-6 text-center text-sm text-gray-500">Noch keine Aufgaben für diese Teilnahme.</p>
                        </div>
                    </div>
                </div>

                <!-- =================== Teilnahmeabschluss =================== -->
                <div v-else-if="activeTab === 'Teilnahmeabschluss'" class="mx-auto mt-6 max-w-6xl">
                    <div class="grid gap-6 lg:grid-cols-[1fr_420px]">
                        <section class="rounded-2xl border bg-white p-6 shadow-sm"><h3 class="text-lg font-semibold text-zbb">Abschlusscheckliste</h3><p class="mb-4 text-sm text-gray-500">Pflichtpunkte müssen vor der Freigabe erledigt sein.</p><div v-if="completionChecklistItems.length" class="space-y-3"><label v-for="item in completionChecklistItems" :key="item.id" class="block rounded-xl border p-4" :class="item.completions?.[0]?.completed ? 'border-green-200 bg-green-50' : 'border-gray-200'"><div class="flex items-start gap-3"><input type="checkbox" class="mt-1 rounded border-gray-300 text-zbb" :checked="Boolean(item.completions?.[0]?.completed)" @change="updateCompletionCheck(item, $event.target.checked)"/><span class="flex-1"><span class="font-semibold">{{ item.label }}</span><span v-if="item.required" class="ml-2 rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">Pflicht</span><span v-if="item.description" class="block text-sm text-gray-500">{{ item.description }}</span></span></div><textarea v-model="item.local_note" rows="2" maxlength="3000" placeholder="Optionaler Prüfvermerk" class="mt-3 w-full rounded border-gray-300 text-sm" @change="updateCompletionCheck(item, Boolean(item.completions?.[0]?.completed))"></textarea></label></div><p v-else class="rounded border border-dashed p-5 text-sm text-gray-500">Im Projekt sind noch keine Abschlussprüfpunkte konfiguriert.</p></section>
                        <form class="rounded-2xl border bg-white p-6 shadow-sm" @submit.prevent="submitCompletionReport"><h3 class="text-lg font-semibold text-zbb">Bericht einreichen</h3><p class="mb-4 text-sm text-gray-500">Jedes Einreichen erzeugt eine unveränderliche neue Version.</p><div class="space-y-3"><select v-model="completionReportForm.completion_type" required class="w-full rounded border-gray-300 text-sm"><option value="completed">Regulär abgeschlossen</option><option value="terminated">Vorzeitig beendet</option></select><input v-model="completionReportForm.exit_date" type="date" required class="w-full rounded border-gray-300 text-sm"/><input v-model="completionReportForm.outcome" required maxlength="255" placeholder="Ergebnis / Verbleib" class="w-full rounded border-gray-300 text-sm"/><textarea v-model="completionReportForm.summary" required maxlength="20000" rows="6" placeholder="Sachliche Zusammenfassung" class="w-full rounded border-gray-300 text-sm"></textarea><textarea v-model="completionReportForm.recommendations" maxlength="10000" rows="3" placeholder="Empfehlungen / nächste Schritte" class="w-full rounded border-gray-300 text-sm"></textarea><button type="submit" class="w-full rounded bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="completionReportSaving">{{ completionReportSaving ? 'Wird eingereicht …' : 'Neue Version einreichen' }}</button></div></form>
                    </div>
                    <section class="mt-6 rounded-2xl border bg-white p-6 shadow-sm"><h3 class="text-lg font-semibold">Berichtsversionen</h3><div v-if="completionReportItems.length" class="mt-4 space-y-3"><article v-for="report in completionReportItems" :key="report.id" class="rounded-xl border p-4"><div class="flex flex-wrap justify-between gap-3"><div><p class="font-semibold">Version {{ report.version }} · {{ report.outcome }}</p><p class="text-xs text-gray-500">{{ formatDate(report.exit_date) }} · SHA-256 {{ report.snapshot_sha256 }}</p></div><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="report.status === 'approved' ? 'bg-green-100 text-green-700' : report.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">{{ report.status === 'approved' ? 'Freigegeben' : report.status === 'rejected' ? 'Abgelehnt' : 'Zur Freigabe' }}</span></div><p class="mt-3 whitespace-pre-wrap text-sm">{{ report.summary }}</p><p v-if="report.decision_note" class="mt-2 rounded bg-gray-50 p-3 text-sm">Entscheidung: {{ report.decision_note }}</p><div class="mt-3 flex flex-wrap gap-2"><template v-if="report.status === 'submitted'"><button type="button" class="rounded bg-green-700 px-3 py-1.5 text-xs text-white" @click="decideCompletionReport(report, 'approved')">Freigeben</button><button type="button" class="rounded bg-red-700 px-3 py-1.5 text-xs text-white" @click="decideCompletionReport(report, 'rejected')">Ablehnen</button></template><a v-if="report.status === 'approved'" :href="route('teilnehmer.completion-reports.export', report.id)" class="rounded border border-zbb px-3 py-1.5 text-xs text-zbb">JSON-Nachweis exportieren</a></div></article></div><p v-else class="mt-4 text-sm text-gray-500">Noch kein Abschlussbericht eingereicht.</p></section>
                </div>

                <!-- =================== Bewerbungen =================== -->
                <div v-else-if="activeTab === 'Bewerbungen'" class="mx-auto mt-6 max-w-6xl">
                    <div class="rounded-2xl border bg-white p-6 shadow-sm">
                        <div class="mb-4"><h3 class="text-lg font-semibold text-zbb">Bewerbungscockpit</h3><p class="text-sm text-gray-500">Bewerbungen dieser Projektteilnahme und vereinbarte nächste Schritte.</p></div>
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4"><h4 class="font-semibold text-emerald-900">Stelle empfehlen</h4><div class="mt-3 grid gap-3 md:grid-cols-2"><input v-model="recommendationForm.title" maxlength="255" placeholder="Stellenbezeichnung" class="rounded border-gray-300 text-sm"/><input v-model="recommendationForm.employer" maxlength="255" placeholder="Arbeitgeber" class="rounded border-gray-300 text-sm"/><input v-model="recommendationForm.location" maxlength="255" placeholder="Ort" class="rounded border-gray-300 text-sm"/><input v-model="recommendationForm.source_url" maxlength="2048" placeholder="Link zur Stellenanzeige" class="rounded border-gray-300 text-sm"/><textarea v-model="recommendationForm.note" maxlength="3000" rows="2" placeholder="Warum passt diese Stelle?" class="rounded border-gray-300 text-sm md:col-span-2"></textarea></div><button class="mt-3 rounded bg-emerald-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="recommendationSaving||!recommendationForm.title.trim()" @click="createRecommendation">Empfehlen</button></div>
                        <div v-if="jobRecommendationItems.length" class="mb-6"><h4 class="text-sm font-semibold text-gray-700">Bisherige Empfehlungen</h4><div class="mt-2 space-y-2"><p v-for="item in jobRecommendationItems" :key="item.id" class="rounded border p-3 text-sm"><span class="font-semibold">{{ item.title }}</span> · {{ item.employer || 'Arbeitgeber offen' }} · <span :class="item.converted_application_id?'text-green-700':item.dismissed_at?'text-red-600':item.viewed_at?'text-blue-600':'text-amber-600'">{{ item.converted_application_id?'als Bewerbung übernommen':item.dismissed_at?'nicht passend':item.viewed_at?'angesehen':'neu' }}</span></p></div></div>
                        <div v-if="participationApplicationItems.length" class="space-y-4">
                            <article v-for="application in participationApplicationItems" :key="application.id" class="rounded-xl border p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-semibold text-gray-900">{{ application.title }}</p><p class="text-sm text-gray-500">{{ application.employer || 'Arbeitgeber nicht angegeben' }} · {{ application.location || 'Ort nicht angegeben' }}</p></div><a v-if="application.source_url" :href="application.source_url" target="_blank" rel="noopener noreferrer" class="text-xs text-zbb underline">Stellenanzeige</a></div>
                                <div class="mt-4 grid gap-3 md:grid-cols-4">
                                    <select v-model="application.status" class="rounded border-gray-300 text-sm"><option v-for="status in applicationStatuses" :key="status" :value="status">{{ applicationStatusLabels[status] }}</option></select>
                                    <label class="text-xs text-gray-500">Beworben am<input v-model="application.applied_at" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                                    <label class="text-xs text-gray-500">Nächster Schritt<input v-model="application.next_action_at" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
                                    <button type="button" class="self-end rounded bg-zbb px-3 py-2 text-sm text-white" @click="saveStaffApplication(application)">Speichern</button>
                                </div>
                                <textarea v-model="application.notes" rows="2" maxlength="3000" placeholder="Sachliche Notiz zum Bewerbungsprozess" class="mt-3 w-full rounded border-gray-300 text-sm"></textarea>
                                <div class="mt-4 rounded-lg bg-gray-50 p-3"><p class="text-sm font-semibold">Bewerbungspaket</p><label v-for="doc in staffApplicationDocuments" :key="doc.id" class="mt-2 flex items-center gap-2 text-sm"><input v-model="application.selected_document_ids" type="checkbox" :value="doc.id"/>{{ doc.original_name }} · {{ doc.category }}</label><p v-if="!staffApplicationDocuments.length" class="mt-2 text-xs text-gray-500">Zuerst ein Portal-Dokument prüfen und freigeben.</p><div class="mt-3 flex flex-wrap items-center gap-2"><button class="rounded border px-3 py-2 text-xs" @click="saveStaffApplicationPackage(application)">Auswahl speichern</button><button class="rounded bg-green-600 px-3 py-2 text-xs text-white" :disabled="!application.selected_document_ids?.length" @click="approveStaffApplicationPackage(application)">Fachlich freigeben</button><span class="text-xs" :class="application.participant_package_approved_at?'text-green-700':'text-amber-700'">Teilnehmer: {{ application.participant_package_approved_at?'freigegeben':'offen' }}</span><span class="text-xs" :class="application.staff_package_approved_at?'text-green-700':'text-amber-700'">Team: {{ application.staff_package_approved_at?'geprüft':'offen' }}</span></div></div>
                                <p class="mt-2 text-xs text-gray-400">{{ application.status_history?.length || 0 }} Statusereignisse dokumentiert</p>
                            </article>
                        </div>
                        <p v-else class="rounded border border-dashed p-6 text-center text-sm text-gray-500">Noch keine Bewerbungen für diese Teilnahme.</p>
                    </div>
                </div>

                <div v-else-if="activeTab === 'Nachrichten'" class="mx-auto mt-6 max-w-6xl">
                    <div class="rounded-2xl border bg-white p-6 shadow-sm">
                        <div class="mb-4"><h3 class="text-lg font-semibold text-zbb">Nachrichten</h3><p class="text-sm text-gray-500">Geschützter Verlauf innerhalb dieser Projektteilnahme.</p></div>
                        <div class="max-h-[32rem] space-y-3 overflow-y-auto rounded-xl bg-gray-50 p-4">
                            <article v-for="message in portalMessageItems" :key="message.id" class="flex" :class="message.sender_kind === 'staff' ? 'justify-end' : 'justify-start'"><div class="max-w-[80%] rounded-2xl px-4 py-3" :class="message.sender_kind === 'staff' ? 'bg-zbb text-white' : 'border bg-white text-gray-800'"><p class="text-xs font-semibold opacity-75">{{ message.sender_kind === 'staff' ? staffMessageSender(message) : 'Teilnehmer' }}</p><p class="mt-1 whitespace-pre-wrap text-sm">{{ message.body }}</p><p class="mt-1 text-right text-[11px] opacity-60">{{ formatDateTime(message.created_at) }}</p></div></article>
                            <p v-if="!portalMessageItems.length" class="py-8 text-center text-sm text-gray-500">Noch keine Nachrichten.</p>
                        </div>
                        <form class="mt-4 flex gap-3" @submit.prevent="sendStaffMessage"><textarea v-model="staffMessageBody" maxlength="5000" rows="2" placeholder="Nachricht an den Teilnehmer" class="flex-1 rounded border-gray-300 text-sm"></textarea><button class="self-end rounded bg-zbb px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="staffMessageSending || !staffMessageBody.trim()">Senden</button></form>
                    </div>
                </div>

                <div v-else-if="activeTab === 'Einwilligungen'" class="mx-auto mt-6 max-w-6xl">
                    <div class="rounded-2xl border bg-white p-6 shadow-sm"><div class="mb-4"><h3 class="text-lg font-semibold text-zbb">Einwilligungen und Widerrufe</h3><p class="text-sm text-gray-500">Unveränderlicher Nachweis für diese Projektteilnahme.</p></div><div class="space-y-4"><article v-for="definition in consentDefinitionItems" :key="definition.id" class="rounded-xl border p-4"><div class="flex flex-wrap justify-between gap-3"><div><p class="font-semibold">{{ definition.title }} <span class="text-xs text-gray-500">v{{ definition.version }}</span></p><p class="mt-1 text-sm text-gray-600">{{ definition.purpose }}</p></div><span class="rounded-full px-3 py-1 text-xs" :class="latestConsentEvent(definition)?.action==='accepted'&&latestConsentEvent(definition)?.definition_version===definition.version?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'">{{ latestConsentEvent(definition)?.action==='accepted'&&latestConsentEvent(definition)?.definition_version===definition.version?'Zugestimmt':'Offen / widerrufen' }}</span></div><details class="mt-3 text-sm"><summary class="cursor-pointer text-zbb">Nachweisverlauf anzeigen</summary><div class="mt-2 space-y-2"><p v-for="event in consentHistory(definition)" :key="event.id" class="rounded bg-gray-50 p-2">{{ event.action==='accepted'?'Zustimmung':'Widerruf' }} · Version {{ event.definition_version }} · {{ formatDateTime(event.occurred_at) }} · SHA-256: {{ event.content_sha256 }}</p><p v-if="!consentHistory(definition).length" class="text-gray-500">Noch kein Ereignis.</p></div></details></article><p v-if="!consentDefinitionItems.length" class="text-sm text-gray-500">Für dieses Projekt sind keine Einwilligungen konfiguriert.</p></div></div>
                </div>

                <div v-else-if="activeTab === 'Datenauskunft'" class="mx-auto mt-6 max-w-6xl"><div class="rounded-2xl border bg-white p-6 shadow-sm"><div class="mb-4"><h3 class="text-lg font-semibold text-zbb">Datenauskunft und Betroffenenanfragen</h3><p class="text-sm text-gray-500">Identität prüfen und Entscheidung dokumentieren. Löschung oder Berichtigung erfolgt niemals automatisch.</p></div><div class="space-y-4"><article v-for="item in participantDataRequestItems" :key="item.id" class="rounded-xl border p-4"><div class="flex justify-between"><div><p class="font-semibold">{{ dataRequestLabels[item.type] }}</p><p class="text-xs text-gray-500">{{ formatDateTime(item.created_at) }}</p></div><span class="rounded-full bg-gray-100 px-3 py-1 text-xs">{{ dataRequestStatuses[item.status] }}</span></div><p v-if="item.request_details" class="mt-2 text-sm text-gray-600">{{ item.request_details }}</p><div v-if="item.status==='submitted'" class="mt-4 grid gap-3 md:grid-cols-2"><input v-model="item.identity_verification_method" maxlength="255" placeholder="Identitätsprüfung, z. B. Ausweis persönlich geprüft" class="rounded border-gray-300 text-sm"/><textarea v-model="item.resolution_note" maxlength="5000" placeholder="Entscheidung / Begründung" class="rounded border-gray-300 text-sm"></textarea><div class="flex gap-2 md:col-span-2"><button class="rounded bg-green-600 px-4 py-2 text-sm text-white" @click="resolveDataRequest(item,'approved')">Freigeben</button><button class="rounded bg-red-600 px-4 py-2 text-sm text-white" @click="resolveDataRequest(item,'rejected')">Ablehnen</button></div></div><p v-else-if="item.resolution_note" class="mt-3 rounded bg-gray-50 p-3 text-sm">{{ item.resolution_note }}</p></article><p v-if="!participantDataRequestItems.length" class="text-sm text-gray-500">Keine Anfragen für diese Projektteilnahme.</p></div></div></div>

                <div v-else-if="activeTab === 'Lebenslauf'" class="mx-auto mt-6 max-w-6xl"><div class="rounded-2xl border bg-white p-6 shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-lg font-semibold text-zbb">Strukturierter Lebenslauf</h3><p class="text-sm text-gray-500">Gemeinsam bearbeiten, Design wählen und als PDF exportieren.</p></div><Link :href="route('teilnehmer.resume.index', personen.id)" class="rounded-lg bg-zbb px-4 py-2 font-semibold text-white">Lebenslauf bearbeiten</Link></div><p v-if="!participantCv.visible" class="mt-4 rounded bg-amber-50 p-4 text-sm text-amber-800">Der Teilnehmer hat sein Portalprofil nicht für Projektmitarbeiter freigegeben. Die Lebenslaufdaten bleiben über die Berechtigungen geschützt.</p><template v-else><div class="mt-4 rounded bg-gray-50 p-4"><p class="font-semibold">{{ participantCv.profile?.professional_headline }}</p><p class="mt-1 text-sm">{{ participantCv.profile?.career_goal }}</p></div><div class="mt-4 space-y-3"><article v-for="entry in participantCv.entries" :key="entry.id" class="rounded border p-4"><div class="flex justify-between"><div><p class="font-semibold">{{ entry.title }}</p><p class="text-sm text-gray-500">{{ entry.organization }} · {{ entry.location }}</p></div><span class="text-xs text-gray-500">{{ entry.type }}</span></div><p class="mt-2 whitespace-pre-wrap text-sm">{{ entry.description }}</p></article></div><div class="mt-4"><p class="font-semibold">Veröffentlichte Versionen</p><p v-for="version in participantCv.versions" :key="version.id" class="mt-2 text-sm">Version {{ version.version }} · {{ version.label||'ohne Bezeichnung' }} · SHA-256 {{ version.snapshot_sha256 }}</p></div></template></div></div>

                <div v-else-if="activeTab === 'Portal-Dokumente'" class="mx-auto mt-6 max-w-6xl">
                    <div class="rounded-2xl border bg-white p-6 shadow-sm"><div class="mb-4"><h3 class="text-lg font-semibold text-zbb">Portal-Dokumente</h3><p class="text-sm text-gray-500">Unterlagen dieser Projektteilnahme prüfen oder bereitstellen.</p></div><div class="mb-5 grid gap-3 md:grid-cols-4"><select v-model="staffDocumentCategory" class="rounded border-gray-300"><option value="cv">Lebenslauf</option><option value="application">Bewerbungsunterlage</option><option value="certificate">Zeugnis/Nachweis</option><option value="other">Sonstiges</option></select><input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="rounded border p-2 text-sm" @change="staffDocumentFile=$event.target.files[0]"/><label class="flex items-center gap-2 text-sm"><input v-model="staffDocumentVisible" type="checkbox"/>Für Teilnehmer sichtbar</label><button class="rounded bg-zbb px-4 py-2 text-white" @click="uploadStaffDocument">Bereitstellen</button></div><div class="space-y-3"><article v-for="doc in portalDocumentItems" :key="doc.id" class="rounded-xl border p-4"><div class="flex flex-wrap justify-between gap-3"><div><p class="font-semibold">{{ doc.original_name }}</p><p class="text-xs text-gray-500">{{ doc.category }} · {{ Math.ceil(doc.size/1024) }} KB · {{ doc.status }}</p></div><a :href="route('teilnehmer.portal-documents.download',doc.id)" class="text-sm text-zbb underline">Download</a></div><div class="mt-3 flex flex-wrap gap-2"><input v-model="doc.review_note" class="min-w-64 flex-1 rounded border-gray-300 text-sm" placeholder="Prüfhinweis"/><label class="flex items-center gap-2 text-sm"><input v-model="doc.visible_to_participant" type="checkbox"/>sichtbar</label><button class="rounded bg-green-600 px-3 py-2 text-xs text-white" @click="reviewPortalDocument(doc,'approved')">Freigeben</button><button class="rounded bg-red-600 px-3 py-2 text-xs text-white" @click="reviewPortalDocument(doc,'rejected')">Ablehnen</button></div></article><p v-if="!portalDocumentItems.length" class="text-sm text-gray-500">Keine Portal-Dokumente vorhanden.</p></div></div>
                </div>

                <!-- =================== Anwesenheit =================== -->
                <div v-else-if="activeTab === 'Anwesenheit'">
                    <div v-if="attendanceCorrectionItems.length" class="mx-auto mt-4 w-5/6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h3 class="font-semibold text-amber-900">Korrekturanfragen</h3>
                        <div class="mt-3 space-y-3"><article v-for="correction in attendanceCorrectionItems" :key="correction.id" class="rounded border bg-white p-3"><p class="text-sm font-semibold">{{ formatDate(correction.attendance?.tag?.datum) }} · {{ correction.attendance?.status?.status }}</p><p class="mt-1 text-sm text-gray-600">{{ correction.message }}</p><div v-if="correction.status==='open'" class="mt-2 flex flex-wrap gap-2"><input v-model="correction.resolution_note" placeholder="Antwort / Begründung" class="min-w-64 flex-1 rounded border-gray-300 text-sm"/><button class="rounded bg-green-600 px-3 py-2 text-xs text-white" @click="resolveAttendanceCorrection(correction,'accepted')">Annehmen</button><button class="rounded bg-red-600 px-3 py-2 text-xs text-white" @click="resolveAttendanceCorrection(correction,'rejected')">Ablehnen</button></div><p v-else class="mt-2 text-xs" :class="correction.status==='accepted'?'text-green-700':'text-red-700'">{{ correction.status==='accepted'?'Angenommen':'Abgelehnt' }} · {{ correction.resolution_note }}</p></article></div>
                    </div>
                    <!-- Anwesenheit hinzufügen -->
                    <div class="flex gap-4 text-center justify-center">
                        <button v-if="can('anwesenheit.manage')" @click="showModalAnwesenheit = true" class="bg-zbb w-4/6 text-white px-4 mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition" >
                            <span v-if="!loadingProjekt">➕ Anwesenheit</span>
                                <span v-else>...</span>
                        </button>
                        <div class="mb-6 mt-4">
                            <select
                                v-model="selectedMonth"
                                class="border-zbb rounded-md text-sm focus:ring-zbb focus:border-zbb"
                            >
                                <option value="">Gesamtlaufzeit des Projekts</option>
                                <option v-for="monat in verfuegbareMonate" :key="monat" :value="monat">
                                    {{ monat }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="teilnehmer.anwesenheiten.length" class="w-5/6 mx-auto mt-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="text-sm text-gray-500">Auswertung</p>
                                <h3 class="text-lg font-semibold text-zbb">{{ selectedMonth || 'Gesamtlaufzeit des aktiven Projekts' }}</h3>
                            </div>
                            <span class="text-sm text-gray-500">{{ anwesenheitsAuswertung.tage }} erfasste Tage</span>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-xl border bg-white p-4 shadow-sm">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Sollzeit</p>
                                <p class="mt-1 text-xl font-semibold font-mono">{{ formatMinutes(anwesenheitsAuswertung.soll) }}</p>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Istzeit</p>
                                <p class="mt-1 text-xl font-semibold font-mono">{{ formatMinutes(anwesenheitsAuswertung.ist) }}</p>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Saldo</p>
                                <p class="mt-1 text-xl font-semibold font-mono" :class="abweichungsClass(anwesenheitsAuswertung.saldo)">
                                    {{ formatMinutes(anwesenheitsAuswertung.saldo) }}
                                </p>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Anwesenheit</p>
                                <p class="mt-1 text-xl font-semibold">{{ anwesenheitsAuswertung.anwesenheitsquote }} %</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <span
                                v-for="status in anwesenheitsAuswertung.statusSummen"
                                :key="status.status"
                                class="inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1 text-sm"
                            >
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: status.farben }"></span>
                                {{ status.status }}: <strong>{{ status.anzahl }}</strong>
                            </span>
                        </div>
                    </div>

                    <!-- Tabelle -->
                    <div class="bg-white rounded-2xl shadow-md border mt-8 p-8 mx-auto w-5/6">
                        <!-- Wenn keine Anwesenheit -->
                        <div v-if="teilnehmer.anwesenheiten.length === 0" class="text-gray-500 italic text-sm">
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
                            <div v-for="(anwesenheiten, monat) in gruppenNachMonat" :key="monat">
                                <div v-if="!selectedMonth || selectedMonth === monat" class="mt-8">
                                    <h4 class="text-lg font-semibold text-zbb border-b pb-1 mb-3">📆 {{ monat }}</h4>
                                    <div v-for="anwesenheit in anwesenheiten" :key="anwesenheit.id" class="bg-white border border-gray-200 rounded-xl px-3 py-4 shadow-sm hover:shadow-md mb-4" >
                                    <div class="flex py-4">
                                        <!-- Bereich & Datum -->
                                        <div class="w-1/4 font-semibold">
                                            <p v-if="anwesenheit.gruppe" class="text-zbb"><span class="text-lg ml-8">🎨</span> {{ anwesenheit.gruppe?.bereich.name }}</p>
                                            <p class="text-zbb"><span class="text-lg ml-8">📅</span> {{ formatDate(anwesenheit.tag.datum) }}</p>
                                        </div>

                                        <!-- Soll -->
                                        <div class="w-1/4 font-semibold ">
                                        <p class="ml-8 mr-8">🗓️ Geplante Arbeitszeit</p>
                                        <p><span class="text-lg ml-8">⏰</span>
                                            {{ formatTime(anwesenheit.zeitgeplant.startzeit) }} -
                                            {{ formatTime(anwesenheit.zeitgeplant.endzeit) }}
                                        </p>
                                        </div>

                                        <!-- Ist -->
                                        <div class="w-1/4 font-semibold ">
                                        <p class="ml-8 mr-8">💼 Tatsächliche Arbeitszeit</p>
                                        <p v-if="istAnwesend(anwesenheit)"><span class="text-lg ml-8">⌛</span>
                                            {{ formatTime(anwesenheit.zeittatsaechlich?.startzeit) }} -
                                            {{ formatTime(anwesenheit.zeittatsaechlich?.endzeit) }}
                                        </p>
                                        <p v-else class="ml-8 text-gray-500">— (abwesend)</p>
                                        </div>

                                        <!-- Abweichung -->
                                        <div class="w-1/4 font-semibold ">
                                        <p class="ml-8 mr-8">🔥 Abweichung</p>
                                            <p class="flex items-center ml-8">
                                                <span
                                                class="text-2xl mr-2"
                                                :class="abweichungsClass(berechneAbweichungMinuten(anwesenheit))"
                                                >
                                                {{ abweichungsIcon(berechneAbweichungMinuten(anwesenheit)) }}
                                                </span>

                                                <span
                                                class="text-lg font-mono"
                                                :class="abweichungsClass(berechneAbweichungMinuten(anwesenheit))"
                                                >
                                                {{ formatMinutes(berechneAbweichungMinuten(anwesenheit)) }}
                                                </span>
                                            </p>
                                        </div>

                                    </div>

                                    <!-- Status-Badge + Buttons -->
                                    <div class="flex justify-between mt-8 px-10">
                                        <div :style="{ backgroundColor: anwesenheit.status.farben }"
                                            class="py-2 px-4 text-white shadow-lg rounded-full">
                                        {{ anwesenheit.status.abkuerzung }}
                                        </div>

                                        <div class="flex gap-2">
                                        <button v-if="can('anwesenheit.manage')" @click="openModalEdit(anwesenheit)"
                                                class="px-4 py-2 text-sm font-medium rounded-md bg-zbb text-white shadow-sm hover:bg-zbb/90">
                                            Verwalten
                                        </button>

                                        <button v-if="can('anwesenheit.destroy')" @click="confirmDelete(anwesenheit, 'anwesenheit')"
                                                class="px-4 py-2 text-sm font-medium rounded-md bg-red-600 text-white shadow-sm hover:bg-red-700">
                                            Löschen
                                        </button>
                                        </div>
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

                        <button @click="showModalPraktikumCreate = true" class="bg-zbb text-white px-4 mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                            <span>➕ Praktikum hinzufügen</span>

                        </button>
                        <div v-if="teilnehmer.praktika && teilnehmer.praktika.length" class="space-y-3 mb-6">
                            <div
                            v-for="praktikum in (teilnehmer.praktika || []).slice().reverse()"
                                :key="praktikum.id"
                                class="flex justify-between items-start bg-gray-50 border rounded-lg px-4 py-3"
                            >
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-zbb uppercase">
                                        {{ praktikum.typ }}
                                    </p>
                                    <p class="text-gray-600 text-sm">
                                        📍 <span class="font-semibold">{{ praktikum.traeger || 'Keine Angabe' }}</span>
                                    </p>
                                    <p class="text-gray-600 text-sm">
                                        📅 {{ formatDate(praktikum.start) }} - {{ formatDate(praktikum.end) }}
                                    </p>
                                    <p
                                        class="text-xs font-medium mt-2 px-2 py-1 rounded-full inline-block"
                                        :class="{
                                            'bg-blue-100 text-blue-700': praktikum.status === 'geplant',
                                            'bg-yellow-100 text-yellow-700': praktikum.status === 'laufend',
                                            'bg-green-100 text-green-700': praktikum.status === 'abgeschlossen',
                                            'bg-red-100 text-red-700': praktikum.status === 'abgebrochen',
                                        }"
                                    >
                                        {{ praktikum.status }}
                                    </p>
                                    <p v-if="praktikum.bemerkung" class="text-gray-600 text-sm mt-2 italic">
                                        💬 {{ praktikum.bemerkung }}
                                    </p>
                                    <p v-if="praktikum.contact_name" class="mt-2 text-xs text-gray-600">Kontakt: {{ praktikum.contact_name }}<span v-if="praktikum.contact_email"> · {{ praktikum.contact_email }}</span><span v-if="praktikum.contact_phone"> · {{ praktikum.contact_phone }}</span></p>
                                    <p v-if="praktikum.next_follow_up_at" class="mt-1 text-xs font-medium" :class="new Date(praktikum.next_follow_up_at) < new Date() && ['geplant','laufend'].includes(praktikum.status) ? 'text-red-600' : 'text-gray-500'">Nachverfolgung: {{ formatDate(praktikum.next_follow_up_at) }}</p>
                                    <p v-if="praktikum.objective" class="mt-2 text-sm"><span class="font-semibold">Ziel:</span> {{ praktikum.objective }}</p>
                                    <p v-if="praktikum.result" class="mt-2 text-sm"><span class="font-semibold">Ergebnis:</span> {{ praktikum.result }}</p>
                                    <div v-if="praktikum.editing" class="mt-4 grid gap-3 rounded border bg-white p-4 md:grid-cols-2"><input v-model="praktikum.traeger" maxlength="255" placeholder="Träger" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.contact_name" maxlength="255" placeholder="Ansprechpartner" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.contact_email" type="email" maxlength="255" placeholder="E-Mail" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.contact_phone" maxlength="50" placeholder="Telefon" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.start" type="date" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.end" type="date" class="rounded border-gray-300 text-sm"/><input v-model="praktikum.next_follow_up_at" type="date" class="rounded border-gray-300 text-sm"/><input v-model.number="praktikum.weekly_hours" type="number" min="1" max="168" placeholder="Wochenstunden" class="rounded border-gray-300 text-sm"/><select v-model="praktikum.status" class="rounded border-gray-300 text-sm"><option value="geplant">Geplant</option><option value="laufend">Laufend</option><option value="abgeschlossen">Abgeschlossen</option><option value="abgebrochen">Abgebrochen</option></select><input v-model="praktikum.status_note" maxlength="3000" placeholder="Vermerk zum Statuswechsel" class="rounded border-gray-300 text-sm"/><textarea v-model="praktikum.objective" maxlength="10000" rows="2" placeholder="Ziel" class="rounded border-gray-300 text-sm md:col-span-2"></textarea><textarea v-model="praktikum.result" maxlength="10000" rows="2" placeholder="Ergebnis (bei Abschluss/Abbruch erforderlich)" class="rounded border-gray-300 text-sm md:col-span-2"></textarea><textarea v-model="praktikum.bemerkung" maxlength="10000" rows="2" placeholder="Bemerkung" class="rounded border-gray-300 text-sm md:col-span-2"></textarea><div class="flex gap-2 md:col-span-2"><button type="button" class="rounded bg-zbb px-3 py-2 text-xs text-white" @click="savePraktikum(praktikum)">Verlauf speichern</button><button type="button" class="rounded border px-3 py-2 text-xs" @click="praktikum.editing=false">Abbrechen</button></div></div>
                                    <details v-if="praktikum.status_history?.length" class="mt-3 text-xs text-gray-500"><summary class="cursor-pointer">Statusverlauf ({{ praktikum.status_history.length }})</summary><p v-for="history in praktikum.status_history" :key="history.id" class="mt-1">{{ history.from_status || 'Neu' }} → {{ history.to_status }} · {{ formatDateTime(history.created_at) }}<span v-if="history.note"> · {{ history.note }}</span></p></details>
                                </div>
                                <div class="ml-4 flex flex-col gap-2"><button type="button" class="text-sm text-zbb" @click="praktikum.editing=!praktikum.editing">Bearbeiten</button><button type="button" class="text-sm text-red-600" @click="archivePraktikum(praktikum)">Archivieren</button></div>
                            </div>
                        </div>
                        <p v-else class="text-gray-400 italic mb-6">
                            Noch keine Praktika vorhanden.
                        </p>

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
                    <div class="flex justify-center mx-auto items-center mb-4">
                        <input type="text" v-model="exportSuche" placeholder="🔍 Dokument suchen..." class="w-3/4 rounded-md border-gray-300 text-sm px-3 py-2 focus:ring-zbb focus:border-zbb" />
                    </div>

                    <div class="flex flex-wrap">
                        <div v-for="dok in gefilterteDokumente" :key="dok.id" class="w-1/4 cursor-pointer" >
                            <a
                                v-if="dok && dok.dateipfadName"
                                :href="route('export.' + dok.dateipfadName, { id: teilnehmer.id, pfad: dok.dateipfad })"
                                class="block"
                            >

                                <div class="rounded-lg shadow m-2 py-6 px-8 min-h-32 flex items-center gap-4 border-4 border-zbb bg-gray-50 hover:bg-gray-100 transition">
                                    <!-- Icon je nach Typ -->
                                    <span class="text-5xl">
                                        <span v-if="dok.typ === 'word'" >
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAAAsTAAALEwEAmpwYAAAC/UlEQVR4nO1ZW0hUQRjeoJfojEtFPUgPLvXQ5amEXop6qTMLolBiRDfCQiK6PASCFaIEJmGIUIJmdeZ4SdMwMTRvabWleM0W0WorRNPcTNSzum3rOnH+3R1SFFwLdrT5YJh//ll+vm/nn38OMwaDgICAwLLEqv05oeiAGokwSZKwUo5kZcDAJfbVr1yNle1IVk5KMslAslIjYWUYYUJnt2BTNayLykGSmeyRZCUOyGJiQZg45yIbdAHGiPw1OlmElUuSrKhIVrqQTDwLJRt0AegviM7XTJnaolrYHc2ypAWYMrXAV08IwEIA5U5AoDAJAZjQ3efKWcDEe20zCHXafoDfPTVN10fmMf/NgnfgHxn/SUPMQU6htRG5dNI1BYTyq22MzIaofCDux94LT9lcmaUXfLWtX/nYA83ddiDU9v478+HLlTOW+3z6GzbX0zsKPn0lgp5CCBOaVdYNAR2TbpYSCVkt4NMmf0GfXd7DVszl9oDvaHI9HwLOpllY0G0nSsBX0vAFxjUt/dA3ddnBH37mCfvtlmPFfKTQrrgyRurQtVrw9X7TYHwxoxH6CaebGs0q/Os6hkac/JwDRrPKUuVKdgs1HS4Ce8zhohujH9Jp317eebqUJj9oB7uyqY+PMop87bV1CIKqzz7SmMQ6sJ+3D8CcrX8MxqdSXtDCuk9gp+S+5UvA7cddEFSvSDfyOsFOK/RWmeJ67364VWilHR+GwY72pRoXKYQwobGpL9nhVNHYB/bx6w0wd/VuK4yrmvvp+IQ31TYdKeJLwI7YUiCm5/vngXGwt/oqUkR8FYz7hhze3u7g51MC+Zpe/0c1Fwv+Z5UJPVhAPf6dTCmcxNwJQJjQho5BFnx2lfFvZB1J99vnFBDUFEKY0PRHVkZydpXxb2QdUQnVfApAS/FzGgkBZPmkkEkICABCAP53AsIytVf/3+XufNfrRlkJZ28BPF+vL+aBA2GSqr/GcPvAsXDQFSGyulkyqzFIJikSJhVIJoMBBBAQEBAwLA38BsvuGopgwMs1AAAAAElFTkSuQmCC" alt="ms-word">
                                        </span>
                                        <span v-else-if="dok.typ === 'excel'">
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAAAsTAAALEwEAmpwYAAACAElEQVR4nGNgGAWjYBSMgiEJxCYEZ4hNCP4oNjHkP6lY81A96fhg3Uetg3Xp1PQAWY4n2wOHwPgD9TxApuMp9MD/UQ/AwKgHDo16YJh6gLvQooC70OILT5HFf4pwieV/oWaPAfHAF4odD8PFFv95GlwxsOSaVLIxAyFANcfD8KgHUkc98F+4zPb/ndeP/oNA1aZJcHGtlsD/P37/+v/t14//Oq3Bg9cDPEUW/yPmlYE98ObL+/8SlU5gsZVnd4LFGrZOH9wxwAPFu64fBzu4cduM/5Y9sf///vv7//arh+AYGhIeMOmM/P/rz+//7799+n/s3oX///79++81LXvw5wEeJDzl4Ir/MLD8zPahkYl5kPDCE5vgHlhxdgdBD4i7a2FgoWlBZGMGSjzgOTULnGxOPbj8/8jd82BP+M/MHxoeEC6z/X/jxX2wo0Hp3n5CEtgzD98++y9W4Tj4PdC9ZwHY8TuuHYWLbbp0ACzWu3fh4PaAWVcUuPQBFZvWvXFwccOOsP+///4BY6ve2MHrAR4K8KgHpo00D3AXmX+mmgcKzAfAA4XmeVTxRIH5P6Eo/W/i7prv0bFqhgPZmIFaQMJD6z+5WD3NnmzMMOoBKBj1QNqoB0a6B9y1PtLfA3bUm+CQ9NBKk/DQ+kA/D9h9UE2xT6WaB0bBKBgFo4CBGgAA6UC2Ig/cY8oAAAAASUVORK5CYII=" alt="microsoft-excel">
                                        </span>
                                        <span v-else-if="dok.typ === 'pdf'">
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAAAsTAAALEwEAmpwYAAACHklEQVR4nO3Xz0uUQRzH8edf8ReJ2iGFDqZ4Euzg1UfLFikTTDxEBhJBCUFmt6gIsSB08SIeokAXEb2FoOlBaDMslFzGiCWy/LXuO8ZhfdLFInNmd2g+MDzPHha+r2e/83xnPc/FRW/wc9Gy6nK6rQUkW8+YQ6ABsP12luSVCjMILQARM4dAF8AUAp0AYQChHSA0I4wAhEaEMYDQhDAKEBoQxgFiPyIrAX+zPAfwHQD7AHdCsLIIP9Yg3G0h4EuMvSR3oLXcIsCl0qD4+dfq2nPZIsD5E7CTUIUvv7MQ4OfCh/mgfWQ6ay0DhO8FbZRIQKjYMkBzGWxtKMD7uSMXT0bnwMy4AkSnoT7PMkBDAcRXgzbqu2nhIJPZ3tq97A609iqLABNDqvDhhzAVUfexj9By2gJAcxlsfFezoK0SLp6C5QWFWHijfp1nt2HkOYwNQiQMj67BuYIsAQw9UMXKTSw/d9RAZIA/ZnI4CwBNJ+FbXBUUnQGxtL/I1J5IZWURlqLqfnM9wwB5hHjZl/5kZfu86IWuBmgsVENObuqDmZ3MEKA+H3pvwOdPQTGyQNnfhx0h5IHvcQe8egqj/er7oZIMAK5Wq435a+Tkla10lLeXbxJwqw7Wvqb3+PWzx1Y82gBy0qZaJi6CU+fg/WMtHm2AC0XpbxQ5vP7hzIPxFnrSqZ6+/PvYf/fQQZS9AN/M8hzAdwAc4L8GuLh4v81P2tZphAMmDCgAAAAASUVORK5CYII=" alt="pdf">
                                        </span>

                                        <span v-else>
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAtElEQVR4nO3WQQrCQBBE0TqFiPc/kiiepiQwCxFcRLpqkun60GQZHukhA6RzReE8AFxXgBDAC8DNCanOjqEY8vx4StdMDbkAuDu+DMUQuDA0QCwYmiByDI2QLRnGDZFhZkAkmFmQcozjz77nbrYEhBUvnB0DGQVSHAPpBmHx/CqQdqvlioF0g1B0uL8LpN1quWIg3SBc5YrCVSCuGMgokOIYyCiQ4hjIKJCjQniQ+btlIAnm3gBxwrjC8DB8AAAAAElFTkSuQmCC" alt="document--v1">                                </span>
                                    </span>

                                    <div>
                                        <div class="text-l font-bold">{{ dok.name }}</div>
                                        <div class="text-sm text-gray-600">Version: {{ dok.version }}</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ================= LuV ================= -->
                <div v-else-if="activeTab === 'LuV'">
                    <!-- Projekt hinzufügen -->
                    <button @click="showModalLuvCreate = true" class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full" >
                        <span v-if="!loadingProjekt">➕ Luv erstellen</span>
                        <span v-else>...</span>
                    </button>
                    <div class="border p-6 bg-gray-50 rounded-lg">
                        <template v-for="(projekt, i) in teilnehmer.projekte" :key="i">
                        <div v-for="(luv, z) in (projekt.pivot_model?.luv || []).slice().reverse()" :key="luv.id" class="mb-4">
                            <!-- Kopfzeile / Klickbereich -->
                            <div
                            class="flex justify-between items-center bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md cursor-pointer transition"
                            @click="toggleLuv(luv.id)"
                            >
                            <div>
                                <h3 class="font-semibold text-zbb">📄 {{ luv.typ }} </h3>

                                <h3 class="font-semibold text-zbb">Bericht vom {{ formatDate(luv.von) }} bis {{ formatDate(luv.bis) }}</h3>
                                <p class="text-xs text-gray-500">Erstellt am: {{ formatDateTime(luv.updated_at) }}</p>
                            </div>

                            <div class="flex items-center space-x-3">
                                <button
                                @click.stop="showModalLuvBearbeiten = true"
                                class="w-9 h-9 flex items-center justify-center bg-blue-50 text-blue-600 rounded-full hover:bg-blue-100 transition"
                                title="Bearbeiten"
                                >
                                ✏️
                                </button>
                                <button
                                    @click="confirmDelete(luv, 'projekthasteilnehmer.luv')"
                                class="w-9 h-9 flex items-center justify-center bg-red-50 text-red-600 rounded-full hover:bg-red-100 transition"
                                title="Löschen"
                                >
                                🗑️
                                </button>

                                <a  target="_blank" :href="route('projekthasteilnehmer.luv.export', [luv.id])" class="flex justify-center w-9 h-9 rounded-full bg-green-100 cursor-pointer py-1 items-center hover:bg-green-150" >💾</a>

                                <span
                                class="text-gray-500 text-lg transition-transform duration-300"
                                :class="{ 'rotate-180': expandedLuv === luv.id }"
                                >
                                ⬇️
                                </span>
                            </div>
                            </div>

                            <!-- Inhalt -->
                            <transition name="fade">
                            <div
                                v-if="expandedLuv === luv.id"
                                class="mt-3 bg-white rounded-lg border border-gray-200 shadow-inner p-4 space-y-4"
                            >
                                <div class="border p-4 rounded bg-blue-50">
                                <h4 class="text-zbb font-semibold mb-1">🎯 Darstellung der individuellen Ausgangssituation</h4>
                                <p class="text-gray-700 leading-relaxed text-sm leading-relaxed text-sm whitespace-pre-line">{{ luv.ausgangssituation || 'Keine Angaben' }}</p>
                                </div>

                                <div class="border p-4 rounded bg-yellow-50">
                                <h4 class="text-zbb font-semibold mb-1">🧭 Schritte zur Zielvereinbarung</h4>
                                <p class="text-gray-700 leading-relaxed text-sm leading-relaxed whitespace-pre-line">{{ luv.zielvereinbarung || 'Keine Angaben' }}</p>
                                </div>

                                <div class="border p-4 rounded bg-red-50">
                                <h4 class="text-zbb font-semibold mb-1">🎓 Im Berichtszeitraum erworbene Qualifikationen</h4>
                                <p class="text-gray-700 leading-relaxed text-sm">{{ luv.qualifikationen || 'Keine Angaben' }}</p>
                                </div>
                            </div>
                            </transition>
                        </div>
                        </template>
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
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 relative">
            <!-- Schließen-Button -->
            <button
                @click="showModalProjektzuweisen = false"
                class="absolute top-2 right-3 text-gray-400 hover:text-gray-600 text-xl"
            >
                ✕
            </button>

            <!-- Titel -->
            <h3 class="text-lg font-semibold mb-4 text-zbb">Projekt zuweisen</h3>

            <!-- GRID: Zwei Spalten -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Projekt -->
                <div class="col-span-2">
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

                <div class="col-span-2">
                <FloatLabel variant="on">
                    <Select
                    v-model="neuesProjekt.standort_id"
                    :options="props.standorte"
                    optionValue="id"
                    optionLabel="name"
                    class="w-full"
                    />
                    <label>Standort</label>
                </FloatLabel>
                </div>

                <!-- Betreuer -->
                <div>
                <FloatLabel variant="on">
                    <Select
                    v-model="neuesProjekt.betreuer"
                    :options="props.betreuer"
                    optionValue="id"
                    :optionLabel="t => `${t.vorname} ${t.nachname}`"
                    class="w-full"
                    />
                    <label>Betreuer</label>
                </FloatLabel>
                </div>

                <!-- Ansprechpartner -->
                <div>
                <FloatLabel variant="on">
                    <Select
                    v-model="neuesProjekt.massnahmebegleiter"
                    :options="props.arbeitsvermittler"
                    optionValue="id"
                    :optionLabel="t => `${t.vorname} ${t.nachname}`"
                    class="w-full"
                    />
                    <label>Ansprechpartner</label>
                </FloatLabel>
                </div>

                <!-- Antragsdatum -->
                <div>
                <label>Antragsdatum</label>
                <input type="date" v-model="neuesProjekt.antragsdatum" class="input" />
                </div>

                <!-- Starttermin -->
                <div>
                <label>Starttermin</label>
                <input type="date" v-model="neuesProjekt.starttermin" class="input" />
                </div>

                <!-- Endtermin -->
                <div>
                <label>Endtermin</label>
                <input type="date" v-model="neuesProjekt.endtermin" class="input" />
                </div>

                <!-- Anfangsdatum -->
                <div>
                <label>Anfangsdatum</label>
                <input type="date" v-model="neuesProjekt.anfangsdatum" class="input" />
                </div>

                <!-- Enddatum -->
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

    <!-- MODAL: PROJEKT BEARBEITEN -->
    <!-- 🔥 Modal: Projekt bearbeiten -->

    <Modal v-if="showEditZuwseisungModal"   @close="showEditZuwseisungModal = false">
        <template #header  >{{ $t('Projekt bearbeiten') }}</template>

        <template #body>
            <div class="grid grid-cols-2 gap-4">

                <div class="mb-4 w-full mx-1">

                    <FloatLabel variant="on">
                        <Select v-model="editForm.projektname" disabled :options="props.projekte" optionValue="id" optionLabel="name" class="w-full"/>

                        <label>Projektname</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="editForm.status" :options="participationStatuses" optionValue="value" optionLabel="label" class="w-full"/>
                        <label>Teilnahmestatus</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="editForm.standort_id" :options="props.standorte" optionValue="id" optionLabel="name" class="w-full"/>

                        <label>Standort</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <DatePicker v-model="editForm.antragsdatum" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" :manualInput="true" showIcon iconDisplay="input" />
                        <label>Antragsdatum</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="editForm.betreuer" :options="props.betreuer" optionValue="id" :optionLabel="(t) => `${t.vorname} ${t.nachname}`" class="w-full"/>

                        <label>Betreuer</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="editForm.massnahmebegleiter" :options="props.arbeitsvermittler" optionValue="id" :optionLabel="(t) => `${t.vorname} ${t.nachname}`" class="w-full"/>

                        <label>Ansprechpartner</label>
                    </FloatLabel>
                </div>
            </div>


            <div class="grid grid-cols-2 gap-4">

                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <DatePicker v-model="editForm.starttermin" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" :manualInput="true" showIcon iconDisplay="input" />
                        <label>Starttermin</label>
                    </FloatLabel>
                </div>

                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <DatePicker v-model="editForm.anfangsdatum" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" :manualInput="true"  showIcon iconDisplay="input" />
                        <label>Anfangsdatum</label>
                    </FloatLabel>
                </div>

                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <DatePicker v-model="editForm.endtermin" dateFormat="dd.mm.yy"  class="w-full" inputClass="w-full" :manualInput="true"  showIcon iconDisplay="input" />
                        <label>Endtermin</label>
                    </FloatLabel>
                </div>

                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <DatePicker v-model="editForm.enddatum" dateFormat="dd.mm.yy"  class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
                        <label>Enddatum</label>
                    </FloatLabel>
                </div>
            </div>
        </template>

        <template #footer>
            <button @click="saveEdit"
                    class="bg-zbb text-white px-4 py-2 rounded">
                Speichern
            </button>

            <button @click="showEditZuwseisungModal = false"
                    class="border px-4 py-2 rounded">
                Abbrechen
            </button>
        </template>
    </Modal>

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
                            <input v-model="neueAnwesenheit.startTime" type="time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                        </div>
                        <div class="w-1/2">
                            <label for="endTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                geplante Endzeit <span class="text-red-500">*</span>
                            </label>
                            <input v-model="neueAnwesenheit.endTime" type="time"  required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                        </div>
                    </div>

                    <!-- Zeitraum tatsächlich-->
                    <div class="flex space-x-4">
                        <div class="w-1/2">
                            <label for="startTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                tatsächliche Startzeit
                            </label>
                            <input v-model="neueAnwesenheit.tatstartTime" type="time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                        </div>
                        <div class="w-1/2">
                            <label for="endTime" class="block text-sm font-medium text-gray-700 mb-2" >
                                tatsächliche Endzeit
                            </label>
                            <input v-model="neueAnwesenheit.tatendTime" type="time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
                        </div>
                    </div>

                    <!-- Bereiche -->
                    <div>
                        <label for="startDate" class="block text-sm font-medium text-gray-700 mb-2" >
                                Beriech <span class="text-red-500">*</span>
                        </label>
                        <Select
                            v-model="neueAnwesenheit.gruppe"
                            :options="props.bereiche"
                            optionLabel="name"
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

                        <!-- DROPDOWN OPTION -->
                        <template #option="slotProps">
                            <div class="flex items-center space-x-2">
                                <span
                                    class="w-4 h-4 rounded-full"
                                    :style="{ backgroundColor: slotProps.option.farben }"
                                ></span>

                                <span>{{ slotProps.option.status }}</span>
                            </div>
                        </template>

                        <!-- AUSGEWÄHLTER WERT -->
                        <template #value="slotProps">
                            <div class="flex items-center space-x-2">
                                <span
                                    class="w-3 h-3 rounded-full"
                                    :style="{
                                        backgroundColor: props.anwesenheitsstatuten.find(s => s.id === slotProps.value)?.farben
                                            || '#cccccc'
                                    }"
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

    <!-- ================= MODAL Luv ================= -->
    <ModalLuvCreate
        :visible="showModalLuvCreate"
        :teilnehmer="props.teilnehmer"
        @close="showModalLuvCreate = false"
    />


     <!-- ================= MODAL Praktikum ================= -->
    <ModalPraktikumCreate
        :visible="showModalPraktikumCreate"
        :teilnehmer="teilnehmer"

        @close="showModalPraktikumCreate = false"
        @added="praktikumAdded"
    />



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
    import { toLocalDateString } from '@/utils/dateFormat';
    import { formatDate } from '@/utils/dateFormat';
    import { formatTime } from '@/utils/timeFormat';
    import {formatDateTime} from '@/utils/dateFormat';
    import Select from 'primevue/select';
    import Swal from 'sweetalert2'
    import axios from 'axios';
    import Toggle from '@/Components/Toggle.vue';
    import Alert from '@/Components/Utils/SweetalertSuccessError.vue'
    import Stammdaten from '@/Pages/Teilnehmer/Tabs/StammdatenSection.vue';
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import DatePicker from 'primevue/datepicker';
    import Modal from '@/Components/ModalForm.vue';
    import ModalLuvCreate from '@/Pages/Teilnehmer/Tabs/LuV/LuVModalCreate.vue';
    import ModalPraktikumCreate from '@/Pages/Teilnehmer/Tabs/Praktikum/PraktikumModalCreate.vue';
    import { timeToMinutes, istAnwesend, berechneAbweichungMinuten, formatMinutes, abweichungsIcon, abweichungsClass} from "@/utils/arbeitszeit.js";
    import { usePermissions } from '@/utils/permissions';

    const { flash } = usePage().props;
    const { can, canAny } = usePermissions();

    const props = defineProps({
        teilnehmer: Object,
        gruppen: Array,
        kontakttypen: Array,
        projekte: Array,
        standorte: Array,
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
        dokumente: Array,
        zeitraum: Object,
        bereiche: Array,
        arbeitsvermittler: Array,
        activeParticipationId: Number,
        intakeChecklist: { type: Array, default: () => [] },
        participationTasks: { type: Array, default: () => [] },
        completionChecklist: { type: Array, default: () => [] },
        completionReports: { type: Array, default: () => [] },
        portalAccess: { type: Object, default: () => ({ account: null, latest_invitation: null }) },
        participationApplications: { type: Array, default: () => [] },
        attendanceCorrections: { type: Array, default: () => [] },
        portalDocuments: { type: Array, default: () => [] },
        portalMessages: { type: Array, default: () => [] },
        participantConsents: { type: Object, default: () => ({ definitions: [], events: [] }) },
        participantDataRequests: { type: Array, default: () => [] },
        jobRecommendations: { type: Array, default: () => [] },
        participantCv: { type: Object, default: () => ({ visible: false, profile: null, entries: [], versions: [] }) },
    });


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
    const featurePage = usePage();
    const projectFeatures = computed(() => featurePage.props.currentProjekt?.features || {});
    const portalFeatures = computed(() => featurePage.props.currentProjekt?.portal_features || {});
    const portalEnabled = computed(() => Boolean(featurePage.props.enabledModules?.participant_portal));
    const projectFeatureEnabled = (key) => projectFeatures.value[key] !== false;
    const portalFeatureEnabled = (key) => portalEnabled.value && portalFeatures.value[key] === true;
    const tabs = computed(() => [
        "Stammdaten",
        "Sozialdaten",
        "Adresse",
        "Kontaktdaten",
        "Projektverlauf",
        "Aufnahme",
        ...(portalFeatureEnabled('tasks_and_appointments') ? ["Aufgaben"] : []),
        ...(projectFeatureEnabled('completion_management') ? ["Teilnahmeabschluss"] : []),
        ...(portalFeatureEnabled('job_search') || portalFeatureEnabled('application_management') ? ["Bewerbungen"] : []),
        ...(portalFeatureEnabled('messaging') ? ["Nachrichten"] : []),
        ...(portalFeatureEnabled('consents_and_approvals') ? ["Einwilligungen"] : []),
        ...(portalFeatureEnabled('profile') ? ["Datenauskunft", "Lebenslauf", "Portal-Dokumente"] : []),
        ...(projectFeatureEnabled('attendance_management') && canAny([
            'anwesenheit.index',
            'anwesenheit.manage',
            'anwesenheit.destroy',
            'anwesenheit.export',
        ]) ? ["Anwesenheit"] : []),
        "Bank",
        ...(projectFeatureEnabled('completion_management') ? ["Schule/Beruf"] : []),
        "Briefe",
        "Notizen",
        "Kinder",
        "Netzwerke",
        "Vermittlung",
        ...(projectFeatureEnabled('internship_management') ? ["Praktika"] : []),
        "Fahrtkosten",
        ...(projectFeatureEnabled('potential_analysis') ? ["LuV"] : []),
        "Exportieren"
    ]);

    // Lokale Kopie der Teilnehmerdaten
    const teilnehmer = ref(JSON.parse(JSON.stringify(props.teilnehmer)));
    teilnehmer.value.praktika = (teilnehmer.value.praktika || []).map((item) => ({ ...item, start: item.start?.slice(0, 10) || '', end: item.end?.slice(0, 10) || '', next_follow_up_at: item.next_follow_up_at?.slice(0, 10) || '', status_note: '', editing: false }));
    const intakeChecklistItems = ref(JSON.parse(JSON.stringify(props.intakeChecklist || [])));
    const intakeSavingItemId = ref(null);
    const participationTaskItems = ref(JSON.parse(JSON.stringify(props.participationTasks || [])));
    const completionChecklistItems = ref((props.completionChecklist || []).map((item) => ({ ...item, local_note: item.completions?.[0]?.note || '' })));
    const completionReportItems = ref(JSON.parse(JSON.stringify(props.completionReports || [])));
    const completionReportSaving = ref(false);
    const completionReportForm = ref({ completion_type: 'completed', exit_date: '', outcome: '', summary: '', recommendations: '' });
    const applicationStatuses = ['draft', 'preparing', 'sent', 'response', 'interview', 'accepted', 'rejected', 'withdrawn'];
    const applicationStatusLabels = { draft: 'Entwurf', preparing: 'Vorbereitung', sent: 'Versendet', response: 'Rückmeldung', interview: 'Vorstellungsgespräch', accepted: 'Zusage', rejected: 'Absage', withdrawn: 'Zurückgezogen' };
    const participationApplicationItems = ref((props.participationApplications || []).map((item) => ({
        ...item,
        applied_at: item.applied_at?.slice(0, 10) || '',
        next_action_at: item.next_action_at?.slice(0, 10) || '',
        selected_document_ids: (item.documents || []).map((document) => document.id),
    })));
    const jobRecommendationItems = ref(JSON.parse(JSON.stringify(props.jobRecommendations || [])));
    const recommendationForm = ref({ external_ref: null, title: '', employer: '', location: '', source_url: '', note: '' });
    const recommendationSaving = ref(false);
    const createRecommendation = async () => { if (!props.activeParticipationId || !recommendationForm.value.title.trim()) return; recommendationSaving.value = true; try { const response = await axios.post(route('teilnehmer.recommendations.store', props.activeParticipationId), { ...recommendationForm.value, employer: recommendationForm.value.employer || null, location: recommendationForm.value.location || null, source_url: recommendationForm.value.source_url || null, note: recommendationForm.value.note || null }); jobRecommendationItems.value.unshift(response.data.recommendation); recommendationForm.value = { external_ref: null, title: '', employer: '', location: '', source_url: '', note: '' }; } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Empfehlung konnte nicht gespeichert werden.', 'error'); } finally { recommendationSaving.value = false; } };
    const attendanceCorrectionItems = ref(JSON.parse(JSON.stringify(props.attendanceCorrections || [])));
    const portalDocumentItems = ref(JSON.parse(JSON.stringify(props.portalDocuments || [])));
    const staffApplicationDocuments = computed(() => portalDocumentItems.value.filter((document) => document.status === 'approved'));
    const saveStaffApplicationPackage = async (application) => { try { const response = await axios.put(route('teilnehmer.applications.documents.sync', application.id), { document_ids: application.selected_document_ids || [] }); Object.assign(application, response.data.application, { selected_document_ids: (response.data.application.documents || []).map((document) => document.id) }); } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Bewerbungspaket konnte nicht gespeichert werden.', 'error'); } };
    const approveStaffApplicationPackage = async (application) => { try { const response = await axios.post(route('teilnehmer.applications.package.approve', application.id)); Object.assign(application, response.data.application, { selected_document_ids: (response.data.application.documents || []).map((document) => document.id) }); } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Bewerbungspaket konnte nicht freigegeben werden.', 'error'); } };
    const portalMessageItems = ref(JSON.parse(JSON.stringify(props.portalMessages || [])));
    const consentDefinitionItems = ref(JSON.parse(JSON.stringify(props.participantConsents?.definitions || [])));
    const consentEventItems = ref(JSON.parse(JSON.stringify(props.participantConsents?.events || [])));
    const consentHistory = (definition) => consentEventItems.value.filter((event) => event.definition_key === definition.key).sort((a, b) => new Date(b.occurred_at) - new Date(a.occurred_at));
    const latestConsentEvent = (definition) => consentHistory(definition)[0];
    const participantDataRequestItems = ref(JSON.parse(JSON.stringify(props.participantDataRequests || [])));
    const dataRequestLabels = { access_export: 'Datenauskunft / Export', correction: 'Berichtigung', deletion: 'Löschanfrage' };
    const dataRequestStatuses = { submitted: 'Eingereicht', approved: 'Freigegeben', rejected: 'Abgelehnt', completed: 'Abgeschlossen' };
    const resolveDataRequest = async (item, status) => { try { const response = await axios.put(route('teilnehmer.data-requests.resolve', item.id), { status, resolution_note: item.resolution_note || '', identity_verification_method: item.identity_verification_method || '' }); Object.assign(item, response.data.request); } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Anfrage konnte nicht bearbeitet werden.', 'error'); } };
    const staffMessageBody = ref('');
    const staffMessageSending = ref(false);
    const staffMessageSender = (message) => `${message.sender?.person?.vorname || ''} ${message.sender?.person?.nachname || ''}`.trim() || message.sender?.username || 'Projektteam';
    const sendStaffMessage = async () => {
        if (!staffMessageBody.value.trim() || !props.activeParticipationId) return;
        staffMessageSending.value = true;
        try { const response = await axios.post(route('teilnehmer.messages.store', props.activeParticipationId), { body: staffMessageBody.value }); portalMessageItems.value.push(response.data.item); staffMessageBody.value = ''; }
        catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Nachricht konnte nicht gesendet werden.', 'error'); }
        finally { staffMessageSending.value = false; }
    };
    const staffDocumentFile = ref(null);const staffDocumentCategory=ref('other');const staffDocumentVisible=ref(true);
    const uploadStaffDocument=async()=>{if(!staffDocumentFile.value||!props.activeParticipationId)return;const data=new FormData();data.append('file',staffDocumentFile.value);data.append('category',staffDocumentCategory.value);data.append('visible_to_participant',staffDocumentVisible.value?'1':'0');try{const r=await axios.post(route('teilnehmer.portal-documents.store',props.activeParticipationId),data,{headers:{'Content-Type':'multipart/form-data'}});portalDocumentItems.value.unshift(r.data.document);staffDocumentFile.value=null;}catch(error){Swal.fire('Fehler',error.response?.data?.message||'Dokument konnte nicht gespeichert werden.','error');}};
    const reviewPortalDocument=async(doc,status)=>{try{const r=await axios.put(route('teilnehmer.portal-documents.review',doc.id),{status,review_note:doc.review_note||null,visible_to_participant:Boolean(doc.visible_to_participant)});Object.assign(doc,r.data.document);}catch(error){Swal.fire('Fehler',error.response?.data?.message||'Dokument konnte nicht geprüft werden.','error');}};
    const resolveAttendanceCorrection = async (correction, status) => {
        try { const response=await axios.put(route('teilnehmer.attendance.corrections.resolve',correction.id),{status,resolution_note:correction.resolution_note||null});Object.assign(correction,response.data.correction); }
        catch(error){Swal.fire('Fehler',error.response?.data?.message||'Die Anfrage konnte nicht bearbeitet werden.','error');}
    };
    const taskSaving = ref(false);
    const taskForm = ref({ title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_at: '', visible_to_participant: false });
    const openParticipationTasks = computed(() => participationTaskItems.value.filter((task) => task.status !== 'done').length);
    const isTaskOverdue = (task) => task.status !== 'done' && task.due_at && task.due_at.slice(0, 10) < toLocalDateString(new Date());
    const overdueParticipationTasks = computed(() => participationTaskItems.value.filter(isTaskOverdue).length);

    const taskPayload = (task) => ({
        title: task.title,
        description: task.description || null,
        assignee_person_id: task.assignee_person_id || task.assignee?.id || null,
        status: task.status || 'open',
        priority: task.priority || 'normal',
        due_at: task.due_at ? task.due_at.slice(0, 10) : null,
        visible_to_participant: Boolean(task.visible_to_participant),
    });

    const createParticipationTask = async () => {
        taskSaving.value = true;
        try {
            const response = await axios.post(route('teilnehmer.tasks.store', props.activeParticipationId), taskPayload(taskForm.value));
            participationTaskItems.value.unshift(response.data.task);
            taskForm.value = { title: '', description: '', assignee_person_id: '', status: 'open', priority: 'normal', due_at: '', visible_to_participant: false };
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Aufgabe konnte nicht angelegt werden.', 'error');
        } finally {
            taskSaving.value = false;
        }
    };

    const saveParticipationTask = async (task) => {
        try {
            const response = await axios.put(route('teilnehmer.tasks.update', task.id), taskPayload(task));
            Object.assign(task, response.data.task);
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Aufgabe konnte nicht gespeichert werden.', 'error');
            router.reload({ only: ['participationTasks'] });
        }
    };

    const deleteParticipationTask = async (task) => {
        const result = await Swal.fire({ title: 'Aufgabe löschen?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Löschen', cancelButtonText: 'Abbrechen' });
        if (!result.isConfirmed) return;
        try {
            await axios.delete(route('teilnehmer.tasks.destroy', task.id));
            participationTaskItems.value = participationTaskItems.value.filter((item) => item.id !== task.id);
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Aufgabe konnte nicht gelöscht werden.', 'error');
        }
    };

    const saveStaffApplication = async (application) => {
        try {
            const response = await axios.put(route('teilnehmer.applications.update', application.id), {
                status: application.status,
                applied_at: application.applied_at || null,
                next_action_at: application.next_action_at || null,
                notes: application.notes || null,
            });
            Object.assign(application, response.data.application, {
                applied_at: response.data.application.applied_at?.slice(0, 10) || '',
                next_action_at: response.data.application.next_action_at?.slice(0, 10) || '',
            });
            Swal.fire('Gespeichert', response.data.message, 'success');
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Bewerbung konnte nicht gespeichert werden.', 'error');
        }
    };
    const intakeProgress = computed(() => {
        const total = intakeChecklistItems.value.length;
        const completed = intakeChecklistItems.value.filter((item) => item.completions?.[0]?.completed).length;
        return { total, completed, percent: total ? Math.round((completed / total) * 100) : 0 };
    });

    const updateIntakeCompletion = async (item, completed) => {
        if (!props.activeParticipationId) return;
        const previous = JSON.parse(JSON.stringify(item.completions || []));
        item.completions = [{ ...(item.completions?.[0] || {}), completed }];
        intakeSavingItemId.value = item.id;
        try {
            const response = await axios.put(route('teilnehmer.intake-checklist.update', {
                participation: props.activeParticipationId,
                item: item.id,
            }), { completed });
            item.completions = [response.data.completion];
        } catch (error) {
            item.completions = previous;
            Swal.fire('Fehler', error.response?.data?.message || 'Der Checklistenpunkt konnte nicht gespeichert werden.', 'error');
        } finally {
            intakeSavingItemId.value = null;
        }
    };

    const updateCompletionCheck = async (item, completed) => {
        const previous = JSON.parse(JSON.stringify(item.completions || []));
        item.completions = [{ ...(item.completions?.[0] || {}), completed, note: item.local_note || null }];
        try {
            const response = await axios.put(route('teilnehmer.completion-checklist.update', { participation: props.activeParticipationId, item: item.id }), { completed, note: item.local_note || null });
            item.completions = [response.data.completion];
            item.local_note = response.data.completion.note || '';
        } catch (error) {
            item.completions = previous;
            Swal.fire('Fehler', error.response?.data?.message || 'Der Abschlussprüfpunkt konnte nicht gespeichert werden.', 'error');
        }
    };

    const submitCompletionReport = async () => {
        completionReportSaving.value = true;
        try {
            const response = await axios.post(route('teilnehmer.completion-reports.submit', props.activeParticipationId), completionReportForm.value);
            completionReportItems.value.unshift(response.data.report);
            completionReportForm.value = { completion_type: 'completed', exit_date: '', outcome: '', summary: '', recommendations: '' };
            Swal.fire('Eingereicht', response.data.message, 'success');
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Der Abschlussbericht konnte nicht eingereicht werden.', 'error');
        } finally { completionReportSaving.value = false; }
    };

    const decideCompletionReport = async (report, decision) => {
        const prompt = await Swal.fire({ title: decision === 'approved' ? 'Abschluss freigeben?' : 'Bericht ablehnen?', input: 'textarea', inputLabel: decision === 'approved' ? 'Optionaler Freigabevermerk' : 'Begründung', inputValidator: (value) => decision === 'rejected' && !value?.trim() ? 'Bitte eine Begründung angeben.' : undefined, showCancelButton: true, confirmButtonText: decision === 'approved' ? 'Freigeben' : 'Ablehnen', cancelButtonText: 'Abbrechen' });
        if (!prompt.isConfirmed) return;
        try {
            const response = await axios.put(route('teilnehmer.completion-reports.decide', report.id), { decision, decision_note: prompt.value || null });
            Object.assign(report, response.data.report);
            Swal.fire('Gespeichert', response.data.message, 'success');
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Entscheidung konnte nicht gespeichert werden.', 'error');
        }
    };

    const createPortalInvitation = async () => {
        const result = await Swal.fire({
            title: 'Portalzugang einladen',
            input: 'email',
            inputLabel: 'E-Mail-Adresse des Teilnehmers',
            inputValue: props.portalAccess?.latest_invitation?.email || '',
            showCancelButton: true,
            confirmButtonText: 'Einladung erstellen',
            cancelButtonText: 'Abbrechen',
            inputValidator: (value) => !value ? 'Bitte eine E-Mail-Adresse angeben.' : undefined,
        });
        if (!result.isConfirmed) return;
        try {
            const response = await axios.post(route('teilnehmer.portal.invite', props.activeParticipationId), { email: result.value });
            await Swal.fire({
                icon: 'success',
                title: 'Einladung erstellt',
                html: `<p class="mb-3">Der Link ist sieben Tage gültig.</p><input id="portal-invitation-url" class="swal2-input" value="${response.data.invitation_url}" readonly>`,
                confirmButtonText: 'Link kopieren',
                preConfirm: () => navigator.clipboard?.writeText(response.data.invitation_url),
            });
        } catch (error) {
            Swal.fire('Fehler', error.response?.data?.message || 'Die Einladung konnte nicht erstellt werden.', 'error');
        }
    };
    const neuesProjektId = ref('');
    const loadingProjekt = ref(false);
    const neuesBriefFreigeben = ref([]);


    const neuesProjekt = ref({
        antragsdatum: '',
        starttermin: '',
        endtermin: '',
        anfangsdatum: '',
        enddatum: '',
        standort_id: '',
        betreuer: "",
        massnahmebegleiter: "",
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
watch(tabs, (visibleTabs) => {
    if (activeTab.value && !visibleTabs.includes(activeTab.value)) activeTab.value = '';
});
watch(activeTab, async (tab) => {
  if (tab !== 'Nachrichten' || !props.activeParticipationId) return;
  try {
    await axios.put(route('teilnehmer.messages.read', props.activeParticipationId));
    portalMessageItems.value.filter((message) => message.sender_kind === 'participant').forEach((message) => { message.staff_read_at ||= new Date().toISOString(); });
  } catch (_) {
    // Der Verlauf bleibt lesbar; ein erneuter Aufruf setzt den Lesestatus später.
  }
});

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

const getProjektStandortName = (projekt) => {
  if (projekt?.pivot_model?.standort?.name) {
    return projekt.pivot_model.standort.name;
  }

  const standortId = projekt?.pivot_model?.standort_id;
  const standort = props.standorte?.find(
    (item) => Number(item.id) === Number(standortId)
  );

  return standort?.name || "Kein Standort";
};

// =======  Sozialdaten  =======
// script setup (Ausschnitt)
const drittstaatsangehoerig  = ref(!!props.teilnehmer.sozialedaten?.drittstaatsangehoerig);
const behinderung            = ref(!!props.teilnehmer.sozialedaten?.behinderung);
const gefluechtet            = ref(!!props.teilnehmer.sozialedaten?.gefluechtet);
const migrationshintergrund  = ref(!!props.teilnehmer.sozialedaten?.migrationshintergrund);
const leistungsbezug_id      = ref(props.teilnehmer.sozialedaten?.leistungsbezug_id);
const wohnsitz_stabil        = ref(!!props.teilnehmer.sozialedaten?.wohnsitz_stabil);
const kundennummer = ref(props.teilnehmer.sozialedaten?.kundennummer || '');
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
      kundennummer: kundennummer.value,
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


    /* ======= KONTAKTE ======= */
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
    /* ======= End KONTAKTE =======*/

    // =======  ANWESENHEIT =======
        const selectedMonth = ref("");
        const minutenZwischen = (start, ende) => {
            const startMinuten = timeToMinutes(start);
            const endMinuten = timeToMinutes(ende);
            return startMinuten === null || endMinuten === null ? 0 : Math.max(0, endMinuten - startMinuten);
        };
        // 🧠 Gruppiere Anwesenheiten (bzw. Gruppen) nach Monat
           const gruppenNachMonat = computed(() => {
                if (!teilnehmer.value?.anwesenheiten) return {};

                const gruppiert = {};

                teilnehmer.value.anwesenheiten.forEach((a) => {
                    const datum = new Date(a.tag.datum);
                    const monat = datum.toLocaleString("de-DE", { month: "long", year: "numeric" });

                    if (!gruppiert[monat]) gruppiert[monat] = [];
                    gruppiert[monat].push(a);
                });

                for (const key in gruppiert) {
                    gruppiert[key].sort((a, b) => new Date(b.tag.datum) - new Date(a.tag.datum));
                }

                return gruppiert;
            });


        // 🧮 Liste aller verfügbaren Monate für Filter
        const verfuegbareMonate = computed(() => Object.keys(gruppenNachMonat.value))

        const gefilterteAnwesenheiten = computed(() => {
            const alle = teilnehmer.value?.anwesenheiten || [];
            if (!selectedMonth.value) return alle;

            return alle.filter((anwesenheit) => {
                const datum = new Date(anwesenheit.tag.datum);
                return datum.toLocaleString("de-DE", { month: "long", year: "numeric" }) === selectedMonth.value;
            });
        });

        const anwesenheitsAuswertung = computed(() => {
            const eintraege = gefilterteAnwesenheiten.value;
            let soll = 0;
            let ist = 0;
            const statusSummen = new Map();

            eintraege.forEach((anwesenheit) => {
                soll += minutenZwischen(
                    anwesenheit.zeitgeplant?.startzeit,
                    anwesenheit.zeitgeplant?.endzeit
                );

                if (istAnwesend(anwesenheit)) {
                    ist += minutenZwischen(
                        anwesenheit.zeittatsaechlich?.startzeit,
                        anwesenheit.zeittatsaechlich?.endzeit
                    );
                }

                const status = anwesenheit.status?.status || "Ohne Status";
                const bisher = statusSummen.get(status) || {
                    status,
                    farben: anwesenheit.status?.farben || "#9ca3af",
                    anzahl: 0,
                };
                bisher.anzahl += 1;
                statusSummen.set(status, bisher);
            });

            const anwesend = eintraege.filter(istAnwesend).length;

            return {
                tage: eintraege.length,
                soll,
                ist,
                saldo: ist - soll,
                anwesenheitsquote: eintraege.length ? Math.round((anwesend / eintraege.length) * 100) : 0,
                statusSummen: Array.from(statusSummen.values()),
            };
        });

        const editMode = ref(false);
            const aktuelleAnwesenheit = ref(null);
            const openModalEdit = (anwesenheit) => {
            console.log(anwesenheit)
            editMode.value = true;
            aktuelleAnwesenheit.value = anwesenheit;
            showModalAnwesenheit.value = true;

            neueAnwesenheit.value = {
                dateAnwesenheit: anwesenheit.tag?.datum || '',
                startTime: anwesenheit.zeitgeplant?.startzeit || '',
                endTime: anwesenheit.zeitgeplant?.endzeit || '',
                tatstartTime: anwesenheit.zeittatsaechlich?.startzeit || '',
                tatendTime: anwesenheit.zeittatsaechlich?.endzeit || '',
                gruppe: anwesenheit.bereich?.id || '',


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
            !neueAnwesenheit.value.endTime
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
            startzeit: formatTime(neueAnwesenheit.value.startTime),
            endzeit: formatTime(neueAnwesenheit.value.endTime),
            anwesenheitsstatuten_id: neueAnwesenheit.value.anwesenheitsstatus,
            bemerkung: neueAnwesenheit.value.bemerkungen,
            bereich_id : neueAnwesenheit.value.gruppe,
            tatstartTime: formatTime(neueAnwesenheit.value.tatstartTime),
            tatendTime: formatTime(neueAnwesenheit.value.tatendTime),
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

                // Index im Array finden
                const index = teilnehmer.value.anwesenheiten.findIndex(
                    a => a.id === aktuelleAnwesenheit.value.id
                );

                if (index !== -1) {
                    // Neue aktualisierte Anwesenheit sauber aufbauen
                    const updated = {
                        id: aktuelleAnwesenheit.value.id,
                        gruppe: props.gruppen.find(g => g.id === payload.bereich_id),
                        tag: { datum: payload.tag },
                        zeitgeplant: {
                            startzeit: payload.startzeit,
                            endzeit: payload.endzeit
                        },
                        zeittatsaechlich: payload.tatstartTime && payload.tatendTime
                            ? { startzeit: payload.tatstartTime, endzeit: payload.tatendTime }
                            : null,
                        status: props.anwesenheitsstatuten.find(
                            s => s.id === payload.anwesenheitsstatuten_id
                        ),
                        bemerkung: payload.bemerkung,
                    };

                    // === WICHTIG ===
                    // Element ersetzen -> reaktives Update!
                    teilnehmer.value.anwesenheiten.splice(index, 1, updated);
                }

            } else {
                // CREATE-FALL
                teilnehmer.value.anwesenheiten.unshift({
                    id: Date.now(),
                    gruppe: props.gruppen.find(g => g.id === payload.bereich_id),
                    tag: { datum: payload.tag },
                    zeitgeplant: {
                        startzeit: payload.startzeit,
                        endzeit: payload.endzeit
                    },
                    zeittatsaechlich: payload.tatstartTime && payload.tatendTime
                        ? { startzeit: payload.tatstartTime, endzeit: payload.tatendTime }
                        : null,
                    status: props.anwesenheitsstatuten.find(
                        s => s.id === payload.anwesenheitsstatuten_id
                    ),
                    bemerkung: payload.bemerkung,
                });
            }


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

            onError: (errors) => {
            // ❤️ Hier kommen deine Validation Errors direkt rein!
            Swal.fire({
                icon: "error",
                title: "Validierungsfehler",
                html: Object.values(errors)
                .flat()
                .map(msg => `<p>🔸 ${msg}</p>`)
                .join(""),
            });
            },

            onFinish: () => {
            loadingAnwesenheit.value = false;
            }
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
    // ======= End ANWESENHEIT =======

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
    onSuccess: (page) => {
      const projekt = props.projekte.find(
        (p) => p.id === neuesProjektId.value
      );
      const selectedStandort = props.standorte?.find(
        (standort) => Number(standort.id) === Number(neuesProjekt.value.standort_id)
      );

      if (projekt) {
        // 🔥 Neues Projekt in lokale Liste einfügen
        teilnehmer.value.projekte.unshift({
          ...projekt,
          pivot_model: {
            standort_id: neuesProjekt.value.standort_id || null,
            status: page.props.currentProjekt?.rules?.participation_initial_status || 'aktiv',
            standort: selectedStandort || null,
            zeitraume: [
              {
                antragsdatum: neuesProjekt.value.antragsdatum || null,
                starttermin: neuesProjekt.value.starttermin || null,
                endtermin: neuesProjekt.value.endtermin || null,
                anfangsdatum: neuesProjekt.value.anfangsdatum || null,
                enddatum: neuesProjekt.value.enddatum || null,
              },
            ],
            // 🔹 direkt die meta-Daten hinzufügen (frontend-seitig sichtbar)
            meta: {
              betreuer: props.betreuer.find(
                (b) => b.id === neuesProjekt.value.betreuer
              ) || { vorname: "", nachname: "" },
              projektbegleiter: props.arbeitsvermittler.find(
                (m) => m.id === neuesProjekt.value.massnahmebegleiter
              ) || { vorname: "", nachname: "" },
            },
          },
          esf: false,
          jc_mitarbeiter: "",
        });
      }

      // ✅ Eingaben zurücksetzen
      neuesProjektId.value = "";
      neuesProjekt.value = {
        antragsdatum: "",
        starttermin: "",
        endtermin: "",
        anfangsdatum: "",
        enddatum: "",
        standort_id: "",
        betreuer: "",
        massnahmebegleiter: "",
      };

      showModalProjektzuweisen.value = false; // Modal schließen
    },
  }
);

};

const showEditZuwseisungModal = ref(false);
const participationStatuses = [
    { value: 'angefragt', label: 'Angefragt' },
    { value: 'angemeldet', label: 'Angemeldet (Bestand)' },
    { value: 'aufgenommen', label: 'Aufgenommen' },
    { value: 'aktiv', label: 'Aktiv' },
    { value: 'pausiert', label: 'Pausiert' },
    { value: 'abgeschlossen', label: 'Abgeschlossen' },
    { value: 'abgebrochen', label: 'Abgebrochen' },
];
const participationStatusLabel = (status) => participationStatuses.find((item) => item.value === status)?.label || status || '-';

const editForm = ref({
    id: null,
    projektname: "",
    abteilung_id: "",
    massnahmebegleiter: "",
    kostenstelle: "",
    antragsdatum: "",
    starttermin: "",
    anfangsdatum: "",
    endtermin: "",
    enddatum: "",
    standort_id: "",
    status: "aktiv",
});
const openProjektEdit = (zeit, projekt) =>  {
    showEditZuwseisungModal.value = true;
    editForm.value = {
        id: projekt.pivot_model.id ?? "",
        betreuer: projekt.pivot_model.meta?.betreuer_id ?? "",
        massnahmebegleiter: projekt.pivot_model.meta?.projektbegleiter_id ?? "",
        projektname: projekt.id,
        standort_id: projekt.pivot_model.standort_id ?? "",
        status: projekt.pivot_model.status ?? "aktiv",
        antragsdatum: formatDate(zeit?.antragsdatum),
        starttermin: formatDate(zeit?.starttermin),
        anfangsdatum: formatDate(zeit?.anfangsdatum),
        endtermin: formatDate(zeit?.endtermin),
        enddatum: formatDate(zeit?.enddatum),
    };
}
    const page = usePage();

function saveEdit() {
    const payload = {
        id: editForm.value.id,
        projektbegleiter_id: editForm.value.massnahmebegleiter,
        betreuer_id: editForm.value.betreuer,
        standort_id: editForm.value.standort_id,
        status: editForm.value.status,
        antragsdatum: toLocalDateString(editForm.value.antragsdatum),
        starttermin: toLocalDateString(editForm.value.starttermin),
        endtermin: toLocalDateString(editForm.value.endtermin),
        anfangsdatum: toLocalDateString(editForm.value.anfangsdatum),
        enddatum: toLocalDateString(editForm.value.enddatum),
    };

   axios.put(route("projekthasteilnehmer.update", editForm.value.id), payload)
   //router.put(route("projekthasteilnehmer.update", editForm.value.id), payload)
        .then(response => {

            const newData = response.data.zeitraum;
            const newMeta = response.data.meta;

            const projekt = teilnehmer.value.projekte.find(
                p => Number(p.pivot_model.id) === Number(editForm.value.id)
            );

            if (projekt) {
                projekt.pivot_model.status = response.data.status;
                if (newData) {
                    const zeitraume = projekt.pivot_model.zeitraume || [];
                    const index = zeitraume.findIndex(
                        z => Number(z.id) === Number(newData.id)
                    );

                    if (index !== -1) {
                        zeitraume[index] = newData;
                    } else {
                        zeitraume.push(newData);
                    }

                    projekt.pivot_model.zeitraume = zeitraume;
                }
                 // 🔹 Projektbegleiter im Meta-Objekt aktualisieren
                projekt.pivot_model.meta = newMeta;
                projekt.pivot_model.standort_id = response.data.standort_id;
                projekt.pivot_model.standort = response.data.standort;
            }

            showEditZuwseisungModal.value = false;

            Swal.fire({
                icon: "success",
                title: "Gespeichert!",
                timer: 1500,
                showConfirmButton: false,
            });
        })
        .catch(err => console.error(err));
}





// ======= BANK =======

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

// ======= Exportieren  =======
const exportSuche = ref("");

const gefilterteDokumente = computed(() => {
  if (!props.dokumente) return [];
  const term = exportSuche.value.toLowerCase();
  return props.dokumente.filter(
    (d) =>
      d.name.toLowerCase().includes(term) ||
      d.typ.toLowerCase().includes(term) ||
      (d.version && d.version.toString().includes(term))
  );
});

// ====================== LuV ======================
const expandedLuv = ref(null); // speichert die aktuell geöffnete LUV-ID
const showModalLuvCreate = ref(false);

const toggleLuv = (id) => {
  expandedLuv.value = expandedLuv.value === id ? null : id;
};


// ====================== Praktikum ======================
const showModalPraktikumCreate = ref(false);

const praktikumAdded = (daten) => {
    if (!teilnehmer.value.praktika) {  // ← "value" hinzufügen
        teilnehmer.value.praktika = [];
    }
    teilnehmer.value.praktika.push(daten);  // ← "value" hinzufügen
};

const savePraktikum = async (praktikum) => {
    try {
        const response = await axios.put(route('teilnehmer.praktikum.update', praktikum.id), praktikum);
        Object.assign(praktikum, response.data.data, { editing: false });
        Swal.fire('Gespeichert', response.data.message, 'success');
    } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Der Verlauf konnte nicht gespeichert werden.', 'error'); }
};

const archivePraktikum = async (praktikum) => {
    const result = await Swal.fire({ title: 'Eintrag archivieren?', text: 'Die Historie bleibt erhalten.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Archivieren', cancelButtonText: 'Abbrechen' });
    if (!result.isConfirmed) return;
    try {
        const response = await axios.delete(route('teilnehmer.praktikum.destroy', praktikum.id));
        teilnehmer.value.praktika = teilnehmer.value.praktika.filter((item) => item.id !== praktikum.id);
        Swal.fire('Archiviert', response.data.message, 'success');
    } catch (error) { Swal.fire('Fehler', error.response?.data?.message || 'Der Eintrag konnte nicht archiviert werden.', 'error'); }
};


// ====================== LÖSCHEN ======================
// Lokale Kopien der Brief-Arrays (reaktiv)
const meineBriefe = ref([...props.meineBriefe]);
const erhalteneBriefe = ref([...props.erhalteneBriefe]);

const confirmDelete = (item, type) => {
    console.log(item)
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
if (seite.value === 'anwesenheit') {
   teilnehmer.value.anwesenheiten = teilnehmer.value.anwesenheiten.filter(
        (anwesenheit) => anwesenheit.id !== id
      );
}
if (seite.value === 'projekthasteilnehmer.luv') {

  // Durch alle Projekte des Teilnehmers loopen
  teilnehmer.value.projekte.forEach((projekt) => {
    if (projekt.pivot_model?.luv) {
      projekt.pivot_model.luv = projekt.pivot_model.luv.filter(
        (l) => l.id !== id
      );
    }
  });
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

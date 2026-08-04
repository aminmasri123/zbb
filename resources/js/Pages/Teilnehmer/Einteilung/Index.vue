<template>
  <Head title="Einteilung" />

  <app-layout>
    <template #header>Einteilung</template>

    <main class="space-y-5 p-4 lg:p-6">
      <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-5 p-5 xl:flex-row xl:items-center xl:justify-between">
          <div class="min-w-0">
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
              <span class="rounded-full bg-orange-50 px-2.5 py-1 text-orange-700">BOP-Programm</span>
              <span>Schuljahr {{ schuljahr }}</span><span aria-hidden="true">·</span><span>Teil {{ teil }}</span>
            </div>
            <h1 class="truncate text-2xl font-bold text-gray-900">{{ partner.name }}</h1>
            <p v-if="updatedAt" class="mt-1 text-sm text-gray-500">
              <i class="la la-clock mr-1" aria-hidden="true"></i>Zuletzt geändert: {{ formatDate(updatedAt) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button v-if="canEinteilungStore" type="button" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2" @click="openCreateModal">
              <i class="la la-user-plus mr-1.5 text-base" aria-hidden="true"></i>Teilnehmer hinzufügen
            </button>
            <button v-if="canEinteilungExport" type="button" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2" @click="openExportModal">
              <i class="la la-download mr-1.5 text-base" aria-hidden="true"></i>Exportieren
            </button>
            <button v-if="canEinteilungStore" type="button" :disabled="isBusy" class="inline-flex items-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" @click="submitEinteilen">
              <i class="la la-magic mr-1.5 text-base" aria-hidden="true"></i>{{ isBusy ? 'Bitte warten …' : 'Automatisch einteilen' }}
            </button>
          </div>
        </div>

        <div v-if="canEinteilungPlanning || canEinteilungDestroy" class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div v-if="canEinteilungPlanning" class="flex flex-wrap items-center gap-1">
            <span class="mr-2 text-xs font-bold uppercase tracking-wide text-gray-500">Planung</span>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-white hover:text-orange-700 hover:shadow-sm" @click="openParameterModal"><i class="la la-sliders-h mr-1" aria-hidden="true"></i>Parameter</button>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-white hover:text-orange-700 hover:shadow-sm" @click="openSwitchModal"><i class="la la-exchange-alt mr-1" aria-hidden="true"></i>Runden tauschen</button>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-white hover:text-orange-700 hover:shadow-sm" @click="openGruppenModal"><i class="la la-users mr-1" aria-hidden="true"></i>Gruppen generieren</button>
          </div>
          <button v-if="canEinteilungDestroy" type="button" :disabled="isBusy" class="self-start rounded-md px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 sm:self-auto" @click="submitDestroy">
            <i class="la la-trash mr-1" aria-hidden="true"></i>Einteilung löschen
          </button>
        </div>
      </section>

      <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div v-for="card in statCards" :key="card.label" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ card.label }}</p>
              <p class="mt-1 text-2xl font-bold text-gray-900">{{ card.value }}</p>
              <p class="mt-0.5 text-xs text-gray-500">{{ card.hint }}</p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-lg text-orange-600"><i :class="card.icon" aria-hidden="true"></i></span>
          </div>
        </div>
      </section>

      <div v-if="statusMessage" role="status" class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow-sm" :class="statusType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'">
        <div class="flex items-start gap-2">
          <i :class="statusType === 'error' ? 'la la-exclamation-circle' : 'la la-check-circle'" class="mt-0.5 text-base" aria-hidden="true"></i>
          <span>{{ statusMessage }}</span>
        </div>
        <button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" aria-label="Meldung schließen" @click="statusMessage = ''"><i class="la la-times" aria-hidden="true"></i></button>
      </div>

      <section
        class="border border-gray-200 bg-white shadow-sm"
        :class="isFullscreen ? 'fixed inset-0 z-[9999] flex flex-col overflow-hidden rounded-none border-0 bg-gray-100 p-4' : 'rounded-xl'"
      >
        <div class="flex flex-col gap-4 border-b border-gray-200 bg-white p-4 lg:flex-row lg:items-center lg:justify-between" :class="isFullscreen ? 'z-10 shrink-0 rounded-xl shadow-sm' : ''">
          <div>
            <h2 class="text-base font-bold text-gray-900">{{ isFullscreen ? 'Alle Einteilungen' : 'Einteilung nach Runde' }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">Teilnehmer anklicken, um ihre Zuordnung zu bearbeiten.</p>
          </div>
          <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
            <label class="relative block w-full lg:w-80">
              <span class="sr-only">Teilnehmer suchen</span>
              <i class="la la-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
              <input v-model.trim="searchQuery" type="search" placeholder="Name oder Klasse suchen …" class="block w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-orange-400 focus:ring-orange-400" />
            </label>
            <button v-if="!isFullscreen" type="button" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2" aria-label="Alle Einteilungen im Vollbild anzeigen" @click="toggleFullscreen">
              <i class="la la-expand mr-1.5 text-base" aria-hidden="true"></i>Vollbild
            </button>
            <button v-else type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-xl text-gray-600 shadow-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2" aria-label="Vollbild schließen" title="Vollbild schließen (Esc)" @click="toggleFullscreen">
              <i class="la la-times" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div v-if="!isFullscreen" class="flex gap-1 overflow-x-auto border-b border-gray-200 px-4 pt-3" role="tablist" aria-label="Runde auswählen">
          <button v-for="runde in runden" :key="`tab-${runde}`" type="button" role="tab" :aria-selected="selectedRound === runde" class="relative shrink-0 rounded-t-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-orange-400" :class="selectedRound === runde ? 'bg-orange-50 text-orange-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'" @click="selectedRound = runde">
            <span class="flex items-center justify-center">
              Runde {{ runde }}
              <span class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs" :class="selectedRound === runde ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'">{{ assignedCountForRound(runde) }}</span>
            </span>
            <span class="mt-0.5 block text-[10px] font-medium opacity-75">{{ roundDateLabel(runde) }}</span>
            <span v-if="selectedRound === runde" class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-orange-500"></span>
          </button>
        </div>

        <div v-if="isFullscreen && allBereiche.length" class="grid min-h-0 flex-1 gap-px overflow-hidden rounded-xl bg-gray-200 p-px" :style="fullscreenGridStyle">
          <div class="flex items-center justify-center bg-gray-50 px-1 text-[10px] font-bold uppercase tracking-wide text-gray-500">Runde</div>
          <div v-for="bereich in allBereiche" :key="`fullscreen-header-${bereich.id}`" class="flex min-w-0 flex-col items-center justify-center bg-gray-50 px-1.5 py-1.5 text-center">
            <span class="w-full truncate text-[10px] font-bold uppercase leading-tight text-gray-800" :title="bereich.name">{{ bereich.name }}</span>
            <span class="mt-0.5 text-[9px] text-gray-500">Kapazität {{ capacityForBereich(bereich.name) }}</span>
          </div>

          <template v-for="runde in runden" :key="`fullscreen-row-${runde}`">
            <div class="flex flex-col items-center justify-center bg-orange-50 px-1 text-center text-orange-700">
              <span class="text-sm font-bold">{{ runde }}</span>
              <span class="text-[9px] font-medium">{{ assignedCountForRound(runde) }} TN</span>
              <span class="mt-0.5 text-[8px] leading-tight text-orange-600">{{ roundDateLabel(runde, true) }}</span>
            </div>
            <div v-for="bereich in allBereiche" :key="`fullscreen-cell-${runde}-${bereich.id}`" class="flex min-h-0 min-w-0 flex-col bg-white p-1">
              <div class="mb-0.5 flex items-center justify-between gap-1 border-b border-gray-100 pb-0.5 text-[9px]">
                <span class="font-semibold text-gray-500">{{ roundParticipants(bereich.name, runde).length }} / {{ capacityForBereich(bereich.name) }}</span>
                <span :class="capacityState(bereich.name, runde).barClass" class="h-1.5 w-1.5 rounded-full" :title="capacityState(bereich.name, runde).label"></span>
              </div>
              <div class="min-h-0 flex-1 overflow-hidden">
                <button
                  v-for="schueler in filteredParticipants(bereich.name, runde)"
                  :key="`fullscreen-student-${runde}-${bereich.id}-${schueler.id}`"
                  type="button"
                  class="group flex w-full min-w-0 items-center gap-1 rounded px-1 py-px text-left text-[10px] leading-[1.25] transition"
                  :class="canEinteilungUpdate ? 'hover:bg-orange-50 focus:bg-orange-50 focus:outline-none focus:ring-1 focus:ring-orange-300' : 'cursor-default'"
                  :disabled="!canEinteilungUpdate"
                  :title="`${schueler.nachname}, ${schueler.vorname} · Klasse ${schueler.klasse || '–'}`"
                  @click="openEditModal(schueler)"
                >
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="schueler.geschlecht === 'w' ? 'bg-pink-400' : 'bg-emerald-400'"></span>
                  <span class="min-w-0 flex-1 truncate font-medium text-gray-800 group-hover:text-orange-700">{{ schueler.nachname }}, {{ schueler.vorname }}</span>
                  <span class="shrink-0 text-[9px] text-gray-400">{{ schueler.klasse || '–' }}</span>
                </button>
                <div v-if="filteredParticipants(bereich.name, runde).length === 0" class="flex h-full items-center justify-center px-1 text-center text-[9px] text-gray-300">
                  {{ searchQuery ? 'Keine Treffer' : 'Nicht belegt' }}
                </div>
              </div>
            </div>
          </template>
        </div>

        <div
          v-else-if="allBereiche.length"
          :class="isFullscreen ? 'grid min-h-0 flex-1 gap-4 overflow-hidden pt-4' : ''"
          :style="isFullscreen ? { gridTemplateColumns: `repeat(${displayedRounds.length}, minmax(0, 1fr))` } : undefined"
        >
          <div v-for="displayRound in displayedRounds" :key="`board-round-${displayRound}`" class="p-4" :class="isFullscreen ? 'min-h-0 overflow-y-auto rounded-xl bg-white shadow-sm' : ''">
            <div v-if="isFullscreen" class="sticky top-0 z-[1] mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-white pb-3">
              <h3 class="text-lg font-bold text-gray-900">Runde {{ displayRound }}</h3>
              <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700">{{ utilizationTextForRound(displayRound) }}</span>
            </div>
            <div class="grid gap-4" :class="isFullscreen ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4'">
            <article v-for="bereich in allBereiche" :key="`${bereich.id}-${displayRound}`" class="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-gray-50/60" :class="capacityState(bereich.name, displayRound).borderClass">
              <header class="border-b border-gray-200 bg-white p-3.5">
                <h3 class="min-w-0 text-sm font-bold leading-5 text-gray-900">{{ bereich.name }}</h3>
              </header>

              <ul class="min-h-[18rem] flex-1 space-y-1.5 overflow-y-auto p-2.5">
                <li v-for="schueler in filteredParticipants(bereich.name, displayRound)" :key="schueler.id">
                  <button type="button" class="group flex w-full items-center gap-2.5 rounded-lg border border-transparent bg-white px-2.5 py-2 text-left shadow-sm transition" :class="canEinteilungUpdate ? 'hover:border-orange-200 hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-orange-300' : 'cursor-default'" :disabled="!canEinteilungUpdate" @click="openEditModal(schueler)">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="schueler.geschlecht === 'w' ? 'bg-pink-50 text-pink-700' : 'bg-emerald-50 text-emerald-700'">{{ initialsFor(schueler) }}</span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-xs font-semibold text-gray-900 group-hover:text-orange-700">{{ schueler.nachname }}, {{ schueler.vorname }}</span>
                      <span class="mt-0.5 block text-[11px] text-gray-500">Klasse {{ schueler.klasse || '–' }}</span>
                    </span>
                    <i v-if="canEinteilungUpdate" class="la la-pen text-gray-300 group-hover:text-orange-500" aria-hidden="true"></i>
                  </button>
                </li>
                <li v-if="filteredParticipants(bereich.name, displayRound).length === 0" class="flex min-h-[15rem] items-center justify-center px-4 text-center text-xs text-gray-400">{{ searchQuery ? 'Keine passenden Teilnehmer' : 'Noch keine Teilnehmer eingeteilt' }}</li>
              </ul>
              <footer class="border-t border-gray-200 bg-white p-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-xs font-semibold text-gray-500">Belegung</span>
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold" :class="capacityState(bereich.name, displayRound).badgeClass">{{ roundParticipants(bereich.name, displayRound).length }} / {{ capacityForBereich(bereich.name) }}</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-label="`Auslastung ${bereich.name} in Runde ${displayRound}`" :aria-valuenow="capacityState(bereich.name, displayRound).percentage" aria-valuemin="0" aria-valuemax="100">
                  <div class="h-full rounded-full transition-all" :class="capacityState(bereich.name, displayRound).barClass" :style="{ width: `${capacityState(bereich.name, displayRound).percentage}%` }"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">{{ capacityState(bereich.name, displayRound).label }}</p>
              </footer>
            </article>
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-16 text-center">
          <i class="la la-layer-group text-4xl text-gray-300" aria-hidden="true"></i>
          <h3 class="mt-3 text-sm font-bold text-gray-800">Keine Bereiche vorhanden</h3>
          <p class="mt-1 text-sm text-gray-500">Lege zunächst Bereiche im Projekt an.</p>
        </div>

        <div v-if="!isFullscreen" class="flex justify-end border-t border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-500">
          <span><i class="la la-chart-pie mr-1" aria-hidden="true"></i>{{ roundUtilizationText }}</span>
        </div>
      </section>
    </main>

    <div v-if="showModal" :class="isFullscreen ? 'fixed inset-0 z-[10000]' : ''">
    <Modal :show="showModal" @close="showModal = false">
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
    </div>

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

          <div>
            <div class="mb-2">
              <h3 class="text-sm font-bold text-gray-800">Rundentermine</h3>
              <p class="text-xs text-gray-500">Diese Termine gelten für die Einteilung und werden beim Generieren der Gruppen übernommen.</p>
            </div>
            <div class="space-y-3">
              <div v-for="runde in parameterRounds" :key="`parameter-termin-${runde}`" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="mb-2 text-sm font-bold text-orange-700">Runde {{ runde }}</p>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                  <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Von</label>
                    <input v-model="parameterForm.rundentermine[runde].anfangsdatum" required type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Bis</label>
                    <input v-model="parameterForm.rundentermine[runde].enddatum" required type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Startzeit</label>
                    <input v-model="parameterForm.rundentermine[runde].startzeit" required type="time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-600">Endzeit</label>
                    <input v-model="parameterForm.rundentermine[runde].endzeit" required type="time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb sm:text-sm" />
                  </div>
                </div>
              </div>
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
          <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
            <p class="mb-2 text-sm font-bold text-orange-800">Gespeicherte Rundentermine</p>
            <div class="grid gap-2 sm:grid-cols-2">
              <div v-for="r in runden" :key="`gruppen-termin-${r}`" class="rounded-md bg-white px-3 py-2 text-sm text-gray-700 shadow-sm">
                <span class="font-bold">Runde {{ r }}:</span>
                {{ roundDateLabel(r) }} · {{ roundTimeLabel(r) }}
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
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
import { ref, computed, reactive, onMounted, onBeforeUnmount } from 'vue'
import InputText from 'primevue/inputtext';
import axios from 'axios'

const props = defineProps({
  abilities: Object,
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
const canEinteilungStore = computed(() => Boolean(props.abilities?.store))
const canEinteilungUpdate = computed(() => Boolean(props.abilities?.update))
const canEinteilungDestroy = computed(() => Boolean(props.abilities?.destroy))
const canEinteilungExport = computed(() => Boolean(props.abilities?.export))
const canEinteilungPlanning = computed(() => Boolean(props.abilities?.planning))

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
const teilnehmerOptions = ref([...(props.teilnehmerOptions ?? [])])
const raeume = ref([...(props.raeume ?? [])])
const betreuer = ref([...(props.betreuer ?? [])])
const statusMessage = ref('')
const statusType = ref('success')
const isBusy = ref(false)
const maxRoundNumbers = [1, 2, 3, 4, 5]
const runden = ref([...(props.runden?.length ? props.runden : [1, 2, 3])])
const selectedRound = ref(runden.value[0] ?? 1)
const searchQuery = ref('')
const isFullscreen = ref(false)
const displayedRounds = computed(() => isFullscreen.value ? runden.value : [selectedRound.value])
const fullscreenGridStyle = computed(() => ({
  gridTemplateColumns: `5rem repeat(${allBereiche.value.length}, minmax(0, 1fr))`,
  gridTemplateRows: `2.5rem repeat(${runden.value.length}, minmax(0, 1fr))`,
}))
let previousBodyOverflow = ''

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value

  if (isFullscreen.value) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = previousBodyOverflow
  }
}

const handleEscape = (event) => {
  if (event.key !== 'Escape' || !isFullscreen.value) return

  if (showModal.value) {
    showModal.value = false
    return
  }

  toggleFullscreen()
}

onMounted(() => window.addEventListener('keydown', handleEscape))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleEscape)
  if (isFullscreen.value) document.body.style.overflow = previousBodyOverflow
})

const emptyRoundSchedule = () => ({
  anfangsdatum: '',
  enddatum: '',
  startzeit: '08:00',
  endzeit: '15:00',
})

const normalizeParameter = (parameter = {}) => {
  const rundentermine = {}
  maxRoundNumbers.forEach((runde) => {
    rundentermine[runde] = {
      ...emptyRoundSchedule(),
      ...(parameter.rundentermine?.[runde] ?? {}),
    }
  })

  return {
    runden_anzahl: Number(parameter.runden_anzahl ?? 3),
    standard_kapazitaet: Number(parameter.standard_kapazitaet ?? 15),
    kapazitaeten: { ...(parameter.kapazitaeten ?? {}) },
    rundentermine,
  }
}

const parameter = ref(normalizeParameter(props.parameter))

const participantAssignments = computed(() => {
  const assignments = new Map()

  Object.values(results.value ?? {}).forEach((rounds) => {
    runden.value.forEach((runde) => {
      const participants = rounds?.[runde] ?? []
      participants.forEach((schueler) => {
        if (!assignments.has(schueler.id)) assignments.set(schueler.id, new Set())
        assignments.get(schueler.id).add(runde)
      })
    })
  })

  return assignments
})

const fullyAssignedCount = computed(() => {
  return [...participantAssignments.value.values()].filter(rounds => rounds.size === runden.value.length).length
})

const statCards = computed(() => [
  { label: 'Teilnehmer', value: teilnehmerOptions.value.length, hint: 'in dieser Schule', icon: 'la la-user-graduate' },
  { label: 'Vollständig eingeteilt', value: `${fullyAssignedCount.value} / ${teilnehmerOptions.value.length}`, hint: `für alle ${runden.value.length} Runden`, icon: 'la la-check-circle' },
  { label: 'Runden', value: runden.value.length, hint: 'aktuell geplant', icon: 'la la-sync' },
  { label: 'Bereiche', value: allBereiche.value.length, hint: 'verfügbare Angebote', icon: 'la la-layer-group' },
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
  runden.value = [...(payload.runden?.length ? payload.runden : [1, 2, 3])]
  if (!runden.value.includes(selectedRound.value)) {
    selectedRound.value = runden.value[0] ?? 1
  }
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
  rundentermine: JSON.parse(JSON.stringify(parameter.value.rundentermine)),
  processing: false,
})
const parameterRounds = computed(() => Array.from(
  { length: Number(parameterForm.runden_anzahl) || 0 },
  (_, index) => index + 1,
))
const switchForm = reactive({
  quelle_runde: runden.value[0] ?? null,
  ziel_runde: runden.value[1] ?? null,
  processing: false,
})
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

const capacityForBereich = (bereichName) => {
  const bereich = allBereiche.value.find(b => b.name === bereichName)
  if (!bereich) return parameter.value.standard_kapazitaet ?? 0
  return parameter.value.kapazitaeten?.[bereich.id] ?? parameter.value.standard_kapazitaet ?? 0
}

const roundParticipants = (bereichName, runde) => results.value?.[bereichName]?.[runde] ?? []

const roundSchedule = (runde) => parameter.value.rundentermine?.[runde] ?? emptyRoundSchedule()

const formatScheduleDate = (value, compact = false) => {
  if (!value) return null
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('de-DE', compact
    ? { day: '2-digit', month: '2-digit' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' }
  ).format(date)
}

const roundDateLabel = (runde, compact = false) => {
  const termin = roundSchedule(runde)
  const von = formatScheduleDate(termin.anfangsdatum, compact)
  const bis = formatScheduleDate(termin.enddatum, compact)
  if (!von || !bis) return 'Termin offen'
  return von === bis ? von : `${von}–${bis}`
}

const roundTimeLabel = (runde) => {
  const termin = roundSchedule(runde)
  if (!termin.startzeit || !termin.endzeit) return 'Uhrzeit offen'
  return `${termin.startzeit}–${termin.endzeit} Uhr`
}

const assignedCountForRound = (runde) => {
  return allBereiche.value.reduce((sum, bereich) => sum + roundParticipants(bereich.name, runde).length, 0)
}

const filteredParticipants = (bereichName, runde) => {
  const query = searchQuery.value.toLocaleLowerCase('de-DE')
  const participants = roundParticipants(bereichName, runde)

  if (!query) return participants

  return participants.filter((schueler) => {
    const searchable = [schueler.nachname, schueler.vorname, schueler.klasse]
      .filter(Boolean)
      .join(' ')
      .toLocaleLowerCase('de-DE')

    return searchable.includes(query)
  })
}

const initialsFor = (schueler) => {
  return `${schueler.vorname?.[0] ?? ''}${schueler.nachname?.[0] ?? ''}`.toUpperCase() || '–'
}

const capacityState = (bereichName, runde) => {
  const assigned = roundParticipants(bereichName, runde).length
  const capacity = Number(capacityForBereich(bereichName))
  const remaining = Math.max(0, capacity - assigned)
  const percentage = capacity > 0 ? Math.min(100, Math.round((assigned / capacity) * 100)) : 0

  if (capacity <= 0 || assigned >= capacity) {
    return {
      percentage,
      label: capacity <= 0 ? 'Keine Kapazität festgelegt' : 'Voll belegt',
      borderClass: 'border-red-200',
      badgeClass: 'bg-red-50 text-red-700',
      barClass: 'bg-red-500',
    }
  }

  if (percentage >= 80) {
    return {
      percentage,
      label: `${remaining} ${remaining === 1 ? 'Platz' : 'Plätze'} frei`,
      borderClass: 'border-amber-200',
      badgeClass: 'bg-amber-50 text-amber-700',
      barClass: 'bg-amber-500',
    }
  }

  return {
    percentage,
    label: `${remaining} ${remaining === 1 ? 'Platz' : 'Plätze'} frei`,
    borderClass: 'border-gray-200',
    badgeClass: 'bg-emerald-50 text-emerald-700',
    barClass: 'bg-emerald-500',
  }
}

const utilizationTextForRound = (runde) => {
  const assigned = assignedCountForRound(runde)
  const capacity = allBereiche.value.reduce((sum, bereich) => sum + Number(capacityForBereich(bereich.name)), 0)
  return `${assigned} von ${capacity} Plätzen belegt`
}

const roundUtilizationText = computed(() => `Runde ${selectedRound.value}: ${utilizationTextForRound(selectedRound.value)}`)

const openParameterModal = () => {
  const current = normalizeParameter(parameter.value)
  parameterForm.runden_anzahl = current.runden_anzahl
  parameterForm.standard_kapazitaet = current.standard_kapazitaet
  parameterForm.kapazitaeten = { ...current.kapazitaeten }
  parameterForm.rundentermine = JSON.parse(JSON.stringify(current.rundentermine))
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
  if (!canEinteilungStore.value) return
  createForm.schueler_id = null
  resetRoundFields(createForm)
  showCreateModal.value = true
}

const openGruppenModal = () => {
  if (!canEinteilungPlanning.value) return
  const fehlendeRunde = runden.value.find((runde) => {
    const termin = roundSchedule(runde)
    return !termin.anfangsdatum || !termin.enddatum || !termin.startzeit || !termin.endzeit
  })
  if (fehlendeRunde) {
    setStatus(`Bitte zuerst unter Parameter den Termin für Runde ${fehlendeRunde} festlegen.`, 'error')
    openParameterModal()
    return
  }

  if (!gruppenForm.bereiche.length) {
    gruppenForm.bereiche = allBereiche.value.map(b => b.id)
  }
  gruppenForm.raum_id = gruppenForm.raum_id ?? raeume.value[0]?.id ?? null
  gruppenForm.betreuer_id = gruppenForm.betreuer_id ?? betreuer.value[0]?.id ?? null
  showGruppenModal.value = true
}

const openExportModal = () => {
  if (!canEinteilungExport.value) return
  const termine = runden.value.map(roundSchedule)
  if (!exportForm.eintritt) {
    exportForm.eintritt = termine.map(termin => termin.anfangsdatum).filter(Boolean).sort()[0] ?? ''
  }
  if (!exportForm.austritt) {
    exportForm.austritt = termine.map(termin => termin.enddatum).filter(Boolean).sort().at(-1) ?? ''
  }
  showExportModal.value = true
}

const submitParameter = async () => {
  if (!canEinteilungPlanning.value) return
  parameterForm.processing = true
  try {
    const response = await axios.post(route('einteilung.parameter.update'), {
      ...contextPayload(),
      runden_anzahl: parameterForm.runden_anzahl,
      standard_kapazitaet: parameterForm.standard_kapazitaet,
      kapazitaeten: parameterForm.kapazitaeten,
      rundentermine: parameterForm.rundentermine,
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
  if (!canEinteilungPlanning.value) return
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
  if (!canEinteilungStore.value) return
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
  if (!canEinteilungStore.value) return
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
  if (!canEinteilungDestroy.value) return
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
  if (!canEinteilungPlanning.value) return
  gruppenForm.processing = true
  try {
    const response = await axios.post(route('gruppen.generieren'), {
      ...contextPayload(),
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
  if (!canEinteilungExport.value) return
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
  if (!canEinteilungUpdate.value) return
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
  if (!canEinteilungUpdate.value) return
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

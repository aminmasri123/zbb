<template>
  <Head title="Räumlichkeiten" />

  <AppLayout>
    <template #header>Räumlichkeiten</template>

    <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-slate-950">Raumverwaltung</h1>
            <p class="mt-1 text-sm text-slate-600">Standorte, Räume, Buchungen und Defektmeldungen</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              @click="openModalCreate"
              class="inline-flex items-center gap-2 rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600"
            >
              <i class="la la-plus"></i>
              Raum
            </button>
            <button
              @click="openBookingModal()"
              class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            >
              <i class="la la-calendar-plus"></i>
              Buchung
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div v-for="stat in stats" :key="stat.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-slate-500">{{ stat.label }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ stat.value }}</div>
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-xl">
              <i class="la la-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
              <input
                v-model="search"
                type="text"
                placeholder="Räume, Nummern, Typen, Standorte suchen"
                class="w-full rounded-md border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-zbb focus:ring-zbb"
              />
            </div>

            <div class="flex overflow-hidden rounded-md border border-slate-300 bg-white">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="activeTab = tab.value"
                class="px-4 py-2 text-sm font-semibold"
                :class="activeTab === tab.value ? 'bg-zbb text-white' : 'text-slate-700 hover:bg-slate-100'"
              >
                {{ tab.label }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'raeume'" class="space-y-5 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <select v-model="selectedStandortId" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Standorte</option>
                <option v-for="standort in localStandorte" :key="standort.id" :value="String(standort.id)">
                  {{ standort.name }}
                </option>
              </select>

              <select v-model="selectedStatus" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Status</option>
                <option value="verfuegbar">Verfügbar</option>
                <option value="eingeschraenkt">Eingeschränkt</option>
                <option value="wartung">Wartung</option>
                <option value="gesperrt">Gesperrt</option>
              </select>

              <select v-model="selectedBookable" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Buchbarkeiten</option>
                <option value="1">Buchbar</option>
                <option value="0">Nicht buchbar</option>
              </select>
            </div>

            <div v-if="filteredRooms.length === 0" class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
              Keine Räume gefunden.
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <article
                v-for="raum in filteredRooms"
                :key="raum.id"
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
              >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h2 class="text-lg font-semibold text-slate-950">{{ raum.name }}</h2>
                      <span :class="statusClass(raum.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ statusLabel(raum.status) }}
                      </span>
                      <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="raum.buchbar && raum.aktiv ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                      >
                        {{ raum.buchbar && raum.aktiv ? 'buchbar' : 'nicht buchbar' }}
                      </span>
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ raum.standort?.name || standortName(raum.standort_id) }}
                      <span v-if="raum.raumnummer"> · Raum {{ raum.raumnummer }}</span>
                      <span v-if="raum.etage"> · {{ raum.etage }}</span>
                    </div>
                  </div>

                  <div class="flex gap-2">
                    <button
                      @click="openEditModal(raum)"
                      class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                      <i class="la la-pen"></i>
                    </button>
                    <button
                      @click="confirmDelete(raum)"
                      class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                    >
                      <i class="la la-trash"></i>
                    </button>
                  </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                  <div>
                    <dt class="text-slate-500">Typ</dt>
                    <dd class="font-medium text-slate-900">{{ raum.typ || '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Kapazität</dt>
                    <dd class="font-medium text-slate-900">{{ raum.kapazitaet || '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Fläche</dt>
                    <dd class="font-medium text-slate-900">{{ raum.flaeche_qm ? `${raum.flaeche_qm} qm` : '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Belegung</dt>
                    <dd class="font-medium text-slate-900">{{ belegungsartLabel(raum.belegungsart) }}</dd>
                  </div>
                </dl>

                <div class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                  <div class="rounded-md bg-slate-50 p-3">
                    <div class="text-slate-500">Verantwortlich</div>
                    <div class="font-medium text-slate-900">{{ personName(raum.verantwortliche_person) || '-' }}</div>
                  </div>
                  <div class="rounded-md bg-slate-50 p-3">
                    <div class="text-slate-500">Nächste Belegung</div>
                    <div class="font-medium text-slate-900">{{ nextOccupancyLabel(raum) }}</div>
                  </div>
                </div>

                <p class="mt-4 line-clamp-2 text-sm text-slate-600">{{ raum.beschreibung || 'Keine Beschreibung hinterlegt.' }}</p>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                  <button
                    @click="openBookingModal(raum)"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                  >
                    <i class="la la-calendar-plus"></i>
                    Buchen
                  </button>
                  <button
                    @click="openMeldungModal(raum)"
                    class="inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100"
                  >
                    <i class="la la-exclamation-triangle"></i>
                    Defekt melden
                  </button>
                  <span class="ml-auto self-center text-sm font-medium text-slate-600">
                    {{ offeneMeldungen(raum).length }} offene Meldungen
                  </span>
                </div>
              </article>
            </div>
          </div>

          <div v-if="activeTab === 'buchungen'" class="space-y-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <input
                v-model="buchungsDatum"
                type="date"
                class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"
              />
              <button
                @click="openBookingModal()"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
              >
                <i class="la la-calendar-plus"></i>
                Neue Buchung
              </button>
            </div>

            <div v-if="visibleOccupancies.length === 0" class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
              Keine Belegung für das gewählte Datum.
            </div>

            <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
              <div
                v-for="belegung in visibleOccupancies"
                :key="`${belegung.source}-${belegung.id}`"
                class="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-slate-950">{{ belegung.titel }}</h3>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                      {{ belegung.source === 'gruppe' ? 'Gruppe' : buchungTypLabel(belegung.typ) }}
                    </span>
                    <span :class="bookingStatusClass(belegung.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                      {{ bookingStatusLabel(belegung.status) }}
                    </span>
                  </div>
                  <div class="mt-1 text-sm text-slate-600">
                    {{ belegung.raum?.name }} · {{ formatDateTime(belegung.start_at) }} bis {{ formatDateTime(belegung.end_at) }}
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ belegung.projekt?.name || belegung.gruppe?.projekt?.name || belegung.betreuerLabel || '' }}
                  </div>
                </div>

                <div v-if="belegung.source !== 'gruppe'" class="flex gap-2">
                  <button
                    @click="openBookingEdit(belegung)"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                  >
                    Bearbeiten
                  </button>
                  <button
                    v-if="belegung.status !== 'storniert'"
                    @click="cancelBooking(belegung)"
                    class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                  >
                    Stornieren
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="activeTab === 'meldungen'" class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <select v-model="selectedMeldungStatus" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Meldungen</option>
                <option value="offen">Offen</option>
                <option value="in_bearbeitung">In Bearbeitung</option>
                <option value="wartet_auf_extern">Wartet auf extern</option>
                <option value="behoben">Behoben</option>
                <option value="erledigt">Erledigt</option>
              </select>
              <select v-model="selectedPrioritaet" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Prioritäten</option>
                <option value="kritisch">Kritisch</option>
                <option value="hoch">Hoch</option>
                <option value="normal">Normal</option>
                <option value="niedrig">Niedrig</option>
              </select>
            </div>

            <div v-if="filteredMeldungen.length === 0" class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
              Keine Meldungen gefunden.
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <article
                v-for="meldung in filteredMeldungen"
                :key="meldung.id"
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="font-semibold text-slate-950">{{ meldung.titel }}</h3>
                      <span :class="meldungStatusClass(meldung.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ meldungStatusLabel(meldung.status) }}
                      </span>
                      <span :class="prioritaetClass(meldung.prioritaet)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ prioritaetLabel(meldung.prioritaet) }}
                      </span>
                    </div>
                    <div class="mt-1 text-sm text-slate-600">
                      {{ meldung.raumName }} · {{ meldung.standortName }}
                    </div>
                  </div>

                  <button
                    @click="openMeldungEdit(meldung)"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                  >
                    Bearbeiten
                  </button>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                  <div>
                    <dt class="text-slate-500">Gemeldet</dt>
                    <dd class="font-medium text-slate-900">{{ formatDate(meldung.created_at) }}</dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Fällig</dt>
                    <dd class="font-medium text-slate-900">{{ formatDate(meldung.faellig_am) || '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Zuständig</dt>
                    <dd class="font-medium text-slate-900">{{ personName(meldung.zugewiesen_an_person) || '-' }}</dd>
                  </div>
                </dl>

                <p class="mt-4 line-clamp-3 text-sm text-slate-600">{{ meldung.beschreibung || 'Keine Beschreibung hinterlegt.' }}</p>
                <p v-if="meldung.massnahme" class="mt-3 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">
                  {{ meldung.massnahme }}
                </p>
              </article>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ModalCreate
      :visible="isModalCreateOpen"
      :standorte="localStandorte"
      :personal="props.personal"
      @close="isModalCreateOpen = false"
      @added="upsertRoom"
    />

    <ModalEdit
      :visible="isModalEditOpen"
      :standorte="localStandorte"
      :personal="props.personal"
      :raum="raumToEdit"
      @close="isModalEditOpen = false"
      @updated="upsertRoom"
    />

    <ModalMeldung
      :visible="isMeldungModalOpen"
      :raum="raumForMeldung"
      @close="isMeldungModalOpen = false"
      @added="upsertMeldung"
    />

    <ModalMeldungStatus
      :visible="isMeldungEditOpen"
      :meldung="meldungToEdit"
      :personal="props.personal"
      @close="isMeldungEditOpen = false"
      @updated="upsertMeldung"
    />

    <ModalBuchung
      :visible="isBookingModalOpen"
      :raeume="allRooms"
      :buchung="buchungToEdit"
      :initialRaumId="bookingInitialRaumId"
      @close="closeBookingModal"
      @saved="upsertBuchung"
    />

    <ModalDestroy
      v-if="showModalLoeschen"
      :seite="seite"
      :toDelete="raumToDelete"
      @delete="removeRoom"
      @close="showModalLoeschen = false"
    />
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import ModalCreate from '@/Pages/Raum/ModalCreate.vue';
import ModalEdit from '@/Pages/Raum/ModalEdit.vue';
import ModalMeldung from '@/Pages/Raum/ModalMeldung.vue';
import ModalMeldungStatus from '@/Pages/Raum/ModalMeldungStatus.vue';
import ModalBuchung from '@/Pages/Raum/ModalBuchung.vue';

const props = defineProps({
  standorte: { type: Array, default: () => [] },
  personal: { type: Array, default: () => [] },
});

const seite = 'raeumlichkeiten';
const tabs = [
  { label: 'Räume', value: 'raeume' },
  { label: 'Buchungen', value: 'buchungen' },
  { label: 'Meldungen', value: 'meldungen' },
];

const activeTab = ref('raeume');
const search = ref('');
const selectedStandortId = ref('');
const selectedStatus = ref('');
const selectedBookable = ref('');
const selectedMeldungStatus = ref('');
const selectedPrioritaet = ref('');
const buchungsDatum = ref(localDateInput());
const localStandorte = ref(JSON.parse(JSON.stringify(props.standorte || [])));

const isModalCreateOpen = ref(false);
const isModalEditOpen = ref(false);
const isMeldungModalOpen = ref(false);
const isMeldungEditOpen = ref(false);
const isBookingModalOpen = ref(false);
const showModalLoeschen = ref(false);

const raumToEdit = ref(null);
const raumForMeldung = ref(null);
const meldungToEdit = ref(null);
const raumToDelete = ref(null);
const buchungToEdit = ref(null);
const bookingInitialRaumId = ref(null);

const allRooms = computed(() =>
  localStandorte.value.flatMap((standort) =>
    (standort.raeume || []).map((raum) => ({
      ...raum,
      standort: raum.standort || { id: standort.id, name: standort.name },
    }))
  )
);

const filteredRooms = computed(() => {
  const q = search.value.trim().toLowerCase();

  return allRooms.value.filter((raum) => {
    const matchesSearch = !q || [
      raum.name,
      raum.raumnummer,
      raum.etage,
      raum.typ,
      raum.beschreibung,
      raum.standort?.name,
    ].some((value) => String(value || '').toLowerCase().includes(q));

    const matchesStandort = !selectedStandortId.value || String(raum.standort_id) === selectedStandortId.value;
    const matchesStatus = !selectedStatus.value || (raum.status || 'verfuegbar') === selectedStatus.value;
    const matchesBookable = selectedBookable.value === ''
      || (selectedBookable.value === '1' ? raum.buchbar && raum.aktiv : !raum.buchbar || !raum.aktiv);

    return matchesSearch && matchesStandort && matchesStatus && matchesBookable;
  });
});

const allMeldungen = computed(() =>
  allRooms.value.flatMap((raum) =>
    (raum.meldungen || []).map((meldung) => ({
      ...meldung,
      raumName: raum.name,
      standortName: raum.standort?.name || standortName(raum.standort_id),
      raum,
    }))
  ).sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
);

const filteredMeldungen = computed(() =>
  allMeldungen.value.filter((meldung) => {
    const matchesStatus = !selectedMeldungStatus.value || meldung.status === selectedMeldungStatus.value;
    const matchesPriority = !selectedPrioritaet.value || meldung.prioritaet === selectedPrioritaet.value;
    return matchesStatus && matchesPriority;
  })
);

const allOccupancies = computed(() => {
  const direct = allRooms.value.flatMap((raum) =>
    (raum.buchungen || []).map((buchung) => ({
      ...buchung,
      source: 'buchung',
      raum,
      titel: buchung.titel || 'Buchung',
    }))
  );

  const gruppen = allRooms.value.flatMap((raum) =>
    (raum.gruppen || []).map((gruppe) => ({
      id: gruppe.id,
      source: 'gruppe',
      raum,
      gruppe,
      projekt: gruppe.projekt,
      titel: gruppe.bereich?.name || 'Gruppe',
      typ: 'gruppe',
      status: 'bestaetigt',
      start_at: combineDateTime(gruppe.anfangsdatum, gruppe.startzeit),
      end_at: combineDateTime(gruppe.enddatum || gruppe.anfangsdatum, gruppe.endzeit),
      betreuerLabel: personName(gruppe.betreuer),
    }))
  );

  return [...direct, ...gruppen]
    .filter((belegung) => belegung.start_at && belegung.end_at)
    .sort((a, b) => new Date(a.start_at) - new Date(b.start_at));
});

const visibleOccupancies = computed(() => {
  const dayStart = new Date(`${buchungsDatum.value}T00:00:00`);
  const dayEnd = new Date(`${buchungsDatum.value}T23:59:59`);

  return allOccupancies.value.filter((belegung) => {
    const start = new Date(belegung.start_at);
    const end = new Date(belegung.end_at);
    return start <= dayEnd && end >= dayStart;
  });
});

const stats = computed(() => {
  const openReports = allMeldungen.value.filter((meldung) => !meldungClosed(meldung.status)).length;
  const today = localDateInput();
  const todayCount = allOccupancies.value.filter((belegung) => {
    const start = new Date(belegung.start_at);
    const end = new Date(belegung.end_at);
    const dayStart = new Date(`${today}T00:00:00`);
    const dayEnd = new Date(`${today}T23:59:59`);
    return start <= dayEnd && end >= dayStart && belegung.status !== 'storniert';
  }).length;

  return [
    { label: 'Räume', value: allRooms.value.length },
    { label: 'Buchbar', value: allRooms.value.filter((raum) => raum.aktiv && raum.buchbar).length },
    { label: 'Offene Meldungen', value: openReports },
    { label: 'Belegungen heute', value: todayCount },
  ];
});

const openModalCreate = () => {
  isModalCreateOpen.value = true;
};

const openEditModal = (raum) => {
  raumToEdit.value = JSON.parse(JSON.stringify(raum));
  isModalEditOpen.value = true;
};

const openMeldungModal = (raum) => {
  raumForMeldung.value = raum;
  isMeldungModalOpen.value = true;
};

const openMeldungEdit = (meldung) => {
  meldungToEdit.value = JSON.parse(JSON.stringify(meldung));
  isMeldungEditOpen.value = true;
};

const openBookingModal = (raum = null) => {
  buchungToEdit.value = null;
  bookingInitialRaumId.value = raum?.id ?? null;
  isBookingModalOpen.value = true;
};

const openBookingEdit = (buchung) => {
  buchungToEdit.value = JSON.parse(JSON.stringify(buchung));
  bookingInitialRaumId.value = buchung.raum_id;
  isBookingModalOpen.value = true;
};

const closeBookingModal = () => {
  isBookingModalOpen.value = false;
  buchungToEdit.value = null;
  bookingInitialRaumId.value = null;
};

const confirmDelete = (raum) => {
  raumToDelete.value = { id: raum.id, name: raum.name };
  showModalLoeschen.value = true;
};

const removeRoom = (raumId) => {
  localStandorte.value.forEach((standort) => {
    standort.raeume = (standort.raeume || []).filter((raum) => raum.id !== raumId);
  });
  showModalLoeschen.value = false;
};

const upsertRoom = (raum) => {
  localStandorte.value.forEach((standort) => {
    standort.raeume = (standort.raeume || []).filter((item) => item.id !== raum.id);
  });

  const target = localStandorte.value.find((standort) => Number(standort.id) === Number(raum.standort_id));
  if (!target) return;

  if (!Array.isArray(target.raeume)) {
    target.raeume = [];
  }

  target.raeume.push(raum);
  target.raeume.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
};

const upsertMeldung = (meldung) => {
  const raumId = meldung.raum_id ?? meldung.raum?.id;
  const raum = findRoom(raumId);
  if (!raum) return;

  if (!Array.isArray(raum.meldungen)) {
    raum.meldungen = [];
  }

  const index = raum.meldungen.findIndex((item) => item.id === meldung.id);
  if (index === -1) {
    raum.meldungen.unshift(meldung);
  } else {
    raum.meldungen[index] = meldung;
  }
};

const upsertBuchung = (buchung) => {
  localStandorte.value.forEach((standort) => {
    (standort.raeume || []).forEach((raum) => {
      raum.buchungen = (raum.buchungen || []).filter((item) => item.id !== buchung.id);
    });
  });

  const raum = findRoom(buchung.raum_id ?? buchung.raum?.id);
  if (!raum) return;

  if (!Array.isArray(raum.buchungen)) {
    raum.buchungen = [];
  }

  raum.buchungen.push(buchung);
  raum.buchungen.sort((a, b) => new Date(a.start_at) - new Date(b.start_at));
};

const cancelBooking = async (buchung) => {
  const result = await Swal.fire({
    title: 'Buchung stornieren?',
    text: buchung.titel,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Stornieren',
    cancelButtonText: 'Abbrechen',
  });

  if (!result.isConfirmed) return;

  try {
    const response = await axios.delete(route('raeumlichkeiten.buchung.destroy', buchung.id));
    upsertBuchung(response.data.buchung);
    Swal.fire('Storniert', 'Die Buchung wurde storniert.', 'success');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Buchung konnte nicht storniert werden.', 'error');
  }
};

function findRoom(raumId) {
  for (const standort of localStandorte.value) {
    const raum = (standort.raeume || []).find((item) => Number(item.id) === Number(raumId));
    if (raum) return raum;
  }
  return null;
}

function standortName(standortId) {
  return localStandorte.value.find((standort) => Number(standort.id) === Number(standortId))?.name || '';
}

function offeneMeldungen(raum) {
  return (raum.meldungen || []).filter((meldung) => !meldungClosed(meldung.status));
}

function meldingStatusesClosed() {
  return ['behoben', 'erledigt'];
}

function meldungClosed(status) {
  return meldingStatusesClosed().includes(status);
}

function nextOccupancyLabel(raum) {
  const now = new Date();
  const next = allOccupancies.value.find((belegung) =>
    Number(belegung.raum?.id) === Number(raum.id)
    && belegung.status !== 'storniert'
    && new Date(belegung.end_at) >= now
  );

  return next ? `${formatDate(next.start_at)} ${formatTimeRange(next.start_at, next.end_at)}` : '-';
}

function combineDateTime(date, time) {
  if (!date || !time) return null;
  return `${String(date).slice(0, 10)}T${String(time).slice(0, 5)}`;
}

function localDateInput(date = new Date()) {
  const pad = (value) => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function formatDateTime(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date);
}

function formatTimeRange(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);
  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return '';

  return `${startDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}-${endDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`;
}

function personName(person) {
  if (!person) return '';
  return `${person.vorname ?? ''} ${person.nachname ?? ''}`.trim();
}

function belegungsartLabel(value) {
  return {
    frei: 'frei vergebbar',
    standard: 'feste Belegung',
    teilweise: 'teilweise belegt',
    blockiert: 'blockiert',
  }[value] || 'frei vergebbar';
}

function statusLabel(value) {
  return {
    verfuegbar: 'verfügbar',
    eingeschraenkt: 'eingeschränkt',
    wartung: 'Wartung',
    gesperrt: 'gesperrt',
  }[value || 'verfuegbar'] || 'verfügbar';
}

function statusClass(value) {
  return {
    verfuegbar: 'bg-emerald-50 text-emerald-700',
    eingeschraenkt: 'bg-amber-50 text-amber-700',
    wartung: 'bg-sky-50 text-sky-700',
    gesperrt: 'bg-red-50 text-red-700',
  }[value || 'verfuegbar'] || 'bg-emerald-50 text-emerald-700';
}

function buchungTypLabel(value) {
  return {
    buchung: 'Buchung',
    wartung: 'Wartung',
    sperre: 'Sperrzeit',
  }[value] || 'Buchung';
}

function bookingStatusLabel(value) {
  return {
    reserviert: 'reserviert',
    bestaetigt: 'bestätigt',
    storniert: 'storniert',
  }[value] || 'reserviert';
}

function bookingStatusClass(value) {
  return {
    reserviert: 'bg-amber-50 text-amber-700',
    bestaetigt: 'bg-emerald-50 text-emerald-700',
    storniert: 'bg-slate-100 text-slate-600',
  }[value] || 'bg-amber-50 text-amber-700';
}

function meldungStatusLabel(value) {
  return {
    offen: 'offen',
    in_bearbeitung: 'in Bearbeitung',
    wartet_auf_extern: 'wartet extern',
    behoben: 'behoben',
    erledigt: 'erledigt',
  }[value] || 'offen';
}

function meldungStatusClass(value) {
  return {
    offen: 'bg-red-50 text-red-700',
    in_bearbeitung: 'bg-amber-50 text-amber-700',
    wartet_auf_extern: 'bg-sky-50 text-sky-700',
    behoben: 'bg-emerald-50 text-emerald-700',
    erledigt: 'bg-slate-100 text-slate-600',
  }[value] || 'bg-red-50 text-red-700';
}

function prioritaetLabel(value) {
  return {
    kritisch: 'kritisch',
    hoch: 'hoch',
    normal: 'normal',
    niedrig: 'niedrig',
  }[value] || 'normal';
}

function prioritaetClass(value) {
  return {
    kritisch: 'bg-red-600 text-white',
    hoch: 'bg-red-50 text-red-700',
    normal: 'bg-slate-100 text-slate-700',
    niedrig: 'bg-emerald-50 text-emerald-700',
  }[value] || 'bg-slate-100 text-slate-700';
}
</script>

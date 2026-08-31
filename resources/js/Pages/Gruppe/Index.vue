<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, defineProps, watch } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Gruppe/ModalCreate.vue';
import ModalEdit from '@/Pages/Gruppe/ModalEdit.vue';
import ModalMeldung from '@/Pages/Raum/ModalMeldung.vue';
import { formatTime } from '@/utils/timeFormat';
import { formatDate } from '@/utils/dateFormat';
import { usePermissions } from '@/utils/permissions';
let seite = 'gruppe';
let search = ref('');
let gruppeToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let gruppeToEdit = ref(null);
let isMeldungModalOpen = ref(false);
let gruppeForMeldung = ref(null);
let raumForMeldung = ref(null);
const { can } = usePermissions();
const canCreateGroup = computed(() => can('gruppe.store'));
const canUpdateGroup = computed(() => can('gruppe.update'));
const canDeleteGroup = computed(() => can('gruppe.destroy'));

const betreuerInitialen = (betreuer) => {
  const vorname = betreuer?.vorname?.trim()?.charAt(0) || '';
  const nachname = betreuer?.nachname?.trim()?.charAt(0) || '';

  return `${vorname}${nachname}`.toUpperCase() || '?';
};

const hatProfilbild = (betreuer) =>
  Boolean(betreuer?.user?.profile_photo_path && betreuer?.user?.profile_photo_url);

// Props
const props = defineProps({
    gruppen: {
        type: [Array, Object],
        required: true,
    },
    betreuer: {
        type: [Array, Object],
        required: true,
    },

     bereiche: {
        type: [Array, Object],
        required: false,
    },

    projekt: {
        type: [Array, Object],
        required: true,
    },
    canSeeAllGroups: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});
const sortiereGruppenNachDatum = (gruppen) => [...gruppen].sort((a, b) => {
  const datumVergleich = String(b?.anfangsdatum || '').localeCompare(String(a?.anfangsdatum || ''));
  if (datumVergleich !== 0) return datumVergleich;

  const zeitVergleich = String(b?.startzeit || '').localeCompare(String(a?.startzeit || ''));
  if (zeitVergleich !== 0) return zeitVergleich;

  return Number(b?.id || 0) - Number(a?.id || 0);
});
// ✅ Lokale Liste – unterstützt Array ODER paginierte Daten
let localGruppen = ref(
  sortiereGruppenNachDatum(Array.isArray(props.gruppen)
    ? [...props.gruppen]
    : [...(props.gruppen.data || [])])
);

let filteredGruppen = ref([...localGruppen.value]);

// 🔹 Modals
const openModalCreate = () => {
  if (!canCreateGroup.value) return;
  isModalCreateOpen.value = true;
};
const closeModalCreate = () => { isModalCreateOpen.value = false; };

const openModalEdit = (gruppe) => {
  if (!canUpdateGroup.value) return;
  gruppeToEdit.value = gruppe;
  isModalEditOpen.value = true;
};
const closeModalEdit = () => { isModalEditOpen.value = false; };

const openMeldungModal = (gruppe) => {
  if (!gruppe?.raum) return;
  gruppeForMeldung.value = gruppe;
  raumForMeldung.value = gruppe.raum;
  isMeldungModalOpen.value = true;
};

const closeMeldungModal = () => {
  isMeldungModalOpen.value = false;
  gruppeForMeldung.value = null;
  raumForMeldung.value = null;
};

// 🔹 CRUD
const addGruppe = (gruppe) => {
  localGruppen.value = sortiereGruppenNachDatum([...localGruppen.value, gruppe]);
  applySearchFilter();
};

const updateGruppe = (updatedGruppe) => {
  const index = localGruppen.value.findIndex(g => g.id === updatedGruppe.id);
  if (index !== -1) {
    localGruppen.value[index] = updatedGruppe;
  }
  localGruppen.value = sortiereGruppenNachDatum(localGruppen.value);
  applySearchFilter();
};

// 🔹 Delete
const confirmDelete = (gruppe) => {
  if (!canDeleteGroup.value) return;
  gruppeToDelete.value = { id: gruppe.id, name: gruppe.bereich?.name || gruppe.raum?.name || `Gruppe ${gruppe.id}` };
  showModalLöschen.value = true;
};

const handleDelete = (gruppeId) => {
  localGruppen.value = localGruppen.value.filter(g => g.id !== gruppeId);
  applySearchFilter();
  showModalLöschen.value = false;
};

// 🔹 Suche
const normalizeSearchValue = (value) => String(value ?? '').toLowerCase().trim();

const toDateParts = (value) => {
  if (!value) return [];

  const formatted = formatDate(value);
  const raw = String(value);
  const rawDate = raw.split('T')[0];
  const parts = [raw, rawDate, formatted];

  if (formatted) {
    const [day, month, year] = formatted.split('.');
    if (day && month && year) {
      parts.push(`${day}.${month}.${year.slice(-2)}`, `${day}.${month}`, `${month}.${year}`);

      const shortDay = String(Number(day));
      const shortMonth = String(Number(month));
      parts.push(
        `${shortDay}.${shortMonth}.${year}`,
        `${shortDay}.${shortMonth}.${year.slice(-2)}`,
        `${shortDay}.${shortMonth}`,
      );
    }
  }

  return parts.filter(Boolean);
};

const gruppeSearchText = (gruppe) => [
  gruppe.bereich?.name,
  gruppe.betreuer?.vorname,
  gruppe.betreuer?.nachname,
  `${gruppe.betreuer?.vorname ?? ''} ${gruppe.betreuer?.nachname ?? ''}`,
  gruppe.raum?.name,
  gruppe.raum?.standort?.name,
  gruppe.standort?.name,
  gruppe.externer_ort,
  gruppe.typ,
  gruppe.partners?.map((partner) => partner.name).join(' '),
  ...toDateParts(gruppe.anfangsdatum),
  ...toDateParts(gruppe.enddatum),
  `${formatDate(gruppe.anfangsdatum) ?? ''} ${formatDate(gruppe.enddatum) ?? ''}`,
  `${formatTime(gruppe.startzeit)}-${formatTime(gruppe.endzeit)}`,
  formatTime(gruppe.startzeit),
  formatTime(gruppe.endzeit),
].map(normalizeSearchValue).filter(Boolean).join(' ');

const applySearchFilter = () => {
  const q = normalizeSearchValue(search.value);

  if (q) {
    filteredGruppen.value = localGruppen.value.filter(g => gruppeSearchText(g).includes(q));
  } else {
    filteredGruppen.value = [...localGruppen.value];
  }
};

watch(search, () => {
  router.get('/gruppe', {
    search: search.value,
    partner_id: props.filters.partner_id || undefined,
  }, { preserveState: true, replace: true });
  applySearchFilter();
});
</script>

<template>
    <Head title="Gruppen" />

    <app-layout>
        <template #header>{{$t('Gruppen')}}</template>

        <!-- Toolbar -->
        <div class="mx-auto mb-3 flex w-full max-w-7xl items-stretch overflow-hidden rounded-md border border-gray-300 bg-white shadow-md">
            <button
                v-if="canCreateGroup"
                type="button"
                @click="openModalCreate"
                class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Gruppe anlegen"
            >
                <i class="la la-plus"></i>
            </button>
            <input v-model="search" type="text"
                         class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                         placeholder="Suchen ..." />
            <Link
                :href="route('gruppe.index', props.filters.partner_id ? { partner_id: props.filters.partner_id } : {})"
                class="flex w-14 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
                title="Aktualisieren"
            >
                <i class="la la-refresh"></i>
            </Link>
        </div>

        <!-- Tabelle -->
        <!-- Gruppenübersicht -->
        <div class="mx-auto mt-6 w-full max-w-7xl rounded-2xl bg-white p-4 shadow-md sm:p-6 lg:mt-8 lg:p-8">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ props.filters.partner ? `Gruppen für ${props.filters.partner.name}` : (props.canSeeAllGroups ? 'Projektgruppen' : 'Meine Gruppen') }}
                </h2>
                <Link
                    v-if="props.filters.partner"
                    :href="route('gruppe.index')"
                    class="rounded-full border border-zbb px-3 py-1 text-sm font-medium text-zbb hover:bg-zbb hover:text-white"
                >
                    Schulfilter entfernen
                </Link>
            </div>

        <!-- Wenn keine Gruppen -->
            <div v-if="filteredGruppen.length === 0" class="text-gray-500 italic text-sm">
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-lg font-medium">Noch keine Gruppen erstellt</p>
                    <p v-if="canCreateGroup" class="text-sm">Klicken Sie auf "Neue Gruppe" um zu beginnen</p>
                </div>
            </div>

            <!-- Karten -->
            <div v-else class="space-y-3">
                <div
                v-for="gruppe in filteredGruppen"
                :key="gruppe.id"
                class="flex min-w-0 flex-col justify-between gap-4 rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm transition-all duration-200 hover:shadow-md sm:px-5 xl:flex-row xl:items-center"
                >
                <!-- Linker Bereich -->
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 flex-wrap items-start gap-3 sm:items-center sm:gap-4">
                    <Link
                        :href="route('gruppeHasTeilnehmer.show', gruppe.id)"
                        class="flex min-w-0 flex-1 items-start gap-2 font-semibold text-gray-800 transition-colors hover:text-zbb sm:items-center"
                    >
                        <img
                            v-if="hatProfilbild(gruppe.betreuer)"
                            :src="gruppe.betreuer.user.profile_photo_url"
                            :alt="`${gruppe.betreuer?.vorname || ''} ${gruppe.betreuer?.nachname || ''}`.trim()"
                            class="h-8 w-8 shrink-0 rounded-full object-cover ring-2 ring-white shadow"
                        />
                        <span
                            v-else
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zbb text-xs font-bold text-white shadow"
                            aria-hidden="true"
                        >
                            {{ betreuerInitialen(gruppe.betreuer) }}
                        </span>
                        <span class="min-w-0 break-words leading-snug">
                            {{ gruppe.betreuer?.vorname }} {{ gruppe.betreuer?.nachname }}
                            <span class="mx-1 text-gray-400">—</span>
                            {{ gruppe.bereich?.name || '– ohne Bereich –' }}
                        </span>
                    </Link>

                    <!-- Gruppentyp-Badge -->
                    <span
                        class="inline-block shrink-0 rounded-full border border-zbb/20 bg-zbb/10 px-3 py-1 text-xs font-medium text-zbb"
                    >
                        {{
                        gruppe.typ === '1-day' ? '1 Tag' :
                        gruppe.typ === '2-day' ? '2 Tage' :
                        gruppe.typ === '3-day' ? '3 Tage' : 'Flexibel'
                        }}
                    </span>
                    </div>
                    <span class="mt-2 block break-words text-sm leading-snug text-red-500">
                        {{ formatDate(gruppe.anfangsdatum) }} {{ formatDate(gruppe.enddatum) }}
                        <span class="whitespace-nowrap">{{ formatTime(gruppe.startzeit) }}-{{ formatTime(gruppe.endzeit) }}</span>
                    </span>

                    <!-- Zusatzinfos -->
                    <div class="mt-2 grid min-w-0 gap-x-4 gap-y-2 text-sm text-gray-500 sm:grid-cols-2 xl:flex xl:flex-wrap">
                    <div class="flex min-w-0 items-center gap-1">
                        <i class="la la-users la-2x text-zbb/70"></i>
                        <span>{{ gruppe.teilnehmer_count || 0 }} Teilnehmer</span>
                    </div>
                    <div class="flex min-w-0 items-center gap-1">
                        <i class="la la-clock la-2x text-zbb/70"></i>
                        <span>{{ gruppe.anwesend_heute || 0 }} heute anwesend</span>
                    </div>
                    <div class="flex min-w-0 items-center gap-1">
                        <i class="la la-door-open la-2x text-zbb/70"></i>
                        <span v-if="gruppe.ort_typ === 'extern'" class="min-w-0 break-words">{{ gruppe.externer_ort || 'Externer Ort' }}</span>
                        <span v-else class="min-w-0 break-words">{{ gruppe.raum?.name || 'Kein Raum' }}</span>
                    </div>
                    <div class="flex min-w-0 items-center gap-1">
                        <i class="la la-map-marker la-2x text-zbb/70"></i>
                        <span class="min-w-0 break-words">{{ gruppe.standort?.name || gruppe.raum?.standort?.name || 'Kein Standort' }}</span>
                    </div>
                    <div v-if="gruppe.partners?.length" class="flex min-w-0 items-start gap-1 sm:col-span-2 xl:max-w-full">
                        <i class="la la-school la-2x text-zbb/70"></i>
                        <span class="min-w-0 break-words">Bezug: {{ gruppe.partners.map((partner) => partner.name).join(', ') }}</span>
                    </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="grid w-full shrink-0 grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap xl:w-auto xl:max-w-[46%] xl:justify-end">
                    <Link
                    v-if="props.projekt?.klassenbuch_aktiv && can('klassenbuch.index')"
                    :href="route('klassenbuch.index')"
                    class="rounded-md border border-zbb px-4 py-2 text-center text-sm font-medium text-zbb shadow-sm transition hover:bg-zbb hover:text-white"
                    >
                    Klassenbuch
                    </Link>
                    <button
                    v-if="gruppe.raum"
                    @click="openMeldungModal(gruppe)"
                    class="rounded-md bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition hover:bg-amber-600"
                    >
                    Melden
                    </button>
                    <button
                    v-if="canUpdateGroup"
                    @click="openModalEdit(gruppe)"
                    class="rounded-md bg-zbb px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition hover:bg-zbb/90"
                    >
                    {{ gruppe.raum || gruppe.ort_typ === 'extern' ? 'Verwalten' : 'Raum eintragen' }}
                    </button>
                    <button
                    v-if="canDeleteGroup"
                    @click="confirmDelete(gruppe)"
                    class="rounded-md bg-red-600 px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition hover:bg-red-700"
                    >
                    Löschen
                    </button>
                </div>
                </div>
            </div>
        </div>



        <!-- Modals -->
        <ModalCreate v-if="canCreateGroup" :visible="isModalCreateOpen" :projekt="props.projekt" :betreuer="props.betreuer"
                                 @close="isModalCreateOpen = false"
                                 @added="(gruppe) => { localGruppen.unshift(gruppe); applySearchFilter(); }"
        />
        <ModalEdit v-if="canUpdateGroup" :visible="isModalEditOpen"
                            :bereiche="props.projekt.bereiche"
                            :personal="props.betreuer"
                            :partners="props.projekt.partners || []"
                            :raeume="props.projekt.raeume"
                            :standorte="props.projekt.standorte"
                            :toEdit="gruppeToEdit"
                            @close="closeModalEdit"
                            @updated="updateGruppe"/>
        <ModalMeldung
                            :visible="isMeldungModalOpen"
                            :raum="raumForMeldung"
                            :projekt-id="props.projekt.id"
                            :gruppe-id="gruppeForMeldung?.id"
                            @close="closeMeldungModal"
                            @added="closeMeldungModal"/>
        <template v-if="canDeleteGroup">
        <ModalDestroy v-if="showModalLöschen"
                                    @delete="handleDelete"
                                    @close="showModalLöschen = false"
                                    :seite="seite"
                                    :toDelete="gruppeToDelete"/>
        </template>
    </app-layout>

</template>

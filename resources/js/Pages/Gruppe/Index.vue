<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, defineProps, watch } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Gruppe/ModalCreate.vue';
import ModalEdit from '@/Pages/Gruppe/ModalEdit.vue';
import { formatTime } from '@/utils/timeFormat';
import { formatDate } from '@/utils/dateFormat';
let seite = 'gruppe';
let search = ref('');
let gruppeToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let gruppeToEdit = ref(null);

// Props
const props = defineProps({
    gruppen: {
        type: [Array, Object],
        required: true,
    },

     bereiche: {
        type: [Array, Object],
        required: true,
    },
    personal: {
        type: [Array, Object],
        required: true,
    },
    projekt: {
        type: [Array, Object],
        required: true,
    },
});
console.log('Props gruppen:', props.projekt);
// ✅ Lokale Liste – unterstützt Array ODER paginierte Daten
let localGruppen = ref(
  Array.isArray(props.gruppen)
    ? [...props.gruppen]
    : [...(props.gruppen.data || [])]
);

let filteredGruppen = ref([...localGruppen.value]);

// 🔹 Modals
const openModalCreate = () => { isModalCreateOpen.value = true; };
const closeModalCreate = () => { isModalCreateOpen.value = false; };

const openModalEdit = (gruppe) => {
  gruppeToEdit.value = gruppe;
  isModalEditOpen.value = true;
};
const closeModalEdit = () => { isModalEditOpen.value = false; };

// 🔹 CRUD
const addGruppe = (gruppe) => {
  localGruppen.value.unshift(gruppe);
  applySearchFilter();
};

const updateGruppe = (updatedGruppe) => {
  const index = localGruppen.value.findIndex(g => g.id === updatedGruppe.id);
  if (index !== -1) {
    localGruppen.value[index] = updatedGruppe;
  }
  applySearchFilter();
};

// 🔹 Delete
const confirmDelete = (gruppe) => {
  gruppeToDelete.value = { id: gruppe.id, name: gruppe.name };
  showModalLöschen.value = true;
};

const handleDelete = (gruppeId) => {
  localGruppen.value = localGruppen.value.filter(g => g.id !== gruppeId);
  applySearchFilter();
  showModalLöschen.value = false;
};

// 🔹 Suche
const applySearchFilter = () => {
  if (search.value) {
    filteredGruppen.value = localGruppen.value.filter(g =>
      g.name?.toLowerCase().includes(search.value.toLowerCase())
    );
  } else {
    filteredGruppen.value = [...localGruppen.value];
  }
};

watch(search, () => {
  router.get('/gruppe', { search: search.value }, { preserveState: true, replace: true });
  applySearchFilter();
});
</script>

<template>
    <Head title="Gruppen" />

    <app-layout>
        <template #header>{{$t('Gruppen')}}</template>

        <!-- Toolbar -->
        <div class="flex justify-around shadow-md items-center w-3/4 mx-auto mb-3">
            <div @click="openModalCreate" class="flex items-center">
                <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </div>
            <input v-model="search" type="text"
                         class="border border-gray-300 text-sm p-2.5 w-full"
                         placeholder="Suchen ..." />
            <Link :href="route('gruppe.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
        </div>

        <!-- Tabelle -->
        <!-- Gruppenübersicht -->
        <div class="bg-white rounded-2xl shadow-md mt-8 p-8 w-3/4 mx-auto">
            <h2 class="text-lg font-semibold text-gray-800 mb-5">Meine Gruppen</h2>

        <!-- Wenn keine Gruppen -->
            <div v-if="filteredGruppen.length === 0" class="text-gray-500 italic text-sm">
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-lg font-medium">Noch keine Gruppen erstellt</p>
                    <p class="text-sm">Klicken Sie auf "Neue Gruppe" um zu beginnen</p>
                </div>
            </div>

            <!-- Karten -->
            <div v-else class="space-y-3">
                <div
                v-for="gruppe in filteredGruppen"
                :key="gruppe.id"
                class="flex flex-col sm:flex-row justify-between sm:items-center bg-white border border-gray-100 rounded-xl px-5 py-4 shadow-sm hover:shadow-md transition-all duration-200"
                >
                <!-- Linker Bereich -->
                <div>
                    <div class="flex items-center gap-4">
                    <Link
                        :href="route('gruppeHasTeilnehmer.show', gruppe.id)"
                        class="font-semibold text-gray-800 hover:text-zbb transition-colors"
                    >
                        {{ gruppe.bereich.name || '– ohne Namen –' }}
                    </Link>

                    <!-- Gruppentyp-Badge -->
                    <span
                        class="inline-block bg-zbb/10 text-zbb text-xs font-medium px-3 py-1 rounded-full border border-zbb/20"
                    >
                        {{
                        gruppe.typ === '1-day' ? '1 Tag' :
                        gruppe.typ === '2-day' ? '2 Tage' :
                        gruppe.typ === '3-day' ? '3 Tage' : 'Flexibel'
                        }}
                    </span>
                    </div>
                    <span class="text-sm p-0 m-0 text-red-500">{{ formatDate(gruppe.anfangsdatum) }} {{ formatDate(gruppe.enddatum) }}   {{ formatTime(gruppe.startzeit) }}-{{ formatTime(gruppe.endzeit) }}</span>

                    <!-- Zusatzinfos -->
                    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-1">
                        <i class="la la-users la-2x text-zbb/70"></i>
                        <span>{{ gruppe.teilnehmer_count || 0 }} Teilnehmer</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i class="la la-clock la-2x text-zbb/70"></i>
                        <span>{{ gruppe.anwesend_heute || 0 }} heute anwesend</span>
                    </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-2 mt-4 sm:mt-0">
                    <button
                    @click="openModalEdit(gruppe)"
                    class="px-4 py-2 text-sm font-medium rounded-md bg-zbb text-white shadow-sm hover:bg-zbb/90 transition"
                    >
                    Verwalten
                    </button>
                    <button
                    @click="confirmDelete(gruppe)"
                    class="px-4 py-2 text-sm font-medium rounded-md bg-red-600 text-white shadow-sm hover:bg-red-700 transition"
                    >
                    Löschen
                    </button>
                </div>
                </div>
            </div>
        </div>



        <!-- Modals -->
        <ModalCreate :visible="isModalCreateOpen" :projekt="props.projekt"
                                 @close="isModalCreateOpen = false"
                                 @added="(gruppe) => { localGruppen.unshift(gruppe); applySearchFilter(); }"
        />
        <ModalEdit :visible="isModalEditOpen"
                            :bereiche="props.bereiche"
                            :personal="props.personal"
                            :toEdit="gruppeToEdit"
                            @close="closeModalEdit"
                            @updated="updateGruppe"/>
        <ModalDestroy v-if="showModalLöschen"
                                    @delete="handleDelete"
                                    @close="showModalLöschen = false"
                                    :seite="seite"
                                    :toDelete="gruppeToDelete"/>
    </app-layout>

</template>



<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';
import ModalDestroy from '@/Components/ModalDestroyForm.vue';
import ModalCreate from '@/Pages/Bereich/ModalCreate.vue';
import ModalEdit from '@/Pages/Bereich/ModalEdit.vue';
import Bereichewaelen from './Bereichewaelen.vue';
const props = defineProps({
    projekt: Object,
    alle_teilnehmer: Array,
    partner: Object,
    schuljahr: String,
    teil: String,
    setting: Object,
});

const selectionCount = ref(props.setting?.auswahl_anzahl ?? 4);
const accessEnabled = ref(props.setting?.zugang_aktiv ?? true);
const publicUrl = ref(props.setting?.public_url ?? '');
const qrSvg = ref(props.setting?.qr_svg ?? '');
const settingSaving = ref(false);

// Reactive Variablen
let search = ref('');
let localBereiche = ref([...props.projekt.bereiche]);
let filteredBereiche = ref([...localBereiche.value]);

let bereichToDelete = ref(null);
let showModalLöschen = ref(false);
let isModalCreateOpen = ref(false);
let isModalEditOpen = ref(false);
let bereichToEdit = ref(null);

let newBereich = ref({
    name: '',
    beschreibung: '',
});

// Modal-Funktionen
const openModalCreate = () => isModalCreateOpen.value = true;
const closeModalCreate = () => isModalCreateOpen.value = false;

const openModalEdit = (bereich) => {
    bereichToEdit.value = bereich;
    isModalEditOpen.value = true;
};
const closeModalEdit = () => isModalEditOpen.value = false;

// Bereich hinzufügen
const addBereich = async (data) => {
    try {
        const response = await axios.post(route('bereich.store'), data);
        localBereiche.value.unshift(response.data.bereich);
        Swal.fire({ title: 'Erfolg!', text: 'Bereich angelegt', icon: 'success', timer: 2000 });
        closeModalCreate();
    } catch (error) {
        Swal.fire({ title: 'Fehler!', text: error.response?.data?.message || 'Fehler beim Erstellen', icon: 'error', timer: 3000 });
    }
};

// Bereich aktualisieren
const updateBereich = (updatedBereich) => {
    const index = localBereiche.value.findIndex(b => b.id === updatedBereich.id);
    if(index !== -1) localBereiche.value[index] = updatedBereich;
    applySearchFilter();
};

// Bereich löschen
const confirmDelete = (bereich) => {
    bereichToDelete.value = { id: bereich.id, name: bereich.name };
    showModalLöschen.value = true;
};
const handleDelete = (bereichId) => {
    localBereiche.value = localBereiche.value.filter(b => b.id !== bereichId);
    showModalLöschen.value = false;
};

// Suchfilter
const applySearchFilter = () => {
    if(search.value) {
        filteredBereiche.value = localBereiche.value.filter(b =>
            b.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredBereiche.value = [...localBereiche.value];
    }
};
watch(search, () => {
    applySearchFilter();
});

const updateSetting = async (count = selectionCount.value) => {
    const previousCount = selectionCount.value;
    selectionCount.value = Number(count);
    settingSaving.value = true;

    try {
        const response = await axios.post(route('bereichsauswahl.setting.update'), {
            partner_id: props.partner.id,
            schuljahr: props.schuljahr,
            teil: props.teil,
            auswahl_anzahl: selectionCount.value,
            zugang_aktiv: accessEnabled.value,
        });

        selectionCount.value = response.data.setting.auswahl_anzahl;
        accessEnabled.value = response.data.setting.zugang_aktiv;

        Swal.fire({
            title: 'Gespeichert',
            text: 'Die Vorgabe wurde aktualisiert.',
            icon: 'success',
            timer: 1200,
            showConfirmButton: false,
        });
    } catch (error) {
        selectionCount.value = previousCount;
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Die Vorgabe konnte nicht gespeichert werden.',
            icon: 'error',
        });
    } finally {
        settingSaving.value = false;
    }
};

const copyPublicUrl = async () => {
    try {
        await navigator.clipboard.writeText(publicUrl.value);
        Swal.fire({
            title: 'Link kopiert',
            icon: 'success',
            timer: 1000,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            title: 'Link',
            text: publicUrl.value,
            icon: 'info',
        });
    }
};
</script>

<template>
<Head title="Bereichsauswahl" />
<AppLayout>
    <template #header>Bereichsauswahl</template>

    <div class="space-y-4">
        <div class="bg-white border border-gray-300 shadow-sm p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <div>
                        <p class="text-xs uppercase text-gray-500 font-semibold">Schule</p>
                        <h1 class="text-xl font-bold text-gray-900">{{ partner.name }}</h1>
                        <p class="text-sm text-gray-600">{{ schuljahr }} - Teil {{ teil }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-gray-500 font-semibold mb-2">Wahlfelder</p>
                        <div class="inline-flex border border-gray-300 overflow-hidden">
                            <button
                                v-for="count in [2, 3, 4]"
                                :key="count"
                                type="button"
                                :disabled="settingSaving"
                                @click="updateSetting(count)"
                                class="px-4 py-2 text-sm border-r border-gray-300 last:border-r-0 disabled:opacity-50"
                                :class="selectionCount === count ? 'bg-zbb text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                            >
                                {{ count }}
                            </button>
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="accessEnabled"
                            type="checkbox"
                            class="rounded border-gray-300 text-zbb focus:ring-zbb"
                            @change="updateSetting(selectionCount)"
                        />
                        Teilnehmerzugang aktiv
                    </label>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Link
                        :href="route('einteilung.show', { partnerId: partner.id, schuljahr, teil })"
                        class="inline-flex items-center justify-center gap-2 bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                    >
                        <i class="las la-arrows-alt"></i>
                        Zur Einteilung
                    </Link>

                    <div
                        v-if="qrSvg"
                        class="w-[132px] h-[132px] border border-gray-300 bg-white p-2 flex items-center justify-center"
                        v-html="qrSvg"
                    />

                    <div class="min-w-0 max-w-xl">
                        <p class="text-xs uppercase text-gray-500 font-semibold">QR-Link</p>
                        <div class="flex mt-1">
                            <input
                                :value="publicUrl"
                                readonly
                                class="border border-gray-300 text-sm px-3 py-2 w-full min-w-0"
                            />
                            <button
                                type="button"
                                class="px-4 py-2 bg-zbb text-white text-sm hover:bg-orange-600"
                                @click="copyPublicUrl"
                            >
                                <i class="las la-copy"></i>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Der QR-Code führt zur Teilnehmerseite für diese Schule, dieses Schuljahr und diesen Teil.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center">
            <label for="bereichsauswahl-search" class="sr-only">Suchen</label>
            <input
                id="bereichsauswahl-search"
                v-model="search"
                type="text"
                placeholder="Teilnehmer, Klasse oder Code suchen ..."
                class="border border-gray-300 text-gray-900 text-sm focus:ring-zbb focus:border-zbb block w-full p-2.5"
            />
        </div>

        <Bereichewaelen
            :alle_teilnehmer="alle_teilnehmer"
            :alle_bereiche="projekt.bereiche"
            :selection-count="selectionCount"
            :search="search"
        />
    </div>

    <div v-if="false">
    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-3">
        <button @click="openModalCreate" class="btn bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="las la-plus mr-2"></i> Neuer Bereich
        </button>

        <input v-model="search" type="text" placeholder="Suchen ..." class="border p-2 rounded w-1/3" />
    </div>

    <Bereichewaelen :alle_teilnehmer="alle_teilnehmer" :alle_bereiche="projekt.bereiche"
    />
    <!-- Modals -->
    <ModalCreate :visible="isModalCreateOpen" @close="closeModalCreate" @add-bereich="addBereich" />
    <ModalEdit :visible="isModalEditOpen" :toEdit="bereichToEdit" @close="closeModalEdit" @updated="updateBereich" />
    <ModalDestroy v-if="showModalLöschen" :toDelete="bereichToDelete" :seite="'bereich'" @delete="handleDelete" @close="showModalLöschen=false" />
    </div>
</AppLayout>
</template>

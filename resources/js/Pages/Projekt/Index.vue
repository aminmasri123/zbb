<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';

    import axios from 'axios';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Projekt/ModalCreate.vue';
    import ModalEdit from '@/Pages/Projekt/ModalEdit.vue';
    import ModalExportAnwesenheitlisteZeitraum from '@/Pages/Projekt/ModalExportAnwesenheitlisteZeitraum.vue';
    let seite = 'projekt';
    let search = ref('');
    let projektToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let projektToEdit = ref(null);

    // Props
    const props = defineProps({
    projekte: Object,
    abteilungen: Object,
    bereiche: Array,
    kostenstellen: Array
    });

    console.log(props.abteilungen)
    // Lokale Liste
    let localProjekte = ref([...props.projekte.data]);
    let filteredProjekte = ref([...localProjekte.value]);
    let localBereiche = ref([...(props.bereiche || [])]);
    let localKostenstellen = ref([...(props.kostenstellen || [])]);

    // Formatierung für Datum
    const formatDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('de-DE');
    };

    // Modals
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (projekt) => {
    projektToEdit.value = projekt;
    isModalEditOpen.value = true;
    };

    const closeModalEdit = () => { isModalEditOpen.value = false; };


    let isModalExportAnwesenheitlisteOpen = ref(false);
    let projektForExport = ref(null);
    const openModalExportAnwesenheitliste = (projekt) => {
        projektForExport.value = projekt;
        isModalExportAnwesenheitlisteOpen.value = true;
    };



    // CRUD
    const addProjekt = (projekt) => {
    localProjekte.value.unshift(projekt);
    applySearchFilter();
    };

    const updateProjekt = (updatedProjekt) => {
    const index = localProjekte.value.findIndex(p => p.id === updatedProjekt.id);
    if (index !== -1) {
        localProjekte.value[index] = updatedProjekt;
    }
    applySearchFilter();
    };

    const addBereichOption = (bereich) => {
    if (!bereich || localBereiche.value.some(b => b.id === bereich.id)) {
        return;
    }

    localBereiche.value.push(bereich);
    localBereiche.value.sort((a, b) => a.name.localeCompare(b.name));
    };

    const addKostenstelleOption = (kostenstelle) => {
    if (!kostenstelle || localKostenstellen.value.some(k => k.id === kostenstelle.id)) {
        return;
    }

    localKostenstellen.value.push(kostenstelle);
    localKostenstellen.value.sort((a, b) => a.kostenstelle.localeCompare(b.kostenstelle));
    };


    // Delete
    const confirmDelete = (projekt) => {
    projektToDelete.value = { id: projekt.id, name: projekt.name };
    showModalLöschen.value = true;
    };
    const handleDelete = (projektId) => {
    localProjekte.value = localProjekte.value.filter(p => p.id !== projektId);
    applySearchFilter();
    showModalLöschen.value = false;
    };

    // Suche
    const applySearchFilter = () => {
    if (search.value) {
        filteredProjekte.value = localProjekte.value.filter(p =>
        p.name.toLowerCase().includes(search.value.toLowerCase())
        );
    } else {
        filteredProjekte.value = [...localProjekte.value];
    }
    };
    watch([search], () => {
    router.get('/projekt', { search: search.value }, { preserveState: true, replace: true });
    applySearchFilter();
    });
</script>

<template>
  <Head title="Projekte" />

  <app-layout>
    <template  #header>{{$t('Projekte')}}</template>

    <!-- Toolbar -->
    <div class="flex justify-around items-center mb-3">
      <div @click="openModalCreate" class="flex items-center">
        <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </div>
            <input v-model="search" type="text"
             class="border border-gray-300 text-sm p-2.5 w-full"
             placeholder="Suchen ..." />
            <Link :href="route('projekt.index')" class="flex items-center">
                <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
            </Link>
    </div>

    <!-- Tabelle -->
    <div class="w-full overflow-x-auto">
        <table class="min-w-[1350px] w-full text-sm shadow-sm border-collapse">
        <thead class="text-md text-gray-600 uppercase bg-gray-200 sticky top-0">
            <tr>
            <th class="border px-3 py-3 text-left">ID</th>
            <th class="border px-3 py-3 text-left">Projekt</th>
            <th class="border px-3 py-3 text-left">Kostenstellen</th>
            <th class="border px-3 py-3 text-left">Abteilung</th>
            <th class="border px-3 py-3 text-left">Bereiche</th>
            <th class="border px-3 py-3 text-left">Antragsdatum</th>
            <th class="border px-3 py-3 text-left">Starttermin</th>
            <th class="border px-3 py-3 text-left">Anfangsdatum</th>
            <th class="border px-3 py-3 text-left">Endtermin</th>
            <th class="border px-3 py-3 text-left">Enddatum</th>
            <th class="border px-3 py-3 text-center">*</th>
            </tr>
        </thead>

        <tbody>
            <template v-for="projekt in filteredProjekte" :key="projekt.id">
            <tr
                v-for="(zeit, index) in (projekt.zeitraume && projekt.zeitraume.length ? projekt.zeitraume : [null])"
                :key="zeit ? zeit.id : 'empty-' + projekt.id"
                class="bg-white border hover:bg-gray-50"
            >
                <!-- Projekt-Daten nur in der ersten Zeile des Projekts -->
                <td class="border px-6 py-4" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                {{ projekt.id }}
                </td>
                <td class="border px-6 py-4" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                {{ projekt.name }}
                </td>
                <td class="border px-6 py-4" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                <template v-if="projekt.kostenstellen?.length">
                    <span
                        v-for="kostenstelle in projekt.kostenstellen"
                        :key="kostenstelle.id"
                        class="bg-zbb mx-1 p-1 rounded text-white"
                    >
                        {{ kostenstelle.kostenstelle }}
                        <span v-if="kostenstelle.pivot?.gueltig_von || kostenstelle.pivot?.gueltig_bis">
                            ({{ formatDate(kostenstelle.pivot?.gueltig_von) || '?' }} - {{ formatDate(kostenstelle.pivot?.gueltig_bis) || '?' }})
                        </span>
                    </span>
                </template>
                <span v-else>{{ projekt.kostenstelle || '-' }}</span>
                </td>
                <td class="border px-6 py-4" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                {{ projekt.abteilung?.name }}
                </td>
                <td class="border px-6 py-4" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                <div v-if="projekt.bereiche?.length" class="flex flex-wrap gap-1">
                    <span
                        v-for="bereich in projekt.bereiche"
                        :key="bereich.id"
                        class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700"
                    >
                        {{ bereich.name }}
                    </span>
                </div>
                <span v-else>-</span>
                </td>

                <!-- Zeitraum-Daten -->
                <td class="border px-6 py-4">
                {{ formatDate(zeit?.antragsdatum) || '-' }}
                </td>
                <td class="border px-6 py-4">
                {{ formatDate(zeit?.starttermin) || '-' }}
                </td>
                <td class="border px-6 py-4">
                {{ formatDate(zeit?.anfangsdatum) || '-' }}
                </td>
                <td class="border px-6 py-4">
                {{ formatDate(zeit?.endtermin) || '-' }}
                </td>
                <td class="border px-6 py-4">
                {{ formatDate(zeit?.enddatum) || '-' }}
                </td>

                <!-- Dropdown-Menü nur in der ersten Zeile -->
                <td class="border px-6 py-4 text-center" v-if="index === 0" :rowspan="projekt.zeitraume?.length || 1">
                <Dropdown>
                    <template #trigger>
                    <i class="la la-ellipsis-v cursor-pointer"></i>
                    </template>
                    <template #content>
                    <span
                        class="flex justify-between cursor-pointer px-6 items-center hover:bg-gray-100"
                        @click="openModalEdit(projekt)"
                    >
                        Bearbeiten <i class="las la-edit"></i>
                    </span>
                     <span
                        class="flex justify-between cursor-pointer px-6 items-center hover:bg-gray-100"
                        @click="openModalExportAnwesenheitliste(projekt)"
                    >
                        Anwesenheitsliste <i class="las la-edit"></i>
                    </span>
                    <span
                        class="flex justify-between cursor-pointer px-6 items-center hover:bg-gray-100"
                        @click="confirmDelete(projekt)"
                    >
                        Löschen <i class="las la-trash-alt"></i>
                    </span>

                    </template>
                </Dropdown>
                </td>
            </tr>
            </template>
        </tbody>
        </table>
    </div>

    <!-- Modals -->
    <ModalCreate :visible="isModalCreateOpen"
                 :abteilungen="props.abteilungen"
                 :bereiche="localBereiche"
                 :kostenstellen="localKostenstellen"
                 @close="isModalCreateOpen = false"
                 @bereich-created="addBereichOption"
                 @kostenstelle-created="addKostenstelleOption"
                 @added="addProjekt"
                 />
    <ModalEdit :visible="isModalEditOpen"
               :toEdit="projektToEdit"
               :abteilungen="props.abteilungen"
               :bereiche="localBereiche"
               :kostenstellen="localKostenstellen"
               @bereich-created="addBereichOption"
               @kostenstelle-created="addKostenstelleOption"
               @close="closeModalEdit"
               @updated="updateProjekt"/>



    <ModalExportAnwesenheitlisteZeitraum
        :visible="isModalExportAnwesenheitlisteOpen"
        :projekt="projektForExport"
        @close="isModalExportAnwesenheitlisteOpen = false"
    />


    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="projektToDelete"/>
  </app-layout>
</template>

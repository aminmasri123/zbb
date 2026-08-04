<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { computed, ref, defineProps, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
     import ModalCreate from '@/Pages/Fahrten/Fahrtkosten/ModalCreate.vue';
    import ModalEdit from '@/Pages/Fahrten/Fahrtkosten/ModalEdit.vue';
    import { formatDate } from '@/utils/dateFormat.js';
    import { usePermissions } from '@/utils/permissions';


    let seite = 'fahrtkosten';
    let search = ref('');
    let fahrtkostenToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let fahrtkostenToEdit = ref(null);
    const { can } = usePermissions();
    const canCreateFahrtkosten = computed(() => can('fahrtkosten.store'));
    const canUpdateFahrtkosten = computed(() => can('fahrtkosten.update'));
    const canDeleteFahrtkosten = computed(() => can('fahrtkosten.destroy'));
    const canManageFahrtkosten = computed(() => canUpdateFahrtkosten.value || canDeleteFahrtkosten.value);

    // Props
    const props = defineProps({
        fahrtkosten: Object,
        fahrtarten: Array,
    });
    console.log(props.fahrtkosten)
    // Lokale Liste
    let localfahrtkosten = ref([...props.fahrtkosten]);
    let filteredfahrtkosten = ref([...localfahrtkosten.value]);

    // Modals
    const openModalCreate = () => {
        if (!canCreateFahrtkosten.value) return;
        isModalCreateOpen.value = true;
    };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (kosten) => {
        if (!canUpdateFahrtkosten.value) return;
        fahrtkostenToEdit.value = kosten;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addFahrtkosten = (kosten) => {
        if (!canCreateFahrtkosten.value) return;

        localfahrtkosten.value.unshift(kosten);
        applySearchFilter();
    };

    const updateFahrtkosten = (updatedTransportart) => {
        const index = localfahrtkosten.value.findIndex(s => s.id === updatedTransportart.id);
        if (index !== -1) {
            localfahrtkosten.value[index] = updatedTransportart;
        }
        applySearchFilter();
    };

    // Delete
    const confirmDelete = (kosten) => {
        if (!canDeleteFahrtkosten.value) return;

        fahrtkostenToDelete.value = { id: kosten.id, name: kosten.fahrtart.name };
        showModalLöschen.value = true;
    };
    const handleDelete = (transportartId) => {
        localfahrtkosten.value = localfahrtkosten.value.filter(s => s.id !== transportartId);
        applySearchFilter();
        showModalLöschen.value = false;
    };

    // Suche
    const applySearchFilter = () => {
        if (search.value) {
            filteredfahrtkosten.value = localfahrtkosten.value.filter(s =>
                s.fahrtart.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            filteredfahrtkosten.value = [...localfahrtkosten.value];
        }
    };
        watch(search, applySearchFilter);

</script>
<script>
    export default {
    methods: {
        einzigartigeProjekte(userArray) {
        const alleProjekte = userArray.flatMap(user => user.projekte || []);
        const unique = {};
        alleProjekte.forEach(projekt => {
            unique[projekt.id] = projekt;
        });
        return Object.values(unique);
        }
    }
    }

</script>
<template>
  <Head title="Fahrtkosten" />

  <app-layout>
    <template #header>{{$t('Fahrtkosten')}}</template>

    <!-- Toolbar -->
    <div class="flex items-stretch mb-3 overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
      <button
        v-if="canCreateFahrtkosten"
        type="button"
        @click="openModalCreate"
        class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
        title="Fahrtkosten anlegen"
      >
        <i class="la la-plus"></i>
      </button>
      <input v-model="search" type="text"
             class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset"
             placeholder="Suchen ..." />
      <Link
        :href="route('fahrtkosten.index')"
        class="flex w-14 items-center justify-center border-l border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
        title="Aktualisieren"
      >
        <i class="la la-refresh"></i>
      </Link>
    </div>

    <!-- Tabelle -->
    <div class="w-full overflow-x-auto">
      <table class="min-w-[800px] w-full text-sm shadow-sm border-collapse">
        <thead class="text-md text-gray-600 uppercase bg-gray-200 sticky top-0">
          <tr>
            <th class="border px-3 py-3 text-left">{{ $t('ID') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Fahrtarten') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Rechentyp') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Betrag/Prozent') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Gültigkeitszeitraum') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Bemwerkung') }}</th>
            <th v-if="canManageFahrtkosten" class="border px-3 py-3 text-center">*</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="kosten in filteredfahrtkosten"
            :key="kosten.id"
            class="bg-white border hover:bg-gray-50"
          >
            <td class="border px-6 py-4">{{ kosten.id }}</td>
            <td class="border px-6 py-4">
                <p>{{ kosten.fahrtart.name }}</p>
            </td>
            <td class="border px-6 py-4">{{ kosten.rechentyp }}</td>
            <td class="border px-6 py-4">{{ kosten.satz }}</td>
            <td class="border px-6 py-4">{{ formatDate(kosten.gueltig_ab) }} - {{ formatDate(kosten.gueltig_bis) }}</td>
            <td class="border px-6 py-4">{{ kosten.bemerkung }}</td>


            <td v-if="canManageFahrtkosten" class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span
                    v-if="canUpdateFahrtkosten"
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="openModalEdit(kosten)"
                  >
                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                  </span>
                  <span
                    v-if="canDeleteFahrtkosten"
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="confirmDelete(kosten)"
                  >
                    {{ $t('Löschen') }} <i class="las la-trash-alt"></i>
                  </span>
                </template>
              </Dropdown>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modals -->
    <ModalCreate
        v-if="canCreateFahrtkosten"
        :visible="isModalCreateOpen"
        :fahrtarten="props.fahrtarten"
        @close="isModalCreateOpen = false"
        @added="addFahrtkosten"/>


    <ModalEdit v-if="canUpdateFahrtkosten" :visible="isModalEditOpen"
            :toEdit="fahrtkostenToEdit"
            @close="closeModalEdit"
            @updated="updateFahrtkosten"/>

    <template v-if="canDeleteFahrtkosten">
    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="fahrtkostenToDelete"/>
    </template>
  </app-layout>
</template>

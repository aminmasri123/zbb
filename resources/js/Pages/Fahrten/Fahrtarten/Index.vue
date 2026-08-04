<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { computed, ref, defineProps, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Fahrten/Fahrtarten/ModalCreate.vue';
    import ModalEdit from '@/Pages/Fahrten/Fahrtarten/ModalEdit.vue';
    import { usePermissions } from '@/utils/permissions';

    let seite = 'fahrtarten';
    let search = ref('');
    let fahrtartenToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let fahrtartenToEdit = ref(null);
    const { can } = usePermissions();
    const canCreateFahrtarten = computed(() => can('fahrtarten.store'));
    const canUpdateFahrtarten = computed(() => can('fahrtarten.update'));
    const canDeleteFahrtarten = computed(() => can('fahrtarten.destroy'));
    const canManageFahrtarten = computed(() => canUpdateFahrtarten.value || canDeleteFahrtarten.value);

    // Props
    const props = defineProps({
        fahrtarten: Array,
    });
    // Lokale Liste
    let localfahrtarten = ref([...props.fahrtarten]);
    let filteredfahrtarten = ref([...localfahrtarten.value]);

    // Modals
    const openModalCreate = () => {
        if (!canCreateFahrtarten.value) return;
        isModalCreateOpen.value = true;
    };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (fahrtart) => {
        if (!canUpdateFahrtarten.value) return;
        fahrtartenToEdit.value = fahrtart;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addFahrtart = (fahrtart) => {
        if (!canCreateFahrtarten.value) return;

        localfahrtarten.value.unshift(fahrtart);
        applySearchFilter();
    };

    const updateFahrtarten = (updatedFahrtart) => {
        const index = localfahrtarten.value.findIndex(s => s.id === updatedFahrtart.id);
        if (index !== -1) {
            localfahrtarten.value[index] = updatedFahrtart;
        }
        applySearchFilter();
    };

    // Delete
    const confirmDelete = (fahrtart) => {
        if (!canDeleteFahrtarten.value) return;

        fahrtartenToDelete.value = { id: fahrtart.id, name: fahrtart.name };
        showModalLöschen.value = true;
    };
    const handleDelete = (fahrtartId) => {
        localfahrtarten.value = localfahrtarten.value.filter(s => s.id !== fahrtartId);
        applySearchFilter();
        showModalLöschen.value = false;
    };

    // Suche
    const applySearchFilter = () => {
        if (search.value) {
            filteredfahrtarten.value = localfahrtarten.value.filter(s =>
                s.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            filteredfahrtarten.value = [...localfahrtarten.value];
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
  <Head title="Fahrtarten" />

  <app-layout>
    <template #header>{{$t('Fahrtarten')}}</template>

    <!-- Toolbar -->
    <div class="flex items-stretch mb-3 overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
      <button
        v-if="canCreateFahrtarten"
        type="button"
        @click="openModalCreate"
        class="flex w-14 items-center justify-center border-r border-gray-300 text-zbb transition hover:bg-zbb hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-inset"
        title="Fahrtart anlegen"
      >
        <i class="la la-plus"></i>
      </button>
      <input v-model="search" type="text"
             class="min-w-0 flex-1 border-0 text-sm px-3 py-2.5 focus:ring-2 focus:ring-orange-400 focus:ring-inset"
             placeholder="Suchen ..." />
      <Link
        :href="route('fahrtarten.index')"
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
            <th class="border px-3 py-3 text-left">{{ $t('fahrtarten') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Beschreibung') }}</th>
            <th v-if="canManageFahrtarten" class="border px-3 py-3 text-center">*</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="fahrtart in filteredfahrtarten"
            :key="fahrtart.id"
            class="bg-white border hover:bg-gray-50"
          >
            <td class="border px-6 py-4">{{ fahrtart.id }}</td>
            <td class="border px-6 py-4">
                <p>{{ fahrtart.name }}</p>
            </td>
            <td class="border px-6 py-4">{{ fahrtart.beschreibung }}</td>
            <td v-if="canManageFahrtarten" class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span
                    v-if="canUpdateFahrtarten"
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="openModalEdit(fahrtart)"
                  >
                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                  </span>
                  <span
                    v-if="canDeleteFahrtarten"
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="confirmDelete(fahrtart)"
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
        v-if="canCreateFahrtarten"
        :visible="isModalCreateOpen"
        @close="isModalCreateOpen = false"
        @added="addFahrtart"/>


    <ModalEdit v-if="canUpdateFahrtarten" :visible="isModalEditOpen"
            :toEdit="fahrtartenToEdit"
            @close="closeModalEdit"
            @updated="updateFahrtarten"/>

    <template v-if="canDeleteFahrtarten">
    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="fahrtartenToDelete"/>
    </template>
  </app-layout>
</template>

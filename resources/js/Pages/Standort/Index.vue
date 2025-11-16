<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import Swal from 'sweetalert2';
    import { router, Link, Head } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Standort/ModalCreate.vue';
    import ModalEdit from '@/Pages/Standort/ModalEdit.vue';

    let seite = 'standort';
    let search = ref('');
    let standortToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let standortToEdit = ref(null);

    // Props
    const props = defineProps({
        standorte: Object,
    });
    // Lokale Liste
    let localStandorte = ref([...props.standorte]);
    let filteredStandorte = ref([...localStandorte.value]);

    // Formatierung für Datum
    const formatDate = (date) => {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('de-DE');
    };

    // Modals
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (standort) => {
        standortToEdit.value = standort;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addStandort = (standort) => {
        localStandorte.value.unshift(standort);
        applySearchFilter();
    };

    const updateStandort = (updatedStandort) => {
        const index = localStandorte.value.findIndex(s => s.id === updatedStandort.id);
        if (index !== -1) {
            localStandorte.value[index] = updatedStandort;
        }
        applySearchFilter();
    };

    // Delete
    const confirmDelete = (standort) => {
        standortToDelete.value = { id: standort.id, name: standort.name };
        showModalLöschen.value = true;
    };
    const handleDelete = (standortId) => {
        localStandorte.value = localStandorte.value.filter(s => s.id !== standortId);
        applySearchFilter();
        showModalLöschen.value = false;
    };

    // Suche
    const applySearchFilter = () => {
        if (search.value) {
            filteredStandorte.value = localStandorte.value.filter(s =>
                s.name.toLowerCase().includes(search.value.toLowerCase())
            );
        } else {
            filteredStandorte.value = [...localStandorte.value];
        }
    };
    watch([search], () => {
        router.get('/standort', { search: search.value }, { preserveState: true, replace: true });
        applySearchFilter();
    });
</script>
<script>
    export default {
    methods: {
    einzigartigeProjekte(userArray) {
      if (!Array.isArray(userArray)) return []; // 🧠 Schutz: Kein Array → leere Liste
      const alleProjekte = userArray.flatMap(user => user.projekte || []);
      const unique = {};
      alleProjekte.forEach(projekt => {
        if (projekt?.id) {
          unique[projekt.id] = projekt;
        }
      });
      return Object.values(unique);
    }
  }
    }
</script>
<template>
  <Head title="Standorte" />

  <app-layout>
    <template #header>{{$t('Standorte')}}</template>

    <!-- Toolbar -->
    <div class="flex justify-around items-center mb-3">
      <div @click="openModalCreate" class="flex items-center">
        <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </div>
      <input v-model="search" type="text"
             class="border border-gray-300 text-sm p-2.5 w-full"
             placeholder="Suchen ..." />
      <Link :href="route('standort.index')" class="flex items-center">
        <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </Link>
    </div>

    <!-- Tabelle -->
    <div class="w-full ">
      <table class="min-w-[800px] w-full text-sm shadow-sm border-collapse">
        <thead class="text-md text-gray-600 uppercase bg-gray-200 sticky top-0">
          <tr>
            <th class="border px-3 py-3 text-left">{{ $t('ID') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Standorte') }}</th>
            <th class="border px-3 py-3 text-left">{{ $t('Beschreibung') }}</th>
            <th class="border px-3 py-3 text-center">*</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="standort in filteredStandorte"
            :key="standort.id"
            class="bg-white border hover:bg-gray-50"
          >
            <td class="border px-6 py-4">{{ standort.id }}</td>
            <td class="border px-6 py-4">
                <p>{{ standort.name }}</p>
                <span
                    v-for="projekt in einzigartigeProjekte(standort.personen)"
                    :key="projekt.id"
                    class="inline-block bg-gray-200 text-xs text-gray-800 rounded px-2 py-1 mr-1"
                >
                    {{ projekt.name }}
                </span>
            </td>
            <td class="border px-6 py-4">{{ standort.beschreibung }}</td>
            <td class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="openModalEdit(standort)"
                  >
                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                  </span>
                  <span
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="confirmDelete(standort)"
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
        :visible="isModalCreateOpen"
        @close="isModalCreateOpen = false"
        @added="addStandort"/>


    <ModalEdit :visible="isModalEditOpen"
            :toEdit="standortToEdit"
            @close="closeModalEdit"
            @updated="updateStandort"/>

    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="standortToDelete"/>
  </app-layout>
</template>

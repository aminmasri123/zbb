<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
    import ModalCreate from '@/Pages/Fahrten/Fahrtarten/ModalCreate.vue';
    import ModalEdit from '@/Pages/Fahrten/Fahrtarten/ModalEdit.vue';

    let seite = 'fahrtarten';
    let search = ref('');
    let fahrtartenToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let fahrtartenToEdit = ref(null);

    // Props
    const props = defineProps({
        fahrtarten: Array,
    });
    // Lokale Liste
    let localfahrtarten = ref([...props.fahrtarten]);
    let filteredfahrtarten = ref([...localfahrtarten.value]);

    // Modals
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (fahrtart) => {
        fahrtartenToEdit.value = fahrtart;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addFahrtart = (fahrtart) => {
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
    <div class="flex justify-around items-center mb-3">
      <div @click="openModalCreate" class="flex items-center">
        <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </div>
      <input v-model="search" type="text"
             class="border border-gray-300 text-sm p-2.5 w-full"
             placeholder="Suchen ..." />
      <Link :href="route('fahrtarten.index')" class="flex items-center">
        <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
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
            <th class="border px-3 py-3 text-center">*</th>
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
            <td class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="openModalEdit(fahrtart)"
                  >
                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                  </span>
                  <span
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
        :visible="isModalCreateOpen"
        @close="isModalCreateOpen = false"
        @added="addFahrtart"/>


    <ModalEdit :visible="isModalEditOpen"
            :toEdit="fahrtartenToEdit"
            @close="closeModalEdit"
            @updated="updateFahrtarten"/>

    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="fahrtartenToDelete"/>
  </app-layout>
</template>

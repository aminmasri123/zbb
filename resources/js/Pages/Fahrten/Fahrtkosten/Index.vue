<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps, watch } from 'vue';
    import { router, Link, Head } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';
     import ModalCreate from '@/Pages/Fahrten/Fahrtkosten/ModalCreate.vue';
    import ModalEdit from '@/Pages/Fahrten/Fahrtkosten/ModalEdit.vue';
    import { formatDate } from '@/utils/dateFormat.js';


    let seite = 'fahrtkosten';
    let search = ref('');
    let fahrtkostenToDelete = ref(null);
    let showModalLöschen = ref(false);
    let isModalCreateOpen = ref(false);
    let isModalEditOpen = ref(false);
    let fahrtkostenToEdit = ref(null);

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
    const openModalCreate = () => { isModalCreateOpen.value = true; };
    const closeModalCreate = () => { isModalCreateOpen.value = false; };

    const openModalEdit = (kosten) => {
        fahrtkostenToEdit.value = kosten;
        isModalEditOpen.value = true;
    };
    const closeModalEdit = () => { isModalEditOpen.value = false; };

    // CRUD
    const addFahrtkosten = (kosten) => {
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
    <div class="flex justify-around items-center mb-3">
      <div @click="openModalCreate" class="flex items-center">
        <i class="la la-plus bg-white border border-gray-300 rounded-l-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
      </div>
      <input v-model="search" type="text"
             class="border border-gray-300 text-sm p-2.5 w-full"
             placeholder="Suchen ..." />
      <Link :href="route('fahrtkosten.index')" class="flex items-center">
        <i class="la la-refresh bg-white border border-gray-300 rounded-r-md px-5 py-3 text-zbb hover:text-white hover:bg-zbb hover:border hover:border-orange-500"></i>
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
            <th class="border px-3 py-3 text-center">*</th>
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


            <td class="border px-6 py-4 text-center">
              <Dropdown>
                <template #trigger>
                  <i class="la la-ellipsis-v cursor-pointer"></i>
                </template>
                <template #content>
                  <span
                    class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"
                    @click="openModalEdit(kosten)"
                  >
                    {{ $t('Bearbeiten') }} <i class="las la-edit"></i>
                  </span>
                  <span
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
        :visible="isModalCreateOpen"
        :fahrtarten="props.fahrtarten"
        @close="isModalCreateOpen = false"
        @added="addFahrtkosten"/>


    <ModalEdit :visible="isModalEditOpen"
            :toEdit="fahrtkostenToEdit"
            @close="closeModalEdit"
            @updated="updateFahrtkosten"/>

    <ModalDestroy v-if="showModalLöschen"
                  @delete="handleDelete"
                  @close="showModalLöschen = false"
                  :seite="seite"
                  :toDelete="fahrtkostenToDelete"/>
  </app-layout>
</template>

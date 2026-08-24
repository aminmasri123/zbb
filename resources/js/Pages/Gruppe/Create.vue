<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalCreate from '@/Pages/Gruppe/ModalCreate.vue'

defineProps({
  projekt: {
    type: Object,
    required: true,
  },
  betreuer: {
    type: [Array, Object],
    required: true,
  },
})

const backToOverview = () => {
  router.visit(route('gruppe.index'), { replace: true })
}
</script>

<template>
  <Head title="Gruppe anlegen" />

  <AppLayout>
    <template #header>{{ $t('Gruppe anlegen') }}</template>

    <div class="mx-auto w-full max-w-7xl rounded-2xl bg-white p-6 shadow-md">
      <p class="text-gray-600">Legen Sie eine neue Gruppe für das aktive Projekt an.</p>
      <Link
        :href="route('gruppe.index')"
        class="mt-4 inline-flex rounded-md border border-zbb px-4 py-2 text-sm font-medium text-zbb transition hover:bg-zbb hover:text-white"
      >
        Zur Gruppenübersicht
      </Link>
    </div>

    <ModalCreate
      :visible="true"
      :projekt="projekt"
      :betreuer="betreuer"
      @close="backToOverview"
    />
  </AppLayout>
</template>

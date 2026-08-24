<script setup>
import { Head, router } from '@inertiajs/vue3'
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

    <div class="mx-auto w-full max-w-4xl">
      <ModalCreate
        standalone
        :projekt="projekt"
        :betreuer="betreuer"
        @close="backToOverview"
      />
    </div>
  </AppLayout>
</template>

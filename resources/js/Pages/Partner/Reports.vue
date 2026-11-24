<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ParticipantAreaReport from './BOP/ParticipantAreaReport.vue';

defineProps({ report: Object });
</script>

<template>
    <Head title="BOP-Berichte" />
    <AppLayout>
        <template #header>BOP-Berichte</template>
        <div class="mx-auto w-full max-w-7xl space-y-4">
            <Link :href="route('partner.index')" class="inline-flex items-center gap-2 rounded-md border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="la la-arrow-left" aria-hidden="true"></i>
                Zur Partnerübersicht
            </Link>
            <ParticipantAreaReport :report="report" />
        </div>
    </AppLayout>
</template>

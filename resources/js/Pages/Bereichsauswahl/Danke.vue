<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    context: Object,
});
</script>

<template>
    <Head title="Danke" />

    <main class="min-h-screen bg-gray-100 text-gray-900">
        <div class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-10">
            <section class="w-full bg-white border border-gray-300 shadow-sm p-6 text-center sm:p-10">
                <img src="/img/logo/logo.png" alt="ZBB" class="mx-auto mb-6 h-14 w-auto" />

                <h1 class="text-2xl font-bold text-gray-900">
                    Danke für die Bereichsauswahl
                </h1>

                <p class="mt-3 text-sm text-gray-600">
                    Deine Auswahl wurde erfolgreich gespeichert.
                </p>

                <p v-if="context?.schule" class="mt-6 text-xs text-gray-500">
                    {{ context.schule }} · {{ context.schuljahr }} · Teil {{ context.teil }}
                </p>
            </section>
        </div>
    </main>
</template>

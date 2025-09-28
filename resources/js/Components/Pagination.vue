<template>
    <div v-if="pagination.last_page > 2" class="mt-6 py-5 text-sm  text-black">
            <div class="flex flex-row md:justify-center">
                <Link
                    v-for="link in pagination.links"
                    :key="link.label"
                    :class="{
                        'text-gray-500': !link.active || !link.url, // Grau für inaktive oder nicht klickbare Links
                        'text-zbb font-bold': link.active && link.url, // Formatierung für aktive Links
                        'cursor-not-allowed': !link.url || link.active
                    }"
                    :href="link.url && !link.active ? link.url : 'javascript:void(0)'"
                    @click="(!link.url || link.active) && $event.preventDefault()"
                    class="px-3 py-1 mx-1 border rounded"
                    >
                    <span>{{ isNaN(link.label) ? $t(link.label) : link.label }}</span>
                </Link>
            </div>
        </div>
</template>
<script setup>
import { router, Link } from '@inertiajs/vue3';

defineProps({
    pagination: Object,
    users: {
        type: Object,
        default: () => ({ data: [], links: [] })
    }
});
// Method to handle page navigation
const goToPage = (url) => {
    if (url) {
        router.get(url);
    }
};
</script>

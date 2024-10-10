<template>
    <div class="mt-6 flex justify-center p-5 m-3  text-black">
                <Link
                    v-for="link in pagination.links"
                    :key="link.label"
                    :href="link.url || '#'"

                    :class="{ 'text-gray-500': !link.active, 'text-orange-500 font-bold': link.active }"
                    @click.prevent="goToPage(link.url)"
                    class="px-3 py-1 mx-1 border rounded"
                >
                    <!-- Label-Anpassung -->

                    <span v-html="link.label"></span>
                </Link>
            </div>
</template>
<script setup>
import { ref, watch } from 'vue';
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

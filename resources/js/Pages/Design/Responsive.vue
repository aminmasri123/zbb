
<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3'
import { reactive } from 'vue'

export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },
};
</script>



<template>
    <app-layout>

        <div class="grid grid-col-2 gap-4">
            <div class="bg-orange-200 mx-10 p-10  ">No Prefix - [0-639px]</div>
            <div class="bg-orange-200 mx-10 hidden  sm:block p-10">SM - [639 - 768px]</div>
            <div class="bg-orange-200 mx-10 hidden md:block p-10">MD - [768 - 1023px]</div>
            <div class="bg-orange-200 mx-10 hidden lg:block  p-10">LG - [1024 - 1279px]</div>
            <div class="bg-orange-200 mx-10 hidden xl:block p-10">XL - [1279 - 00px]</div>
        </div>

    </app-layout>
</template>


<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-buttonPrimary border border-transparent rounded-md font-semibold text-xs text-buttonTextPrimary uppercase tracking-widest hover:bg-buttonPrimaryHover focus:bg-buttonPrimaryHover active:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2 transition ease-in-out duration-150">
        <slot />
    </button>
</template>

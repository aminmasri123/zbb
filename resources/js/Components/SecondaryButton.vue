<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-card border border-border rounded-md font-semibold text-xs text-buttonTextSecondary uppercase tracking-widest shadow-sm hover:bg-muted focus:outline-none focus:ring-2 focus:ring-borderHover focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: Array,
        default: () => ['bg-white'],
    },
});

let open = ref(false);
const triggerElement = ref(null);
const dropdownElement = ref(null);
const dropdownStyle = ref({
    top: '0px',
    left: '0px',
    visibility: 'hidden',
});

const viewportPadding = 8;

const close = () => {
    open.value = false;
};

const updatePosition = () => {
    if (!open.value || !triggerElement.value || !dropdownElement.value) {
        return;
    }

    const triggerRect = triggerElement.value.getBoundingClientRect();
    const dropdownWidth = dropdownElement.value.offsetWidth;
    const dropdownHeight = dropdownElement.value.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = triggerRect.left + (triggerRect.width / 2) - (dropdownWidth / 2);
    let originX = 'center';

    if (props.align === 'left') {
        left = triggerRect.left;
        originX = 'left';
    }

    if (props.align === 'right') {
        left = triggerRect.right - dropdownWidth;
        originX = 'right';
    }

    left = Math.min(
        Math.max(viewportPadding, left),
        Math.max(viewportPadding, viewportWidth - dropdownWidth - viewportPadding),
    );

    const spaceBelow = viewportHeight - triggerRect.bottom;
    const spaceAbove = triggerRect.top;
    const opensUp = spaceBelow < dropdownHeight + viewportPadding && spaceAbove > spaceBelow;

    let top = opensUp
        ? triggerRect.top - dropdownHeight - viewportPadding
        : triggerRect.bottom + viewportPadding;

    top = Math.min(
        Math.max(viewportPadding, top),
        Math.max(viewportPadding, viewportHeight - dropdownHeight - viewportPadding),
    );

    dropdownStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
        visibility: 'visible',
        transformOrigin: `${originX} ${opensUp ? 'bottom' : 'top'}`,
    };
};

const toggleOpen = () => {
    if (!open.value) {
        dropdownStyle.value = {
            ...dropdownStyle.value,
            visibility: 'hidden',
        };
    }

    open.value = !open.value;
};

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        close();
    }
};

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        updatePosition();
    }
});

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', updatePosition, true);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', updatePosition);
    window.removeEventListener('scroll', updatePosition, true);
});

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '60': 'w-60',
        '80': 'w-80'
    }[props.width.toString()] || 'w-48';
});
</script>

<template>
    <div ref="triggerElement" class="relative">
        <div @click="toggleOpen">
            <slot name="trigger" />
        </div>

        <Teleport to="body">
            <!-- Full Screen Dropdown Overlay -->
            <div v-show="open" class="fixed inset-0 z-40" @click="close" />

            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
            >
                <div
                    v-show="open"
                    ref="dropdownElement"
                    class="fixed z-50 rounded-md shadow-lg"
                    :class="widthClass"
                    :style="dropdownStyle"
                    @click="close"
                >
                    <div class="rounded-md ring-1 ring-black ring-opacity-5" :class="contentClasses">
                        <slot name="content" />
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

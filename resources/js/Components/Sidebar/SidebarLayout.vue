<template>
    <div
        :class="{'block ': sidebarOpen, 'hidden ': !sidebarOpen, 'md:block': true}"
        class="theme-sidebar fixed inset-x-0 bottom-0 top-16 z-40 w-full overflow-hidden transition duration-300 sm:top-20 md:static md:z-auto md:min-h-[calc(100vh-5rem)] md:w-auto md:max-w-xs md:self-stretch md:overflow-visible"
    >
            <div class="h-full overflow-y-auto overscroll-contain md:sticky md:top-20 md:h-auto md:max-h-[calc(100vh-5rem)]">
                <div id="sidebar-menu " class="p-4">
                    <ul class="space-y-4 pl-0">
                        <slot/>
                    </ul>
                </div>
            </div>
        </div>

</template>


<script>
    import { ref } from 'vue';

const sidebarTextHidden = ref(true);

export default {
    props: {
    sidebarOpen: Boolean, // Offen oder geschlossen
    displayHideTextSidebar: Boolean,

  },

  }



</script>

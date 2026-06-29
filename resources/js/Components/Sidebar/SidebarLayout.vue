<template>
    <div :class="{'block ': sidebarOpen, 'hidden ': !sidebarOpen, 'md:block': true}" class="theme-sidebar absolute w-full sm:w-auto md:relative z-40 transition duration-300">
            <div class="overflow-y-auto">
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

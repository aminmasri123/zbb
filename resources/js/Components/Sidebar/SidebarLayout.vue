<template>
    <div :class="{'block': sidebarOpen, 'hidden w-64': !sidebarOpen, 'md:block': true}" class=" absolute  w-full md:w-64 md:relative min-h-svh  xl:min-h-[42rem] bg-gray-800 z-40 transition duration-300">
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

export default {
    props: {
    sidebarOpen: Boolean, // Offen oder geschlossen

  },

  }



</script>

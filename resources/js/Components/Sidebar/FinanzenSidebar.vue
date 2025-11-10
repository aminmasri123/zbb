    <template  >
        <SidebarLayout>
            <!-- Übersicht -->
            <li v-if="!displayHideTextSidebar"  class="text-white text-sm font-bold uppercase">
                <span>{{$t('übersicht')}}</span>
            </li>
            <!-- Dashboard Submenu -->
            <li v-if="['dashboard.index'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('dashboard')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-dashboard la-lg mr-2"></i>
                    <span  v-if="!displayHideTextSidebar" :class="{'pr-16': !displayHideTextSidebar === true}">{{$t('dashboard')}}</span>
                    <span  :class="{'rotate-180': activeMenu === 'dashboard', 'hidden': displayHideTextSidebar === true}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'dashboard'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('dashboard.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('übersicht')}}</Link></li>
                </ul>
            </li>

            <!-- Fahrten Submenu -->
                <li v-if="['fahrtarten.index', 'fahrtarten.store', 'fahrtarten.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                    <a href="#" @click.prevent="toggleMenu('fahrten')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                        <i class="las la-road la-lg mr-2"></i>
                        <span v-if="!displayHideTextSidebar" >{{$t('Fahrten')}}</span>
                        <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'fahrten', 'text-zbb': $page.component.startsWith('fahrten')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                    </a>
                    <ul v-show="activeMenu === 'fahrten'" class="pl-6 mt-2 space-y-2">
                        <li v-if="$page.props.permissions.includes('fahrtarten.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('fahrtarten.index')">{{$t('Fahrtarten')}}</Link></li>
                        <li v-if="$page.props.permissions.includes('fahrtkosten.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('fahrtkosten.index')">{{$t('Fahrtkosten')}}</Link></li>

                    </ul>
                </li>


            <!-- Drucker Submenu -->
                <li v-if="['printing.index', 'printing.store', 'printing.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                    <a href="#" @click.prevent="toggleMenu('print')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                        <i class="las la-print la-lg mr-2"></i>
                        <span v-if="!displayHideTextSidebar" >{{$t('Druck')}}</span>
                        <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'print', 'text-zbb': $page.component.startsWith('print')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                    </a>
                    <ul v-show="activeMenu === 'print'" class="pl-6 mt-2 space-y-2">
                        <li v-if="$page.props.permissions.includes('printing.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('fahrtarten.index')">{{$t('Druckübersicht')}}</Link></li>
                    </ul>
                </li>

            <!-- Bestellungen Submenu -->
            <li v-if="['bereich.index', 'bereich.store', 'bereich.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('bereich')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-shopping-cart la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('Bestellungen')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'bereich', 'text-zbb': $page.component.startsWith('Bereich')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'bereich'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('bereich.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('bereich.index')">{{$t('Bereichübersicht')}}</Link></li>
                </ul>
            </li>

            <!-- Weitere Menüpunkte -->
        </SidebarLayout>

  </template>

<script setup>
    import { Head, Link, router } from '@inertiajs/vue3';
    import SidebarLayout from '../Sidebar/SidebarLayout.vue';

</script>
<script>
    export default {
        props: {
        activeMenu: String, // Welches Menü ist aktiv
        toggleMenu: Function, // Funktion, um Menüs umzuschalten
        roles: Array, // Rollen vom Backend
        permissions: Array, // Berechtigungen vom Backend
        displayHideTextSidebar: Boolean,
        },
    }
</script>

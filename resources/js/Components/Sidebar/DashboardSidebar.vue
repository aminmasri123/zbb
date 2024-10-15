    <template  >
        <SidebarLayout>
            <!-- Übersicht -->
            <li v-if="!displayHideTextSidebar"  class="text-white text-sm font-bold uppercase">
                <span>{{$t('übersicht')}}</span>
            </li>
            <!-- Dashboard Submenu -->
            <li v-if="['dashboard.index'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('dashboard')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                    <i class="la la-dashboard la-lg mr-2"></i>

                    <span  v-if="!displayHideTextSidebar" :class="{'pr-16': !displayHideTextSidebar === true}">{{$t('dashboard')}}</span>
                    <span  :class="{'rotate-180': activeMenu === 'dashboard', 'hidden': displayHideTextSidebar === true}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'dashboard'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('dashboard.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('übersicht')}}</Link></li>
                </ul>
            </li>

            <!-- Benutzer Submenu -->
            <li v-if="['benutzer.index', 'benutzer.store'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('benutzer')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-user mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('team')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'benutzer', 'hidden': displayHideTextSidebar === true}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'benutzer'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('benutzer.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">{{$t('teamübersicht')}}</Link></li>
                    <li v-if="$page.props.permissions.includes('benutzer.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">{{$t('team_anlegen')}}</Link></li>
                </ul>
            </li>


             <!-- Kooperationspartner Submenu -->
             <li v-if="['kooperationspartner.index', 'kooperationspartner.store', 'kooperationspartner.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('kooperationspartner')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-building mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('partner')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'kooperationspartner'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'kooperationspartner'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('kooperationspartner.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('benutzerübersicht')}}</Link></li>
                </ul>
            </li>

             <!-- Berechtigung Submenu -->
             <li v-if="['berechtigung.index', 'berechtigung.store', 'berechtigung.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('berechtigung')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-unlock mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('berechtigung')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'berechtigung'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'berechtigung'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('berechtigung.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('berechtigung.index')">{{$t('berechtigungsübersicht')}}</Link></li>
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

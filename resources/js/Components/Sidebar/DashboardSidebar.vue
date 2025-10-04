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
                    <span  :class="{'rotate-180': activeMenu === 'dashboard', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('Dashboard')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'dashboard'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('dashboard.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('übersicht')}}</Link></li>
                </ul>
            </li>

            <!-- Benutzer Submenu -->
            <li v-if="['benutzer.index', 'benutzer.store'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('benutzer')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-user mr-2"></i>
                    <span v-if="!displayHideTextSidebar">{{$t('team')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'benutzer', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('User')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'benutzer'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('benutzer.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">{{$t('teamübersicht')}}</Link></li>
                    <li v-if="$page.props.permissions.includes('benutzer.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">{{$t('team_anlegen')}}</Link></li>
                </ul>
            </li>


             <!-- Kooperationspartner Submenu -->
             <li v-if="['kooperationspartner.index', 'kooperationspartner.store', 'kooperationspartner.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('kooperationspartner')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-building mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('partner')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'kooperationspartner', 'text-zbb': $page.component.startsWith('Partner')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'kooperationspartner'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('kooperationspartner.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('benutzerübersicht')}}</Link></li>
                </ul>
            </li>






            <!-- Standort Submenu -->
            <li v-if="['standort.index', 'standort.store', 'standort.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('standort')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-map-marker mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('Standorte')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'standort', 'text-zbb': $page.component.startsWith('Standort')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'standort'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('standort.index')">
                        <Link class="text-gray-400 hover:text-white transition duration-200" :href="route('standort.index')">{{$t('Standortübersicht')}}</Link></li>
                </ul>
            </li>


            <!-- Abteilung Submenu -->
            <li v-if="['abteilung.index', 'abteilung.store', 'abteilung.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('abteilung')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-unlock mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('abteilungen')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'abteilung', 'text-zbb': $page.component.startsWith('Abteilung')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'abteilung'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('abteilung.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('abteilung.index')">{{$t('berechtigungsübersicht')}}</Link></li>
                </ul>
            </li>
            <!-- Projekt Submenu -->
            <li v-if="['projekt.index', 'projekt.store', 'projekt.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('projekt')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-project-diagram mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('projekte')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'projekt', 'text-zbb': $page.component.startsWith('Projekt')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'projekt'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('projekt.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('projekt.index')">{{$t('berechtigungsübersicht')}}</Link></li>
                </ul>
            </li>
            <!-- Bereich Submenu -->
            <li v-if="['bereich.index', 'bereich.store', 'bereich.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('bereich')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-braille mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('Bereiche')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'bereich', 'text-zbb': $page.component.startsWith('Bereich')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'bereich'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('bereich.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('bereich.index')">{{$t('Bereichübersicht')}}</Link></li>
                </ul>
            </li>
            <!-- Teilnehmer Submenu -->
            <li v-if="$page.props.roles.includes('Administrator')" class="submenu" >
                <a href="#" @click.prevent="toggleMenu('teilnehmer')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="las la-user-graduate la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" class="pr-16">{{$t('Teilnehmer')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'teilnehmer'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'teilnehmer'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('teilnehmer.index')">{{$t('Teilnehmerübersicht')}}</Link></li>
                </ul>
                <ul v-show="activeMenu === 'teilnehmer'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('teilnehmer.create')">{{$t('Teilnehmer anlegen')}}</Link></li>
                </ul>
            </li>
            <!-- Berechtigung Submenu -->
             <li v-if="['berechtigung.index', 'berechtigung.store', 'berechtigung.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('berechtigung')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-unlock mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('Berechtigungen')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'berechtigung', 'text-zbb': $page.component.startsWith('Einstellung')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
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


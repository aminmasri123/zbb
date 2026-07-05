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

            <!-- Team Apps Submenu -->
            <li v-if="['dashboard.index'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('teamApps')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-th-large la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar">Apps</span>
                    <span :class="{'rotate-180': activeMenu === 'teamApps', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('Apps')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'teamApps'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.index')">Apps</Link></li>
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.calendar')">Kalender</Link></li>
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.contacts')">Kontakte</Link></li>
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.files')">Dateimanager</Link></li>
                    <li v-if="$page.props.permissions.includes('teilnehmer.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('teilnehmer.index')">Teilnehmer</Link></li>
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.tasks')">Taskmanager</Link></li>
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('apps.popups')">Popups</Link></li>
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
                    <li v-if="$page.props.permissions.includes('kooperationspartner.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard.partner.index')">{{$t('benutzerübersicht')}}</Link></li>
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

            <!-- Gruppe Submenu -->
            <li v-if="['teilnehmer.index', 'teilnehmer.store'].some(permission => $page.props.permissions.includes(permission))" class="submenu" >
                <a href="#" @click.prevent="toggleMenu('gruppe')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="las la-cookie la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" class="pr-16">{{$t('Gruppe')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'gruppe'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'gruppe'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('gruppe.index')">{{$t('Gruppenübersicht')}}</Link></li>
                </ul>
                <ul v-show="activeMenu === 'gruppe'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('gruppe.index')">{{$t('Gruppe anlegen')}}</Link></li>
                </ul>
            </li>

            <!-- Klassenbuch Submenu -->
            <li v-if="$page.props.currentProjekt?.klassenbuch_aktiv && ['gruppe.index', 'gruppe.view.all', 'teilnehmer.index'].some(permission => $page.props.permissions.includes(permission))" class="submenu" >
                <a href="#" @click.prevent="toggleMenu('klassenbuch')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="las la-book-open la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" class="pr-16">Klassenbuch</span>
                    <span :class="{'rotate-180': activeMenu === 'klassenbuch', 'text-zbb': $page.component.startsWith('Klassenbuch')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'klassenbuch'" class="pl-6 mt-2 space-y-2">
                    <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('klassenbuch.index')">Uebersicht</Link></li>
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
                    <li v-if="$page.props.permissions.includes('teilnehmer.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('teilnehmer.index')">{{$t('Teilnehmerübersicht')}}</Link></li>
                </ul>
                <ul v-show="activeMenu === 'teilnehmer'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('teilnehmer.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('teilnehmer.create')">{{$t('Teilnehmer anlegen')}}</Link></li>
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



             <li v-if="['materialanforderung.index', 'materialanforderung.store', 'materialanforderung.update'].some(permission => $page.props.permissions.includes(permission))" class="submenu">
                <a href="#" @click.prevent="toggleMenu('materialanforderung')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="las la-shopping-bag mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('Materialanforderung')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'materialanforderung', 'text-zbb': $page.component.startsWith('Einstellung')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'materialanforderung'" class="pl-6 mt-2 space-y-2">
                    <li v-if="$page.props.permissions.includes('materialanforderung.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('materialanforderung.index')">{{$t('Bestellungen')}}</Link></li>
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

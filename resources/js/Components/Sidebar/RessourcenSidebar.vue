    <template  >
        <SidebarLayout>
            <!-- Übersicht -->
            <li v-if="!displayHideTextSidebar"  class="text-white text-sm font-bold uppercase">
                <span>{{$t('übersicht')}}</span>
            </li>
            <!-- Dashboard Submenu -->
            <li v-if="can('dashboard.index')" class="submenu">
                <a href="#" @click.prevent="toggleMenu('dashboard')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-dashboard la-lg mr-2"></i>
                    <span  v-if="!displayHideTextSidebar" :class="{'pr-16': !displayHideTextSidebar === true}">{{$t('dashboard')}}</span>
                    <span  :class="{'rotate-180': activeMenu === 'dashboard', 'hidden': displayHideTextSidebar === true}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'dashboard'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('dashboard.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">{{$t('übersicht')}}</Link></li>
                </ul>
            </li>

               <!-- Personal Submenu -->
            <li v-if="canAny(['personal.index', 'personal.store'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('personal')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-user mr-2"></i>
                    <span v-if="!displayHideTextSidebar">{{$t('Personal')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'personal', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('Personal')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'personal'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('personal.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('personal.index')">{{$t('Personalübersicht')}}</Link></li>
                    <li v-if="can('personal.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('personal.index')">{{$t('Personal anlegen')}}</Link></li>
                </ul>
            </li>

            <!-- Dienstwagen Submenu -->
            <li v-if="moduleEnabled('vehicle_management') && canAny(['dienstwagen.index', 'dienstwagen.store', 'dienstwagen.fahrtenbuch.index', 'dienstwagen.wartung.index', 'dienstwagen.buchungen.index', 'dienstwagen.meldungen.index', 'dienstwagen.reports.index'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('dienstwagen')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-car la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar">{{$t('Dienstwagen')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'dienstwagen', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('dienstwagen')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'dienstwagen'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('dienstwagen.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.index')">{{$t('Dienstwagenübersicht')}}</Link></li>
                    <li v-if="can('dienstwagen.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.index')">{{$t('Dienstwagen anlegen')}}</Link></li>
                    <li v-if="can('dienstwagen.buchungen.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.buchungen.index')">Buchungen</Link></li>
                    <li v-if="can('dienstwagen.meldungen.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.meldungen.index')">Meldungen</Link></li>
                    <li v-if="can('dienstwagen.fahrtenbuch.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.fahrtenbuch.index')">{{$t('Fahrtenbuch')}}</Link></li>
                    <li v-if="can('dienstwagen.wartung.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.wartung.index')">{{$t('Wartungen')}}</Link></li>
                    <li v-if="can('dienstwagen.reports.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dienstwagen.reports.index')">{{$t('Reports')}}</Link></li>
                </ul>
            </li>


            <!-- Räumlichkeiten Submenu -->
            <li v-if="moduleEnabled('room_management') && canAny(['raeumlichkeiten.index', 'raeumlichkeiten.store'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('räumlichkeiten')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="las la-door-open la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar">{{$t('Räumlichkeiten')}}</span>
                    <span :class="{'rotate-180': activeMenu === 'benutzer', 'hidden': displayHideTextSidebar === true, 'text-zbb': $page.component.startsWith('User')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'räumlichkeiten'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('raeumlichkeiten.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('raeumlichkeiten.index')">{{$t('Raumübersicht')}}</Link></li>
                    <li v-if="can('raeumlichkeiten.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('raeumlichkeiten.index')">{{$t('Raum anlegen')}}</Link></li>
                </ul>
            </li>


             <!-- Geräte Submenu -->
             <li v-if="moduleEnabled('it_management') && canAny(['geraet.index', 'geraet.store', 'geraet.update', 'geraet.ausgabe.index', 'geraet.rueckgabe.index'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('geraet')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-desktop la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('Geräte')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'geraet', 'text-zbb': $page.component.startsWith('Geraet')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'geraet'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('geraet.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('geraet.index')">{{$t('Geräteübersicht')}}</Link></li>
                    <li v-if="can('geraet.ausgabe.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('geraet.ausgabe.index')">{{$t('Ausgabe')}}</Link></li>
                    <li v-if="can('geraet.rueckgabe.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('geraet.rueckgabe.index')">{{$t('Rückgabe')}}</Link></li>

                    <li v-if="can('geraet.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('geraet.store')">{{$t('Gerät anlegen')}}</Link></li>
                </ul>
            </li>

            <!-- Lager Submenu -->
            <li v-if="moduleEnabled('warehouse_management') && canAny(['lager.index', 'lager.artikel.store', 'lager.bewegung.store', 'lager.reservierung.store'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('lager')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-warehouse la-lg mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('Lager')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'lager', 'text-zbb': $page.component.startsWith('Lager')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'lager'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('lager.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('lager.index')">Lagerübersicht</Link></li>
                </ul>
            </li>
            <!-- IT-Service Submenu -->
            <li v-if="moduleEnabled('it_management') && canAny(['it.service.index', 'it.ticket.store', 'it.ticket.update', 'it.geraet.store', 'it.geraet.update'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('it-service')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-lg la-headset mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('IT-Service')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'it-service', 'text-zbb': $page.component.startsWith('ITService')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'it-service'" class="pl-6 mt-2 space-y-2">
                    <li v-if="canAny(['it.service.index', 'it.ticket.update'])"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('it-service.index')">Tickets und Geräte</Link></li>
                    <li v-if="can('it.ticket.store')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('it-service.index')">Ticket erfassen</Link></li>
                    <li v-if="canAny(['it.geraet.store', 'it.geraet.update'])"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('it-service.index')">Geräte verwalten</Link></li>
                </ul>
            </li>
            <!-- Bestellungen Submenu -->
            <li v-if="canAny(['bereich.index', 'bereich.store', 'bereich.update'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('bereich')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-shopping-cart la-lg mr-2"></i>
                    <span v-if="!displayHideTextSidebar" >{{$t('Bestellungen')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'bereich', 'text-zbb': $page.component.startsWith('Bereich')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'bereich'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('bereich.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('bereich.index')">{{$t('Bereichübersicht')}}</Link></li>
                </ul>
            </li>
             <!-- Termine Submenu -->
             <li v-if="canAny(['berechtigung.index', 'berechtigung.store', 'berechtigung.update'])" class="submenu">
                <a href="#" @click.prevent="toggleMenu('berechtigung')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                    <i class="la la-calendar la-lg mr-2"></i>

                    <span v-if="!displayHideTextSidebar" >{{$t('Termine')}}</span>
                    <span v-if="!displayHideTextSidebar" :class="{'rotate-180': activeMenu === 'berechtigung', 'text-zbb': $page.component.startsWith('Einstellung')}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
                </a>
                <ul v-show="activeMenu === 'berechtigung'" class="pl-6 mt-2 space-y-2">
                    <li v-if="can('berechtigung.index')"><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('berechtigung.index')">{{$t('berechtigungsübersicht')}}</Link></li>
                </ul>
            </li>
            <!-- Weitere Menüpunkte -->
        </SidebarLayout>

  </template>

<script setup>
    import { Head, Link, router } from '@inertiajs/vue3';
    import SidebarLayout from '../Sidebar/SidebarLayout.vue';
    import { usePermissions } from '@/utils/permissions';
    import { useModules } from '@/utils/modules';

    const { can, canAny } = usePermissions();
    const { moduleEnabled } = useModules();

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

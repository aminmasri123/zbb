import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useModules() {
    const page = usePage()
    const states = computed(() => page.props.enabledModules || {})

    const moduleEnabled = (key) => states.value[key] !== false

    return { states, moduleEnabled }
}

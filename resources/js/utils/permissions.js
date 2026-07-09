import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const asArray = (value) => (Array.isArray(value) ? value : []);
const asList = (value) => (Array.isArray(value) ? value : [value]).filter(Boolean);

const aliasesFor = (permission) => {
    if (typeof permission !== 'string') {
        return [];
    }

    if (permission.startsWith('raeumlichkeiten.')) {
        return [permission, permission.replace('raeumlichkeiten.', 'räumlichkeiten.')];
    }

    if (permission.startsWith('räumlichkeiten.')) {
        return [permission, permission.replace('räumlichkeiten.', 'raeumlichkeiten.')];
    }

    return [permission];
};

export function hasPermission(permissions, permission) {
    const available = new Set(asArray(permissions));

    return aliasesFor(permission).some((alias) => available.has(alias));
}

export function hasAnyPermission(permissions, requiredPermissions) {
    return asList(requiredPermissions).some((permission) => hasPermission(permissions, permission));
}

export function hasAllPermissions(permissions, requiredPermissions) {
    const required = asList(requiredPermissions);

    return required.length > 0 && required.every((permission) => hasPermission(permissions, permission));
}

export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => asArray(page.props.permissions));
    const roles = computed(() => asArray(page.props.roles));

    const can = (permission) => hasPermission(permissions.value, permission);
    const canAny = (requiredPermissions) => hasAnyPermission(permissions.value, requiredPermissions);
    const canAll = (requiredPermissions) => hasAllPermissions(permissions.value, requiredPermissions);
    const hasRole = (role) => roles.value.includes(role);

    return {
        permissions,
        roles,
        can,
        canAny,
        canAll,
        hasRole,
    };
}

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const isSuperAdmin = computed(() => page.props.auth?.user?.is_super_admin ?? false);
    const permissions = computed(() => page.props.auth?.permissions ?? []);

    function can(scope) {
        if (isSuperAdmin.value) return true;

        for (const perm of permissions.value) {
            if (perm === '*' || perm === scope) return true;

            if (perm.endsWith('.*')) {
                const prefix = perm.slice(0, -2);
                if (scope.startsWith(prefix + '.')) return true;
            }
        }

        return false;
    }

    function canAny(scopes) {
        return scopes.some(scope => can(scope));
    }

    return { can, canAny, isSuperAdmin, permissions };
}

import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const isNavigating = ref(false);
const isPartialLoading = ref(false);
const skeletonVariant = ref('page');

let listenersRegistered = false;

const resolveSkeletonVariant = (urlValue) => {
    try {
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        const pathname = new URL(String(urlValue || '/'), base).pathname.replace(/\/+$/, '') || '/';

        if (pathname === '/' || pathname === '/events') {
            return 'catalog';
        }

        if (pathname === '/admin' || pathname === '/manager' || pathname === '/manager/dashboard') {
            return 'dashboard';
        }

        if (
            pathname.startsWith('/admin/events/') && !pathname.endsWith('/create') && !pathname.endsWith('/edit')
        ) {
            return 'detail';
        }

        if (
            /^\/admin\/teams\/\d+$/.test(pathname) ||
            /^\/admin\/screening\/(team|auditions)\/\d+/.test(pathname) ||
            pathname.startsWith('/events/')
        ) {
            return 'detail';
        }

        if (
            pathname.startsWith('/admin/') ||
            pathname.startsWith('/manager/players') ||
            pathname.startsWith('/manager/officials') ||
            pathname.startsWith('/manager/matches') ||
            pathname.startsWith('/manager/payments')
        ) {
            return 'table';
        }

        return 'page';
    } catch {
        return 'page';
    }
};

export function initNavigationLoading() {
    if (listenersRegistered || typeof window === 'undefined') {
        return;
    }

    listenersRegistered = true;

    router.on('start', (event) => {
        const visit = event?.detail?.visit;
        if (!visit) return;

        const isGet = String(visit.method || 'get').toLowerCase() === 'get';
        if (!isGet) return;

        if (visit.preserveState) {
            isPartialLoading.value = true;
            return;
        }

        skeletonVariant.value = resolveSkeletonVariant(visit.url);
        isNavigating.value = true;
        document.documentElement.classList.add('is-page-navigating');
    });

    router.on('finish', () => {
        isNavigating.value = false;
        isPartialLoading.value = false;
        document.documentElement.classList.remove('is-page-navigating');
    });
}

export function useNavigationLoading() {
    return {
        isNavigating,
        isPartialLoading,
        skeletonVariant,
    };
}

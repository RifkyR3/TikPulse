import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';
import { authStore } from '@/stores/auth.svelte';
import '../css/app.css';

createInertiaApp({
    resolve: async (name: string) => {
        const pages = import.meta.glob('./Pages/**/*.svelte');
        let match = pages[`./Pages/${name}.svelte`];
        if (!match) {
            const key = Object.keys(pages).find(
                (k) => k.toLowerCase() === `./pages/${name.toLowerCase()}.svelte`
            );
            if (key) match = pages[key];
        }
        if (!match) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        const module: any = await match();
        return module.default ? module : { default: module };
    },
    setup({ el, App, props }) {
        const sharedUser = (props.initialPage?.props as any)?.auth?.user;
        if (sharedUser) {
            authStore.initFromPageProps(sharedUser);
        }
        if (el) {
            mount(App, { target: el, props });
        }
    },
});

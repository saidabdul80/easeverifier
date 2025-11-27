<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { logout as logoutRoute } from '@/routes';

const props = defineProps<{
    user: { name: string; email: string };
}>();

const drawer = ref(true);
const rail = ref(false);

const navItems = [
    { title: 'Dashboard', icon: 'mdi-view-dashboard', route: '/admin' },
    { title: 'Customers', icon: 'mdi-account-group', route: '/admin/customers' },
    { title: 'Services', icon: 'mdi-cog-outline', route: '/admin/services' },
    { title: 'Wallets', icon: 'mdi-wallet', route: '/admin/wallets' },
    { title: 'Transactions', icon: 'mdi-swap-horizontal', route: '/admin/transactions' },
    { title: 'Verifications', icon: 'mdi-shield-check', route: '/admin/verifications' },
];

const logout = () => {
    router.post(logoutRoute() as unknown as string);
};

const initials = computed(() => {
    return props.user?.name?.split(' ').map(n => n[0]).join('').toUpperCase() || 'A';
});
</script>

<template>
    <v-app>
        <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="primary-darken-1">
            <v-list-item class="py-4 px-4" :nav="false">
                <template #prepend>
                    <v-avatar color="white" size="40">
                        <v-icon color="primary">mdi-shield-check</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-h6 font-weight-bold text-white">EaseVerifier</v-list-item-title>
                <v-list-item-subtitle class="text-white opacity-60">Admin Panel</v-list-item-subtitle>
                <template #append>
                    <v-btn variant="text" :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'" color="white" @click="rail = !rail" />
                </template>
            </v-list-item>

            <v-divider class="border-opacity-25" />

            <v-list nav density="comfortable" class="pa-2">
                <v-list-item v-for="item in navItems" :key="item.title" :href="item.route" :prepend-icon="item.icon" :title="item.title" rounded="lg" color="secondary" class="mb-1" />
            </v-list>

            <template #append>
                <v-divider class="border-opacity-25" />
                <v-list nav class="pa-2">
                    <v-list-item prepend-icon="mdi-cog" title="Settings" href="/settings/profile" rounded="lg" color="secondary" />
                </v-list>
            </template>
        </v-navigation-drawer>

        <v-app-bar flat color="white" elevation="1">
            <v-app-bar-nav-icon @click="drawer = !drawer" class="d-md-none" />
            <v-spacer />
            <v-btn icon variant="text" class="mr-2"><v-icon>mdi-bell-outline</v-icon><v-badge color="error" content="3" floating /></v-btn>
            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" variant="text" class="text-none">
                        <v-avatar color="primary" size="32" class="mr-2">{{ initials }}</v-avatar>
                        <span class="d-none d-sm-inline">{{ user?.name }}</span>
                        <v-icon end>mdi-chevron-down</v-icon>
                    </v-btn>
                </template>
                <v-list density="compact" width="200">
                    <v-list-item prepend-icon="mdi-account" title="Profile" href="/settings/profile" />
                    <v-list-item prepend-icon="mdi-cog" title="Settings" href="/settings/profile" />
                    <v-divider />
                    <v-list-item prepend-icon="mdi-logout" title="Logout" @click="logout" />
                </v-list>
            </v-menu>
        </v-app-bar>

        <v-main class="bg-grey-lighten-4">
            <v-container fluid class="pa-6">
                <slot />
            </v-container>
        </v-main>
    </v-app>
</template>


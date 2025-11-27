<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { logout as logoutRoute } from '@/routes';

const props = defineProps<{
    user: { name: string; email: string };
    wallet?: { balance: number; bonus_balance: number };
}>();

const drawer = ref(true);
const rail = ref(false);

const navItems = [
    { title: 'Dashboard', icon: 'mdi-view-dashboard', route: '/customer' },
    { title: 'Verify', icon: 'mdi-shield-check', route: '/customer/verify' },
    { title: 'History', icon: 'mdi-history', route: '/customer/history' },
    { title: 'Wallet', icon: 'mdi-wallet', route: '/customer/wallet' },
    { title: 'API Keys', icon: 'mdi-key', route: '/customer/api' },
];

const logout = () => {
    router.post(logoutRoute() as unknown as string);
};

const initials = computed(() => {
    return props.user?.name?.split(' ').map(n => n[0]).join('').toUpperCase() || 'C';
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
};
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
                <v-list-item-subtitle class="text-white opacity-60">Customer Portal</v-list-item-subtitle>
                <template #append>
                    <v-btn variant="text" :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'" color="white" @click="rail = !rail" />
                </template>
            </v-list-item>

            <v-divider class="border-opacity-25" />

            <!-- Wallet Balance Card -->
            <div v-if="!rail" class="pa-4">
                <v-card color="secondary" class="rounded-lg">
                    <v-card-text class="pa-3">
                        <p class="text-caption text-white opacity-80 mb-1">Wallet Balance</p>
                        <p class="text-h5 font-weight-bold text-white mb-0">{{ formatCurrency(wallet?.balance) }}</p>
                        <p class="text-caption text-white opacity-60">Bonus: {{ formatCurrency(wallet?.bonus_balance) }}</p>
                    </v-card-text>
                </v-card>
            </div>

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
            
            <!-- Quick Balance -->
            <v-chip color="primary" variant="flat" class="mr-4 d-none d-sm-flex">
                <v-icon start>mdi-wallet</v-icon>
                {{ formatCurrency(wallet?.balance) }}
            </v-chip>

            <v-btn variant="flat" color="secondary" class="mr-4" href="/customer/wallet/fund">
                <v-icon start>mdi-plus</v-icon>Fund Wallet
            </v-btn>

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


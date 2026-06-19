<script setup lang="ts">
import { blog, dashboard, documentation, home, login, pricing, register, services } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        current?: 'home' | 'services' | 'pricing' | 'docs' | 'blog';
    }>(),
    {
        canRegister: true,
        current: undefined,
    },
);

const mobileMenu = ref(false);
const page = usePage();

const authUser = computed(() => page.props.auth?.user);

const navItems = [
    { key: 'services', label: 'Services', href: services() },
    { key: 'pricing', label: 'Pricing', href: pricing() },
    { key: 'docs', label: 'Docs', href: documentation() },
    { key: 'blog', label: 'Blog', href: blog() },
] as const;
</script>

<template>
    <v-app-bar flat class="public-bar">
        <v-container class="py-0">
            <div class="nav-shell">
                <Link :href="home()" class="text-decoration-none d-flex align-center brand-lockup">
                    <v-avatar color="white" size="38" class="mr-3 brand-avatar">
                        <img src="/ashlabtech.png" alt="EaseVerifier" class="brand-logo-img" />
                    </v-avatar>
                    <div class="brand-copy">
                        <div class="text-subtitle-1 font-weight-bold text-white">EaseVerifier</div>
                        <div class="text-caption text-white text-opacity-70 brand-subtitle">Identity API for Nigeria</div>
                    </div>
                </Link>

                <div class="d-none d-lg-flex nav-center">
                    <Link v-for="item in navItems" :key="item.key" :href="item.href">
                        <v-btn
                            variant="text"
                            color="white"
                            class="nav-link-btn"
                            :class="{ 'is-active': current === item.key }"
                        >
                            {{ item.label }}
                        </v-btn>
                    </Link>
                </div>

                <div class="d-none d-lg-flex align-center ga-2 nav-right">
                    <template v-if="authUser">
                        <Link :href="dashboard()"><v-btn color="white" variant="flat" class="nav-action">Dashboard</v-btn></Link>
                    </template>
                    <template v-else>
                        <Link :href="login()"><v-btn color="white" variant="text" class="nav-link-btn">Log in</v-btn></Link>
                        <Link v-if="canRegister" :href="register()"><v-btn color="secondary" variant="flat" class="nav-action">Get Started</v-btn></Link>
                    </template>
                </div>

                <v-app-bar-nav-icon class="d-lg-none text-white" @click="mobileMenu = !mobileMenu" />
            </div>
        </v-container>
    </v-app-bar>

    <v-navigation-drawer v-model="mobileMenu" temporary location="right" class="mobile-drawer">
        <v-list nav class="pa-3">
            <Link v-for="item in navItems" :key="item.key" :href="item.href">
                <v-list-item :title="item.label" :active="current === item.key" />
            </Link>
            <v-divider class="my-2" />
            <template v-if="authUser">
                <Link :href="dashboard()"><v-list-item prepend-icon="mdi-view-dashboard" title="Dashboard" /></Link>
            </template>
            <template v-else>
                <Link :href="login()"><v-list-item prepend-icon="mdi-login" title="Log in" /></Link>
                <Link v-if="canRegister" :href="register()"><v-list-item prepend-icon="mdi-rocket-launch" title="Get Started" /></Link>
            </template>
        </v-list>
    </v-navigation-drawer>
</template>

<style scoped>
.public-bar {
    background: transparent !important;
    box-shadow: none !important;
    padding-top: 18px;
}

.nav-shell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 24px;
    background: rgba(7, 31, 16, 0.56);
    backdrop-filter: blur(18px);
}

.brand-avatar {
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
    flex-shrink: 0;
}

.brand-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-copy,
.brand-lockup {
    min-width: 0;
}

.brand-lockup {
    flex: 1 1 auto;
    max-width: 320px;
}

.brand-copy {
    overflow: hidden;
}

.brand-copy .text-subtitle-1 {
    line-height: 1.1;
    white-space: nowrap;
}

.brand-subtitle {
    white-space: nowrap;
}

.nav-center {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1 1 auto;
    min-width: 0;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
}

.nav-right {
    flex-shrink: 0;
}

.nav-link-btn,
.nav-action {
    border-radius: 999px;
    text-transform: none;
    font-weight: 700;
}

.nav-link-btn.is-active {
    background: rgba(255, 255, 255, 0.12);
}

.mobile-drawer {
    background: #f5fbf6;
}

@media (max-width: 960px) {
    .nav-center {
        display: none;
    }
    .nav-shell {
        padding: 0.6rem 0.75rem;
        border-radius: 20px;
        background: rgba(7, 31, 16, 0.72);
    }

    .brand-avatar {
        width: 34px !important;
        height: 34px !important;
    }

    .brand-subtitle {
        display: none;
    }
}

@media (max-width: 1280px) {
    .brand-lockup {
        max-width: 250px;
    }

    .brand-subtitle {
        display: none;
    }
}
</style>

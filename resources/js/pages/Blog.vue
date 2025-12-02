<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';
import { ref, computed } from 'vue';

interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    category: string;
    featured_image: string | null;
    published_at: string;
    views: number;
    author: { id: number; name: string };
}

const props = defineProps<{
    posts: { data: Post[]; links: any };
    categories: string[];
}>();

const selectedCategory = ref('All');

const filteredPosts = computed(() => {
    if (selectedCategory.value === 'All') return props.posts.data;
    return props.posts.data.filter(p => p.category === selectedCategory.value);
});

const getCategoryIcon = (category: string): string => {
    const icons: Record<string, string> = {
        'Education': 'mdi-school',
        'Compliance': 'mdi-clipboard-check',
        'Technical': 'mdi-api',
        'Security': 'mdi-shield-lock',
        'News': 'mdi-newspaper',
        'Updates': 'mdi-update',
    };
    return icons[category] || 'mdi-post';
};

const formatDate = (date: string) => new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Blog - Identity Verification News & Insights | EaseVerifier">
        <meta name="description" content="Stay updated with the latest news on identity verification, KYC compliance, fraud prevention, and API integration tips from EaseVerifier's expert team." />
        <meta name="keywords" content="identity verification blog, KYC news Nigeria, NIN verification updates, fintech compliance, fraud prevention tips" />
        <meta property="og:title" content="Blog - EaseVerifier" />
        <meta property="og:description" content="Expert insights on identity verification, KYC compliance, and fraud prevention for Nigerian businesses." />
        <meta property="og:type" content="blog" />
        <link rel="canonical" href="https://easeverifier.com/blog" />
    </Head>
    <v-app>
        <v-app-bar flat color="white" elevation="1">
            <v-container class="d-flex align-center">
                <Link :href="home()" class="text-decoration-none d-flex align-center">
                    <v-avatar color="primary" size="36" class="mr-2">
                        <v-icon color="white" size="20">mdi-shield-check</v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                </Link>
                <v-spacer />
                <div class="d-none d-md-flex align-center ga-2">
                    <v-btn variant="text" :href="services()">Services</v-btn>
                    <v-btn variant="text" :href="pricing()">Pricing</v-btn>
                    <v-btn variant="text" :href="documentation()">Documentation</v-btn>
                </div>
                <v-spacer />
                <div class="d-flex ga-2">
                    <v-btn variant="outlined" color="primary" :href="login()">Login</v-btn>
                    <v-btn variant="flat" color="primary" :href="register()" class="d-none d-sm-flex">Get Started</v-btn>
                </div>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="hero-section py-16 text-center">
                <v-container>
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">Blog & Resources</h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">Insights, tutorials, and updates from the EaseVerifier team.</p>
                </v-container>
            </section>

            <section class="py-8">
                <v-container>
                    <div class="d-flex flex-wrap ga-2 justify-center mb-8">
                        <v-chip v-for="cat in ['All', ...categories]" :key="cat" :color="selectedCategory === cat ? 'primary' : undefined" :variant="selectedCategory === cat ? 'flat' : 'outlined'" class="cursor-pointer" @click="selectedCategory = cat">{{ cat }}</v-chip>
                    </div>
                    <v-row v-if="filteredPosts.length">
                        <v-col v-for="post in filteredPosts" :key="post.id" cols="12" md="6" lg="4">
                            <Link :href="`/blog/${post.slug}`" class="text-decoration-none">
                                <v-card class="h-100" hover>
                                    <div v-if="post.featured_image" class="post-image" :style="{ backgroundImage: `url(${post.featured_image})` }" />
                                    <div v-else class="bg-primary-lighten-5 pa-8 text-center">
                                        <v-icon color="primary" size="48">{{ getCategoryIcon(post.category) }}</v-icon>
                                    </div>
                                    <v-card-text class="pa-6">
                                        <div class="d-flex align-center mb-2">
                                            <v-chip size="small" color="secondary" variant="flat">{{ post.category }}</v-chip>
                                            <v-spacer />
                                            <span class="text-caption text-grey">{{ formatDate(post.published_at) }}</span>
                                        </div>
                                        <h3 class="text-h6 font-weight-bold mb-2 text-grey-darken-4">{{ post.title }}</h3>
                                        <p class="text-body-2 text-grey-darken-1 mb-4">{{ post.excerpt }}</p>
                                        <div class="d-flex align-center justify-space-between">
                                            <span class="text-primary font-weight-medium">Read More <v-icon size="small">mdi-arrow-right</v-icon></span>
                                            <span class="text-caption text-grey"><v-icon size="14">mdi-eye</v-icon> {{ post.views }}</span>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>
                    </v-row>
                    <v-row v-else>
                        <v-col cols="12">
                            <v-card class="text-center py-12">
                                <v-icon size="64" color="grey-lighten-1" class="mb-4">mdi-post-outline</v-icon>
                                <p class="text-h6 text-grey">No blog posts yet</p>
                                <p class="text-body-2 text-grey">Check back soon for updates!</p>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
.post-image { height: 180px; background-size: cover; background-position: center; }
.cursor-pointer { cursor: pointer; }
</style>


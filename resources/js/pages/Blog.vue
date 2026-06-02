<script setup lang="ts">
import PublicTopNav from '@/components/PublicTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

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
    posts: { data: Post[]; links: unknown };
    hottestPosts: Post[];
    categories: string[];
}>();

const adsenseScriptSelector = 'script[data-adsense-loader="true"]';

onMounted(() => {
    if (!document.head.querySelector(adsenseScriptSelector)) {
        const adsenseScript = document.createElement('script');
        adsenseScript.async = true;
        adsenseScript.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5615909705062666';
        adsenseScript.crossOrigin = 'anonymous';
        adsenseScript.dataset.adsenseLoader = 'true';
        document.head.appendChild(adsenseScript);
    }
});

const selectedCategory = ref('All');

const filteredPosts = computed(() => {
    if (selectedCategory.value === 'All') return props.posts.data;
    return props.posts.data.filter((post) => post.category === selectedCategory.value);
});

const featuredHotPost = computed(() => props.hottestPosts[0] ?? null);
const trailingHotPosts = computed(() => props.hottestPosts.slice(1, 4));

const getCategoryIcon = (category: string): string => {
    const icons: Record<string, string> = {
        Education: 'mdi-school',
        Compliance: 'mdi-clipboard-check',
        Technical: 'mdi-api',
        Security: 'mdi-shield-lock',
        News: 'mdi-newspaper',
        Updates: 'mdi-update',
        Guides: 'mdi-book-open-page-variant',
    };

    return icons[category] || 'mdi-post';
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Blog - Identity Verification News & Insights | EaseVerifier">
        <meta name="description" content="Stay updated with the latest news on identity verification, KYC compliance, fraud prevention, and API integration tips from EaseVerifier's expert team." />
        <meta name="keywords" content="identity verification blog, KYC news Nigeria, NIN verification updates, fintech compliance, fraud prevention tips" />
        <meta property="og:title" content="Blog - EaseVerifier" />
        <meta property="og:description" content="Expert insights on identity verification, KYC compliance, and fraud prevention for Nigerian businesses." />
        <meta property="og:type" content="blog" />
        <link rel="canonical" href="https://verify.ashlabtech.ng/blog" />
    </Head>

    <v-app class="blog-app">
        <PublicTopNav current="blog" />

        <v-main>
            <section class="blog-hero">
                <div class="blog-hero-glow" />
                <v-container class="blog-hero-content">
                    <div class="blog-badge">Blog</div>
                    <h1 class="blog-title">Sharp updates. Short reads.</h1>
                    <p class="blog-copy">Guides, policy shifts, and search-driven help topics.</p>
                </v-container>
            </section>

            <section class="blog-body">
                <v-container>
                    <v-row v-if="featuredHotPost" class="mb-8">
                        <v-col cols="12" lg="7">
                            <Link :href="`/blog/${featuredHotPost.slug}`" class="text-decoration-none">
                                <v-card class="feature-card" hover>
                                    <v-card-text class="pa-7">
                                        <div class="d-flex align-center justify-space-between mb-4">
                                            <v-chip size="small" color="secondary" variant="flat">{{ featuredHotPost.category }}</v-chip>
                                            <span class="text-caption text-grey-darken-1">{{ featuredHotPost.views.toLocaleString() }} views</span>
                                        </div>
                                        <div class="feature-icon">
                                            <v-icon color="primary" size="30">{{ getCategoryIcon(featuredHotPost.category) }}</v-icon>
                                        </div>
                                        <h2 class="text-h4 font-weight-bold text-grey-darken-4 mb-3">{{ featuredHotPost.title }}</h2>
                                        <p class="text-body-1 text-grey-darken-1 mb-4">{{ featuredHotPost.excerpt }}</p>
                                        <div class="text-caption text-grey">{{ formatDate(featuredHotPost.published_at) }}</div>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>

                        <v-col cols="12" lg="5">
                            <div class="trending-stack">
                                <Link
                                    v-for="post in trailingHotPosts"
                                    :key="`hot-${post.id}`"
                                    :href="`/blog/${post.slug}`"
                                    class="text-decoration-none"
                                >
                                    <v-card class="mini-hot-card" hover>
                                        <v-card-text class="pa-5">
                                            <div class="d-flex align-center justify-space-between mb-2">
                                                <v-chip size="small" variant="outlined" color="primary">{{ post.category }}</v-chip>
                                                <span class="text-caption text-grey">{{ formatDate(post.published_at) }}</span>
                                            </div>
                                            <p class="font-weight-bold text-grey-darken-4 mb-1">{{ post.title }}</p>
                                            <p class="text-body-2 text-grey-darken-1 mb-0">{{ post.excerpt }}</p>
                                        </v-card-text>
                                    </v-card>
                                </Link>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="chip-row mb-8">
                        <v-chip
                            v-for="cat in ['All', ...categories]"
                            :key="cat"
                            :color="selectedCategory === cat ? 'primary' : undefined"
                            :variant="selectedCategory === cat ? 'flat' : 'outlined'"
                            class="filter-chip"
                            @click="selectedCategory = cat"
                        >
                            {{ cat }}
                        </v-chip>
                    </div>

                    <v-row v-if="filteredPosts.length">
                        <v-col v-for="post in filteredPosts" :key="post.id" cols="12" md="6" lg="4">
                            <Link :href="`/blog/${post.slug}`" class="text-decoration-none">
                                <v-card class="post-card h-100" hover>
                                    <div v-if="post.featured_image" class="post-image" :style="{ backgroundImage: `url(${post.featured_image})` }" />
                                    <div v-else class="post-image-fallback">
                                        <v-icon color="primary" size="42">{{ getCategoryIcon(post.category) }}</v-icon>
                                    </div>

                                    <v-card-text class="pa-6">
                                        <div class="d-flex align-center justify-space-between mb-3">
                                            <v-chip size="small" color="secondary" variant="flat">{{ post.category }}</v-chip>
                                            <span class="text-caption text-grey">{{ formatDate(post.published_at) }}</span>
                                        </div>
                                        <h3 class="text-h6 font-weight-bold text-grey-darken-4 mb-2">{{ post.title }}</h3>
                                        <p class="text-body-2 text-grey-darken-1 mb-4">{{ post.excerpt }}</p>
                                        <div class="d-flex align-center justify-space-between">
                                            <span class="text-primary font-weight-medium">Read</span>
                                            <span class="text-caption text-grey"><v-icon size="14">mdi-eye</v-icon> {{ post.views }}</span>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>
                    </v-row>

                    <v-row v-else>
                        <v-col cols="12">
                            <v-card class="empty-card text-center py-12">
                                <v-icon size="54" color="grey-lighten-1" class="mb-3">mdi-post-outline</v-icon>
                                <p class="text-h6 text-grey mb-1">No posts in this category.</p>
                                <p class="text-body-2 text-grey">Try another filter.</p>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.blog-app {
    background:
        radial-gradient(circle at top right, rgba(244, 199, 76, 0.16), transparent 28%),
        linear-gradient(180deg, #f6fbf7 0%, #edf5ef 100%);
}

.blog-hero {
    position: relative;
    overflow: hidden;
    padding: 6.8rem 0 3.5rem;
    background: linear-gradient(135deg, #0d3a1c 0%, #12542a 56%, #1c7f44 100%);
}

.blog-hero-glow {
    position: absolute;
    top: -80px;
    right: -80px;
    width: 280px;
    height: 280px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    filter: blur(6px);
}

.blog-hero-content {
    position: relative;
    z-index: 1;
}

.blog-badge {
    display: inline-block;
    padding: 0.45rem 0.8rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.blog-title {
    margin: 0 0 0.75rem;
    color: #fff;
    font-size: clamp(2.6rem, 6vw, 4.5rem);
    line-height: 0.96;
    letter-spacing: -0.05em;
}

.blog-copy {
    margin: 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 1rem;
}

.blog-body {
    padding: 3rem 0 5rem;
}

.feature-card,
.mini-hot-card,
.post-card,
.empty-card {
    border: 1px solid rgba(23, 83, 44, 0.08);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 18px 40px rgba(22, 63, 37, 0.05);
}

.feature-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    margin-bottom: 1.1rem;
    border-radius: 20px;
    background: #eef7f0;
}

.trending-stack {
    display: grid;
    gap: 1rem;
}

.chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.filter-chip {
    cursor: pointer;
}

.post-image {
    height: 180px;
    background-size: cover;
    background-position: center;
    border-radius: 28px 28px 0 0;
}

.post-image-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 180px;
    border-radius: 28px 28px 0 0;
    background: linear-gradient(135deg, #edf8ef 0%, #dff1e4 100%);
}

@media (max-width: 960px) {
    .blog-hero {
        padding-top: 6rem;
    }
}
</style>

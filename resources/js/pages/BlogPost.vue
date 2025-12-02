<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';

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
    meta_title: string | null;
    meta_description: string | null;
    author: { id: number; name: string };
}

const props = defineProps<{
    post: Post;
    relatedPosts: Post[];
}>();

const formatDate = (date: string) => new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });

const getCategoryIcon = (category: string): string => {
    const icons: Record<string, string> = {
        'Education': 'mdi-school', 'Compliance': 'mdi-clipboard-check', 'Technical': 'mdi-api',
        'Security': 'mdi-shield-lock', 'News': 'mdi-newspaper', 'Updates': 'mdi-update',
    };
    return icons[category] || 'mdi-post';
};
</script>

<template>
    <Head :title="`${post.meta_title || post.title} | EaseVerifier Blog`">
        <meta name="description" :content="post.meta_description || post.excerpt" />
        <meta property="og:title" :content="post.title" />
        <meta property="og:description" :content="post.excerpt" />
        <meta property="og:type" content="article" />
        <link rel="canonical" :href="`https://easeverifier.com/blog/${post.slug}`" />
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
            <v-container class="py-8" style="max-width: 900px;">
                <Link href="/blog" class="text-decoration-none d-inline-flex align-center mb-6 text-primary">
                    <v-icon start>mdi-arrow-left</v-icon> Back to Blog
                </Link>

                <article>
                    <v-chip color="secondary" class="mb-4">{{ post.category }}</v-chip>
                    <h1 class="text-h3 font-weight-bold mb-4">{{ post.title }}</h1>
                    <div class="d-flex align-center text-grey mb-6">
                        <span>By {{ post.author?.name || 'EaseVerifier Team' }}</span>
                        <v-divider vertical class="mx-3" />
                        <span>{{ formatDate(post.published_at) }}</span>
                        <v-divider vertical class="mx-3" />
                        <span><v-icon size="16">mdi-eye</v-icon> {{ post.views }} views</span>
                    </div>

                    <v-img v-if="post.featured_image" :src="post.featured_image" class="rounded-lg mb-8" max-height="400" cover />

                    <div class="blog-content text-body-1" v-html="post.content" />
                </article>

                <v-divider class="my-12" />

                <section v-if="relatedPosts.length">
                    <h2 class="text-h5 font-weight-bold mb-6">Related Posts</h2>
                    <v-row>
                        <v-col v-for="related in relatedPosts" :key="related.id" cols="12" md="4">
                            <Link :href="`/blog/${related.slug}`" class="text-decoration-none">
                                <v-card hover>
                                    <div v-if="related.featured_image" class="related-image" :style="{ backgroundImage: `url(${related.featured_image})` }" />
                                    <div v-else class="bg-primary-lighten-5 pa-6 text-center">
                                        <v-icon color="primary" size="36">{{ getCategoryIcon(related.category) }}</v-icon>
                                    </div>
                                    <v-card-text>
                                        <p class="font-weight-bold text-grey-darken-4 mb-1">{{ related.title }}</p>
                                        <p class="text-caption text-grey">{{ formatDate(related.published_at) }}</p>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>
                    </v-row>
                </section>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.blog-content { line-height: 1.8; }
.blog-content :deep(h2) { font-size: 1.5rem; font-weight: 600; margin: 2rem 0 1rem; }
.blog-content :deep(h3) { font-size: 1.25rem; font-weight: 600; margin: 1.5rem 0 0.75rem; }
.blog-content :deep(p) { margin-bottom: 1rem; }
.blog-content :deep(ul), .blog-content :deep(ol) { margin: 1rem 0; padding-left: 1.5rem; }
.blog-content :deep(li) { margin-bottom: 0.5rem; }
.blog-content :deep(code) { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
.blog-content :deep(pre) { background: #1e1e1e; color: #fff; padding: 1rem; border-radius: 8px; overflow-x: auto; margin: 1rem 0; }
.related-image { height: 120px; background-size: cover; background-position: center; }
</style>


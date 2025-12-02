<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    featured_image: string | null;
    category: string;
    tags: string[] | null;
    status: string;
    meta_title: string | null;
    meta_description: string | null;
}

const props = defineProps<{
    post: Post;
    categories: string[];
}>();

const form = useForm({
    title: props.post.title,
    slug: props.post.slug,
    excerpt: props.post.excerpt,
    content: props.post.content,
    featured_image: props.post.featured_image || '',
    category: props.post.category,
    tags: props.post.tags || [],
    status: props.post.status,
    meta_title: props.post.meta_title || '',
    meta_description: props.post.meta_description || '',
});

const submit = () => {
    form.put(`/admin/blog/${props.post.id}`);
};
</script>

<template>
    <Head :title="`Edit: ${post.title} - Admin`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Edit Blog Post</h1>
                <p class="text-body-2 text-grey">{{ post.title }}</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" href="/admin/blog" class="mr-2">Cancel</v-btn>
            <v-btn variant="outlined" color="primary" :href="`/blog/${post.slug}`" target="_blank" prepend-icon="mdi-open-in-new">Preview</v-btn>
        </div>

        <form @submit.prevent="submit">
            <v-row>
                <v-col cols="12" md="8">
                    <v-card class="mb-4">
                        <v-card-title>Content</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.title" label="Title" :error-messages="form.errors.title" variant="outlined" class="mb-4" />
                            <v-text-field v-model="form.slug" label="Slug" :error-messages="form.errors.slug" variant="outlined" class="mb-4" />
                            <v-textarea v-model="form.excerpt" label="Excerpt" :error-messages="form.errors.excerpt" variant="outlined" rows="3" class="mb-4" />
                            <v-textarea v-model="form.content" label="Content" :error-messages="form.errors.content" variant="outlined" rows="15" />
                        </v-card-text>
                    </v-card>

                    <v-card>
                        <v-card-title>SEO Settings</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.meta_title" label="Meta Title" :error-messages="form.errors.meta_title" variant="outlined" class="mb-4" />
                            <v-textarea v-model="form.meta_description" label="Meta Description" :error-messages="form.errors.meta_description" variant="outlined" rows="2" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card class="mb-4">
                        <v-card-title>Publish</v-card-title>
                        <v-card-text>
                            <v-select v-model="form.status" :items="[{ title: 'Draft', value: 'draft' }, { title: 'Published', value: 'published' }, { title: 'Archived', value: 'archived' }]" label="Status" :error-messages="form.errors.status" variant="outlined" class="mb-4" />
                            <v-btn type="submit" color="primary" block size="large" :loading="form.processing">
                                Update Post
                            </v-btn>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-4">
                        <v-card-title>Category & Tags</v-card-title>
                        <v-card-text>
                            <v-select v-model="form.category" :items="categories" label="Category" :error-messages="form.errors.category" variant="outlined" class="mb-4" />
                            <v-combobox v-model="form.tags" label="Tags" variant="outlined" multiple chips closable-chips />
                        </v-card-text>
                    </v-card>

                    <v-card>
                        <v-card-title>Featured Image</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.featured_image" label="Image URL" :error-messages="form.errors.featured_image" variant="outlined" />
                            <v-img v-if="form.featured_image" :src="form.featured_image" max-height="200" class="mt-4 rounded" />
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </form>
    </AdminLayout>
</template>


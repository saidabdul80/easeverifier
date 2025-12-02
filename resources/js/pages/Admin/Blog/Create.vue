<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{
    categories: string[];
}>();

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    featured_image: '',
    category: '',
    tags: [] as string[],
    status: 'draft',
    meta_title: '',
    meta_description: '',
});

const generateSlug = () => {
    form.slug = form.title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
};

const submit = () => {
    form.post('/admin/blog');
};
</script>

<template>
    <Head title="Create Blog Post - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Create Blog Post</h1>
                <p class="text-body-2 text-grey">Add a new blog article</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" href="/admin/blog">Cancel</v-btn>
        </div>

        <form @submit.prevent="submit">
            <v-row>
                <v-col cols="12" md="8">
                    <v-card class="mb-4">
                        <v-card-title>Content</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.title" label="Title" :error-messages="form.errors.title" variant="outlined" class="mb-4" @blur="!form.slug && generateSlug()" />
                            <v-text-field v-model="form.slug" label="Slug" :error-messages="form.errors.slug" variant="outlined" class="mb-4" hint="Leave empty to auto-generate" />
                            <v-textarea v-model="form.excerpt" label="Excerpt" :error-messages="form.errors.excerpt" variant="outlined" rows="3" class="mb-4" hint="Brief summary (max 500 chars)" />
                            <v-textarea v-model="form.content" label="Content" :error-messages="form.errors.content" variant="outlined" rows="15" hint="Full article content (supports HTML)" />
                        </v-card-text>
                    </v-card>

                    <v-card>
                        <v-card-title>SEO Settings</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.meta_title" label="Meta Title" :error-messages="form.errors.meta_title" variant="outlined" class="mb-4" hint="Optional - defaults to post title" />
                            <v-textarea v-model="form.meta_description" label="Meta Description" :error-messages="form.errors.meta_description" variant="outlined" rows="2" hint="Optional - defaults to excerpt" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card class="mb-4">
                        <v-card-title>Publish</v-card-title>
                        <v-card-text>
                            <v-select v-model="form.status" :items="[{ title: 'Draft', value: 'draft' }, { title: 'Published', value: 'published' }, { title: 'Archived', value: 'archived' }]" label="Status" :error-messages="form.errors.status" variant="outlined" class="mb-4" />
                            <v-btn type="submit" color="primary" block size="large" :loading="form.processing">
                                {{ form.status === 'published' ? 'Publish Post' : 'Save Draft' }}
                            </v-btn>
                        </v-card-text>
                    </v-card>

                    <v-card class="mb-4">
                        <v-card-title>Category & Tags</v-card-title>
                        <v-card-text>
                            <v-select v-model="form.category" :items="categories" label="Category" :error-messages="form.errors.category" variant="outlined" class="mb-4" />
                            <v-combobox v-model="form.tags" label="Tags" variant="outlined" multiple chips closable-chips hint="Press enter to add tags" />
                        </v-card-text>
                    </v-card>

                    <v-card>
                        <v-card-title>Featured Image</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.featured_image" label="Image URL" :error-messages="form.errors.featured_image" variant="outlined" hint="Enter image URL" />
                            <v-img v-if="form.featured_image" :src="form.featured_image" max-height="200" class="mt-4 rounded" />
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </form>
    </AdminLayout>
</template>


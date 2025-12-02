<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    category: string;
    status: 'draft' | 'published' | 'archived';
    published_at: string | null;
    views: number;
    author: { id: number; name: string };
}

const props = defineProps<{
    posts: { data: Post[]; links: any; meta: any };
    filters: { status?: string; category?: string; search?: string };
    categories: string[];
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const categoryFilter = ref(props.filters.category || '');

const applyFilters = () => {
    router.get('/admin/blog', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        category: categoryFilter.value || undefined,
    }, { preserveState: true });
};

watch([statusFilter, categoryFilter], applyFilters);

const deletePost = (post: Post) => {
    if (confirm(`Delete "${post.title}"?`)) {
        router.delete(`/admin/blog/${post.id}`);
    }
};

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = { draft: 'warning', published: 'success', archived: 'grey' };
    return colors[status] || 'grey';
};

const formatDate = (date: string | null) => date ? new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' }) : 'Not published';
</script>

<template>
    <Head title="Blog Posts - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Blog Posts</h1>
                <p class="text-body-2 text-grey">Manage blog content</p>
            </div>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-plus" href="/admin/blog/create">New Post</v-btn>
        </div>

        <v-card class="mb-6">
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-text-field v-model="search" label="Search posts" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="applyFilters" @click:clear="search = ''; applyFilters()" />
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-select v-model="statusFilter" :items="[{ title: 'All Status', value: '' }, { title: 'Draft', value: 'draft' }, { title: 'Published', value: 'published' }, { title: 'Archived', value: 'archived' }]" label="Status" variant="outlined" density="compact" hide-details />
                    </v-col>
                    <v-col cols="6" md="3">
                        <v-select v-model="categoryFilter" :items="[{ title: 'All Categories', value: '' }, ...categories.map(c => ({ title: c, value: c }))]" label="Category" variant="outlined" density="compact" hide-details />
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <v-card>
            <v-table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="post in posts.data" :key="post.id">
                        <td>
                            <div class="py-2">
                                <p class="font-weight-medium mb-0">{{ post.title }}</p>
                                <p class="text-caption text-grey">by {{ post.author?.name }}</p>
                            </div>
                        </td>
                        <td><v-chip size="small" variant="outlined">{{ post.category }}</v-chip></td>
                        <td><v-chip size="small" :color="getStatusColor(post.status)">{{ post.status }}</v-chip></td>
                        <td>{{ post.views.toLocaleString() }}</td>
                        <td>{{ formatDate(post.published_at) }}</td>
                        <td class="text-right">
                            <v-btn icon="mdi-pencil" size="small" variant="text" :href="`/admin/blog/${post.id}/edit`" />
                            <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="deletePost(post)" />
                        </td>
                    </tr>
                    <tr v-if="!posts.data?.length">
                        <td colspan="6" class="text-center py-8 text-grey">No blog posts found</td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </AdminLayout>
</template>


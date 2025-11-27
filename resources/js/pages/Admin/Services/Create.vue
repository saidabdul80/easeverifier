<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{ user: { name: string; email: string } }>();

const form = useForm({
    name: '',
    slug: '',
    description: '',
    icon: 'mdi-shield-check',
    default_price: 0,
    cost_price: 0,
    is_active: true,
    sort_order: 0,
});

const icons = [
    'mdi-shield-check', 'mdi-card-account-details', 'mdi-bank', 'mdi-domain',
    'mdi-car', 'mdi-passport', 'mdi-account-check', 'mdi-file-document-check'
];

const submit = () => {
    form.post('/admin/services');
};
</script>

<template>
    <Head title="Add Service - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/admin/services" class="mb-2">Back to Services</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Add New Service</h1>
            <p class="text-body-2 text-grey">Create a new verification service</p>
        </div>

        <v-card max-width="800">
            <v-card-text>
                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.name" label="Service Name *" variant="outlined" :error-messages="form.errors.name" placeholder="e.g., NIN Verification" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.slug" label="Slug" variant="outlined" :error-messages="form.errors.slug" placeholder="e.g., nin" hint="Leave empty to auto-generate" />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea v-model="form.description" label="Description" variant="outlined" rows="3" :error-messages="form.errors.description" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-select v-model="form.icon" label="Icon" :items="icons" variant="outlined">
                                <template #selection="{ item }">
                                    <v-icon class="mr-2">{{ item.value }}</v-icon>
                                    {{ item.value }}
                                </template>
                                <template #item="{ item, props: itemProps }">
                                    <v-list-item v-bind="itemProps">
                                        <template #prepend><v-icon>{{ item.value }}</v-icon></template>
                                    </v-list-item>
                                </template>
                            </v-select>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.sort_order" label="Sort Order" type="number" variant="outlined" :error-messages="form.errors.sort_order" />
                        </v-col>
                    </v-row>

                    <v-divider class="my-6" />

                    <h3 class="text-subtitle-1 font-weight-bold mb-4">Pricing</h3>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.default_price" label="Default Price (NGN) *" type="number" variant="outlined" prepend-inner-icon="mdi-currency-ngn" :error-messages="form.errors.default_price" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.cost_price" label="Cost Price (NGN) *" type="number" variant="outlined" prepend-inner-icon="mdi-currency-ngn" :error-messages="form.errors.cost_price" hint="Your cost from provider" />
                        </v-col>
                        <v-col cols="12">
                            <v-switch v-model="form.is_active" label="Service Active" color="primary" />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end ga-3 mt-6">
                        <v-btn variant="outlined" href="/admin/services">Cancel</v-btn>
                        <v-btn type="submit" color="primary" :loading="form.processing">Create Service</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>


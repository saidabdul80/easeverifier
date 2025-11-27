<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps<{
    user: { name: string; email: string };
    service: any;
}>();

const form = useForm({
    name: props.service.name,
    slug: props.service.slug,
    description: props.service.description || '',
    icon: props.service.icon || 'mdi-shield-check',
    default_price: props.service.default_price,
    cost_price: props.service.cost_price,
    is_active: props.service.is_active,
    sort_order: props.service.sort_order || 0,
});

const icons = [
    'mdi-shield-check', 'mdi-card-account-details', 'mdi-bank', 'mdi-domain',
    'mdi-car', 'mdi-passport', 'mdi-account-check', 'mdi-file-document-check'
];

const submit = () => {
    form.put(`/admin/services/${props.service.id}`);
};
</script>

<template>
    <Head :title="`Edit ${service.name} - Admin`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" :href="`/admin/services/${service.id}`" class="mb-2">Back to Service</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Edit Service</h1>
            <p class="text-body-2 text-grey">Update service configuration</p>
        </div>

        <v-card max-width="800">
            <v-card-text>
                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.name" label="Service Name *" variant="outlined" :error-messages="form.errors.name" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.slug" label="Slug" variant="outlined" :error-messages="form.errors.slug" />
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
                            <v-text-field v-model="form.cost_price" label="Cost Price (NGN) *" type="number" variant="outlined" prepend-inner-icon="mdi-currency-ngn" :error-messages="form.errors.cost_price" />
                        </v-col>
                        <v-col cols="12">
                            <v-switch v-model="form.is_active" label="Service Active" color="primary" />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end ga-3 mt-6">
                        <v-btn variant="outlined" :href="`/admin/services/${service.id}`">Cancel</v-btn>
                        <v-btn type="submit" color="primary" :loading="form.processing">Update Service</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>


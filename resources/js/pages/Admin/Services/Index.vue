<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{
    user: { name: string; email: string };
    services?: any[];
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const toggleService = (service: any) => {
    router.put(`/admin/services/${service.id}`, { ...service, is_active: !service.is_active }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Services - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Verification Services</h1>
                <p class="text-body-2 text-grey">Manage available verification services</p>
            </div>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-plus" href="/admin/services/create">Add Service</v-btn>
        </div>

        <v-row>
            <v-col v-for="service in services" :key="service.id" cols="12" md="6" lg="4">
                <v-card>
                    <v-card-text>
                        <div class="d-flex align-center mb-4">
                            <v-avatar :color="service.is_active ? 'primary-lighten-5' : 'grey-lighten-3'" size="48">
                                <v-icon :color="service.is_active ? 'primary' : 'grey'" size="24">{{ service.icon || 'mdi-shield-check' }}</v-icon>
                            </v-avatar>
                            <div class="ml-3">
                                <p class="font-weight-bold mb-0">{{ service.name }}</p>
                                <p class="text-caption text-grey">{{ service.slug }}</p>
                            </div>
                            <v-spacer />
                            <v-switch v-model="service.is_active" color="success" hide-details density="compact" @change="toggleService(service)" />
                        </div>

                        <v-divider class="mb-4" />

                        <div class="d-flex justify-space-between mb-2">
                            <span class="text-caption text-grey">Default Price</span>
                            <span class="font-weight-medium">{{ formatCurrency(service.default_price) }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-2">
                            <span class="text-caption text-grey">Cost Price</span>
                            <span class="font-weight-medium">{{ formatCurrency(service.cost_price) }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-2">
                            <span class="text-caption text-grey">Providers</span>
                            <span class="font-weight-medium">{{ service.providers_count || 0 }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-4">
                            <span class="text-caption text-grey">Total Requests</span>
                            <span class="font-weight-medium">{{ service.verification_requests_count || 0 }}</span>
                        </div>

                        <div class="d-flex ga-2">
                            <v-btn variant="outlined" size="small" prepend-icon="mdi-eye" :href="`/admin/services/${service.id}`">View</v-btn>
                            <v-btn variant="outlined" size="small" prepend-icon="mdi-pencil" :href="`/admin/services/${service.id}/edit`">Edit</v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="!services?.length" cols="12">
                <v-card>
                    <v-card-text class="text-center py-8">
                        <v-icon size="64" color="grey-lighten-1" class="mb-4">mdi-shield-off</v-icon>
                        <p class="text-h6 text-grey mb-2">No services yet</p>
                        <p class="text-body-2 text-grey mb-4">Create your first verification service to get started.</p>
                        <v-btn color="primary" href="/admin/services/create">Add Service</v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>


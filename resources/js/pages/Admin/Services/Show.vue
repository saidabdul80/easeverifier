    <script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{
    user: { name: string; email: string };
    service: any;
    stats?: { total_requests: number; successful_requests: number; failed_requests: number; total_revenue: number };
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
</script>

<template>
    <Head :title="`${service.name} - Admin`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/admin/services" class="mb-2">Back to Services</v-btn>
                <div class="d-flex align-center">
                    <v-avatar :color="service.is_active ? 'primary-lighten-5' : 'grey-lighten-3'" size="48" class="mr-3">
                        <v-icon :color="service.is_active ? 'primary' : 'grey'">{{ service.icon || 'mdi-shield-check' }}</v-icon>
                    </v-avatar>
                    <div>
                        <h1 class="text-h4 font-weight-bold mb-0">{{ service.name }}</h1>
                        <p class="text-body-2 text-grey">{{ service.slug }}</p>
                    </div>
                </div>
            </div>
            <v-spacer />
            <v-btn variant="outlined" class="mr-2" :href="`/admin/services/${service.id}/edit`" prepend-icon="mdi-pencil">Edit</v-btn>
            <v-chip :color="service.is_active ? 'success' : 'error'" size="large">{{ service.is_active ? 'Active' : 'Inactive' }}</v-chip>
        </div>

        <!-- Stats -->
        <v-row class="mb-6">
            <v-col cols="6" md="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats?.total_requests || 0 }}</p>
                    <p class="text-caption text-grey">Total Requests</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-success mb-0">{{ stats?.successful_requests || 0 }}</p>
                    <p class="text-caption text-grey">Successful</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-error mb-0">{{ stats?.failed_requests || 0 }}</p>
                    <p class="text-caption text-grey">Failed</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-warning mb-0">{{ formatCurrency(stats?.total_revenue) }}</p>
                    <p class="text-caption text-grey">Revenue</p>
                </v-card-text></v-card>
            </v-col>
        </v-row>

        <v-row>
            <!-- Service Details -->
            <v-col cols="12" md="6">
                <v-card>
                    <v-card-title>Service Details</v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item><v-list-item-title class="text-caption">Description</v-list-item-title><v-list-item-subtitle>{{ service.description || 'No description' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Default Price</v-list-item-title><v-list-item-subtitle class="font-weight-bold text-primary">{{ formatCurrency(service.default_price) }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Cost Price</v-list-item-title><v-list-item-subtitle>{{ formatCurrency(service.cost_price) }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Profit Margin</v-list-item-title><v-list-item-subtitle class="text-success">{{ formatCurrency(service.default_price - service.cost_price) }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Sort Order</v-list-item-title><v-list-item-subtitle>{{ service.sort_order }}</v-list-item-subtitle></v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Providers -->
            <v-col cols="12" md="6">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <span>API Providers</span>
                        <v-spacer />
                        <v-btn size="small" color="primary" prepend-icon="mdi-plus" :href="`/admin/services/${service.id}/providers/create`">Add Provider</v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-list v-if="service.providers?.length" lines="three">
                            <v-list-item v-for="provider in service.providers" :key="provider.id" :href="`/admin/providers/${provider.id}/edit`" class="mb-2 rounded border">
                                <template #prepend>
                                    <v-avatar :color="provider.is_active ? 'success-lighten-5' : 'grey-lighten-3'" size="48">
                                        <v-icon :color="provider.is_active ? 'success' : 'grey'" size="24">mdi-server</v-icon>
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="font-weight-bold">{{ provider.name }}</v-list-item-title>
                                <v-list-item-subtitle>
                                    <v-chip size="x-small" class="mr-1">{{ provider.http_method }}</v-chip>
                                    <v-chip size="x-small" variant="outlined">{{ provider.auth_type }}</v-chip>
                                </v-list-item-subtitle>
                                <v-list-item-subtitle class="text-caption mt-1">
                                    {{ provider.base_url }}{{ provider.endpoint }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <div class="text-right">
                                        <v-chip :color="provider.is_active ? 'success' : 'grey'" size="small" class="mb-1">{{ provider.is_active ? 'Active' : 'Inactive' }}</v-chip>
                                        <div class="text-caption text-grey">Priority: {{ provider.priority }}</div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <div v-else class="text-center py-8 text-grey">
                            <v-icon size="64" class="mb-2">mdi-server-off</v-icon>
                            <p class="mb-2">No providers configured</p>
                            <v-btn variant="tonal" color="primary" size="small" :href="`/admin/services/${service.id}/providers/create`">Add First Provider</v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>


<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';

defineProps<{
    user: { name: string; email: string };
    services?: any[];
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const getServiceIcon = (slug: string) => {
    const icons: Record<string, string> = {
        'nin': 'mdi-card-account-details',
        'bvn': 'mdi-bank',
        'cac': 'mdi-domain',
        'drivers-license': 'mdi-car',
        'passport': 'mdi-passport',
    };
    return icons[slug] || 'mdi-shield-check';
};
</script>

<template>
    <Head title="Verify - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Verification Services</h1>
            <p class="text-body-2 text-grey">Select a service to start verification</p>
        </div>

        <v-row>
            <v-col v-for="service in services" :key="service.id" cols="12" sm="6" lg="4">
                <v-card :href="`/customer/verify/${service.id}`" class="h-100 service-card" hover>
                    <v-card-text class="text-center pa-6">
                        <v-avatar :color="service.is_active ? 'primary-lighten-5' : 'grey-lighten-3'" size="72" class="mb-4">
                            <v-icon :color="service.is_active ? 'primary' : 'grey'" size="36">{{ service.icon || getServiceIcon(service.slug) }}</v-icon>
                        </v-avatar>
                        <h3 class="text-h6 font-weight-bold mb-2">{{ service.name }}</h3>
                        <p class="text-body-2 text-grey mb-4">{{ service.description || 'Verify identity using ' + service.name }}</p>
                        <v-chip color="primary" size="large" variant="tonal">
                            <v-icon start>mdi-currency-ngn</v-icon>
                            {{ formatCurrency(service.price) }}
                        </v-chip>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="!services?.length" cols="12">
                <v-card>
                    <v-card-text class="text-center py-12">
                        <v-icon size="64" color="grey-lighten-1" class="mb-4">mdi-shield-off</v-icon>
                        <h3 class="text-h6 text-grey mb-2">No Services Available</h3>
                        <p class="text-body-2 text-grey">Please check back later or contact support.</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

<style scoped>
.service-card:hover {
    border-color: rgb(var(--v-theme-primary));
}
</style>


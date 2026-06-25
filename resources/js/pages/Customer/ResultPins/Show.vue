<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';

defineProps<{
    order: any;
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
</script>

<template>
    <Head title="Result PIN Order - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">{{ order.product?.name }}</h1>
                <p class="text-body-2 text-grey">Reference: {{ order.reference }}</p>
            </div>
            <v-spacer />
            <v-btn href="/customer/result-pins" variant="outlined">Back</v-btn>
        </div>

        <v-row>
            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>Order Summary</v-card-title>
                    <v-list>
                        <v-list-item title="Status"><template #append><v-chip :color="order.status === 'completed' ? 'success' : order.status === 'failed' ? 'error' : 'warning'">{{ order.status }}</v-chip></template></v-list-item>
                        <v-list-item title="Quantity" :subtitle="String(order.quantity)" />
                        <v-list-item title="Amount" :subtitle="formatCurrency(order.total_amount)" />
                        <v-list-item title="Provider Reference" :subtitle="order.provider_reference || 'N/A'" />
                    </v-list>
                </v-card>
            </v-col>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>PINs</v-card-title>
                    <v-alert v-if="order.status === 'failed'" type="error" variant="tonal" class="ma-4">{{ order.error_message }}</v-alert>
                    <v-table v-if="order.pins?.length">
                        <thead><tr><th>PIN</th><th>Serial Number</th></tr></thead>
                        <tbody>
                            <tr v-for="(pin, index) in order.pins" :key="index">
                                <td class="font-weight-bold">{{ pin.pin }}</td>
                                <td>{{ pin.serial_no }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                    <v-card-text v-else class="text-grey">No PINs are available for this order yet.</v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    email: string;
    orders: any[];
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);
</script>

<template>
    <Head title="My Result PIN Orders - EaseVerifier" />

    <v-app>
        <v-main class="orders-shell">
            <v-container class="py-12">
                <div class="d-flex flex-column flex-sm-row align-sm-center mb-6 ga-4">
                    <div>
                        <Link href="/" class="text-decoration-none d-flex align-center mb-4">
                            <v-avatar color="primary" size="36" class="mr-2">
                                <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
                            </v-avatar>
                            <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                        </Link>
                        <h1 class="text-h4 font-weight-bold mb-1">Orders for {{ email }}</h1>
                        <p class="text-body-2 text-grey-darken-1 mb-0">Select an order to view the PINs.</p>
                    </div>
                    <v-spacer />
                    <v-btn href="/result-pins" color="primary">Buy PINs</v-btn>
                </div>

                <v-card class="rounded-xl">
                    <v-table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Qty</th>
                                <th class="text-right">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders" :key="order.id">
                                <td>{{ order.reference }}</td>
                                <td>{{ order.product?.name }}</td>
                                <td><v-chip size="small" :color="order.status === 'completed' ? 'success' : order.status === 'failed' ? 'error' : 'warning'">{{ order.status }}</v-chip></td>
                                <td>{{ order.quantity }}</td>
                                <td class="text-right">{{ formatCurrency(order.total_amount) }}</td>
                                <td class="text-right"><v-btn size="small" variant="text" :href="`/result-pins/orders/${order.reference}`">View</v-btn></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.orders-shell {
    min-height: 100vh;
    background: linear-gradient(135deg, #f7f2df 0%, #edf7ef 100%);
}
</style>

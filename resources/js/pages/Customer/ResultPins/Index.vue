<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    products: any[];
    orders: { data: any[] };
    walletBalance: number;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
const form = useForm({
    product_id: props.products[0]?.id ?? null,
    quantity: 1,
});

const selectedProduct = computed(() => props.products.find((product) => product.id === Number(form.product_id)));
const totalAmount = computed(() => Number(selectedProduct.value?.price ?? 0) * Number(form.quantity || 0));
const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const submit = () => {
    form.post('/customer/result-pins/purchase');
};
</script>

<template>
    <Head title="Result PINs - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <v-alert v-if="flash?.success" type="success" variant="tonal" closable class="mb-4">{{ flash.success }}</v-alert>
        <v-alert v-if="flash?.error" type="error" variant="tonal" closable class="mb-4">{{ flash.error }}</v-alert>

        <div class="d-flex flex-column flex-sm-row align-sm-center mb-6 ga-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Result PINs</h1>
                <p class="text-body-2 text-grey">Buy and manage result checker PINs.</p>
            </div>
            <v-spacer />
            <v-chip color="primary" size="large">Wallet: {{ formatCurrency(walletBalance) }}</v-chip>
        </div>

        <v-row>
            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>Buy PINs</v-card-title>
                    <v-card-text>
                        <v-form @submit.prevent="submit">
                            <v-select v-model="form.product_id" :items="products" item-title="name" item-value="id" label="Product" :error-messages="form.errors.product_id" />
                            <v-text-field v-model.number="form.quantity" type="number" label="Quantity" min="1" max="100" :error-messages="form.errors.quantity" />
                            <v-sheet color="grey-lighten-4" class="pa-4 rounded-lg mb-4">
                                <div class="d-flex justify-space-between"><span>Total</span><strong>{{ formatCurrency(totalAmount) }}</strong></div>
                            </v-sheet>
                            <v-btn type="submit" color="primary" block :loading="form.processing" :disabled="!products.length">Buy PINs</v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>Purchase History</v-card-title>
                    <v-table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th class="text-right">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders?.data || []" :key="order.id">
                                <td>{{ order.reference }}</td>
                                <td>{{ order.product?.name }}</td>
                                <td>{{ order.quantity }}</td>
                                <td><v-chip size="small" :color="order.status === 'completed' ? 'success' : order.status === 'failed' ? 'error' : 'warning'">{{ order.status }}</v-chip></td>
                                <td class="text-right">{{ formatCurrency(order.total_amount) }}</td>
                                <td class="text-right"><v-btn size="small" variant="text" :href="`/customer/result-pins/${order.id}`">View</v-btn></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

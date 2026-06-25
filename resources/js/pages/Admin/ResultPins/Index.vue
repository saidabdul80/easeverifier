<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    products: any[];
    orders: { data: any[] };
    providerAccount?: any;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
const purchaseForm = useForm({
    product_id: props.products[0]?.id ?? null,
    quantity: 1,
});
const syncForm = useForm({});
const priceDialog = ref(false);
const selectedProductForPrice = ref<any>(null);
const priceForm = useForm({
    price: 0,
    is_active: true,
});

const selectedProduct = computed(() => props.products.find((product) => product.id === Number(purchaseForm.product_id)));
const totalAmount = computed(() => Number(selectedProduct.value?.price ?? 0) * Number(purchaseForm.quantity || 0));
const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const syncProducts = () => syncForm.post('/admin/result-pins/sync');
const purchase = () => purchaseForm.post('/admin/result-pins/purchase');
const openPriceDialog = (product: any) => {
    selectedProductForPrice.value = product;
    priceForm.price = Number(product.price || 0);
    priceForm.is_active = Boolean(product.is_active);
    priceDialog.value = true;
};
const updatePrice = () => {
    priceForm.put(`/admin/result-pins/products/${selectedProductForPrice.value.id}`, {
        onSuccess: () => { priceDialog.value = false; },
    });
};
</script>

<template>
    <Head title="Result PINs - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <v-alert v-if="flash?.success" type="success" variant="tonal" closable class="mb-4">{{ flash.success }}</v-alert>
        <v-alert v-if="flash?.error" type="error" variant="tonal" closable class="mb-4">{{ flash.error }}</v-alert>

        <div class="d-flex flex-column flex-sm-row align-sm-center mb-6 ga-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Result PINs</h1>
                <p class="text-body-2 text-grey">Sync provider products and purchase PINs from NaijaResultPins.</p>
            </div>
            <v-spacer />
            <v-btn color="secondary" :loading="syncForm.processing" @click="syncProducts">Sync Products</v-btn>
        </div>

        <v-row class="mb-6">
            <v-col cols="12" md="4">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Local Products</p>
                        <p class="text-h3 font-weight-bold mb-0">{{ products.length }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>Provider Account</v-card-title>
                    <v-card-text>
                        <pre class="text-caption mb-0">{{ providerAccount || 'Provider account unavailable. Check API token/config.' }}</pre>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>Purchase From Provider</v-card-title>
                    <v-card-text>
                        <v-form @submit.prevent="purchase">
                            <v-select v-model="purchaseForm.product_id" :items="products" item-title="name" item-value="id" label="Product" :error-messages="purchaseForm.errors.product_id" />
                            <v-text-field v-model.number="purchaseForm.quantity" type="number" label="Quantity" min="1" max="100" :error-messages="purchaseForm.errors.quantity" />
                            <v-sheet color="grey-lighten-4" class="pa-4 rounded-lg mb-4">
                                <div class="d-flex justify-space-between"><span>Customer Value</span><strong>{{ formatCurrency(totalAmount) }}</strong></div>
                            </v-sheet>
                            <v-btn type="submit" color="primary" block :loading="purchaseForm.processing" :disabled="!products.length">Purchase PINs</v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="8">
                <v-card class="mb-6">
                    <v-card-title>Product Pricing</v-card-title>
                    <v-table>
                        <thead><tr><th>Product</th><th>Provider Cost</th><th>Selling Price</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id">
                                <td>{{ product.name }}</td>
                                <td>{{ formatCurrency(product.cost_price) }}</td>
                                <td class="font-weight-bold">{{ formatCurrency(product.price) }}</td>
                                <td><v-chip size="small" :color="product.is_active ? 'success' : 'error'">{{ product.is_active ? 'Active' : 'Inactive' }}</v-chip></td>
                                <td class="text-right"><v-btn size="small" variant="text" @click="openPriceDialog(product)">Set Price</v-btn></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>

                <v-card>
                    <v-card-title>Recent PIN Orders</v-card-title>
                    <v-table>
                        <thead><tr><th>Reference</th><th>Channel</th><th>Product</th><th>Qty</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="order in orders?.data || []" :key="order.id">
                                <td>{{ order.reference }}</td>
                                <td>{{ order.channel }}</td>
                                <td>{{ order.product?.name }}</td>
                                <td>{{ order.quantity }}</td>
                                <td><v-chip size="small" :color="order.status === 'completed' ? 'success' : order.status === 'failed' ? 'error' : 'warning'">{{ order.status }}</v-chip></td>
                                <td class="text-right">{{ formatCurrency(order.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="priceDialog" max-width="420">
            <v-card>
                <v-card-title>Set Product Price</v-card-title>
                <v-card-text>
                    <p class="text-body-2 text-grey-darken-1 mb-4">{{ selectedProductForPrice?.name }}</p>
                    <v-text-field v-model.number="priceForm.price" label="Selling price (NGN)" type="number" min="0" :error-messages="priceForm.errors.price" />
                    <v-switch v-model="priceForm.is_active" label="Available to users" color="primary" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="priceDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="priceForm.processing" @click="updatePrice">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>

<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppButton from '@/components/AppButton.vue';
import AppCard from '@/components/AppCard.vue';
import AppEmptyState from '@/components/AppEmptyState.vue';
import AppTable from '@/components/AppTable.vue';
import { computed, ref } from 'vue';

interface PinOrder {
    id: number;
    reference: string;
    channel: string;
    quantity: number;
    total_amount: number;
    status: string;
    product?: { name: string } | null;
    user?: { name: string; email: string } | null;
    buyer_name?: string | null;
    buyer_email?: string | null;
    buyer_phone?: string | null;
    provider_reference?: string | null;
    provider_response?: { payment_status?: string; payment_channel?: string; payment_gateway?: string } | null;
    error_message?: string | null;
    pins?: { pin: string; serial_no?: string }[] | null;
}

const props = defineProps<{
    products: any[];
    orders: { data: PinOrder[] };
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
const pinDetailsDialog = ref(false);
const selectedOrder = ref<PinOrder | null>(null);
const selectedProductForPrice = ref<any>(null);
const priceForm = useForm({
    price: 0,
    is_active: true,
});

const selectedProduct = computed(() => props.products.find((product) => product.id === Number(purchaseForm.product_id)));
const totalAmount = computed(() => Number(selectedProduct.value?.price ?? 0) * Number(purchaseForm.quantity || 0));
const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
const paymentStatus = (order: any) => order.provider_response?.payment_status ?? (order.status === 'completed' ? 'success' : 'pending');
const paymentChannel = (order: any) => order.provider_response?.payment_channel ?? order.provider_response?.payment_gateway ?? 'gateway';
const statusColor = (status: string) => status === 'completed' || status === 'success' ? 'success' : status === 'failed' ? 'error' : 'warning';

const syncProducts = () => syncForm.post('/admin/result-pins/sync');
const purchase = () => purchaseForm.post('/admin/result-pins/purchase');
const openPinDetails = (order: PinOrder) => {
    selectedOrder.value = order;
    pinDetailsDialog.value = true;
};
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
                <p class="text-body-2 text-grey">Sync provider products, pay online, and manage result checker PIN orders.</p>
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
                    <v-card-title>Buy PINs</v-card-title>
                    <v-card-text>
                        <v-form @submit.prevent="purchase">
                            <v-select v-model="purchaseForm.product_id" :items="products" item-title="name" item-value="id" label="Product" :error-messages="purchaseForm.errors.product_id" />
                            <v-text-field
                                v-model.number="purchaseForm.quantity"
                                type="number"
                                label="Quantity"
                                :min="selectedProduct?.min_quantity ?? 1"
                                :max="selectedProduct?.max_quantity ?? 100"
                                :error-messages="purchaseForm.errors.quantity"
                            />
                            <v-sheet color="grey-lighten-4" class="pa-4 rounded-lg mb-4">
                                <div class="d-flex justify-space-between"><span>Total</span><strong>{{ formatCurrency(totalAmount) }}</strong></div>
                            </v-sheet>
                            <v-btn type="submit" color="primary" block :loading="purchaseForm.processing" :disabled="!products.length">Proceed to Payment</v-btn>
                            <p class="text-caption text-grey-darken-1 text-center mt-3 mb-0">
                                PINs are purchased from the provider after payment confirmation.
                            </p>
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
                        <thead><tr><th>Reference</th><th>Channel</th><th>Product</th><th>Qty</th><th>Payment</th><th>Status</th><th class="text-right">Amount</th><th>Details</th></tr></thead>
                        <tbody>
                            <tr v-for="order in orders?.data || []" :key="order.id">
                                <td>{{ order.reference }}</td>
                                <td>{{ order.channel }}</td>
                                <td>{{ order.product?.name }}</td>
                                <td>{{ order.quantity }}</td>
                                <td>
                                    <v-chip size="small" :color="statusColor(paymentStatus(order))">
                                        {{ paymentChannel(order) }} · {{ paymentStatus(order) }}
                                    </v-chip>
                                </td>
                                <td><v-chip size="small" :color="statusColor(order.status)">{{ order.status }}</v-chip></td>
                                <td class="text-right">{{ formatCurrency(order.total_amount) }}</td>
                                <td>
                                    <AppButton size="small" variant="text" :aria-label="`View PIN details for ${order.reference}`" @click="openPinDetails(order)">
                                        View PIN details
                                    </AppButton>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="pinDetailsDialog" max-width="760" scrollable aria-labelledby="pin-details-title" @after-leave="selectedOrder = null">
            <AppCard v-if="selectedOrder">
                <template #title><span id="pin-details-title">PIN details</span></template>
                <p class="text-subtitle-1 font-weight-bold mb-1">{{ selectedOrder.product?.name || 'Result PIN order' }}</p>
                <p class="text-body-2 text-break mb-4">Reference: {{ selectedOrder.reference }}</p>
                <v-list density="compact" class="mb-4">
                    <v-list-item title="Status" :subtitle="selectedOrder.status" />
                    <v-list-item title="Quantity" :subtitle="String(selectedOrder.quantity)" />
                    <v-list-item title="Amount" :subtitle="formatCurrency(selectedOrder.total_amount)" />
                    <v-list-item title="Buyer" :subtitle="selectedOrder.buyer_name || selectedOrder.user?.name || 'N/A'" />
                    <v-list-item title="Email" :subtitle="selectedOrder.buyer_email || selectedOrder.user?.email || 'N/A'" />
                    <v-list-item title="Phone" :subtitle="selectedOrder.buyer_phone || 'N/A'" />
                    <v-list-item title="Provider reference" :subtitle="selectedOrder.provider_reference || 'N/A'" />
                </v-list>
                <v-alert v-if="selectedOrder.status === 'failed'" type="error" variant="tonal" class="mb-4">
                    {{ selectedOrder.error_message || 'This PIN order failed. Contact support with the order reference.' }}
                </v-alert>
                <AppTable v-if="selectedOrder.pins?.length" :caption="`PINs for ${selectedOrder.reference}`">
                    <thead><tr><th scope="col">#</th><th scope="col">PIN</th><th scope="col">Serial number</th></tr></thead>
                    <tbody>
                        <tr v-for="(pin, index) in selectedOrder.pins" :key="index">
                            <td>{{ index + 1 }}</td>
                            <td class="font-weight-bold text-break">{{ pin.pin || 'N/A' }}</td>
                            <td class="text-break">{{ pin.serial_no || 'N/A' }}</td>
                        </tr>
                    </tbody>
                </AppTable>
                <AppEmptyState v-else title="No PINs available" description="No PINs have been issued for this order yet." />
                <template #actions>
                    <v-spacer />
                    <AppButton variant="text" @click="pinDetailsDialog = false">Close</AppButton>
                </template>
            </AppCard>
        </v-dialog>

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

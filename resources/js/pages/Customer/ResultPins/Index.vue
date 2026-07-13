<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed, onMounted, ref } from 'vue';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const props = defineProps<{
    products: any[];
    orders: { data: any[] };
    walletBalance: number;
    referral: {
        code?: string | null;
        link?: string | null;
        bonus_amount: number;
        completed_orders: number;
        total_earned: number;
    };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
const copied = ref(false);
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

const startGuide = () => {
    driver({
        showProgress: true,
        steps: [
            {
                element: '[data-guide="result-pin-buy"]',
                popover: {
                    title: 'Buy result PINs',
                    description: 'Select the board, enter quantity, and pay directly from your wallet.',
                },
            },
            {
                element: '[data-guide="referral-kit"]',
                popover: {
                    title: 'Referral link',
                    description: 'Make your candidates/students use this link to purchase result pin and earn.',
                },
            },
            {
                element: '[data-guide="purchase-history"]',
                popover: {
                    title: 'Track purchases',
                    description: 'Completed purchases appear here. Open an order to copy the PIN and serial.',
                },
            },
        ],
    }).drive();
};

const copyReferralLink = async () => {
    if (!props.referral?.link || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(props.referral.link);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1800);
};

onMounted(() => {
    const guideKey = 'result-pin-referral-guide-count';
    const guideCount = Number(window.localStorage.getItem(guideKey) || 0);

    if (guideCount < 3) {
        startGuide();
        window.localStorage.setItem(guideKey, String(guideCount + 1));
    }
});
</script>

<template>
    <Head title="Result PINs - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <v-alert v-if="flash?.success" type="success" variant="tonal" closable class="mb-4">{{ flash.success }}</v-alert>
        <v-alert v-if="flash?.error" type="error" variant="tonal" closable class="mb-4">{{ flash.error }}</v-alert>

        <div class="d-flex flex-column flex-sm-row align-sm-center mb-6 ga-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Result PINs</h1>
                <p class="text-body-2 text-grey">Buy PINs and earn when candidates purchase through your link.</p>
            </div>
            <v-spacer />
            <v-btn variant="tonal" color="primary" prepend-icon="mdi-help-circle-outline" @click="startGuide">Guide</v-btn>
            <v-chip color="primary" size="large">Wallet: {{ formatCurrency(walletBalance) }}</v-chip>
        </div>

        <v-row class="mb-6">
            <v-col cols="12" lg="8">
                <v-card data-guide="referral-kit" class="referral-card" elevation="0">
                    <v-card-text class="pa-5">
                        <div class="d-flex flex-column flex-md-row align-md-center ga-4">
                            <div class="flex-grow-1">
                                <v-chip color="secondary" variant="flat" class="mb-3">Earn {{ formatCurrency(referral.bonus_amount) }} per successful purchase</v-chip>
                                <h2 class="text-h5 font-weight-bold mb-2">Your result PIN referral link</h2>
                                <p class="text-body-2 mb-4">Make your candidates/students use this link to purchase result pin and earn.</p>
                                <v-text-field :model-value="referral.link" readonly hide-details variant="solo-filled" density="comfortable" prepend-inner-icon="mdi-link-variant" />
                            </div>
                            <div class="referral-actions">
                                <v-btn color="secondary" size="large" block prepend-icon="mdi-content-copy" @click="copyReferralLink">
                                    {{ copied ? 'Copied' : 'Copy Link' }}
                                </v-btn>
                                <v-btn :href="referral.link || '#'" target="_blank" variant="outlined" color="white" block prepend-icon="mdi-open-in-new">Open Link</v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" lg="4">
                <v-card class="h-100" elevation="0">
                    <v-card-text class="pa-5">
                        <p class="text-caption text-grey mb-1">Referral earnings</p>
                        <h2 class="text-h4 font-weight-bold mb-1">{{ formatCurrency(referral.total_earned) }}</h2>
                        <p class="text-body-2 text-grey mb-0">{{ referral.completed_orders }} completed referred purchase{{ referral.completed_orders === 1 ? '' : 's' }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" md="4">
                <v-card data-guide="result-pin-buy">
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
                <v-card data-guide="purchase-history">
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
                                <td class="text-right"><v-btn size="small" variant="text" :href="`/customer/result-pins/${order.reference}`">View</v-btn></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

<style scoped>
.referral-card {
    overflow: hidden;
    border: 1px solid rgba(244, 199, 76, 0.28);
    border-radius: 28px;
    background:
        radial-gradient(circle at 86% 20%, rgba(244, 199, 76, 0.28), transparent 28%),
        linear-gradient(135deg, #0f3e20, #082716);
    color: #fff;
}

.referral-card :deep(.v-field) {
    color: #102319;
}

.referral-actions {
    display: grid;
    gap: 0.75rem;
    min-width: 180px;
}
</style>

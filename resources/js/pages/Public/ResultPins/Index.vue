<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

interface Product {
    id: number;
    name: string;
    board?: string;
    price: number;
    min_quantity: number;
    max_quantity: number;
}

const props = defineProps<{
    products: Product[];
    selectedProductId?: number | null;
    prefillEmail?: string | null;
    referral?: {
        code: string;
        customer_name: string;
        help_text: string;
    } | null;
}>();

const initialProductId = props.products.some((product) => product.id === Number(props.selectedProductId))
    ? Number(props.selectedProductId)
    : props.products[0]?.id ?? null;

const form = useForm({
    email: props.prefillEmail ?? '',
    phone: '',
    product_id: initialProductId,
    quantity: 1,
    referral_code: props.referral?.code ?? null,
});

const selectedProduct = computed(() => props.products.find((product) => product.id === Number(form.product_id)));
const totalAmount = computed(() => (Number(selectedProduct.value?.price ?? 0) * Number(form.quantity || 0)));

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const submit = () => {
    form.post('/result-pins/purchase');
};

const selectProduct = (product: Product) => {
    form.product_id = product.id;
};

onMounted(() => {
    if (!props.referral) {
        return;
    }

    const guideKey = `result-pin-kit-guide-count-${props.referral.code}`;
    const guideCount = Number(window.localStorage.getItem(guideKey) || 0);

    if (guideCount >= 3) {
        return;
    }

    driver({
        showProgress: true,
        steps: [
            {
                element: '[data-guide="kit-products"]',
                popover: {
                    title: 'Choose result PIN',
                    description: 'Pick the result checker product your school or candidate needs.',
                },
            },
            {
                element: '[data-guide="kit-checkout"]',
                popover: {
                    title: 'Pay securely',
                    description: 'Enter email and phone, then continue to payment. PIN and serial will be available after payment.',
                },
            },
        ],
    }).drive();

    window.localStorage.setItem(guideKey, String(guideCount + 1));
});
</script>

<template>
    <Head title="Buy Result Checker PINs - EaseVerifier" />

    <v-app>
        <v-app-bar flat color="white" class="pin-nav">
            <v-container class="d-flex align-center">
                <Link href="/" class="text-decoration-none d-flex align-center">
                    <v-avatar color="white" size="36" class="mr-2 brand-avatar">
                        <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
                    </v-avatar>
                    <span class="text-h6 font-weight-bold brand-text">EaseVerifier</span>
                </Link>
                <v-spacer />
                <v-btn variant="text" color="primary" href="/result-pins/my-pins">My PINs</v-btn>
                <v-btn variant="outlined" color="secondary" href="/login">Login</v-btn>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="pin-hero" >
                <v-container style="max-width: 100%;">
                    <v-row align="center" justify="center" class="pin-hero-row">
                        <v-col cols="12" md="5">
                            <div class="hero-copy-block">
                                <v-chip color="secondary" class="mb-4 font-weight-bold">{{ referral ? 'Referral kit' : 'Instant PIN delivery' }}</v-chip>
                                <h1>{{'Buy result PINs.' }}</h1>
                                <p>{{ referral ? 'Choose a PIN, pay online, and access your PIN dashboard immediately after payment.' : 'Choose a product, pay online, receive the PIN and serial in your email.' }}</p>
                                <div v-if="referral" class="referral-note mt-5">
                                    <v-icon color="secondary">mdi-account-cash-outline</v-icon>
                                    <span>{{ referral.customer_name }} earns when this purchase succeeds.</span>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-card class="purchase-card" elevation="0" data-guide="kit-checkout">
                                <v-card-text class="pa-6">
                                
                                    <div class="product-strip mb-5" data-guide="kit-products">
                                        <button
                                            v-for="product in products"
                                            :key="product.id"
                                            type="button"
                                            :class="{ active: Number(form.product_id) === product.id }"
                                            @click="selectProduct(product)"
                                        >
                                            <span>{{ product.board?.toUpperCase() || product.name }}</span>
                                            <strong>{{ formatCurrency(product.price) }}</strong>
                                        </button>
                                    </div>
                                    <v-alert v-if="$page.props.flash?.error" type="error" variant="tonal" class="mb-4">{{ $page.props.flash.error }}</v-alert>
                                    <v-form @submit.prevent="submit">
                                        <!-- <v-select
                                            v-model="form.product_id"
                                            :items="products"
                                            item-title="name"
                                            item-value="id"
                                            label="Exam Type"
                                            :error-messages="form.errors.product_id"
                                            required
                                        /> no need for this since there selectable card -->
                                        <input v-model="form.referral_code" type="hidden" />
                                        <v-text-field v-model="form.email" label="Email address" type="email" :error-messages="form.errors.email" required />
                                        <v-text-field v-model="form.phone" label="Phone number" :error-messages="form.errors.phone" required />
                                        <v-text-field
                                            v-model.number="form.quantity"
                                            label="Quantity"
                                            type="number"
                                            :min="selectedProduct?.min_quantity ?? 1"
                                            :max="selectedProduct?.max_quantity ?? 100"
                                            :error-messages="form.errors.quantity"
                                            required
                                        />
                                        <v-btn type="submit" color="secondary" size="large" block class="purchase-btn" :loading="form.processing" :disabled="!products.length">
                                            Proceed to Payment
                                        </v-btn>
                                        <p class="text-caption text-grey-darken-1 text-center mt-3 mb-0">
                                            Secure payment. Instant order access after confirmation.
                                        </p>
                                    </v-form>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.pin-nav {
    border-bottom: 1px solid rgba(15, 62, 32, 0.08);
}

.brand-avatar {
    border: 1px solid rgba(15, 62, 32, 0.08);
}

.brand-text {
    color: #0f3e20;
}

.pin-hero {
    min-height: 92.5vh;
    padding: 2rem 0 1rem;
    background:
        radial-gradient(circle at 18% 18%, rgba(255, 203, 31, 0.28), transparent 28%),
        radial-gradient(circle at 84% 24%, rgba(46, 160, 90, 0.32), transparent 30%),
        linear-gradient(135deg, #0f3e20 0%, #082716 58%, #04150d 100%);
}

.pin-hero-row {
    min-height: calc(100vh - 11rem);
}

.hero-copy-block h1 {
    max-width: 8ch;
    margin: 0 0 1rem;
    color: #fff;
    font-size: clamp(3rem, 7vw, 5.8rem);
    line-height: 0.9;
    letter-spacing: -0.06em;
}

.hero-copy-block p {
    max-width: 30rem;
    color: rgba(255, 255, 255, 0.76);
    font-size: 1.08rem;
}

.referral-note {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    max-width: 27rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(244, 199, 76, 0.24);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.82);
    font-weight: 700;
}

.purchase-card {
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 32px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 34px 90px rgba(0, 0, 0, 0.28);
    backdrop-filter: blur(18px);
}

.selected-pin-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-radius: 22px;
    background: linear-gradient(135deg, #0d3a1c, #176c38);
}

.selected-pin-summary span {
    overflow: hidden;
    color: rgba(255, 255, 255, 0.74);
    font-size: 0.82rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.selected-pin-summary strong {
    color: #f4c74c;
    font-size: 1.35rem;
    white-space: nowrap;
}

.product-strip {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    max-height: 330px;
    overflow: auto;
    padding-right: 0.15rem;
}

.product-strip button {
    display: grid;
    gap: 0.25rem;
    min-width: 0;
    padding: 0.85rem;
    border: 1px solid rgba(15, 62, 32, 0.08);
    border-radius: 18px;
    background: #f4f8f0;
    color: #102319;
    text-align: left;
    transition: all 0.18s ease;
}

.product-strip button.active {
    border-color: rgba(244, 199, 76, 0.75);
    background: #0f3e20;
    color: #fff;
    transform: translateY(-1px);
}

.product-strip span {
    overflow: hidden;
    font-size: 0.76rem;
    font-weight: 900;
    letter-spacing: 0.07em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.product-strip strong {
    color: #d7a917;
    font-size: 1rem;
}

.purchase-btn {
    border-radius: 999px;
    color: #06170d !important;
    font-weight: 900;
    text-transform: none;
}

@media (max-width: 960px) {
    .pin-hero-row {
        min-height: auto;
    }

    .hero-copy-block h1 {
        max-width: none;
    }
}
</style>

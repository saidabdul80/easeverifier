<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
}>();

const initialProductId = props.products.some((product) => product.id === Number(props.selectedProductId))
    ? Number(props.selectedProductId)
    : props.products[0]?.id ?? null;

const form = useForm({
    email: '',
    phone: '',
    product_id: initialProductId,
    quantity: 1,
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
                <v-btn variant="text" color="primary" href="/result-pins/login">My PINs</v-btn>
                <v-btn variant="outlined" color="secondary" href="/login">Login</v-btn>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="pin-hero">
                <v-container>
                    <v-row align="center" justify="center" class="pin-hero-row">
                        <v-col cols="12" md="5">
                            <div class="hero-copy-block">
                                <v-chip color="secondary" class="mb-4 font-weight-bold">Instant PIN delivery</v-chip>
                                <h1>Buy result PINs.</h1>
                                <p>Choose a product, pay online, receive the PIN and serial in your email.</p>
                            </div>
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-card class="purchase-card" elevation="0">
                                <v-card-text class="pa-6">
                                    <div class="selected-pin-summary mb-5">
                                        <span>{{ selectedProduct?.board?.toUpperCase() || 'Selected PIN' }}</span>
                                        <strong>{{ formatCurrency(totalAmount) }}</strong>
                                    </div>
                                    <v-alert v-if="$page.props.flash?.error" type="error" variant="tonal" class="mb-4">{{ $page.props.flash.error }}</v-alert>
                                    <v-form @submit.prevent="submit">
                                        <v-select
                                            v-model="form.product_id"
                                            :items="products"
                                            item-title="name"
                                            item-value="id"
                                            label="Exam Type"
                                            :error-messages="form.errors.product_id"
                                            required
                                        />
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
    min-height: 100vh;
    padding: 5.5rem 0 4rem;
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

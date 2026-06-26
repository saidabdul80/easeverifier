<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    email: string;
    orders: any[];
    highlightReference?: string | null;
}>();

const primaryOrder = computed(() => {
    return props.orders.find((order) => order.reference === props.highlightReference)
        || props.orders.find((order) => order.status === 'completed')
        || props.orders[0];
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const orderUrl = (order: any) => `/result-pins/orders/${order.reference}`;
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
                        <h1 class="text-h4 font-weight-bold mb-1">My Result PINs</h1>
                        <p class="text-body-2 text-grey-darken-1 mb-0">{{ email }}</p>
                    </div>
                    <v-spacer />
                    <v-btn href="/result-pins" color="primary">Buy PINs</v-btn>
                </div>

                <Link v-if="primaryOrder" :href="orderUrl(primaryOrder)" class="text-decoration-none">
                    <section class="ready-card mb-6">
                        <div class="ready-icon">
                            <v-icon size="34">mdi-ticket-confirmation</v-icon>
                        </div>
                        <div class="ready-copy">
                            <p>{{ primaryOrder.status === 'completed' ? 'Payment confirmed' : 'Order created' }}</p>
                            <h2>{{ primaryOrder.status === 'completed' ? primaryOrder.product?.name : 'Open your order details.' }}</h2>
                            <span>Reference• {{ primaryOrder.reference }}</span>
                        </div>
                        <v-spacer />
                        <div class="ready-meta">
                            <strong>{{ formatCurrency(primaryOrder.total_amount) }}</strong>
                            <v-btn color="secondary" size="large" class="ready-action">
                                View PIN + Serial
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </div>
                    </section>
                </Link>

                <v-card class="orders-card">
                    <v-card-text class="pa-5 pb-3">
                        <div class="d-flex align-center ga-3">
                            <v-avatar color="secondary" size="42">
                                <v-icon color="primary">mdi-ticket-confirmation</v-icon>
                            </v-avatar>
                            <div>
                                <h2 class="text-h6 font-weight-bold mb-1">All purchases</h2>
                                <p class="text-body-2 text-grey-darken-1 mb-0">Tap any order row to view its PIN and serial.</p>
                            </div>
                        </div>
                    </v-card-text>
                    <div class="order-list">
                        <Link
                            v-for="order in orders"
                            :key="order.id"
                            :href="orderUrl(order)"
                            class="order-list-card text-decoration-none"
                            :class="{ featured: order.reference === primaryOrder?.reference }"
                        >
                            <div>
                                <div class="d-flex align-center ga-2 mb-2">
                                    <v-chip v-if="order.reference === primaryOrder?.reference" color="secondary" size="small" variant="flat">Latest</v-chip>
                                    <v-chip size="small" :color="order.status === 'completed' ? 'success' : order.status === 'failed' ? 'error' : 'warning'">{{ order.status }}</v-chip>
                                </div>
                                <p>{{ order.product?.name }}</p>
                                <span>{{ order.reference }}</span>
                            </div>
                            <div class="order-list-side">
                                <strong>{{ formatCurrency(order.total_amount) }}</strong>
                                <small>{{ order.quantity }} PIN{{ Number(order.quantity) === 1 ? '' : 's' }}</small>
                                <v-icon color="primary">mdi-arrow-right</v-icon>
                            </div>
                        </Link>
                    </div>
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

.orders-card {
    overflow: hidden;
    border: 1px solid rgba(22, 120, 62, 0.1);
    border-radius: 28px;
    box-shadow: 0 24px 70px rgba(20, 73, 39, 0.08);
}

.ready-card {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    padding: 1.35rem;
    border: 1px solid rgba(244, 199, 76, 0.34);
    border-radius: 32px;
    background: linear-gradient(135deg, #123f22, #0b2d18);
    box-shadow: 0 28px 80px rgba(20, 73, 39, 0.18);
    color: #fff;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.ready-card:hover {
    box-shadow: 0 34px 90px rgba(20, 73, 39, 0.24);
    transform: translateY(-2px);
}

.ready-icon {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    border-radius: 22px;
    background: #f4c74c;
    color: #0f3e20;
    flex: 0 0 auto;
}

.ready-copy p {
    margin: 0 0 0.2rem;
    color: #f4c74c;
    font-size: 0.75rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.ready-copy h2 {
    margin: 0 0 0.3rem;
    color: #fff;
    font-size: clamp(1.6rem, 4vw, 2.7rem);
    line-height: 0.98;
    letter-spacing: -0.04em;
}

.ready-copy span {
    color: rgba(255, 255, 255, 0.68);
}

.ready-meta {
    display: grid;
    justify-items: end;
    gap: 0.8rem;
    flex: 0 0 auto;
}

.ready-meta strong {
    color: #f4c74c;
    font-size: 1.35rem;
}

.ready-action {
    color: #071f10 !important;
    border-radius: 999px;
    font-weight: 900;
    text-transform: none;
}

.order-list {
    display: grid;
    gap: 0.8rem;
    padding: 0 1.25rem 1.25rem;
}

.order-list-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.05rem 1.2rem;
    border: 1px solid rgba(22, 120, 62, 0.1);
    border-radius: 24px;
    background: #f8fbf5;
    color: #102319;
    transition: border-color 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
}

.order-list-card:hover {
    border-color: rgba(22, 120, 62, 0.22);
    box-shadow: 0 16px 44px rgba(20, 73, 39, 0.08);
    transform: translateY(-1px);
}

.order-list-card.featured {
    border-color: rgba(244, 199, 76, 0.7);
    background: #fff8df;
}

.order-list-card p {
    margin: 0 0 0.2rem;
    color: #102319;
    font-weight: 800;
}

.order-list-card span {
    color: rgba(16, 35, 25, 0.62);
    font-size: 0.9rem;
    font-weight: 700;
}

.order-list-side {
    display: grid;
    justify-items: end;
    gap: 0.25rem;
    min-width: 120px;
}

.order-list-side strong {
    color: #0f3e20;
    font-weight: 900;
    font-size: 1.15rem;
}

.order-list-side small {
    color: rgba(16, 35, 25, 0.56);
    font-weight: 800;
}

@media (max-width: 760px) {
    .ready-card {
        align-items: stretch;
        flex-direction: column;
    }

    .ready-meta {
        justify-items: stretch;
    }

    .order-list {
        padding-inline: 1rem;
    }

    .order-list-card {
        align-items: stretch;
        flex-direction: column;
    }

    .order-list-side {
        align-items: center;
        display: flex;
        justify-content: space-between;
        min-width: 0;
    }
}
</style>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    order: any;
}>();

const statusColor = computed(() => {
    if (props.order.status === 'completed') {
        return 'success';
    }

    if (props.order.status === 'failed') {
        return 'error';
    }

    return 'warning';
});

const copyValue = async (value?: string) => {
    if (!value || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(value);
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const downloadPins = () => {
    if (!props.order.pins?.length) {
        return;
    }

    const lines = [
        'EaseVerifier Result PINs',
        `Product: ${props.order.product?.name || '-'}`,
        `Order reference: ${props.order.reference}`,
        `Buyer email: ${props.order.buyer_email || '-'}`,
        `Buyer phone: ${props.order.buyer_phone || '-'}`,
        `Amount paid: ${formatCurrency(Number(props.order.total_amount || 0))}`,
        '',
        ...props.order.pins.flatMap((pin: any, index: number) => [
            `PIN ${index + 1}`,
            `PIN: ${pin.pin || '-'}`,
            `Serial Number: ${pin.serial_no || '-'}`,
            '',
        ]),
    ];

    const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = `${props.order.reference}-pins.txt`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Result PIN Order - EaseVerifier" />

    <v-app>
        <v-app-bar flat color="white" elevation="1">
            <v-container class="d-flex align-center">
                <Link href="/" class="text-decoration-none d-flex align-center">
                    <v-avatar color="white" size="36" class="mr-2 brand-avatar">
                        <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
                    </v-avatar>
                    <span class="text-h6 font-weight-bold brand-text">EaseVerifier</span>
                </Link>
                <v-spacer />
                <v-btn variant="text" color="primary" href="/result-pins/my-pins">My PINs</v-btn>
                <v-btn variant="outlined" color="secondary" href="/result-pins">Buy More</v-btn>
            </v-container>
        </v-app-bar>

        <v-main class="order-shell">
            <v-container class="py-10">
                <div class="success-hero">
                    <v-icon :color="order.status === 'failed' ? 'error' : 'secondary'" size="42">
                        {{ order.status === 'failed' ? 'mdi-alert-circle' : 'mdi-check-circle' }}
                    </v-icon>
                    <div>
                        <p class="success-kicker">{{ order.status === 'completed' ? 'Payment confirmed' : 'Order status' }}</p>
                        <h1>{{ order.status === 'completed' ? 'Your result PINs' : order.product?.name }}</h1>
                        <span>Order reference: {{ order.reference }}</span>
                    </div>
                    <v-spacer />
                    <v-chip :color="statusColor" size="large" class="text-capitalize">{{ order.status }}</v-chip>
                </div>

                <v-alert v-if="$page.props.flash?.success" type="success" variant="tonal" class="mb-5">{{ $page.props.flash.success }}</v-alert>
                <v-alert v-if="$page.props.flash?.error" type="error" variant="tonal" class="mb-5">{{ $page.props.flash.error }}</v-alert>

                <v-alert v-if="order.status === 'failed'" type="error" variant="tonal" class="mb-5">
                    {{ order.error_message || 'The order failed. Contact support with your order reference.' }}
                </v-alert>

                <v-row align="start">
                    <v-col cols="12" lg="8">
                        <section class="pins-card">
                            <div class="section-heading">
                                <div>
                                    <p>Your purchased PINs</p>
                                    <h3>{{ order.product?.name }}</h3>
                                </div>
                                <div class="section-actions">
                                    <v-chip color="secondary" variant="flat">{{ order.quantity }} PIN{{ Number(order.quantity) === 1 ? '' : 's' }}</v-chip>
                                    <v-btn
                                        v-if="order.pins?.length"
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-download"
                                        @click="downloadPins"
                                    >
                                        Download TXT
                                    </v-btn>
                                </div>
                            </div>

                            <div v-if="order.pins?.length" class="pins-grid">
                                <article v-for="(pin, index) in order.pins" :key="index" class="pin-card">
                                    <div class="pin-card-top">
                                        <span>PIN {{ index + 1 }}</span>
                                        <v-icon color="secondary">mdi-ticket-confirmation</v-icon>
                                    </div>
                                    <div class="credential-block">
                                        <small>PIN</small>
                                        <strong>{{ pin.pin }}</strong>
                                        <v-btn size="small" variant="tonal" color="primary" @click="copyValue(pin.pin)">Copy PIN</v-btn>
                                    </div>
                                    <div class="credential-block">
                                        <small>Serial number</small>
                                        <strong>{{ pin.serial_no }}</strong>
                                        <v-btn size="small" variant="tonal" color="primary" @click="copyValue(pin.serial_no)">Copy Serial</v-btn>
                                    </div>
                                </article>
                            </div>

                            <v-alert v-else type="info" variant="tonal" class="mt-4">
                                No PINs are available yet for this order. If payment was successful, refresh this page or contact support with your order reference.
                            </v-alert>
                        </section>
                    </v-col>

                    <v-col cols="12" lg="4">
                        <aside class="summary-card">
                            <p class="summary-kicker">Order summary</p>
                            <div class="summary-list">
                                <div>
                                    <span>Email</span>
                                    <strong>{{ order.buyer_email }}</strong>
                                </div>
                                <div>
                                    <span>Phone</span>
                                    <strong>{{ order.buyer_phone }}</strong>
                                </div>
                                <div>
                                    <span>Amount paid</span>
                                    <strong>{{ formatCurrency(order.total_amount) }}</strong>
                                </div>
                                <div>
                                    <span>Provider reference</span>
                                    <strong>{{ order.provider_reference || 'Pending' }}</strong>
                                </div>
                            </div>
                            <v-btn href="/result-pins" color="secondary" block size="large" class="mt-5 summary-action">Buy More PINs</v-btn>
                        </aside>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.brand-avatar {
    border: 1px solid rgba(15, 62, 32, 0.08);
}

.brand-text {
    color: #0f3e20;
}

.order-shell {
    min-height: calc(100vh - 64px);
    background:
        radial-gradient(circle at 12% 16%, rgba(244, 199, 76, 0.18), transparent 26%),
        linear-gradient(135deg, #f8f2df 0%, #eef8f0 100%);
}

.success-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(22, 120, 62, 0.12);
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 24px 70px rgba(20, 73, 39, 0.08);
}

.success-kicker,
.section-heading p,
.summary-kicker {
    margin: 0 0 0.25rem;
    color: #168044;
    font-size: 0.76rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.success-hero h1 {
    margin: 0;
    color: #102319;
    font-size: clamp(1.7rem, 4vw, 3rem);
    line-height: 1;
    letter-spacing: -0.04em;
}

.success-hero span {
    color: rgba(16, 35, 25, 0.58);
    font-size: 0.92rem;
}

.pins-card,
.summary-card {
    border: 1px solid rgba(22, 120, 62, 0.1);
    border-radius: 32px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 70px rgba(20, 73, 39, 0.08);
}

.pins-card {
    padding: 1.35rem;
}

.section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.1rem;
}

.section-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.65rem;
}

.section-actions :deep(.v-btn) {
    border-radius: 999px;
    font-weight: 800;
    text-transform: none;
}

.section-heading h2 {
    max-width: 28rem;
    margin: 0;
    color: #102319;
    font-size: clamp(1.35rem, 3vw, 2.1rem);
    line-height: 1.05;
    letter-spacing: -0.04em;
}

.pins-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.pin-card {
    padding: 1rem;
    border: 1px solid rgba(22, 120, 62, 0.12);
    border-radius: 26px;
    background: #f5f9f2;
    color: #102319;
}

.pin-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.pin-card-top span {
    color: rgba(16, 35, 25, 0.62);
    font-size: 0.74rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.credential-block {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid rgba(22, 120, 62, 0.1);
    border-radius: 20px;
    background: #fff;
}

.credential-block + .credential-block {
    margin-top: 0.75rem;
}

.credential-block small {
    display: block;
    margin-bottom: 0.2rem;
    color: rgba(16, 35, 25, 0.62);
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.credential-block strong {
    overflow-wrap: anywhere;
    color: #0f3e20;
    font-size: clamp(1.15rem, 2vw, 1.55rem);
    letter-spacing: 0.02em;
}

.credential-block :deep(.v-btn) {
    flex: 0 0 auto;
    border-radius: 999px;
    font-weight: 800;
}

.summary-card {
    padding: 1.25rem;
}

.summary-card h3 {
    margin: 0 0 1rem;
    color: #102319;
    font-size: 1.35rem;
    line-height: 1.1;
}

.summary-list {
    display: grid;
    gap: 0.8rem;
}

.summary-list div {
    padding: 0.85rem;
    border-radius: 18px;
    background: #f4f8f0;
}

.summary-list span {
    display: block;
    color: rgba(16, 35, 25, 0.55);
    font-size: 0.78rem;
    font-weight: 800;
}

.summary-list strong {
    overflow-wrap: anywhere;
    color: #102319;
    font-size: 0.95rem;
}

.summary-action {
    border-radius: 999px;
    color: #06170d !important;
    font-weight: 900;
    text-transform: none;
}

@media (max-width: 600px) {
    .success-hero,
    .section-heading {
        flex-direction: column;
    }

    .credential-block {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>

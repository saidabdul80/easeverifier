<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    paygoService: {
        name: string;
        price: number;
        service_name?: string | null;
        customer_name?: string | null;
        verify_url: string;
    };
    intent: {
        reference: string;
        status: string;
        paid_at?: string | null;
        expires_at?: string | null;
        nin_last4?: string | null;
    };
}>();
</script>

<template>
    <Head :title="`${paygoService.name} - Payment Already Paid`" />

    <v-app>
        <v-main class="paygo-main">
            <v-container class="paygo-container">
                <v-row justify="center">
                    <v-col cols="12" md="7" lg="5">
                        <v-card class="paygo-card" elevation="0">
                            <v-card-text class="pa-6">
                                <v-chip color="success" variant="flat" class="mb-4">Payment already paid</v-chip>
                                <h1 class="text-h4 font-weight-bold mb-2">{{ paygoService.name }}</h1>
                                <p class="text-body-2 text-grey-darken-1 mb-5">
                                    This NIN already has a successful unused payment.
                                </p>

                                <v-alert type="success" variant="tonal" class="mb-5">
                                    No new payment is required for this NIN.
                                </v-alert>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.paygo-main {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f3e20 0%, #082716 58%, #04150d 100%);
}

.paygo-container {
    padding-top: 5rem;
    padding-bottom: 3rem;
}

.paygo-card {
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.18);
}
</style>

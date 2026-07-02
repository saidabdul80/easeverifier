<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    paygoService: {
        name: string;
        price: number;
        is_active: boolean;
        service_name?: string | null;
        customer_name?: string | null;
        initiate_url: string;
    };
    prefill?: {
        nin?: string | null;
        phone?: string | null;
    };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as any);

const form = useForm({
    nin: props.prefill?.nin || '',
    phone: props.prefill?.phone || '',
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const submit = () => {
    form.get(props.paygoService.initiate_url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${paygoService.name} - PayGo Verification`" />

    <v-app>
        <v-main class="paygo-main">
            <v-container class="paygo-container">
                <v-row justify="center">
                    <v-col cols="12" md="7" lg="5">
                        <v-card class="paygo-card" elevation="0">
                            <v-card-text class="pa-6">
                                <v-chip color="secondary" variant="flat" class="mb-4">{{ paygoService.service_name || 'NIN Verification' }}</v-chip>
                                <h1 class="text-h4 font-weight-bold mb-2">{{ paygoService.name }}</h1>
                                <p class="text-body-2 text-grey-darken-1 mb-5">
                                    {{ paygoService.customer_name ? `${paygoService.customer_name} requires this verification.` : 'Complete payment to continue with verification.' }}
                                </p>

                                <v-alert v-if="!paygoService.is_active" type="warning" variant="tonal" class="mb-4">
                                    This PayGo service is currently unavailable.
                                </v-alert>
                                <v-alert v-if="flash?.error" type="error" variant="tonal" class="mb-4">{{ flash.error }}</v-alert>

                                <div class="price-strip mb-5">
                                    <span>Amount</span>
                                    <strong>{{ formatCurrency(paygoService.price) }}</strong>
                                </div>

                                <v-form @submit.prevent="submit">
                                    <v-text-field
                                        v-model="form.nin"
                                        label="NIN"
                                        inputmode="numeric"
                                        maxlength="11"
                                        variant="outlined"
                                        :error-messages="form.errors.nin"
                                        required
                                    />
                                    <v-text-field
                                        v-model="form.phone"
                                        label="Phone number"
                                        variant="outlined"
                                        :error-messages="form.errors.phone"
                                    />
                                    <v-btn
                                        type="submit"
                                        color="secondary"
                                        size="large"
                                        block
                                        class="mt-2"
                                        :loading="form.processing"
                                        :disabled="!paygoService.is_active"
                                    >
                                        Proceed to Payment
                                    </v-btn>
                                </v-form>
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

.price-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-radius: 8px;
    background: #f6f8f6;
}

.price-strip span {
    color: #5f6f65;
}

.price-strip strong {
    color: #0f3e20;
    font-size: 1.25rem;
}
</style>

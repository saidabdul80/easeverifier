<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    service: any;
    price: number;
    walletBalance: number;
}>();

const form = useForm({ search_parameter: '' });
const loading = ref(false);

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const canVerify = () => props.walletBalance >= props.price;

const getPlaceholder = () => {
    const placeholders: Record<string, string> = {
        'nin': 'Enter 11-digit NIN',
        'bvn': 'Enter 11-digit BVN',
        'cac': 'Enter RC Number',
        'drivers-license': 'Enter License Number',
        'passport': 'Enter Passport Number',
    };
    return placeholders[props.service.slug] || 'Enter search parameter';
};

const submit = () => {
    if (!canVerify()) return;
    loading.value = true;
    form.post(`/customer/verify/${props.service.id}`, {
        preserveScroll: true,
        onFinish: () => loading.value = false,
        onError: (errors) => {
            console.error('Form errors:', errors);
        },
    });
};
</script>

<template>
    <Head :title="`${service.name} - EaseVerifier`" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/verify" class="mb-2">Back to Services</v-btn>
            <div class="d-flex align-center">
                <v-avatar color="primary-lighten-5" size="56" class="mr-4">
                    <v-icon color="primary" size="28">{{ service.icon || 'mdi-shield-check' }}</v-icon>
                </v-avatar>
                <div>
                    <h1 class="text-h4 font-weight-bold mb-0">{{ service.name }}</h1>
                    <p class="text-body-2 text-grey">{{ service.description }}</p>
                </div>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>Enter Details</v-card-title>
                    <v-card-text>
                        <v-alert v-if="!canVerify()" type="warning" variant="tonal" class="mb-4">
                            <v-alert-title>Insufficient Balance</v-alert-title>
                            Your wallet balance ({{ formatCurrency(walletBalance) }}) is less than the verification cost ({{ formatCurrency(price) }}).
                            <template #append>
                                <v-btn color="warning" variant="tonal" href="/customer/wallet/fund">Fund Wallet</v-btn>
                            </template>
                        </v-alert>

                        <v-form @submit.prevent="submit">
                            <v-text-field
                                v-model="form.search_parameter"
                                :label="service.name + ' Number'"
                                :placeholder="getPlaceholder()"
                                variant="outlined"
                                :error-messages="form.errors.search_parameter"
                                prepend-inner-icon="mdi-magnify"
                                class="mb-4"
                            />

                            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                                <strong>Cost:</strong> {{ formatCurrency(price) }} will be deducted from your wallet.
                            </v-alert>

                            <v-btn
                                type="submit"
                                color="primary"
                                size="large"
                                block
                                :loading="loading || form.processing"
                                :disabled="!canVerify() || !form.search_parameter"
                            >
                                <v-icon start>mdi-shield-search</v-icon>
                                Verify Now
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>Service Info</v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item>
                                <v-list-item-title class="text-caption">Price</v-list-item-title>
                                <v-list-item-subtitle class="text-h6 font-weight-bold text-primary">{{ formatCurrency(price) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Your Balance</v-list-item-title>
                                <v-list-item-subtitle :class="canVerify() ? 'text-success' : 'text-error'" class="font-weight-medium">{{ formatCurrency(walletBalance) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Response Time</v-list-item-title>
                                <v-list-item-subtitle>~2-5 seconds</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>Tips</v-card-title>
                    <v-card-text>
                        <ul class="text-body-2 text-grey pl-4">
                            <li class="mb-2">Ensure the number is correct before verifying</li>
                            <li class="mb-2">Verification charges are non-refundable</li>
                            <li>Results are stored in your history</li>
                        </ul>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>


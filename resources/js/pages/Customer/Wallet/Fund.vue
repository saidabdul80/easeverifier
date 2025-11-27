<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, computed } from 'vue';

defineProps<{
    user: { name: string; email: string };
    wallet?: any;
}>();

const form = useForm({ amount: 0 });
const selectedAmount = ref<number | null>(null);

const quickAmounts = [1000, 2000, 5000, 10000, 20000, 50000];

const selectAmount = (amount: number) => {
    selectedAmount.value = amount;
    form.amount = amount;
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const submit = () => {
    form.post('/customer/wallet/fund');
};
</script>

<template>
    <Head title="Fund Wallet - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/wallet" class="mb-2">Back to Wallet</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Fund Your Wallet</h1>
            <p class="text-body-2 text-grey">Add funds to your wallet to start verifying</p>
        </div>

        <v-row justify="center">
            <v-col cols="12" md="8" lg="6">
                <v-card>
                    <v-card-text class="pa-6">
                        <!-- Current Balance -->
                        <div class="text-center mb-6 pa-4 bg-grey-lighten-4 rounded-lg">
                            <p class="text-caption text-grey mb-1">Current Balance</p>
                            <p class="text-h4 font-weight-bold text-primary mb-0">{{ formatCurrency(wallet?.total_balance || 0) }}</p>
                        </div>

                        <!-- Quick Amounts -->
                        <p class="text-subtitle-2 font-weight-medium mb-3">Quick Select</p>
                        <v-row class="mb-6">
                            <v-col v-for="amount in quickAmounts" :key="amount" cols="4">
                                <v-btn
                                    block
                                    :variant="selectedAmount === amount ? 'flat' : 'outlined'"
                                    :color="selectedAmount === amount ? 'primary' : undefined"
                                    @click="selectAmount(amount)"
                                >
                                    {{ formatCurrency(amount) }}
                                </v-btn>
                            </v-col>
                        </v-row>

                        <!-- Custom Amount -->
                        <v-text-field
                            v-model="form.amount"
                            label="Enter Amount"
                            type="number"
                            variant="outlined"
                            prepend-inner-icon="mdi-currency-ngn"
                            :error-messages="form.errors.amount"
                            hint="Minimum: ₦100"
                            class="mb-4"
                            @update:model-value="selectedAmount = null"
                        />

                        <!-- Payment Methods -->
                        <p class="text-subtitle-2 font-weight-medium mb-3">Payment Method</p>
                        <v-radio-group model-value="paystack" class="mb-6">
                            <v-card variant="outlined" class="mb-2 pa-3">
                                <v-radio value="paystack">
                                    <template #label>
                                        <div class="d-flex align-center">
                                            <v-icon color="primary" class="mr-2">mdi-credit-card</v-icon>
                                            <div>
                                                <p class="font-weight-medium mb-0">Paystack</p>
                                                <p class="text-caption text-grey">Card, Bank Transfer, USSD</p>
                                            </div>
                                        </div>
                                    </template>
                                </v-radio>
                            </v-card>
                            <v-card variant="outlined" class="pa-3" disabled>
                                <v-radio value="bank" disabled>
                                    <template #label>
                                        <div class="d-flex align-center">
                                            <v-icon color="grey" class="mr-2">mdi-bank</v-icon>
                                            <div>
                                                <p class="font-weight-medium mb-0">Bank Transfer</p>
                                                <p class="text-caption text-grey">Coming soon</p>
                                            </div>
                                        </div>
                                    </template>
                                </v-radio>
                            </v-card>
                        </v-radio-group>

                        <v-btn
                            block
                            color="primary"
                            size="x-large"
                            :loading="form.processing"
                            :disabled="!form.amount || form.amount < 100"
                            @click="submit"
                        >
                            <v-icon start>mdi-wallet-plus</v-icon>
                            Pay {{ formatCurrency(form.amount) }}
                        </v-btn>

                        <p class="text-caption text-grey text-center mt-4">
                            <v-icon size="small">mdi-lock</v-icon>
                            Secured by Paystack. Your payment information is encrypted.
                        </p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>


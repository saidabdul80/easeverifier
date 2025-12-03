<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, onMounted, computed } from 'vue';

interface DedicatedAccount {
    id: number;
    account_number: string;
    account_name: string;
    bank_name: string;
    bank_slug: string;
    active: boolean;
    created_at: string;
}

defineProps<{
    user: { name: string; email: string };
    wallet?: any;
}>();

const page = usePage();
const form = useForm({ amount: 0 });
const selectedAmount = ref<number | null>(null);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const paymentMethod = ref<'paystack' | 'bank'>('paystack');

// DVA State
const dedicatedAccounts = ref<DedicatedAccount[]>([]);
const loadingAccounts = ref(false);
const creatingAccount = ref(false);
const showDVADialog = ref(false);
const preferredBank = ref('wema-bank');
const copiedField = ref<string | null>(null);

const quickAmounts = [1000, 2000, 5000, 10000, 20000, 50000];

const activeAccount = computed(() => 
    dedicatedAccounts.value.find(acc => acc.active)
);

const hasDedicatedAccount = computed(() => !!activeAccount.value);

onMounted(() => {
    // Check for flash messages
    const flash = page.props.flash as any;
    if (flash?.success) {
        successMessage.value = flash.success;
        setTimeout(() => successMessage.value = null, 5000);
    }
    if (flash?.error) {
        errorMessage.value = flash.error;
        setTimeout(() => errorMessage.value = null, 5000);
    }

    // Load dedicated accounts
    fetchDedicatedAccounts();
});

const fetchDedicatedAccounts = async () => {
    loadingAccounts.value = true;
    try {
        const response = await fetch('/customer/payment/dedicated-accounts');
        const data = await response.json();
        if (data.success) {
            dedicatedAccounts.value = data.accounts;
        }
    } catch (error) {
        console.error('Failed to fetch accounts:', error);
    } finally {
        loadingAccounts.value = false;
    }
};

const createDedicatedAccount = () => {
    creatingAccount.value = true;
    router.post('/customer/payment/dedicated-account/create', {
        preferred_bank: preferredBank.value
    }, {
        onSuccess: () => {
            showDVADialog.value = false;
            fetchDedicatedAccounts();
            successMessage.value = 'Dedicated account created successfully!';
            setTimeout(() => successMessage.value = null, 5000);
        },
        onError: (errors) => {
            errorMessage.value = errors.error || 'Failed to create account';
            setTimeout(() => errorMessage.value = null, 5000);
        },
        onFinish: () => {
            creatingAccount.value = false;
        }
    });
};

const copyToClipboard = async (text: string, field: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => copiedField.value = null, 2000);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

const selectAmount = (amount: number) => {
    selectedAmount.value = amount;
    form.amount = amount;
};

const formatCurrency = (amount: number) => 
    new Intl.NumberFormat('en-NG', { 
        style: 'currency', 
        currency: 'NGN', 
        minimumFractionDigits: 0 
    }).format(amount || 0);

const submit = () => {
    if (paymentMethod.value === 'paystack') {
        form.post('/customer/payment/initialize');
    }
};
</script>

<template>
    <Head title="Fund Wallet - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/wallet" class="mb-2">
                Back to Wallet
            </v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Fund Your Wallet</h1>
            <p class="text-body-2 text-grey">Add funds to your wallet to start verifying</p>
        </div>

        <!-- Success/Error Messages -->
        <v-alert
            v-if="successMessage"
            type="success"
            variant="tonal"
            closable
            class="mb-4"
            @click:close="successMessage = null"
        >
            {{ successMessage }}
        </v-alert>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            closable
            class="mb-4"
            @click:close="errorMessage = null"
        >
            {{ errorMessage }}
        </v-alert>

        <v-row>
            <!-- Dedicated Virtual Account Section -->
            <v-col cols="12" lg="5">
                <v-card class="mb-4">
                    <v-card-text class="pa-6">
                        <div class="d-flex align-center justify-space-between mb-4">
                            <div>
                                <h3 class="text-h6 font-weight-bold mb-1">
                                    <v-icon color="primary" class="mr-2">mdi-bank</v-icon>
                                    Dedicated Account
                                </h3>
                                <p class="text-caption text-grey mb-0">
                                    Your personal bank account for instant funding
                                </p>
                            </div>
                        </div>

                        <v-divider class="mb-4"></v-divider>

                        <!-- Loading State -->
                        <div v-if="loadingAccounts" class="text-center py-8">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                            <p class="text-caption text-grey mt-2">Loading account...</p>
                        </div>

                        <!-- No Account State -->
                        <div v-else-if="!hasDedicatedAccount" class="text-center py-6">
                            <v-icon size="64" color="grey-lighten-1" class="mb-3">mdi-bank-outline</v-icon>
                            <h4 class="text-subtitle-1 font-weight-medium mb-2">No Dedicated Account</h4>
                            <p class="text-caption text-grey mb-4">
                                Create a dedicated bank account to receive instant wallet funding via bank transfer
                            </p>
                            <v-btn
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-plus"
                                @click="showDVADialog = true"
                            >
                                Create Account
                            </v-btn>
                        </div>

                        <!-- Account Details -->
                        <div v-else>
                            <v-alert color="success" variant="tonal" class="mb-4">
                                <div class="d-flex align-center">
                                    <v-icon class="mr-2">mdi-check-circle</v-icon>
                                    <span class="text-caption">Account Active</span>
                                </div>
                            </v-alert>

                            <!-- Bank Name -->
                            <div class="mb-4 pa-3 bg-grey-lighten-5 rounded">
                                <p class="text-caption text-grey mb-1">Bank Name</p>
                                <div class="d-flex align-center justify-space-between">
                                    <p class="text-subtitle-1 font-weight-medium mb-0">
                                        {{ activeAccount.bank_name }}
                                    </p>
                                </div>
                            </div>

                            <!-- Account Number -->
                            <div class="mb-4 pa-3 bg-grey-lighten-5 rounded">
                                <p class="text-caption text-grey mb-1">Account Number</p>
                                <div class="d-flex align-center justify-space-between">
                                    <p class="text-h6 font-weight-bold mb-0 font-mono">
                                        {{ activeAccount.account_number }}
                                    </p>
                                    <v-btn
                                        size="small"
                                        variant="text"
                                        :color="copiedField === 'account_number' ? 'success' : 'primary'"
                                        @click="copyToClipboard(activeAccount.account_number, 'account_number')"
                                    >
                                        <v-icon>
                                            {{ copiedField === 'account_number' ? 'mdi-check' : 'mdi-content-copy' }}
                                        </v-icon>
                                    </v-btn>
                                </div>
                            </div>

                            <!-- Account Name -->
                            <div class="mb-4 pa-3 bg-grey-lighten-5 rounded">
                                <p class="text-caption text-grey mb-1">Account Name</p>
                                <div class="d-flex align-center justify-space-between">
                                    <p class="text-subtitle-1 font-weight-medium mb-0">
                                        {{ activeAccount.account_name }}
                                    </p>
                                    <v-btn
                                        size="small"
                                        variant="text"
                                        :color="copiedField === 'account_name' ? 'success' : 'primary'"
                                        @click="copyToClipboard(activeAccount.account_name, 'account_name')"
                                    >
                                        <v-icon>
                                            {{ copiedField === 'account_name' ? 'mdi-check' : 'mdi-content-copy' }}
                                        </v-icon>
                                    </v-btn>
                                </div>
                            </div>

                            <v-alert color="info" variant="tonal" density="compact">
                                <p class="text-caption mb-0">
                                    <v-icon size="small" class="mr-1">mdi-information</v-icon>
                                    Transfer any amount to this account and your wallet will be credited instantly
                                </p>
                            </v-alert>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Payment Form Section -->
            <v-col cols="12" lg="7">
                <v-card>
                    <v-card-text class="pa-6">
                        <!-- Current Balance -->
                        <div class="text-center mb-6 pa-4 bg-grey-lighten-4 rounded-lg">
                            <p class="text-caption text-grey mb-1">Current Balance</p>
                            <p class="text-h4 font-weight-bold text-primary mb-0">
                                {{ formatCurrency(wallet?.total_balance || 0) }}
                            </p>
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
                        <v-radio-group v-model="paymentMethod" class="mb-6">
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
                            <v-card 
                                variant="outlined" 
                                class="pa-3"
                                :class="{ 'opacity-50': !hasDedicatedAccount }"
                            >
                                <v-radio value="bank" :disabled="!hasDedicatedAccount">
                                    <template #label>
                                        <div class="d-flex align-center">
                                            <v-icon :color="hasDedicatedAccount ? 'primary' : 'grey'" class="mr-2">
                                                mdi-bank
                                            </v-icon>
                                            <div>
                                                <p class="font-weight-medium mb-0">Direct Bank Transfer</p>
                                                <p class="text-caption text-grey">
                                                    {{ hasDedicatedAccount ? 'Use your dedicated account' : 'Create dedicated account first' }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                </v-radio>
                            </v-card>
                        </v-radio-group>

                        <!-- Pay Button (only for Paystack) -->
                        <v-btn
                            v-if="paymentMethod === 'paystack'"
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

                        <!-- Bank Transfer Info -->
                        <v-alert
                            v-else-if="paymentMethod === 'bank' && hasDedicatedAccount"
                            color="info"
                            variant="tonal"
                            class="mb-0"
                        >
                            <p class="text-body-2 font-weight-medium mb-2">
                                <v-icon class="mr-1">mdi-information</v-icon>
                                Transfer Instructions
                            </p>
                            <p class="text-caption mb-2">
                                1. Transfer any amount to your dedicated account shown on the left<br>
                                2. Your wallet will be credited automatically within seconds<br>
                                3. No need to click any button - it's automatic!
                            </p>
                        </v-alert>

                        <p class="text-caption text-grey text-center mt-4">
                            <v-icon size="small">mdi-lock</v-icon>
                            Secured by Paystack. Your payment information is encrypted.
                        </p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Create DVA Dialog -->
        <v-dialog v-model="showDVADialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between pa-4">
                    <span class="text-h6 font-weight-bold">Create Dedicated Account</span>
                    <v-btn icon variant="text" size="small" @click="showDVADialog = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>

                <v-divider></v-divider>

                <v-card-text class="pa-6">
                    <v-alert color="info" variant="tonal" density="compact" class="mb-4">
                        <p class="text-caption mb-0">
                            <v-icon size="small" class="mr-1">mdi-information</v-icon>
                            A unique bank account will be created for you. You can transfer money to this account anytime to fund your wallet instantly.
                        </p>
                    </v-alert>

                    <v-select
                        v-model="preferredBank"
                        label="Preferred Bank"
                        :items="[
                            { title: 'Wema Bank', value: 'wema-bank' },
                            { title: 'Titan Trust Bank (Paystack)', value: 'titan-paystack' }
                        ]"
                        variant="outlined"
                        hint="Choose your preferred bank provider"
                        persistent-hint
                    />
                </v-card-text>

                <v-divider></v-divider>

                <v-card-actions class="pa-4">
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="showDVADialog = false">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="creatingAccount"
                        @click="createDedicatedAccount"
                    >
                        <v-icon start>mdi-plus</v-icon>
                        Create Account
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </CustomerLayout>
</template>

<style scoped>
.font-mono {
    font-family: 'Courier New', monospace;
    letter-spacing: 0.05em;
}

.opacity-50 {
    opacity: 0.5;
}
</style>
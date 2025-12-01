<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    transaction: any;
    user: any;
}>();

const page = usePage();

const formatCurrency = (amount: any) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const formatDate = (date: string) => new Date(date).toLocaleString('en-NG', {
    year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
});

const printReceipt = () => {
    window.print();
};

// Auto-print if print query param is present
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === '1') {
        setTimeout(() => printReceipt(), 500);
    }
});
</script>

<template>
    <Head :title="`Transaction ${transaction.reference} - EaseVerifier`" />
    <CustomerLayout :user="($page.props.auth as any)?.user" :wallet="($page.props.auth as any)?.wallet">
        <div class="d-flex align-center mb-6 d-print-none">
            <div>
                <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/transactions" class="mb-2">
                    Back to Transactions
                </v-btn>
                <h1 class="text-h4 font-weight-bold mb-1">Transaction Details</h1>
                <p class="text-body-2 text-grey">Reference: {{ transaction.reference }}</p>
            </div>
            <v-spacer />
            <v-btn color="primary" variant="outlined" prepend-icon="mdi-printer" @click="printReceipt">
                Print Receipt
            </v-btn>
        </div>

        <!-- Receipt Card -->
        <v-card max-width="600" class="mx-auto receipt-card">
            <v-card-text class="pa-6">
                <!-- Header -->
                <div class="text-center mb-6">
                    <v-avatar color="primary" size="64" class="mb-3">
                        <v-icon size="32">mdi-shield-check</v-icon>
                    </v-avatar>
                    <h2 class="text-h5 font-weight-bold">EaseVerifier</h2>
                    <p class="text-body-2 text-grey mb-0">Transaction Receipt</p>
                </div>

                <v-divider class="mb-4" />

                <!-- Transaction Status -->
                <div class="text-center mb-4">
                    <v-chip :color="transaction.type === 'credit' ? 'success' : 'error'" size="large" variant="flat">
                        <v-icon start>{{ transaction.type === 'credit' ? 'mdi-arrow-down-circle' : 'mdi-arrow-up-circle' }}</v-icon>
                        {{ transaction.type === 'credit' ? 'Credit' : 'Debit' }}
                    </v-chip>
                </div>

                <!-- Amount -->
                <div class="text-center mb-6">
                    <p class="text-overline text-grey mb-1">Amount</p>
                    <p :class="transaction.type === 'credit' ? 'text-success' : 'text-error'" class="text-h3 font-weight-bold mb-0">
                        {{ transaction.type === 'credit' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                    </p>
                </div>

                <!-- Details -->
                <v-list class="bg-grey-lighten-5 rounded-lg">
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-tag</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Reference</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ transaction.reference }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-shape</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Category</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold text-capitalize">{{ transaction.category }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-wallet</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Balance After</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ formatCurrency(transaction.balance_after) }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-calendar</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Date & Time</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ formatDate(transaction.created_at) }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item v-if="transaction.description">
                        <template #prepend><v-icon color="grey">mdi-text</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Description</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ transaction.description }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>

                <!-- Verification Details if applicable -->
                <template v-if="transaction.verification_request">
                    <v-divider class="my-4" />
                    <p class="text-overline text-grey mb-2">Verification Details</p>
                    <v-list class="bg-grey-lighten-5 rounded-lg">
                        <v-list-item>
                            <template #prepend><v-icon color="grey">mdi-shield-check</v-icon></template>
                            <v-list-item-title class="text-caption text-grey">Service</v-list-item-title>
                            <v-list-item-subtitle class="font-weight-bold">{{ transaction.verification_request.verification_service?.name }}</v-list-item-subtitle>
                        </v-list-item>
                        <v-list-item>
                            <template #prepend><v-icon color="grey">mdi-magnify</v-icon></template>
                            <v-list-item-title class="text-caption text-grey">Search Parameter</v-list-item-title>
                            <v-list-item-subtitle class="font-weight-bold">{{ transaction.verification_request.search_parameter }}</v-list-item-subtitle>
                        </v-list-item>
                    </v-list>
                </template>

                <!-- Customer Info -->
                <v-divider class="my-4" />
                <p class="text-overline text-grey mb-2">Account Information</p>
                <v-list class="bg-grey-lighten-5 rounded-lg">
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-account</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Account Name</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ user?.name }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                        <template #prepend><v-icon color="grey">mdi-email</v-icon></template>
                        <v-list-item-title class="text-caption text-grey">Email</v-list-item-title>
                        <v-list-item-subtitle class="font-weight-bold">{{ user?.email }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>

                <!-- Footer -->
                <div class="text-center mt-6 pt-4 border-t">
                    <p class="text-caption text-grey mb-1">Thank you for using EaseVerifier</p>
                    <p class="text-caption text-grey mb-0">support@easeverifier.com | www.easeverifier.com</p>
                </div>
            </v-card-text>
        </v-card>
    </CustomerLayout>
</template>

<style>
@media print {
    .d-print-none, .v-navigation-drawer, .v-app-bar, nav { display: none !important; }
    .v-main { padding: 0 !important; }
    .v-container { padding: 0 !important; max-width: 100% !important; }
    .receipt-card { box-shadow: none !important; max-width: 100% !important; }
}
</style>


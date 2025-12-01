<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    wallet?: any;
    transactions?: { data: any[]; links: any; meta: any; current_page: number; last_page: number; per_page: number; total: number };
    stats?: { balance: number; bonus_balance: number; total_balance: number; total_funded: number; total_spent: number };
    filters?: { type?: string; category?: string; date_from?: string; date_to?: string; page?: number };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const filterType = ref(props.filters?.type || '');
const filterCategory = ref(props.filters?.category || '');
const currentPage = ref(props.transactions?.current_page || 1);

const totalPages = computed(() => props.transactions?.last_page || 1);

watch([filterType, filterCategory], ([t, c]) => {
    currentPage.value = 1;
    router.get('/customer/wallet', { type: t || undefined, category: c || undefined, page: 1 }, { preserveState: true, replace: true });
});

const goToPage = (page: number) => {
    currentPage.value = page;
    router.get('/customer/wallet', {
        type: filterType.value || undefined,
        category: filterCategory.value || undefined,
        page
    }, { preserveState: true, replace: true });
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Reference', key: 'reference' },
    { title: 'Type', key: 'type' },
    { title: 'Category', key: 'category' },
    { title: 'Amount', key: 'amount' },
    { title: 'Balance After', key: 'balance_after' },
    { title: 'Date', key: 'created_at' },
];
</script>

<template>
    <Head title="Wallet - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <!-- Flash Messages -->
        <v-alert v-if="flash?.success" type="success" variant="tonal" closable class="mb-4">
            {{ flash.success }}
        </v-alert>
        <v-alert v-if="flash?.error" type="error" variant="tonal" closable class="mb-4">
            {{ flash.error }}
        </v-alert>

        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">My Wallet</h1>
                <p class="text-body-2 text-grey">Manage your wallet and view transactions</p>
            </div>
            <v-spacer />
            <v-btn color="primary" size="large" href="/customer/wallet/fund" prepend-icon="mdi-wallet-plus">
                Fund Wallet
            </v-btn>
        </div>

        <!-- Balance Cards -->
        <v-row class="mb-6">
            <v-col cols="12" md="4">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Main Balance</p>
                        <p class="text-h3 font-weight-bold mb-0">{{ formatCurrency(stats?.balance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="4">
                <v-card color="secondary">
                    <v-card-text>
                        <p class="text-overline opacity-80">Bonus Balance</p>
                        <p class="text-h3 font-weight-bold mb-0">{{ formatCurrency(stats?.bonus_balance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="4">
                <v-card>
                    <v-card-text>
                        <p class="text-overline text-grey">Total Balance</p>
                        <p class="text-h3 font-weight-bold text-primary mb-0">{{ formatCurrency(stats?.total_balance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Summary Stats -->
        <v-row class="mb-6">
            <v-col cols="6">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="success" size="32" class="mr-3">mdi-arrow-down-circle</v-icon>
                        <div>
                            <p class="text-caption text-grey mb-0">Total Funded</p>
                            <p class="text-h6 font-weight-bold text-success mb-0">{{ formatCurrency(stats?.total_funded) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="error" size="32" class="mr-3">mdi-arrow-up-circle</v-icon>
                        <div>
                            <p class="text-caption text-grey mb-0">Total Spent</p>
                            <p class="text-h6 font-weight-bold text-error mb-0">{{ formatCurrency(stats?.total_spent) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Transactions -->
        <v-card>
            <v-card-title class="d-flex align-center">
                <span>Transaction History</span>
                <v-spacer />
                <v-btn variant="outlined" size="small" prepend-icon="mdi-download">Export</v-btn>
            </v-card-title>
            <v-card-text>
                <div class="d-flex ga-4 mb-4">
                    <v-btn-toggle v-model="filterType" variant="outlined" density="compact">
                        <v-btn value="">All</v-btn>
                        <v-btn value="credit">Credits</v-btn>
                        <v-btn value="debit">Debits</v-btn>
                    </v-btn-toggle>
                </div>

                <v-data-table :headers="headers" :items="transactions?.data || []" :items-per-page="-1" hover>
                    <template #item.reference="{ item }">
                        <span class="font-weight-medium">{{ item.reference }}</span>
                    </template>
                    <template #item.type="{ item }">
                        <v-chip :color="item.type === 'credit' ? 'success' : 'error'" size="small" variant="tonal">
                            <v-icon start size="small">{{ item.type === 'credit' ? 'mdi-arrow-down' : 'mdi-arrow-up' }}</v-icon>
                            {{ item.type }}
                        </v-chip>
                    </template>
                    <template #item.category="{ item }">
                        <v-chip size="small" variant="outlined">{{ item.category }}</v-chip>
                    </template>
                    <template #item.amount="{ item }">
                        <span :class="item.type === 'credit' ? 'text-success' : 'text-error'" class="font-weight-bold">
                            {{ item.type === 'credit' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                        </span>
                    </template>
                    <template #item.balance_after="{ item }">
                        {{ formatCurrency(item.balance_after) }}
                    </template>
                    <template #item.created_at="{ item }">
                        {{ new Date(item.created_at).toLocaleString() }}
                    </template>
                    <template #bottom></template>
                </v-data-table>

                <!-- Server-side Pagination -->
                <div class="d-flex align-center justify-space-between mt-4">
                    <span class="text-caption text-grey">
                        Showing {{ ((transactions?.current_page || 1) - 1) * (transactions?.per_page || 15) + 1 }}
                        to {{ Math.min((transactions?.current_page || 1) * (transactions?.per_page || 15), transactions?.total || 0) }}
                        of {{ transactions?.total || 0 }} results
                    </span>
                    <v-pagination
                        v-model="currentPage"
                        :length="totalPages"
                        :total-visible="7"
                        density="comfortable"
                        @update:model-value="goToPage"
                    />
                </div>
            </v-card-text>
        </v-card>
    </CustomerLayout>
</template>


<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    transactions?: { data: any[]; current_page: number; last_page: number; per_page: number; total: number };
    stats?: { total_credits: number; total_debits: number; this_month_credits: number; this_month_debits: number };
    filters?: { search?: string; type?: string; category?: string; min_amount?: string; date_from?: string; date_to?: string };
}>();

const search = ref(props.filters?.search || '');
const filterType = ref(props.filters?.type || '');
const filterCategory = ref(props.filters?.category || '');
const minAmount = ref(props.filters?.min_amount || '');
const currentPage = ref(props.transactions?.current_page || 1);

const totalPages = computed(() => props.transactions?.last_page || 1);

const applyFilters = () => {
    currentPage.value = 1;
    router.get('/customer/transactions', {
        search: search.value || undefined,
        type: filterType.value || undefined,
        category: filterCategory.value || undefined,
        min_amount: minAmount.value || undefined,
        page: 1
    }, { preserveState: true, replace: true });
};

watch([filterType, filterCategory, minAmount], () => applyFilters());

const goToPage = (page: number) => {
    currentPage.value = page;
    router.get('/customer/transactions', {
        search: search.value || undefined,
        type: filterType.value || undefined,
        category: filterCategory.value || undefined,
        min_amount: minAmount.value || undefined,
        page
    }, { preserveState: true, replace: true });
};

const formatCurrency = (amount: any) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Reference', key: 'reference' },
    { title: 'Type', key: 'type' },
    { title: 'Category', key: 'category' },
    { title: 'Amount', key: 'amount' },
    { title: 'Balance After', key: 'balance_after' },
    { title: 'Date', key: 'created_at' },
    { title: 'Actions', key: 'actions', sortable: false },
];

const categoryOptions = [
    { title: 'All Categories', value: '' },
    { title: 'Funding', value: 'funding' },
    { title: 'Verification', value: 'verification' },
    { title: 'Refund', value: 'refund' },
    { title: 'Bonus', value: 'bonus' },
];
</script>

<template>
    <Head title="Transactions - EaseVerifier" />
    <CustomerLayout :user="($page.props.auth as any)?.user" :wallet="($page.props.auth as any)?.wallet">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Transactions</h1>
                <p class="text-body-2 text-grey">View and manage all your transactions</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" prepend-icon="mdi-download">Export</v-btn>
        </div>

        <!-- Stats Cards -->
        <v-row class="mb-6">
            <v-col cols="6" md="3">
                <v-card color="success-lighten-5">
                    <v-card-text class="d-flex align-center">
                        <v-icon color="success" size="32" class="mr-3">mdi-arrow-down-circle</v-icon>
                        <div>
                            <p class="text-caption mb-0">Total Credits</p>
                            <p class="text-h6 font-weight-bold text-success mb-0">{{ formatCurrency(stats?.total_credits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card color="error-lighten-5">
                    <v-card-text class="d-flex align-center">
                        <v-icon color="error" size="32" class="mr-3">mdi-arrow-up-circle</v-icon>
                        <div>
                            <p class="text-caption mb-0">Total Debits</p>
                            <p class="text-h6 font-weight-bold text-error mb-0">{{ formatCurrency(stats?.total_debits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="success" size="32" class="mr-3">mdi-calendar-month</v-icon>
                        <div>
                            <p class="text-caption mb-0">This Month Credits</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(stats?.this_month_credits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="error" size="32" class="mr-3">mdi-calendar-month</v-icon>
                        <div>
                            <p class="text-caption mb-0">This Month Debits</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(stats?.this_month_debits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Transactions Table -->
        <v-card>
            <v-card-text>
                <div class="d-flex flex-wrap align-center ga-4 mb-4">
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search reference..." variant="outlined" density="compact" hide-details style="max-width: 250px;" @keyup.enter="applyFilters" />
                    <v-btn-toggle v-model="filterType" variant="outlined" density="compact">
                        <v-btn value="">All</v-btn>
                        <v-btn value="credit">Credits</v-btn>
                        <v-btn value="debit">Debits</v-btn>
                    </v-btn-toggle>
                    <v-select v-model="filterCategory" :items="categoryOptions" item-title="title" item-value="value" label="Category" variant="outlined" density="compact" hide-details style="max-width: 180px;" />
                    <v-text-field v-model="minAmount" prepend-inner-icon="mdi-currency-ngn" label="Min Amount" variant="outlined" density="compact" hide-details type="number" style="max-width: 150px;" />
                </div>

                <v-data-table :headers="headers" :items="transactions?.data || []" :items-per-page="-1" hover>
                    <template #item.reference="{ item }">
                        <span class="font-weight-medium text-primary">{{ item.reference }}</span>
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
                    <template #item.actions="{ item }">
                        <v-btn size="small" variant="text" color="primary" :href="`/customer/transactions/${item.id}`" icon="mdi-eye" />
                        <v-btn size="small" variant="text" color="secondary" :href="`/customer/transactions/${item.id}?print=1`" icon="mdi-printer" />
                    </template>
                    <template #bottom></template>
                </v-data-table>

                <!-- Pagination -->
                <div class="d-flex align-center justify-space-between mt-4">
                    <span class="text-caption text-grey">
                        Showing {{ ((transactions?.current_page || 1) - 1) * (transactions?.per_page || 20) + 1 }}
                        to {{ Math.min((transactions?.current_page || 1) * (transactions?.per_page || 20), transactions?.total || 0) }}
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


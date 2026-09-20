<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    transactions?: { data: any[]; current_page: number; last_page: number; per_page: number; total: number };
    stats?: { total_credits: number; total_debits: number; this_month_credits: number; this_month_debits: number };
    filters?: { search?: string; type?: string; category?: string; min_amount?: string; date_from?: string; date_to?: string };
    paygoIntents?: { data: any[]; current_page: number; last_page: number; per_page: number; total: number };
    paygoStats?: { gross_revenue: number; system_settlement: number; net_earnings: number; successful_payments: number; reference_packages: number; this_month_revenue: number; this_month_earnings: number };
    paygoFilters?: { paygo_search?: string; paygo_status?: string; paygo_package?: string; paygo_date_from?: string; paygo_date_to?: string };
    activeTab?: 'wallet' | 'paygo';
}>();

const activeTab = ref(props.activeTab || 'wallet');
const search = ref(props.filters?.search || '');
const filterType = ref(props.filters?.type || '');
const filterCategory = ref(props.filters?.category || '');
const minAmount = ref(props.filters?.min_amount || '');
const currentPage = ref(props.transactions?.current_page || 1);
const paygoSearch = ref(props.paygoFilters?.paygo_search || '');
const paygoStatus = ref(props.paygoFilters?.paygo_status || '');
const paygoPackage = ref(props.paygoFilters?.paygo_package || '');
const paygoDateFrom = ref(props.paygoFilters?.paygo_date_from || '');
const paygoDateTo = ref(props.paygoFilters?.paygo_date_to || '');
const paygoCurrentPage = ref(props.paygoIntents?.current_page || 1);

const totalPages = computed(() => props.transactions?.last_page || 1);
const paygoTotalPages = computed(() => props.paygoIntents?.last_page || 1);
const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (activeTab.value === 'paygo') {
        params.set('tab', 'paygo');
        if (paygoSearch.value) params.set('paygo_search', paygoSearch.value);
        if (paygoStatus.value) params.set('paygo_status', paygoStatus.value);
        if (paygoPackage.value) params.set('paygo_package', paygoPackage.value);
        if (paygoDateFrom.value) params.set('paygo_date_from', paygoDateFrom.value);
        if (paygoDateTo.value) params.set('paygo_date_to', paygoDateTo.value);

        return `/customer/transactions/export?${params.toString()}`;
    }

    if (search.value) params.set('search', search.value);
    if (filterType.value) params.set('type', filterType.value);
    if (filterCategory.value) params.set('category', filterCategory.value);
    if (minAmount.value) params.set('min_amount', minAmount.value);

    const query = params.toString();

    return query ? `/customer/transactions/export?${query}` : '/customer/transactions/export';
});

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

const applyPaygoFilters = () => {
    paygoCurrentPage.value = 1;
    router.get('/customer/transactions', {
        tab: 'paygo',
        paygo_search: paygoSearch.value || undefined,
        paygo_status: paygoStatus.value || undefined,
        paygo_package: paygoPackage.value || undefined,
        paygo_date_from: paygoDateFrom.value || undefined,
        paygo_date_to: paygoDateTo.value || undefined,
        paygo_page: 1,
    }, { preserveState: true, replace: true });
};

watch([paygoStatus, paygoPackage, paygoDateFrom, paygoDateTo], () => applyPaygoFilters());

const goToPaygoPage = (page: number) => {
    paygoCurrentPage.value = page;
    router.get('/customer/transactions', {
        tab: 'paygo',
        paygo_search: paygoSearch.value || undefined,
        paygo_status: paygoStatus.value || undefined,
        paygo_package: paygoPackage.value || undefined,
        paygo_date_from: paygoDateFrom.value || undefined,
        paygo_date_to: paygoDateTo.value || undefined,
        paygo_page: page,
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

const paygoStatusOptions = [
    { title: 'All statuses', value: '' },
    { title: 'Paid', value: 'paid' },
    { title: 'Verifying', value: 'verifying' },
    { title: 'Used', value: 'used' },
    { title: 'Pending', value: 'pending' },
    { title: 'Failed', value: 'failed' },
    { title: 'Expired', value: 'expired' },
];

const paygoPackageOptions = [
    { title: 'All packages', value: '' },
    { title: 'Reference package', value: 'reference' },
    { title: 'Normal payment', value: 'normal' },
];

const statusColor = (status: string) => {
    if (['paid', 'used', 'completed'].includes(status)) return 'success';
    if (['pending', 'verifying'].includes(status)) return 'warning';
    if (['failed', 'expired'].includes(status)) return 'error';
    return 'grey';
};
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
            <v-btn variant="outlined" prepend-icon="mdi-download" :href="exportUrl">Export</v-btn>
        </div>

        <v-tabs v-model="activeTab" color="primary" class="mb-5">
            <v-tab value="wallet" prepend-icon="mdi-wallet-outline">Wallet transactions</v-tab>
            <v-tab value="paygo" prepend-icon="mdi-credit-card-check-outline">PayGo payments</v-tab>
        </v-tabs>

        <!-- Stats Cards -->
        <v-row v-if="activeTab === 'wallet'" class="mb-6">
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

        <v-row v-else class="mb-6">
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="primary" size="32" class="mr-3">mdi-cash-multiple</v-icon>
                        <div>
                            <p class="text-caption mb-0">Gross Revenue</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(paygoStats?.gross_revenue) }}</p>
                            <p class="text-caption text-grey mb-0">{{ formatCurrency(paygoStats?.this_month_revenue) }} this month</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="warning" size="32" class="mr-3">mdi-bank-transfer-out</v-icon>
                        <div>
                            <p class="text-caption mb-0">EaseVerifier Settlement</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(paygoStats?.system_settlement) }}</p>
                            <p class="text-caption text-grey mb-0">Paid transactions only</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="success" size="32" class="mr-3">mdi-trending-up</v-icon>
                        <div>
                            <p class="text-caption mb-0">Net Earnings</p>
                            <p class="text-h6 font-weight-bold text-success mb-0">{{ formatCurrency(paygoStats?.net_earnings) }}</p>
                            <p class="text-caption text-grey mb-0">{{ formatCurrency(paygoStats?.this_month_earnings) }} this month</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="info" size="32" class="mr-3">mdi-check-decagram-outline</v-icon>
                        <div>
                            <p class="text-caption mb-0">Successful Payments</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ paygoStats?.successful_payments || 0 }}</p>
                            <p class="text-caption text-grey mb-0">{{ paygoStats?.reference_packages || 0 }} reference packages</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Transactions Table -->
        <v-card v-if="activeTab === 'wallet'">
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

        <v-card v-else>
            <v-card-text>
                <div class="d-flex flex-wrap align-center ga-4 mb-4">
                    <v-text-field
                        v-model="paygoSearch"
                        prepend-inner-icon="mdi-magnify"
                        label="Search reference or lookup"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 280px;"
                        @keyup.enter="applyPaygoFilters"
                    />
                    <v-select v-model="paygoStatus" :items="paygoStatusOptions" item-title="title" item-value="value" label="Status" variant="outlined" density="compact" hide-details style="max-width: 180px;" />
                    <v-select v-model="paygoPackage" :items="paygoPackageOptions" item-title="title" item-value="value" label="Package" variant="outlined" density="compact" hide-details style="max-width: 210px;" />
                    <v-text-field v-model="paygoDateFrom" type="date" label="From" variant="outlined" density="compact" hide-details style="max-width: 170px;" />
                    <v-text-field v-model="paygoDateTo" type="date" label="To" variant="outlined" density="compact" hide-details style="max-width: 170px;" />
                </div>

                <div class="table-wrap">
                    <v-table density="comfortable" hover>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Service</th>
                                <th>Package</th>
                                <th>Charged Amount</th>
                                <th>Settlement</th>
                                <th>Earning</th>
                                <th>Lookup</th>
                                <th>Attempts</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="intent in paygoIntents?.data || []" :key="intent.id">
                                <td class="font-weight-medium text-primary">{{ intent.reference }}</td>
                                <td>{{ intent.board || intent.service_name || '-' }}</td>
                                <td>
                                    <v-chip size="small" variant="tonal" :color="intent.package_type === 'reference' ? 'info' : 'default'">
                                        {{ intent.package_type === 'reference' ? 'Reference package' : 'Normal payment' }}
                                    </v-chip>
                                </td>
                                <td class="font-weight-medium">{{ formatCurrency(intent.amount) }}</td>
                                <td>{{ formatCurrency(intent.system_price) }}</td>
                                <td class="font-weight-bold text-success">{{ formatCurrency(intent.earning) }}</td>
                                <td>{{ intent.lookup_label || '-' }}</td>
                                <td>{{ intent.attempts_used }}/{{ intent.attempts_allowed || 3 }}</td>
                                <td><v-chip size="small" variant="tonal" :color="statusColor(intent.status)">{{ intent.status }}</v-chip></td>
                                <td>{{ new Date(intent.created_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="!paygoIntents?.data?.length">
                                <td colspan="10" class="text-center text-grey py-8">No PayGo payments match these filters.</td>
                            </tr>
                        </tbody>
                    </v-table>
                </div>

                <div class="d-flex align-center justify-space-between mt-4">
                    <span class="text-caption text-grey">
                        Showing {{ paygoIntents?.total ? ((paygoIntents.current_page - 1) * paygoIntents.per_page) + 1 : 0 }}
                        to {{ Math.min((paygoIntents?.current_page || 1) * (paygoIntents?.per_page || 20), paygoIntents?.total || 0) }}
                        of {{ paygoIntents?.total || 0 }} results
                    </span>
                    <v-pagination
                        v-model="paygoCurrentPage"
                        :length="paygoTotalPages"
                        :total-visible="7"
                        density="comfortable"
                        @update:model-value="goToPaygoPage"
                    />
                </div>
            </v-card-text>
        </v-card>
    </CustomerLayout>
</template>

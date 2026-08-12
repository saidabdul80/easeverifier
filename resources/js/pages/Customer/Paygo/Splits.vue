<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, watch } from 'vue';

interface SplitLedger {
    id: number;
    payment_reference: string;
    paygo_reference?: string | null;
    service_name?: string | null;
    subaccount_label?: string | null;
    subaccount_code: string;
    flat_amount: number;
    transaction_amount: number;
    main_account_remainder: number;
    status: string;
    paid_at?: string | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    filters?: { search?: string };
    stats?: { total_amount: number; total_entries: number; today_amount: number };
    ledgers: Paginator<SplitLedger>;
}>();

const search = ref(props.filters?.search || '');
const currentPage = ref(props.ledgers.current_page || 1);

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const formatDate = (date?: string | null) => date ? new Date(date).toLocaleString() : '-';

const applyFilters = (page = 1) => {
    currentPage.value = page;
    router.get('/customer/paygo-splits', {
        search: search.value || undefined,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => applyFilters(1));
</script>

<template>
    <Head title="PayGo Split Ledger - EaseVerifier" />

    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="splits-page">
            <section class="page-header">
                <div>
                    <div class="eyebrow">
                        <v-icon size="18">mdi-bank-transfer-out</v-icon>
                        Paystack settlement
                    </div>
                    <h1>Split Ledger</h1>
                    <p>See PayGo payments settled directly to your configured Paystack subaccounts.</p>
                </div>
                <v-btn color="primary" variant="outlined" prepend-icon="mdi-cash-fast" href="/customer/paygo-services">PayGo Services</v-btn>
            </section>

            <v-row class="mb-6">
                <v-col cols="12" md="4">
                    <v-card>
                        <v-card-text>
                            <p class="text-caption text-grey mb-1">Total Settled</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ formatCurrency(stats?.total_amount || 0) }}</p>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="4">
                    <v-card>
                        <v-card-text>
                            <p class="text-caption text-grey mb-1">Today</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ formatCurrency(stats?.today_amount || 0) }}</p>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="4">
                    <v-card>
                        <v-card-text>
                            <p class="text-caption text-grey mb-1">Ledger Entries</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ stats?.total_entries || 0 }}</p>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <section class="ledger-surface">
                <div class="toolbar">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        label="Search reference or subaccount"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="search-field"
                    />
                </div>

                <div class="table-wrap">
                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th>Service</th>
                                <th>Subaccount</th>
                                <th>Split Amount</th>
                                <th>Transaction</th>
                                <th>Main Remainder</th>
                                <th>Status</th>
                                <th>Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ledger in ledgers.data" :key="ledger.id">
                                <td>
                                    <div class="font-weight-medium">{{ ledger.payment_reference }}</div>
                                    <div class="text-caption text-grey">{{ ledger.paygo_reference || '-' }}</div>
                                </td>
                                <td>{{ ledger.service_name || '-' }}</td>
                                <td>
                                    <div>{{ ledger.subaccount_label || '-' }}</div>
                                    <div class="text-caption text-grey">{{ ledger.subaccount_code }}</div>
                                </td>
                                <td class="font-weight-bold text-success">{{ formatCurrency(ledger.flat_amount) }}</td>
                                <td>{{ formatCurrency(ledger.transaction_amount) }}</td>
                                <td>{{ formatCurrency(ledger.main_account_remainder) }}</td>
                                <td><v-chip size="small" color="success" variant="tonal">{{ ledger.status }}</v-chip></td>
                                <td>{{ formatDate(ledger.paid_at) }}</td>
                            </tr>
                            <tr v-if="!ledgers.data.length">
                                <td colspan="8" class="text-center text-grey py-8">No Paystack split settlements yet.</td>
                            </tr>
                        </tbody>
                    </v-table>
                </div>

                <div class="pagination-row">
                    <span class="text-caption text-grey">
                        Showing {{ ((ledgers.current_page || 1) - 1) * (ledgers.per_page || 20) + 1 }}
                        to {{ Math.min((ledgers.current_page || 1) * (ledgers.per_page || 20), ledgers.total || 0) }}
                        of {{ ledgers.total || 0 }} results
                    </span>
                    <v-pagination
                        v-model="currentPage"
                        :length="ledgers.last_page || 1"
                        :total-visible="7"
                        density="comfortable"
                        @update:model-value="applyFilters"
                    />
                </div>
            </section>
        </div>
    </CustomerLayout>
</template>

<style scoped>
.splits-page {
    max-width: 1180px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 24px;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 6px;
}

.page-header p {
    color: rgba(var(--v-theme-on-surface), 0.65);
    margin: 0;
}

.ledger-surface {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    border-radius: 8px;
    padding: 20px;
}

.toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.search-field {
    max-width: 360px;
}

.table-wrap {
    overflow-x: auto;
}

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 16px;
}

@media (max-width: 720px) {
    .page-header,
    .pagination-row {
        flex-direction: column;
        align-items: stretch;
    }

    .search-field {
        max-width: none;
    }
}
</style>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

interface SplitLedger {
    id: number;
    customer_name?: string | null;
    customer_email?: string | null;
    referral_code?: string | null;
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
    filters?: { search?: string; date_from?: string; date_to?: string };
    stats?: { total_amount: number; total_entries: number; today_amount: number };
    ledgers: Paginator<SplitLedger>;
}>();

const search = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const currentPage = ref(props.ledgers.current_page || 1);

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const formatDate = (date?: string | null) => date ? new Date(date).toLocaleString() : '-';

const applyFilters = (page = 1) => {
    currentPage.value = page;
    router.get('/admin/paystack-splits', {
        search: search.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([search, dateFrom, dateTo], () => applyFilters(1));
</script>

<template>
    <Head title="Paystack Splits - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Paystack Splits</h1>
                <p class="text-body-2 text-grey">Track PayGo payments settled directly to customer subaccounts.</p>
            </div>
        </div>

        <v-row class="mb-6">
            <v-col cols="12" md="4">
                <v-card>
                    <v-card-text>
                        <p class="text-caption text-grey mb-1">Total Split Settled</p>
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

        <v-card>
            <v-card-text>
                <div class="d-flex flex-wrap align-center ga-4 mb-4">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        label="Search customer, reference, subaccount"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 360px;"
                    />
                    <v-text-field v-model="dateFrom" label="From" type="date" variant="outlined" density="compact" hide-details style="max-width: 170px;" />
                    <v-text-field v-model="dateTo" label="To" type="date" variant="outlined" density="compact" hide-details style="max-width: 170px;" />
                </div>

                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Customer</th>
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
                                <div class="font-weight-medium">{{ ledger.customer_name || '-' }}</div>
                                <div class="text-caption text-grey">{{ ledger.customer_email || ledger.referral_code || '-' }}</div>
                            </td>
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
                            <td colspan="9" class="text-center text-grey py-8">No Paystack split ledger entries found.</td>
                        </tr>
                    </tbody>
                </v-table>

                <div class="d-flex align-center justify-space-between mt-4">
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
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>

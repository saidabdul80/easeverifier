<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed, ref } from 'vue';

interface ServiceOption {
    id: number;
    name: string;
    public_slug: string;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
}

interface WalletTransaction {
    reference: string;
    type: string;
    category: string;
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string;
    status: string;
    metadata?: Record<string, any> | null;
    created_at: string;
}

interface PaymentIntent {
    reference: string;
    service_name?: string | null;
    amount: number;
    system_price: number;
    earning: number;
    status: string;
    verification_attempts: number;
    nin_last4?: string | null;
    paid_at?: string | null;
    used_at?: string | null;
    created_at: string;
}

const props = defineProps<{
    filters: {
        service?: number | null;
    };
    services: ServiceOption[];
    walletTransactions: Paginator<WalletTransaction>;
    paymentIntents: Paginator<PaymentIntent>;
}>();

const tab = ref('payments');
const selectedService = ref<number | null>(props.filters.service || null);

const selectedServiceName = computed(() => props.services.find((service) => service.id === selectedService.value)?.name || 'All services');

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const formatDate = (date?: string | null) => date ? new Date(date).toLocaleString() : '-';

const cleanLabel = (label: string) => label
    .replace('&laquo;', '<')
    .replace('&raquo;', '>');

const statusColor = (status: string) => {
    if (['paid', 'completed', 'used'].includes(status)) return 'success';
    if (['pending', 'verifying'].includes(status)) return 'warning';
    if (['failed', 'expired', 'reversed'].includes(status)) return 'error';
    return 'grey';
};

const applyFilter = () => {
    router.get('/customer/paygo-transactions', {
        service: selectedService.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="PayGo Transactions - EaseVerifier" />

    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="transactions-page">
            <section class="page-header">
                <div>
                    <div class="eyebrow">
                        <v-icon size="18">mdi-receipt-text-outline</v-icon>
                        PayGo transactions
                    </div>
                    <h1>{{ selectedServiceName }}</h1>
                    <p>Review PayGo payments and the dedicated PayGo wallet ledger.</p>
                </div>
                <div class="header-actions">
                    <v-select
                        v-model="selectedService"
                        :items="[{ title: 'All services', value: null }, ...services.map(service => ({ title: service.name, value: service.id }))]"
                        label="Service"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="service-filter"
                        @update:model-value="applyFilter"
                    />
                    <v-btn color="primary" variant="outlined" prepend-icon="mdi-cash-fast" href="/customer/paygo-services">Services</v-btn>
                </div>
            </section>

            <section class="transactions-surface">
                <v-tabs v-model="tab" color="primary" density="comfortable">
                    <v-tab value="payments">
                        <v-icon start>mdi-credit-card-check-outline</v-icon>
                        Payments
                    </v-tab>
                    <v-tab value="wallet">
                        <v-icon start>mdi-wallet-outline</v-icon>
                        Wallet ledger
                    </v-tab>
                </v-tabs>

                <v-window v-model="tab" class="mt-4">
                    <v-window-item value="payments">
                        <div class="table-wrap">
                            <v-table density="comfortable">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>System Price</th>
                                        <th>Earning</th>
                                        <th>NIN</th>
                                        <th>Attempts</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="intent in paymentIntents.data" :key="intent.reference">
                                        <td class="font-weight-bold">{{ intent.reference }}</td>
                                        <td>{{ intent.service_name || '-' }}</td>
                                        <td>{{ formatCurrency(intent.amount) }}</td>
                                        <td>{{ formatCurrency(intent.system_price) }}</td>
                                        <td class="text-success font-weight-bold">{{ formatCurrency(intent.earning) }}</td>
                                        <td>{{ intent.nin_last4 ? `****${intent.nin_last4}` : '-' }}</td>
                                        <td>{{ intent.verification_attempts }}/3</td>
                                        <td><v-chip size="small" variant="tonal" :color="statusColor(intent.status)">{{ intent.status }}</v-chip></td>
                                        <td>{{ formatDate(intent.created_at) }}</td>
                                    </tr>
                                    <tr v-if="!paymentIntents.data.length">
                                        <td colspan="9" class="text-center text-grey py-8">No PayGo payment transactions found.</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>

                        <div v-if="paymentIntents.links.length > 3" class="pagination-row">
                            <v-btn
                                v-for="link in paymentIntents.links"
                                :key="link.label"
                                size="small"
                                :variant="link.active ? 'flat' : 'outlined'"
                                color="primary"
                                :disabled="!link.url"
                                :href="link.url || undefined"
                            >
                                {{ cleanLabel(link.label) }}
                            </v-btn>
                        </div>
                    </v-window-item>

                    <v-window-item value="wallet">
                        <div class="table-wrap">
                            <v-table density="comfortable">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="transaction in walletTransactions.data" :key="transaction.reference">
                                        <td class="font-weight-bold">{{ transaction.reference }}</td>
                                        <td>
                                            <v-chip size="small" variant="tonal" :color="transaction.type === 'credit' ? 'success' : 'warning'">{{ transaction.type }}</v-chip>
                                        </td>
                                        <td>{{ transaction.category }}</td>
                                        <td>{{ formatCurrency(transaction.amount) }}</td>
                                        <td>{{ formatCurrency(transaction.balance_before) }}</td>
                                        <td>{{ formatCurrency(transaction.balance_after) }}</td>
                                        <td>{{ transaction.description }}</td>
                                        <td><v-chip size="small" variant="tonal" :color="statusColor(transaction.status)">{{ transaction.status }}</v-chip></td>
                                        <td>{{ formatDate(transaction.created_at) }}</td>
                                    </tr>
                                    <tr v-if="!walletTransactions.data.length">
                                        <td colspan="9" class="text-center text-grey py-8">No PayGo wallet transactions found.</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>

                        <div v-if="walletTransactions.links.length > 3" class="pagination-row">
                            <v-btn
                                v-for="link in walletTransactions.links"
                                :key="link.label"
                                size="small"
                                :variant="link.active ? 'flat' : 'outlined'"
                                color="primary"
                                :disabled="!link.url"
                                :href="link.url || undefined"
                            >
                                {{ cleanLabel(link.label) }}
                            </v-btn>
                        </div>
                    </v-window-item>
                </v-window>
            </section>
        </div>
    </CustomerLayout>
</template>

<style scoped>
.transactions-page {
    display: grid;
    gap: 1.25rem;
}

.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid #dfe7e2;
    border-radius: 8px;
    background: #ffffff;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.7rem;
    color: #146b3a;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
}

.page-header h1 {
    margin: 0;
    color: #101817;
    font-size: 2rem;
    font-weight: 850;
}

.page-header p {
    margin: 0.35rem 0 0;
    color: #65706b;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.service-filter {
    width: 260px;
}

.transactions-surface {
    padding: 1.25rem;
    border: 1px solid #e3e7e4;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(20, 32, 28, 0.05);
}

.table-wrap {
    overflow-x: auto;
}

.pagination-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

@media (max-width: 760px) {
    .page-header,
    .header-actions {
        flex-direction: column;
    }

    .header-actions,
    .service-filter,
    .header-actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>

<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed } from 'vue';
import { useDisplay } from 'vuetify';

const props = defineProps<{
    stats?: Record<string, any>;
    verificationCounts?: Record<string, number>;
    recentVerifications?: any[];
    recentTransactions?: any[];
    services?: any[];
    branches?: any[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { smAndDown } = useDisplay();

const formatCurrency = (amount: any) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
    }).format(amount || 0);

const firstName = computed(() => user.value?.name?.split(' ')[0] || 'there');

const statCards = computed(() => [
    {
        label: 'Completed',
        value: props.stats?.successful_verifications || 0,
        tone: 'success',
        icon: 'mdi-check-decagram',
    },
    {
        label: 'Pending',
        value: props.stats?.pending_verifications || 0,
        tone: 'warning',
        icon: 'mdi-timer-sand',
    },
    {
        label: 'Failed',
        value: props.stats?.failed_verifications || 0,
        tone: 'error',
        icon: 'mdi-alert-circle',
    },
    {
        label: 'API Keys',
        value: props.stats?.api_key_count || 0,
        tone: 'info',
        icon: 'mdi-key-variant',
    },
]);
</script>

<template>
    <Head title="Dashboard - EaseVerifier" />
    <CustomerLayout :user="($page.props.auth as any)?.user" :wallet="($page.props.auth as any)?.wallet">
        <v-row class="mb-4" align="stretch">
            <v-col cols="12" xl="8">
                <v-card class="dashboard-hero h-100">
                    <v-card-text class="pa-4 pa-md-5">
                        <div class="hero-shell">
                            <div class="hero-content">
                                <div class="text-overline text-white text-opacity-80 mb-2">Customer Workspace</div>
                                <h1 class="text-h5 text-md-h4 font-weight-bold text-white mb-2">Welcome back, {{ firstName }}.</h1>
                                <p class="text-body-2 text-white text-opacity-80 mb-3">
                                    Wallets, branches, and verifications in one view.
                                </p>

                                <div class="hero-actions">
                                    <v-btn color="white" variant="flat" href="/customer/verify" prepend-icon="mdi-shield-search" size="small">
                                        New Verification
                                    </v-btn>
                                    <v-btn color="white" variant="outlined" href="/customer/wallet/fund" prepend-icon="mdi-wallet-plus" size="small">
                                        Fund Wallet
                                    </v-btn>
                                    <v-btn color="white" variant="text" href="/customer/branches" prepend-icon="mdi-source-branch" size="small">
                                        Manage Branches
                                    </v-btn>
                                </div>

                                <div class="hero-inline-stats">
                                    <div class="hero-inline-stat">
                                        <span>Success</span>
                                        <strong>{{ stats?.success_rate || 0 }}%</strong>
                                    </div>
                                    <div class="hero-inline-stat">
                                        <span>This month</span>
                                        <strong>{{ stats?.this_month_verifications || 0 }}</strong>
                                    </div>
                                    <div class="hero-inline-stat">
                                        <span>Keys</span>
                                        <strong>{{ stats?.api_key_count || 0 }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="hero-balance-panel">
                                <div class="text-caption text-white text-opacity-70 mb-1">Available Balance</div>
                                <div class="text-h6 text-md-h5 font-weight-bold text-white mb-1">
                                    {{ formatCurrency(stats?.wallet_total_balance) }}
                                </div>
                                <div class="text-caption text-white text-opacity-80 mb-3">
                                    Bonus: {{ formatCurrency(stats?.bonus_balance) }}
                                </div>

                                <div class="balance-meta-grid">
                                    <div>
                                        <div class="text-caption text-white text-opacity-70">Branches</div>
                                        <div class="font-weight-bold text-white">{{ stats?.branch_count || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="text-caption text-white text-opacity-70">Branch Balance</div>
                                        <div class="font-weight-bold text-white">{{ formatCurrency(stats?.branch_balance) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-caption text-white text-opacity-70">Success Rate</div>
                                        <div class="font-weight-bold text-white">{{ stats?.success_rate || 0 }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-caption text-white text-opacity-70">Month Spend</div>
                                        <div class="font-weight-bold text-white">{{ formatCurrency(stats?.this_month_spent) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" xl="4">
                <v-card class="h-100">
                    <v-card-text class="pa-4">
                        <div class="section-head mb-4">
                            <div>
                                <div class="text-overline text-grey-darken-1">Overview</div>
                                <div class="text-h6 font-weight-bold">Activity Snapshot</div>
                            </div>
                            <v-chip color="primary" variant="tonal" size="small">
                                {{ stats?.total_verifications || 0 }} total
                            </v-chip>
                        </div>

                        <div class="snapshot-grid">
                            <div
                                v-for="card in statCards"
                                :key="card.label"
                                class="snapshot-card"
                            >
                                <v-avatar :color="`${card.tone}-lighten-5`" size="42">
                                    <v-icon :color="card.tone">{{ card.icon }}</v-icon>
                                </v-avatar>
                                <div>
                                    <div class="text-caption text-grey-darken-1">{{ card.label }}</div>
                                    <div class="text-subtitle-1 font-weight-bold">{{ card.value }}</div>
                                </div>
                            </div>
                        </div>

                        <v-divider class="my-4" />

                        <div class="summary-row mb-2">
                            <span class="text-caption text-grey-darken-1">Total Spent</span>
                            <span class="font-weight-bold">{{ formatCurrency(stats?.total_spent) }}</span>
                        </div>
                        <div class="summary-row mb-2">
                            <span class="text-caption text-grey-darken-1">This Month</span>
                            <span class="font-weight-bold">{{ stats?.this_month_verifications || 0 }} requests</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-caption text-grey-darken-1">Active Branches</span>
                            <span class="font-weight-bold">{{ stats?.active_branch_count || 0 }}</span>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mb-4">
            <v-col cols="12" lg="7">
                <v-card class="h-100">
                    <v-card-title class="section-head py-4">
                        <span>Verification Services</span>
                        <v-btn variant="text" color="primary" size="small" href="/customer/verify">All Services</v-btn>
                    </v-card-title>
                    <v-card-text class="pt-0">
                        <div class="services-grid">
                            <v-card v-for="service in services || []" :key="service.id" variant="outlined" class="service-card h-100" :href="`/customer/verify/${service.id}`">
                                <v-card-text class="pa-3">
                                    <div class="service-card-head">
                                        <v-avatar color="primary-lighten-5" size="36">
                                            <v-icon color="primary">{{ service.icon || 'mdi-shield-check' }}</v-icon>
                                        </v-avatar>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="font-weight-bold text-body-2">{{ service.name }}</div>
                                            <div class="text-caption text-grey line-clamp-1">{{ service.description }}</div>
                                        </div>
                                    </div>
                                    <div class="summary-row mt-3">
                                        <span class="text-caption text-grey-darken-1">Price</span>
                                        <span class="font-weight-bold text-primary">{{ formatCurrency(service.price) }}</span>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="5">
                <v-card class="h-100">
                    <v-card-title class="section-head py-4">
                        <span>Branch Wallets</span>
                        <v-btn variant="text" color="primary" size="small" href="/customer/branches">View All</v-btn>
                    </v-card-title>
                    <v-card-text class="pt-0">
                        <div v-if="branches?.length" class="grid gap-3">
                            <div v-for="branch in branches" :key="branch.id" class="branch-row">
                                <div class="min-w-0">
                                    <div class="font-weight-bold text-truncate">{{ branch.name }}</div>
                                    <div class="text-caption text-grey">{{ branch.code }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-weight-bold">{{ formatCurrency(branch.wallet_balance) }}</div>
                                    <v-chip size="x-small" :color="branch.is_active ? 'success' : 'grey'" variant="tonal">
                                        {{ branch.is_active ? 'Active' : 'Inactive' }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            You have not created any branches yet.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" xl="7">
                <v-card>
                    <v-card-title class="section-head py-4">
                        <span>Recent Verifications</span>
                        <v-btn variant="text" color="primary" size="small" href="/customer/history">History</v-btn>
                    </v-card-title>
                    <v-card-text class="pt-0">
                        <div v-if="smAndDown" class="grid gap-3">
                            <v-card v-for="item in recentVerifications || []" :key="item.id" variant="outlined">
                                <v-card-text class="pa-4">
                                    <div class="d-flex align-start justify-space-between ga-3">
                                        <div>
                                            <div class="font-weight-bold">{{ item.verification_service?.name || 'N/A' }}</div>
                                            <div class="text-caption text-grey">{{ item.branch?.name || 'Primary wallet' }}</div>
                                        </div>
                                        <v-chip :color="item.status === 'completed' ? 'success' : item.status === 'processing' ? 'warning' : 'error'" size="small" variant="tonal">
                                            {{ item.status }}
                                        </v-chip>
                                    </div>
                                    <div class="mt-3">
                                        <code class="bg-grey-lighten-4 px-2 py-1 rounded">{{ item.search_parameter }}</code>
                                    </div>
                                    <div class="text-caption text-grey mt-3">{{ new Date(item.created_at).toLocaleString() }}</div>
                                </v-card-text>
                            </v-card>
                            <v-alert v-if="!recentVerifications?.length" type="info" variant="tonal" density="compact">No verifications yet.</v-alert>
                        </div>

                        <v-table v-else density="comfortable">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Wallet</th>
                                    <th>Query</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in recentVerifications || []" :key="item.id">
                                    <td class="font-weight-medium">{{ item.verification_service?.name || 'N/A' }}</td>
                                    <td>{{ item.branch?.name || 'Primary wallet' }}</td>
                                    <td><code class="bg-grey-lighten-4 px-2 py-1 rounded">{{ item.search_parameter }}</code></td>
                                    <td>
                                        <v-chip :color="item.status === 'completed' ? 'success' : item.status === 'processing' ? 'warning' : 'error'" size="small" variant="tonal">
                                            {{ item.status }}
                                        </v-chip>
                                    </td>
                                    <td class="text-grey">{{ new Date(item.created_at).toLocaleString() }}</td>
                                </tr>
                                <tr v-if="!recentVerifications?.length">
                                    <td colspan="5" class="text-center text-grey py-4">No verifications yet</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" xl="5">
                <v-card>
                    <v-card-title class="section-head py-4">
                        <span>Recent Transactions</span>
                        <v-btn variant="text" color="primary" size="small" href="/customer/transactions">Transactions</v-btn>
                    </v-card-title>
                    <v-card-text class="pt-0">
                        <div v-if="recentTransactions?.length" class="grid gap-3">
                            <div v-for="item in recentTransactions" :key="item.id" class="transaction-row">
                                <div class="d-flex align-center ga-3 min-w-0">
                                    <v-avatar :color="item.type === 'credit' ? 'success-lighten-5' : 'error-lighten-5'" size="40">
                                        <v-icon :color="item.type === 'credit' ? 'success' : 'error'">
                                            {{ item.type === 'credit' ? 'mdi-arrow-down' : 'mdi-arrow-up' }}
                                        </v-icon>
                                    </v-avatar>
                                    <div class="min-w-0">
                                        <div class="font-weight-bold text-truncate">{{ item.category }}</div>
                                        <div class="text-caption text-grey text-truncate">{{ item.reference }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div :class="item.type === 'credit' ? 'text-success' : 'text-error'" class="font-weight-bold">
                                        {{ item.type === 'credit' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                                    </div>
                                    <div class="text-caption text-grey">{{ new Date(item.created_at).toLocaleDateString() }}</div>
                                </div>
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            No transactions yet.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

<style scoped>
.dashboard-hero {
    background:
        radial-gradient(circle at top left, rgba(142, 255, 179, 0.2), transparent 30%),
        linear-gradient(135deg, #143822 0%, #0f2d1b 50%, #1d4d2c 100%);
}

.hero-shell {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(270px, 0.95fr);
    gap: 1rem;
    align-items: stretch;
}

.hero-content {
    display: flex;
    flex-direction: column;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.hero-inline-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.9rem;
}

.hero-inline-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    padding: 0.45rem 0.7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: white;
}

.hero-inline-stat span {
    font-size: 0.72rem;
    opacity: 0.72;
}

.hero-balance-panel {
    min-width: 0;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(8px);
}

.balance-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.snapshot-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.snapshot-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    padding: 0.75rem 0.85rem;
    border: 1px solid rgba(18, 18, 18, 0.06);
    border-radius: 14px;
    background: #fbfcfb;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.service-card-head {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.service-card {
    transition: transform 0.16s ease, border-color 0.16s ease;
}

.service-card:hover {
    transform: translateY(-2px);
    border-color: rgba(28, 91, 52, 0.25);
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.branch-row,
.transaction-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 0;
    border-bottom: 1px solid rgba(18, 18, 18, 0.08);
}

.branch-row:last-child,
.transaction-row:last-child {
    border-bottom: 0;
}

.min-w-0 {
    min-width: 0;
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (max-width: 959px) {
    .hero-shell,
    .services-grid,
    .snapshot-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {
    .hero-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .branch-row,
    .transaction-row,
    .summary-row,
    .section-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .transaction-row .text-right,
    .branch-row .text-right {
        text-align: left !important;
    }
}
</style>

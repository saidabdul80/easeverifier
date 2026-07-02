<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';

interface Analytics {
    wallet_balance: number;
    pending_withdrawal: number;
    services_count: number;
    active_services_count: number;
    total_intents: number;
    paid_intents: number;
    used_intents: number;
    pending_intents: number;
    failed_intents: number;
    expired_intents: number;
    gross_revenue: number;
    system_cost: number;
    net_earnings: number;
    wallet_credits: number;
    withdrawals: number;
}

interface DailyPoint {
    date: string;
    intents: number;
    revenue: number;
    earnings: number;
}

interface ServicePerformance {
    id: number;
    name: string;
    service_name: string;
    is_active: boolean;
    price: number;
    system_price: number;
    margin: number;
    intents_count: number;
    paid_count: number;
    used_count: number;
}

const props = defineProps<{
    analytics: Analytics;
    daily: DailyPoint[];
    servicePerformance: ServicePerformance[];
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const maxDailyEarnings = () => Math.max(1, ...props.daily.map((point) => point.earnings || 0));
</script>

<template>
    <Head title="PayGo Analytics - EaseVerifier" />

    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="analytics-page">
            <section class="page-header">
                <div>
                    <div class="eyebrow">
                        <v-icon size="18">mdi-chart-box-outline</v-icon>
                        PayGo analytics
                    </div>
                    <h1>PayGo performance</h1>
                    <p>Track prepaid NIN revenue, markup earnings, and service conversion.</p>
                </div>
                <v-btn color="primary" variant="outlined" prepend-icon="mdi-cash-fast" href="/customer/paygo-services">Services</v-btn>
            </section>

            <section class="metric-grid">
                <div class="metric-card primary-card">
                    <span>Net earnings</span>
                    <strong>{{ formatCurrency(analytics.net_earnings) }}</strong>
                    <small>Public price minus system price</small>
                </div>
                <div class="metric-card">
                    <span>Gross PayGo revenue</span>
                    <strong>{{ formatCurrency(analytics.gross_revenue) }}</strong>
                    <small>Paid PayGo verification payments</small>
                </div>
                <div class="metric-card">
                    <span>System cost</span>
                    <strong>{{ formatCurrency(analytics.system_cost) }}</strong>
                    <small>Verification minimum retained by system</small>
                </div>
                <div class="metric-card">
                    <span>Withdrawals</span>
                    <strong>{{ formatCurrency(analytics.withdrawals) }}</strong>
                    <small>{{ formatCurrency(analytics.pending_withdrawal) }} pending</small>
                </div>
            </section>

            <section class="status-grid">
                <div>
                    <span>Total intents</span>
                    <strong>{{ analytics.total_intents }}</strong>
                </div>
                <div>
                    <span>Pending</span>
                    <strong>{{ analytics.pending_intents }}</strong>
                </div>
                <div>
                    <span>Paid</span>
                    <strong>{{ analytics.paid_intents }}</strong>
                </div>
                <div>
                    <span>Used</span>
                    <strong>{{ analytics.used_intents }}</strong>
                </div>
                <div>
                    <span>Failed</span>
                    <strong>{{ analytics.failed_intents }}</strong>
                </div>
                <div>
                    <span>Expired</span>
                    <strong>{{ analytics.expired_intents }}</strong>
                </div>
            </section>

            <section class="analytics-panel">
                <div class="panel-header">
                    <div>
                        <h2>Daily earnings</h2>
                        <p>Last 14 days of PayGo markup earnings.</p>
                    </div>
                </div>
                <div v-if="daily.length" class="bar-chart">
                    <div v-for="point in daily" :key="point.date" class="bar-item">
                        <div class="bar-track">
                            <div class="bar-fill" :style="{ height: `${Math.max(8, (point.earnings / maxDailyEarnings()) * 100)}%` }"></div>
                        </div>
                        <strong>{{ formatCurrency(point.earnings) }}</strong>
                        <span>{{ new Date(point.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) }}</span>
                    </div>
                </div>
                <div v-else class="empty-panel">No PayGo earnings yet.</div>
            </section>

            <section class="analytics-panel">
                <div class="panel-header">
                    <div>
                        <h2>Service performance</h2>
                        <p>Compare price, markup, and paid usage per service.</p>
                    </div>
                </div>
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Price</th>
                            <th>Margin</th>
                            <th>Paid</th>
                            <th>Used</th>
                            <th>Status</th>
                            <th class="text-right">Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="service in servicePerformance" :key="service.id">
                            <td>
                                <div class="font-weight-bold">{{ service.name }}</div>
                                <div class="text-caption text-grey">{{ service.service_name }}</div>
                            </td>
                            <td>{{ formatCurrency(service.price) }}</td>
                            <td>{{ formatCurrency(service.margin) }}</td>
                            <td>{{ service.paid_count }}</td>
                            <td>{{ service.used_count }}</td>
                            <td><v-chip size="small" variant="tonal" :color="service.is_active ? 'success' : 'grey'">{{ service.is_active ? 'Active' : 'Paused' }}</v-chip></td>
                            <td class="text-right">
                                <v-btn icon size="small" variant="text" title="View transactions" :href="`/customer/paygo-transactions?service=${service.id}`">
                                    <v-icon>mdi-receipt-text-outline</v-icon>
                                </v-btn>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </section>
        </div>
    </CustomerLayout>
</template>

<style scoped>
.analytics-page {
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

.page-header p,
.panel-header p {
    margin: 0.35rem 0 0;
    color: #65706b;
}

.metric-grid,
.status-grid {
    display: grid;
    gap: 1rem;
}

.metric-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.metric-card,
.status-grid div,
.analytics-panel {
    border: 1px solid #e3e7e4;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(20, 32, 28, 0.05);
}

.metric-card {
    padding: 1.15rem;
}

.metric-card span,
.metric-card small,
.status-grid span {
    display: block;
    color: #68736e;
}

.metric-card span,
.status-grid span {
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
}

.metric-card strong {
    display: block;
    margin: 0.25rem 0;
    color: #101817;
    font-size: 1.55rem;
}

.primary-card {
    border-color: #146b3a;
    background: #146b3a;
}

.primary-card span,
.primary-card strong,
.primary-card small {
    color: #ffffff;
}

.primary-card span,
.primary-card small {
    opacity: 0.78;
}

.status-grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.status-grid div {
    padding: 1rem;
}

.status-grid strong {
    display: block;
    margin-top: 0.15rem;
    color: #101817;
    font-size: 1.6rem;
}

.analytics-panel {
    padding: 1.25rem;
}

.panel-header {
    margin-bottom: 1rem;
}

.panel-header h2 {
    margin: 0;
    color: #101817;
    font-size: 1.2rem;
    font-weight: 850;
}

.bar-chart {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(72px, 1fr));
    gap: 0.75rem;
    align-items: end;
    min-height: 210px;
}

.bar-item {
    display: grid;
    gap: 0.45rem;
    align-items: end;
    min-width: 0;
}

.bar-track {
    display: flex;
    align-items: end;
    height: 138px;
    border-radius: 8px;
    background: #eef3f0;
    overflow: hidden;
}

.bar-fill {
    width: 100%;
    border-radius: 8px 8px 0 0;
    background: #146b3a;
}

.bar-item strong,
.bar-item span {
    overflow: hidden;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bar-item strong {
    color: #101817;
    font-size: 0.86rem;
}

.bar-item span {
    color: #68736e;
    font-size: 0.8rem;
}

.empty-panel {
    padding: 2rem;
    border: 1px dashed #cfd8d3;
    border-radius: 8px;
    color: #65706b;
    text-align: center;
}

@media (max-width: 1100px) {
    .metric-grid,
    .status-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
    }

    .metric-grid,
    .status-grid {
        grid-template-columns: 1fr;
    }
}
</style>

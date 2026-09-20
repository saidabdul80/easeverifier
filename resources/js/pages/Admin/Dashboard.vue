<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    stats?: { total_customers: number; active_customers: number; total_services: number; active_services: number; total_verifications: number; successful_verifications: number; failed_verifications: number; pending_verifications: number; total_revenue: number; total_wallet_balance: number; today_verifications: number; today_revenue: number };
    paygoStats?: { total_payments: number; successful_payments: number; pending_payments: number; failed_payments: number; gross_revenue: number; system_settlement: number; customer_earnings: number; reference_packages: number; conversion_rate: number };
    recentVerifications?: any[];
    recentPaygo?: any[];
    paygoFilters?: { paygo_customer?: number | string; paygo_status?: string; paygo_package?: string; paygo_date_from?: string; paygo_date_to?: string };
    customerOptions?: Array<{ title: string; value: number }>;
}>();

const customer = ref(props.paygoFilters?.paygo_customer || '');
const status = ref(props.paygoFilters?.paygo_status || '');
const packageType = ref(props.paygoFilters?.paygo_package || '');
const dateFrom = ref(props.paygoFilters?.paygo_date_from || '');
const dateTo = ref(props.paygoFilters?.paygo_date_to || '');
const statusOptions = [{ title: 'All statuses', value: '' }, { title: 'Paid', value: 'paid' }, { title: 'Verifying', value: 'verifying' }, { title: 'Used', value: 'used' }, { title: 'Pending', value: 'pending' }, { title: 'Failed', value: 'failed' }, { title: 'Expired', value: 'expired' }];
const packageOptions = [{ title: 'All packages', value: '' }, { title: 'Reference package', value: 'reference' }, { title: 'Normal payment', value: 'normal' }];

const applyFilters = () => router.get('/admin', {
    paygo_customer: customer.value || undefined,
    paygo_status: status.value || undefined,
    paygo_package: packageType.value || undefined,
    paygo_date_from: dateFrom.value || undefined,
    paygo_date_to: dateTo.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });

watch([customer, status, packageType, dateFrom, dateTo], applyFilters);
const resetFilters = () => { customer.value = ''; status.value = ''; packageType.value = ''; dateFrom.value = ''; dateTo.value = ''; };
const formatCurrency = (amount?: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
const formatDate = (date?: string | null) => date ? new Date(date).toLocaleString() : '-';
const statusColor = (value: string) => ['paid', 'used', 'completed'].includes(value) ? 'success' : ['pending', 'processing', 'verifying'].includes(value) ? 'warning' : ['failed', 'expired'].includes(value) ? 'error' : 'grey';
</script>

<template>
    <Head title="Admin Dashboard - EaseVerifier" />
    <AdminLayout :user="$page.props.auth.user">
        <header class="dashboard-header">
            <div><h1 class="text-h4 font-weight-bold mb-1">Operations dashboard</h1><p class="text-body-2 text-grey mb-0">Platform activity, verification health, and PayGo settlement performance.</p></div>
            <div class="header-actions"><v-btn variant="outlined" prepend-icon="mdi-shield-search" href="/admin/verifications">Verifications</v-btn><v-btn color="primary" prepend-icon="mdi-account-plus" href="/admin/customers/create">Add customer</v-btn></div>
        </header>

        <section class="metric-grid platform-metrics">
            <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="primary">mdi-account-group-outline</v-icon><span>Customers</span></div><div class="metric-value">{{ stats?.total_customers || 0 }}</div><div class="metric-detail">{{ stats?.active_customers || 0 }} active</div></v-card-text></v-card>
            <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="info">mdi-shield-check-outline</v-icon><span>Verifications</span></div><div class="metric-value">{{ stats?.total_verifications || 0 }}</div><div class="metric-detail">{{ stats?.today_verifications || 0 }} today</div></v-card-text></v-card>
            <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="success">mdi-cash-check</v-icon><span>Verification revenue</span></div><div class="metric-value currency">{{ formatCurrency(stats?.total_revenue) }}</div><div class="metric-detail">{{ formatCurrency(stats?.today_revenue) }} today</div></v-card-text></v-card>
            <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="warning">mdi-wallet-outline</v-icon><span>Wallet balances</span></div><div class="metric-value currency">{{ formatCurrency(stats?.total_wallet_balance) }}</div><div class="metric-detail">{{ stats?.active_services || 0 }} active services</div></v-card-text></v-card>
        </section>

        <section class="section-block">
            <div class="section-heading"><div><h2>PayGo analytics</h2><p>Revenue and settlements reflect successful payments in the selected scope.</p></div><v-btn variant="text" color="primary" prepend-icon="mdi-bank-transfer" href="/admin/paystack-splits">Split ledger</v-btn></div>
            <v-card class="filter-panel" variant="outlined"><v-card-text class="filter-grid">
                <v-select v-model="customer" :items="customerOptions || []" label="Customer" variant="outlined" density="compact" hide-details clearable />
                <v-select v-model="status" :items="statusOptions" label="Status" variant="outlined" density="compact" hide-details />
                <v-select v-model="packageType" :items="packageOptions" label="Package" variant="outlined" density="compact" hide-details />
                <v-text-field v-model="dateFrom" type="date" label="From" variant="outlined" density="compact" hide-details />
                <v-text-field v-model="dateTo" type="date" label="To" variant="outlined" density="compact" hide-details />
                <v-btn variant="outlined" prepend-icon="mdi-filter-remove-outline" @click="resetFilters">Reset</v-btn>
            </v-card-text></v-card>

            <div class="metric-grid paygo-metrics">
                <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="primary">mdi-cash-multiple</v-icon><span>Gross revenue</span></div><div class="metric-value currency">{{ formatCurrency(paygoStats?.gross_revenue) }}</div><div class="metric-detail">Successful PayGo charges</div></v-card-text></v-card>
                <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="warning">mdi-bank-transfer-out</v-icon><span>EaseVerifier settlement</span></div><div class="metric-value currency">{{ formatCurrency(paygoStats?.system_settlement) }}</div><div class="metric-detail">System price snapshots</div></v-card-text></v-card>
                <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="success">mdi-trending-up</v-icon><span>Customer earnings</span></div><div class="metric-value currency text-success">{{ formatCurrency(paygoStats?.customer_earnings) }}</div><div class="metric-detail">Gross less settlement</div></v-card-text></v-card>
                <v-card class="metric-card" variant="outlined"><v-card-text><div class="metric-heading"><v-icon color="info">mdi-chart-donut</v-icon><span>Payment conversion</span></div><div class="metric-value">{{ paygoStats?.conversion_rate || 0 }}%</div><div class="metric-detail">{{ paygoStats?.successful_payments || 0 }} of {{ paygoStats?.total_payments || 0 }} successful</div></v-card-text></v-card>
            </div>
        </section>

        <v-row class="dashboard-columns">
            <v-col cols="12" lg="9"><v-card class="data-panel" variant="outlined"><v-card-title class="panel-title"><span>Recent PayGo payments</span><v-btn size="small" variant="text" color="primary" href="/admin/transactions">All transactions</v-btn></v-card-title><div class="table-scroll"><v-table density="comfortable">
                <thead><tr><th>Reference</th><th>Customer</th><th>Package</th><th>Amount</th><th>Settlement</th><th>Earning</th><th>Status</th><th>Date</th></tr></thead>
                <tbody><tr v-for="payment in recentPaygo || []" :key="payment.id"><td class="font-weight-medium">{{ payment.reference }}</td><td><div>{{ payment.customer_name || '-' }}</div><small class="text-grey">{{ payment.customer_email }}</small></td><td><v-chip size="small" variant="tonal" :color="payment.package_type === 'reference' ? 'info' : 'default'">{{ payment.package_type === 'reference' ? 'Reference' : 'Normal' }}</v-chip></td><td>{{ formatCurrency(payment.amount) }}</td><td>{{ formatCurrency(payment.system_price) }}</td><td class="font-weight-bold text-success">{{ formatCurrency(payment.customer_earning) }}</td><td><v-chip size="small" variant="tonal" :color="statusColor(payment.status)">{{ payment.status }}</v-chip></td><td class="text-no-wrap">{{ formatDate(payment.created_at) }}</td></tr><tr v-if="!recentPaygo?.length"><td colspan="8" class="text-center text-grey py-8">No PayGo payments match this scope.</td></tr></tbody>
            </v-table></div></v-card></v-col>
            <v-col cols="12" lg="3"><v-card class="data-panel status-panel" variant="outlined"><v-card-title class="panel-title">Payment health</v-card-title><v-card-text>
                <div class="status-row"><span><i class="status-dot success" />Successful</span><strong>{{ paygoStats?.successful_payments || 0 }}</strong></div>
                <div class="status-row"><span><i class="status-dot warning" />Pending</span><strong>{{ paygoStats?.pending_payments || 0 }}</strong></div>
                <div class="status-row"><span><i class="status-dot error" />Failed / expired</span><strong>{{ paygoStats?.failed_payments || 0 }}</strong></div>
                <div class="status-row"><span><i class="status-dot info" />Reference packages</span><strong>{{ paygoStats?.reference_packages || 0 }}</strong></div>
            </v-card-text></v-card></v-col>
        </v-row>

        <section class="section-block recent-section"><div class="section-heading"><div><h2>Recent verifications</h2><p>Latest verification activity across customers and services.</p></div><v-btn variant="text" color="primary" href="/admin/verifications">View all</v-btn></div><v-card class="data-panel" variant="outlined"><v-table density="comfortable">
            <thead><tr><th>Reference</th><th>Customer</th><th>Service</th><th>Status</th><th>Time</th></tr></thead><tbody><tr v-for="verification in (recentVerifications || []).slice(0, 8)" :key="verification.id"><td class="font-weight-medium">{{ verification.reference }}</td><td>{{ verification.user?.name || 'N/A' }}</td><td>{{ verification.verification_service?.name || 'N/A' }}</td><td><v-chip :color="statusColor(verification.status)" variant="tonal" size="small">{{ verification.status }}</v-chip></td><td>{{ formatDate(verification.created_at) }}</td></tr><tr v-if="!recentVerifications?.length"><td colspan="5" class="text-center text-grey py-8">No verification activity yet.</td></tr></tbody>
        </v-table></v-card></section>
    </AdminLayout>
</template>

<style scoped>
.dashboard-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; margin-bottom:24px; }.header-actions { display:flex; gap:10px; flex-wrap:wrap; }.metric-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }.platform-metrics { margin-bottom:30px; }.metric-card,.filter-panel,.data-panel { border-radius:8px; }.metric-card .v-card-text { padding:18px; }.metric-heading { display:flex; align-items:center; gap:9px; color:rgb(var(--v-theme-on-surface-variant)); font-size:.82rem; margin-bottom:12px; }.metric-value { font-size:1.65rem; line-height:1.15; font-weight:700; }.metric-value.currency { font-size:1.4rem; }.metric-detail { color:#7a817d; font-size:.76rem; margin-top:6px; }.section-block { margin-top:8px; margin-bottom:26px; }.section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; margin-bottom:12px; }.section-heading h2 { font-size:1.15rem; font-weight:700; margin:0 0 3px; }.section-heading p { color:#747b77; font-size:.82rem; margin:0; }.filter-panel { margin-bottom:14px; }.filter-grid { display:grid; grid-template-columns:minmax(210px,1.4fr) repeat(4,minmax(145px,1fr)) auto; align-items:center; gap:10px; padding:14px; }.paygo-metrics { margin-bottom:4px; }.dashboard-columns { margin-top:0; margin-bottom:18px; }.panel-title { display:flex; align-items:center; justify-content:space-between; font-size:1rem; font-weight:700; min-height:52px; border-bottom:1px solid rgba(0,0,0,.08); }.table-scroll { overflow-x:auto; }.status-panel { height:100%; }.status-row { display:flex; align-items:center; justify-content:space-between; padding:15px 0; border-bottom:1px solid rgba(0,0,0,.08); }.status-row:last-child { border-bottom:0; }.status-row span { display:flex; align-items:center; gap:9px; color:#555d58; }.status-dot { display:inline-block; width:9px; height:9px; border-radius:50%; }.status-dot.success { background:#20b97a; }.status-dot.warning { background:#f3a01a; }.status-dot.error { background:#e84d55; }.status-dot.info { background:#3978e8; }.recent-section { margin-bottom:0; }
@media (max-width:1100px) { .metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.filter-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } } @media (max-width:700px) { .dashboard-header,.section-heading { align-items:stretch; flex-direction:column; }.header-actions .v-btn { flex:1; }.metric-grid,.filter-grid { grid-template-columns:1fr; }.metric-value { font-size:1.4rem; } }
</style>

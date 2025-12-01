<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{
    user: { name: string; email: string };
    stats?: {
        total_customers: number;
        active_customers: number;
        total_services: number;
        active_services: number;
        total_verifications: number;
        successful_verifications: number;
        failed_verifications: number;
        pending_verifications: number;
        total_revenue: number;
        total_wallet_balance: number;
        today_verifications: number;
        today_revenue: number;
    };
    recentVerifications?: any[];
    recentTransactions?: any[];
}>();

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
</script>

<template>
    <Head title="Admin Dashboard - EaseVerifier" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Dashboard</h1>
            <p class="text-body-2 text-grey">Welcome back! Here's an overview of your platform.</p>
        </div>

        <!-- Verification Counts by Status -->
        <v-row class="mb-4">
            <v-col cols="6" sm="3">
                <v-card color="primary" variant="tonal">
                    <v-card-text class="text-center py-4">
                        <p class="text-h4 font-weight-bold mb-1">{{ stats?.total_verifications || 0 }}</p>
                        <p class="text-caption mb-0">Total Requests</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card color="success" variant="tonal">
                    <v-card-text class="text-center py-4">
                        <p class="text-h4 font-weight-bold mb-1">{{ stats?.successful_verifications || 0 }}</p>
                        <p class="text-caption mb-0">Completed</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card color="error" variant="tonal">
                    <v-card-text class="text-center py-4">
                        <p class="text-h4 font-weight-bold mb-1">{{ stats?.failed_verifications || 0 }}</p>
                        <p class="text-caption mb-0">Failed</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card color="warning" variant="tonal">
                    <v-card-text class="text-center py-4">
                        <p class="text-h4 font-weight-bold mb-1">{{ stats?.pending_verifications || 0 }}</p>
                        <p class="text-caption mb-0">Pending</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Stats Cards -->
        <v-row class="mb-6">
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="primary-lighten-5" size="48" class="mr-3">
                            <v-icon color="primary">mdi-account-group</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">Total Customers</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ stats?.total_customers || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="success-lighten-5" size="48" class="mr-3">
                            <v-icon color="success">mdi-percent</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">Success Rate</p>
                            <p class="text-h5 font-weight-bold text-success mb-0">
                                {{ stats?.total_verifications ? Math.round((stats?.successful_verifications || 0) / stats.total_verifications * 100) : 0 }}%
                            </p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="warning-lighten-5" size="48" class="mr-3">
                            <v-icon color="warning">mdi-cash</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">Total Revenue</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ formatCurrency(stats?.total_revenue) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="info-lighten-5" size="48" class="mr-3">
                            <v-icon color="info">mdi-wallet</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">Wallet Balance</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ formatCurrency(stats?.total_wallet_balance) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Today's Stats -->
        <v-row class="mb-6">
            <v-col cols="12" md="6">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <p class="text-overline opacity-80">Today's Verifications</p>
                                <p class="text-h3 font-weight-bold mb-0">{{ stats?.today_verifications || 0 }}</p>
                            </div>
                            <v-icon size="64" class="opacity-30">mdi-shield-check</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card color="secondary">
                    <v-card-text class="text-white">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <p class="text-overline opacity-80">Today's Revenue</p>
                                <p class="text-h3 font-weight-bold mb-0">{{ formatCurrency(stats?.today_revenue) }}</p>
                            </div>
                            <v-icon size="64" class="opacity-30">mdi-cash-multiple</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <!-- Recent Verifications -->
            <v-col cols="12" lg="8">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <span>Recent Verifications</span>
                        <v-spacer />
                        <v-btn variant="text" color="primary" size="small" href="/admin/verifications">View All</v-btn>
                    </v-card-title>
                    <v-table density="comfortable">
                        <thead><tr><th>Customer</th><th>Service</th><th>Status</th><th>Time</th></tr></thead>
                        <tbody>
                            <tr v-for="v in (recentVerifications || []).slice(0, 5)" :key="v.id">
                                <td>{{ v.user?.name || 'N/A' }}</td>
                                <td><v-chip size="small" variant="tonal" color="primary">{{ v.verification_service?.name || 'N/A' }}</v-chip></td>
                                <td><v-chip :color="v.status === 'completed' ? 'success' : 'error'" size="small">{{ v.status }}</v-chip></td>
                                <td class="text-grey">{{ new Date(v.created_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="!recentVerifications?.length"><td colspan="4" class="text-center text-grey py-4">No verifications yet</td></tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>

            <!-- Quick Actions -->
            <v-col cols="12" lg="4">
                <v-card class="h-100">
                    <v-card-title>Quick Actions</v-card-title>
                    <v-card-text>
                        <v-btn block color="primary" size="large" class="mb-3" href="/admin/customers/create" prepend-icon="mdi-account-plus">Add Customer</v-btn>
                        <v-btn block variant="outlined" size="large" class="mb-3" href="/admin/services" prepend-icon="mdi-cog">Manage Services</v-btn>
                        <v-btn block variant="outlined" size="large" class="mb-3" href="/admin/wallets" prepend-icon="mdi-wallet">Manage Wallets</v-btn>
                        <v-btn block variant="outlined" size="large" href="/admin/transactions" prepend-icon="mdi-swap-horizontal">View Transactions</v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>


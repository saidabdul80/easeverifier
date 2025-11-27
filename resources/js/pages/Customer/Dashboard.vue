<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed } from 'vue';

defineProps<{
    stats?: {
        wallet_balance: number;
        bonus_balance: number;
        total_verifications: number;
        successful_verifications: number;
        failed_verifications: number;
        this_month_verifications: number;
        this_month_spent: number;
    };
    recentVerifications?: any[];
    recentTransactions?: any[];
    services?: any[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
</script>

<template>
    <Head title="Dashboard - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Welcome back, {{ user?.name?.split(' ')[0] }}!</h1>
            <p class="text-body-2 text-grey">Here's your verification activity at a glance.</p>
        </div>

        <!-- Stats -->
        <v-row class="mb-6">
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="primary-lighten-5" size="48" class="mr-3">
                            <v-icon color="primary">mdi-shield-check</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">Total Verifications</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ stats?.total_verifications || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" lg="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-avatar color="success-lighten-5" size="48" class="mr-3">
                            <v-icon color="success">mdi-check-circle</v-icon>
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
                        <v-avatar color="info-lighten-5" size="48" class="mr-3">
                            <v-icon color="info">mdi-calendar</v-icon>
                        </v-avatar>
                        <div>
                            <p class="text-caption text-grey mb-0">This Month</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ stats?.this_month_verifications || 0 }}</p>
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
                            <p class="text-caption text-grey mb-0">Month Spent</p>
                            <p class="text-h5 font-weight-bold mb-0">{{ formatCurrency(stats?.this_month_spent) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <!-- Quick Actions -->
            <v-col cols="12" lg="4">
                <v-card class="h-100">
                    <v-card-title>Quick Actions</v-card-title>
                    <v-card-text>
                        <v-btn block color="primary" size="large" class="mb-3" href="/customer/verify" prepend-icon="mdi-shield-search">
                            New Verification
                        </v-btn>
                        <v-btn block variant="outlined" size="large" class="mb-3" href="/customer/wallet/fund" prepend-icon="mdi-wallet-plus">
                            Fund Wallet
                        </v-btn>
                        <v-btn block variant="outlined" size="large" href="/customer/api" prepend-icon="mdi-key">
                            API Keys
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Recent Verifications -->
            <v-col cols="12" lg="8">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <span>Recent Verifications</span>
                        <v-spacer />
                        <v-btn variant="text" color="primary" size="small" href="/customer/history">View All</v-btn>
                    </v-card-title>
                    <v-table density="comfortable">
                        <thead>
                            <tr><th>Service</th><th>Search Parameter</th><th>Status</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in (recentVerifications || []).slice(0, 5)" :key="v.id">
                                <td><v-chip size="small" variant="tonal" color="primary">{{ v.verification_service?.name || 'N/A' }}</v-chip></td>
                                <td><code class="bg-grey-lighten-4 pa-1 rounded">{{ v.search_parameter }}</code></td>
                                <td><v-chip :color="v.status === 'completed' ? 'success' : 'error'" size="small">{{ v.status }}</v-chip></td>
                                <td class="text-grey">{{ new Date(v.created_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="!recentVerifications?.length">
                                <td colspan="4" class="text-center text-grey py-4">No verifications yet</td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>


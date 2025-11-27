<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    transactions?: { data: any[]; links: any; meta: any };
    stats?: { total_credits: number; total_debits: number; today_credits: number; today_debits: number };
    filters?: { search?: string; type?: string; category?: string };
}>();

const search = ref(props.filters?.search || '');
const filterType = ref(props.filters?.type || '');

watch([search, filterType], ([s, t]) => {
    router.get('/admin/transactions', { search: s, type: t || undefined }, { preserveState: true, replace: true });
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Reference', key: 'reference' },
    { title: 'Customer', key: 'user' },
    { title: 'Type', key: 'type' },
    { title: 'Category', key: 'category' },
    { title: 'Amount', key: 'amount' },
    { title: 'Status', key: 'status' },
    { title: 'Date', key: 'created_at' },
];
</script>

<template>
    <Head title="Transactions - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Transactions</h1>
                <p class="text-body-2 text-grey">View all platform transactions</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" prepend-icon="mdi-download">Export</v-btn>
        </div>

        <!-- Stats -->
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
                        <v-icon color="success" size="32" class="mr-3">mdi-calendar-today</v-icon>
                        <div>
                            <p class="text-caption mb-0">Today Credits</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(stats?.today_credits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="error" size="32" class="mr-3">mdi-calendar-today</v-icon>
                        <div>
                            <p class="text-caption mb-0">Today Debits</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ formatCurrency(stats?.today_debits) }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-card>
            <v-card-text>
                <div class="d-flex align-center ga-4 mb-4">
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search..." variant="outlined" density="compact" hide-details style="max-width: 300px;" />
                    <v-btn-toggle v-model="filterType" variant="outlined" density="compact">
                        <v-btn value="">All</v-btn>
                        <v-btn value="credit">Credits</v-btn>
                        <v-btn value="debit">Debits</v-btn>
                    </v-btn-toggle>
                </div>

                <v-data-table :headers="headers" :items="transactions?.data || []" hover>
                    <template #item.reference="{ item }">
                        <span class="font-weight-medium text-primary">{{ item.reference }}</span>
                    </template>
                    <template #item.user="{ item }">
                        {{ item.user?.name || 'N/A' }}
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
                        <span :class="item.type === 'credit' ? 'text-success' : 'text-error'" class="font-weight-medium">
                            {{ item.type === 'credit' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                        </span>
                    </template>
                    <template #item.status="{ item }">
                        <v-chip :color="item.status === 'completed' ? 'success' : item.status === 'pending' ? 'warning' : 'error'" size="small">{{ item.status }}</v-chip>
                    </template>
                    <template #item.created_at="{ item }">
                        {{ new Date(item.created_at).toLocaleString() }}
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>


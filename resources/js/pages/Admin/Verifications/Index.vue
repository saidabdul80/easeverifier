<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    verifications?: { data: any[]; links: any; meta: any };
    stats?: { total: number; completed: number; failed: number; pending: number };
    filters?: { search?: string; status?: string };
}>();

const search = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || '');
const detailDialog = ref(false);
const selectedVerification = ref<any>(null);

watch([search, filterStatus], ([s, st]) => {
    router.get('/admin/verifications', { search: s, status: st || undefined }, { preserveState: true, replace: true });
});

const openDetail = (v: any) => {
    selectedVerification.value = v;
    detailDialog.value = true;
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Reference', key: 'reference' },
    { title: 'Customer', key: 'user' },
    { title: 'Service', key: 'service' },
    { title: 'Search Parameter', key: 'search_parameter' },
    { title: 'Status', key: 'status' },
    { title: 'Amount', key: 'amount_charged' },
    { title: 'Date', key: 'created_at' },
    { title: '', key: 'actions', sortable: false },
];
</script>

<template>
    <Head title="Verifications - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Verifications</h1>
                <p class="text-body-2 text-grey">Monitor all verification requests</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" prepend-icon="mdi-download">Export</v-btn>
        </div>

        <!-- Stats -->
        <v-row class="mb-6">
            <v-col cols="6" sm="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats?.total || 0 }}</p>
                    <p class="text-caption text-grey">Total</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-success mb-0">{{ stats?.completed || 0 }}</p>
                    <p class="text-caption text-grey">Completed</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-error mb-0">{{ stats?.failed || 0 }}</p>
                    <p class="text-caption text-grey">Failed</p>
                </v-card-text></v-card>
            </v-col>
            <v-col cols="6" sm="3">
                <v-card><v-card-text class="text-center">
                    <p class="text-h4 font-weight-bold text-warning mb-0">{{ stats?.pending || 0 }}</p>
                    <p class="text-caption text-grey">Pending</p>
                </v-card-text></v-card>
            </v-col>
        </v-row>

        <v-card>
            <v-card-text>
                <div class="d-flex align-center ga-4 mb-4">
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search..." variant="outlined" density="compact" hide-details style="max-width: 300px;" />
                    <v-btn-toggle v-model="filterStatus" variant="outlined" density="compact">
                        <v-btn value="">All</v-btn>
                        <v-btn value="completed">Completed</v-btn>
                        <v-btn value="failed">Failed</v-btn>
                        <v-btn value="pending">Pending</v-btn>
                    </v-btn-toggle>
                </div>

                <v-data-table :headers="headers" :items="verifications?.data || []" hover>
                    <template #item.reference="{ item }">
                        <span class="font-weight-medium text-primary">{{ item.reference }}</span>
                    </template>
                    <template #item.user="{ item }">
                        {{ item.user?.name || 'N/A' }}
                    </template>
                    <template #item.service="{ item }">
                        <v-chip size="small" variant="tonal" color="primary">{{ item.verification_service?.name || 'N/A' }}</v-chip>
                    </template>
                    <template #item.search_parameter="{ item }">
                        <code class="bg-grey-lighten-4 pa-1 rounded">{{ item.search_parameter }}</code>
                    </template>
                    <template #item.status="{ item }">
                        <v-chip :color="item.status === 'completed' ? 'success' : item.status === 'pending' ? 'warning' : 'error'" size="small">
                            <v-icon start size="small">{{ item.status === 'completed' ? 'mdi-check-circle' : item.status === 'pending' ? 'mdi-clock' : 'mdi-close-circle' }}</v-icon>
                            {{ item.status }}
                        </v-chip>
                    </template>
                    <template #item.amount_charged="{ item }">
                        <span class="font-weight-medium">{{ formatCurrency(item.amount_charged) }}</span>
                    </template>
                    <template #item.created_at="{ item }">
                        {{ new Date(item.created_at).toLocaleString() }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn icon variant="text" size="small" @click="openDetail(item)"><v-icon>mdi-eye</v-icon></v-btn>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <!-- Detail Dialog -->
        <v-dialog v-model="detailDialog" max-width="600">
            <v-card v-if="selectedVerification">
                <v-card-title class="d-flex align-center">
                    <v-icon color="primary" class="mr-2">mdi-shield-check</v-icon>
                    Verification Details
                </v-card-title>
                <v-card-text>
                    <v-list density="compact">
                        <v-list-item><v-list-item-title class="text-caption">Reference</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.reference }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Customer</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.user?.name }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Service</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.verification_service?.name }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Search Parameter</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.search_parameter }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Provider</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.service_provider?.name || 'N/A' }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Response Time</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.response_time_ms }}ms</v-list-item-subtitle></v-list-item>
                    </v-list>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog = false">Close</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>


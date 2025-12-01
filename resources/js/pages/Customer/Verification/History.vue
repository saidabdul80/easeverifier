<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    verifications?: { data: any[]; links: any; meta: any; current_page: number; last_page: number; per_page: number; total: number };
    services?: any[];
    filters?: { service?: string; status?: string; date_from?: string; date_to?: string; page?: number };
}>();

const filterService = ref(props.filters?.service || '');
const filterStatus = ref(props.filters?.status || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const currentPage = ref(props.verifications?.current_page || 1);
const detailDialog = ref(false);
const selectedVerification = ref<any>(null);

const totalPages = computed(() => props.verifications?.last_page || 1);

watch([filterService, filterStatus, dateFrom, dateTo], ([s, st, df, dt]) => {
    currentPage.value = 1;
    router.get('/customer/history', {
        service: s || undefined,
        status: st || undefined,
        date_from: df || undefined,
        date_to: dt || undefined,
        page: 1,
    }, { preserveState: true, replace: true });
});

const goToPage = (page: number) => {
    currentPage.value = page;
    router.get('/customer/history', {
        service: filterService.value || undefined,
        status: filterStatus.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        page,
    }, { preserveState: true, replace: true });
};

const openDetail = (v: any) => {
    selectedVerification.value = v;
    detailDialog.value = true;
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Service', key: 'service' },
    { title: 'Search Parameter', key: 'search_parameter' },
    { title: 'Status', key: 'status' },
    { title: 'Amount', key: 'amount_charged' },
    { title: 'Date', key: 'created_at' },
    { title: '', key: 'actions', sortable: false },
];
</script>

<template>
    <Head title="Verification History - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Verification History</h1>
                <p class="text-body-2 text-grey">View all your past verifications</p>
            </div>
            <v-spacer />
            <v-btn variant="outlined" prepend-icon="mdi-download">Export</v-btn>
        </div>

        <v-card>
            <v-card-text>
                <!-- Filters -->
                <v-row class="mb-4">
                    <v-col cols="12" sm="6" md="3">
                        <v-select v-model="filterService" :items="[{ title: 'All Services', value: '' }, ...(services || []).map(s => ({ title: s.name, value: s.id }))]" label="Service" variant="outlined" density="compact" hide-details />
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-select v-model="filterStatus" :items="[{ title: 'All Status', value: '' }, { title: 'Completed', value: 'completed' }, { title: 'Failed', value: 'failed' }, { title: 'Pending', value: 'pending' }]" label="Status" variant="outlined" density="compact" hide-details />
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-text-field v-model="dateFrom" label="From Date" type="date" variant="outlined" density="compact" hide-details />
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-text-field v-model="dateTo" label="To Date" type="date" variant="outlined" density="compact" hide-details />
                    </v-col>
                </v-row>

                <v-data-table :headers="headers" :items="verifications?.data || []" :items-per-page="-1" hover>
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
                        <v-btn icon variant="text" size="small" :href="`/customer/verification/${item.id}`"><v-icon>mdi-open-in-new</v-icon></v-btn>
                    </template>
                    <template #bottom></template>
                </v-data-table>

                <!-- Server-side Pagination -->
                <div class="d-flex align-center justify-space-between mt-4">
                    <span class="text-caption text-grey">
                        Showing {{ ((verifications?.current_page || 1) - 1) * (verifications?.per_page || 20) + 1 }}
                        to {{ Math.min((verifications?.current_page || 1) * (verifications?.per_page || 20), verifications?.total || 0) }}
                        of {{ verifications?.total || 0 }} results
                    </span>
                    <v-pagination
                        v-model="currentPage"
                        :length="totalPages"
                        :total-visible="7"
                        density="comfortable"
                        @update:model-value="goToPage"
                    />
                </div>
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
                        <v-list-item><v-list-item-title class="text-caption">Service</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.verification_service?.name }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Search Parameter</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.search_parameter }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Amount Charged</v-list-item-title><v-list-item-subtitle>{{ formatCurrency(selectedVerification.amount_charged) }}</v-list-item-subtitle></v-list-item>
                        <v-list-item><v-list-item-title class="text-caption">Response Time</v-list-item-title><v-list-item-subtitle>{{ selectedVerification.response_time_ms }}ms</v-list-item-subtitle></v-list-item>
                    </v-list>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="detailDialog = false">Close</v-btn>
                    <v-btn color="primary" :href="`/customer/verification/${selectedVerification.id}`">View Full Result</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </CustomerLayout>
</template>


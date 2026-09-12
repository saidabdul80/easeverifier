<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed, ref, watch } from 'vue';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type PaygoUser = {
    id: number;
    reference: string;
    email?: string | null;
    phone?: string | null;
    flow_type: string;
    status: string;
    lookup_label?: string | null;
    amount: number;
    paid_at?: string | null;
    used_at?: string | null;
    expires_at?: string | null;
    created_at: string;
    customer?: {
        id?: number | null;
        name?: string | null;
        email?: string | null;
        referral_code?: string | null;
    };
    paygo_service?: {
        id?: number | null;
        name?: string | null;
        public_slug?: string | null;
    };
    verification_service?: {
        id?: number | null;
        name?: string | null;
        slug?: string | null;
    };
    verification_request?: {
        id: number;
        reference: string;
        status: string;
        search_parameter?: string | null;
    } | null;
};

const props = defineProps<{
    paygoUsers?: Paginated<PaygoUser>;
    stats?: { total: number; paid: number; pending: number; result: number };
    filters?: { search?: string; status?: string; flow_type?: string; page?: number };
}>();

const search = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || '');
const filterFlow = ref(props.filters?.flow_type || '');
const currentPage = ref(props.paygoUsers?.current_page || 1);

const totalPages = computed(() => props.paygoUsers?.last_page || 1);

watch([search, filterStatus, filterFlow], ([searchValue, statusValue, flowValue]) => {
    currentPage.value = 1;
    router.get(
        '/admin/paygo-users',
        {
            search: searchValue || undefined,
            status: statusValue || undefined,
            flow_type: flowValue || undefined,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
});

const goToPage = (page: number) => {
    currentPage.value = page;
    router.get(
        '/admin/paygo-users',
        {
            search: search.value || undefined,
            status: filterStatus.value || undefined,
            flow_type: filterFlow.value || undefined,
            page,
        },
        { preserveState: true, replace: true },
    );
};

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
    }).format(amount || 0);

const formatDate = (value?: string | null) => {
    if (!value) return '-';

    return new Date(value).toLocaleString();
};

const statusColor = (status: string) => {
    if (['paid', 'used'].includes(status)) return 'success';
    if (['pending', 'verifying'].includes(status)) return 'warning';
    if (['failed', 'expired'].includes(status)) return 'error';

    return 'default';
};

const headers = [
    { title: 'Contact', key: 'contact', sortable: false },
    { title: 'Reference', key: 'reference' },
    { title: 'PayGo Service', key: 'paygo_service', sortable: false },
    { title: 'Customer', key: 'customer', sortable: false },
    { title: 'Flow', key: 'flow_type' },
    { title: 'Status', key: 'status' },
    { title: 'Amount', key: 'amount' },
    { title: 'Created', key: 'created_at' },
    { title: '', key: 'actions', sortable: false },
];
</script>

<template>
    <Head title="PayGo Users - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">PayGo Users</h1>
                <p class="text-body-2 text-grey">Manage public PayGo contacts collected during verification flows</p>
            </div>
        </div>

        <v-row class="mb-6">
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="primary" size="32" class="mr-3">mdi-account-cash-outline</v-icon>
                        <div>
                            <p class="text-caption mb-0">Contacts</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ stats?.total || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="success" size="32" class="mr-3">mdi-check-circle-outline</v-icon>
                        <div>
                            <p class="text-caption mb-0">Paid / Used</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ stats?.paid || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="warning" size="32" class="mr-3">mdi-clock-outline</v-icon>
                        <div>
                            <p class="text-caption mb-0">Pending</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ stats?.pending || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="3">
                <v-card>
                    <v-card-text class="d-flex align-center">
                        <v-icon color="secondary" size="32" class="mr-3">mdi-school-outline</v-icon>
                        <div>
                            <p class="text-caption mb-0">Result Flow</p>
                            <p class="text-h6 font-weight-bold mb-0">{{ stats?.result || 0 }}</p>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-card>
            <v-card-text>
                <div class="d-flex flex-wrap align-center ga-3 mb-4">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        label="Search email, phone, reference..."
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 340px"
                    />
                    <v-select
                        v-model="filterFlow"
                        :items="[
                            { title: 'All flows', value: '' },
                            { title: 'Identity', value: 'identity' },
                            { title: 'Result', value: 'result' },
                        ]"
                        label="Flow"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 180px"
                    />
                    <v-select
                        v-model="filterStatus"
                        :items="[
                            { title: 'All statuses', value: '' },
                            { title: 'Pending', value: 'pending' },
                            { title: 'Paid', value: 'paid' },
                            { title: 'Verifying', value: 'verifying' },
                            { title: 'Used', value: 'used' },
                            { title: 'Failed', value: 'failed' },
                            { title: 'Expired', value: 'expired' },
                        ]"
                        label="Status"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 190px"
                    />
                </div>

                <v-data-table :headers="headers" :items="paygoUsers?.data || []" :items-per-page="-1" hover>
                    <template #item.contact="{ item }">
                        <div class="py-2">
                            <div class="font-weight-medium">{{ item.email || 'No email provided' }}</div>
                            <div class="text-caption text-grey">{{ item.phone || 'No phone provided' }}</div>
                        </div>
                    </template>
                    <template #item.reference="{ item }">
                        <div>
                            <span class="font-weight-medium text-primary">{{ item.reference }}</span>
                            <div class="text-caption text-grey">{{ item.lookup_label || '-' }}</div>
                        </div>
                    </template>
                    <template #item.paygo_service="{ item }">
                        <div>
                            <v-chip size="small" color="primary" variant="tonal">{{ item.paygo_service?.name || 'N/A' }}</v-chip>
                            <div class="text-caption text-grey mt-1">{{ item.verification_service?.name || '-' }}</div>
                        </div>
                    </template>
                    <template #item.customer="{ item }">
                        <div>
                            <span>{{ item.customer?.name || 'N/A' }}</span>
                            <div class="text-caption text-grey">{{ item.customer?.email || '-' }}</div>
                        </div>
                    </template>
                    <template #item.flow_type="{ item }">
                        <v-chip size="small" variant="outlined">{{ item.flow_type }}</v-chip>
                    </template>
                    <template #item.status="{ item }">
                        <v-chip :color="statusColor(item.status)" size="small" variant="tonal">
                            {{ item.status }}
                        </v-chip>
                    </template>
                    <template #item.amount="{ item }">
                        <span class="font-weight-medium">{{ formatCurrency(item.amount) }}</span>
                    </template>
                    <template #item.created_at="{ item }">
                        <div>
                            {{ formatDate(item.created_at) }}
                            <div v-if="item.paid_at" class="text-caption text-success">Paid: {{ formatDate(item.paid_at) }}</div>
                        </div>
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn
                            v-if="item.verification_request"
                            icon
                            variant="text"
                            size="small"
                            :href="`/admin/verifications/${item.verification_request.id}`"
                        >
                            <v-icon>mdi-shield-search</v-icon>
                        </v-btn>
                    </template>
                    <template #bottom></template>
                </v-data-table>

                <div class="d-flex align-center justify-space-between mt-4">
                    <span class="text-caption text-grey">
                        Showing {{ ((paygoUsers?.current_page || 1) - 1) * (paygoUsers?.per_page || 20) + 1 }}
                        to {{ Math.min((paygoUsers?.current_page || 1) * (paygoUsers?.per_page || 20), paygoUsers?.total || 0) }}
                        of {{ paygoUsers?.total || 0 }} contacts
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
    </AdminLayout>
</template>

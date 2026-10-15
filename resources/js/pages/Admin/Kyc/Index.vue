<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    submissions: {
        data: {
            id: number;
            name: string;
            email: string;
            company: string | null;
            status: string;
            submitted_at: string;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { status: string; search: string };
}>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const filter = (page = 1) =>
    router.get(
        '/admin/kyc',
        { search: search.value, status: status.value, page },
        { preserveState: true, replace: true },
    );
const statusColor = (value: string) =>
    ({ pending: 'warning', approved: 'success', rejected: 'error' })[value] ||
    'grey';
</script>

<template>
    <Head title="KYC Reviews - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <header class="mb-6">
            <h1 class="text-h5 font-weight-bold">KYC Reviews</h1>
            <p class="text-body-2 text-grey">Customer API access approvals</p>
        </header>
        <div class="d-flex flex-column flex-sm-row ga-3 mb-5">
            <v-text-field
                v-model="search"
                label="Customer name or email"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                @keyup.enter="filter()"
                clearable
                @click:clear="
                    search = '';
                    filter();
                "
            />
            <v-select
                v-model="status"
                :items="[
                    { title: 'Awaiting review', value: 'pending' },
                    { title: 'Approved', value: 'approved' },
                    { title: 'Rejected', value: 'rejected' },
                    { title: 'All submissions', value: 'all' },
                ]"
                label="Status"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 260px; min-width: 180px"
                @update:model-value="filter()"
            />
            <v-btn
                variant="outlined"
                prepend-icon="mdi-filter-outline"
                @click="filter()"
                >Filter</v-btn
            >
        </div>
        <div class="kyc-table-wrap">
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-right">Review</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="submission in submissions.data"
                        :key="submission.id"
                    >
                        <td class="py-3">
                            <strong>{{ submission.name }}</strong>
                            <div class="text-caption text-grey">
                                {{ submission.email }}
                            </div>
                        </td>
                        <td>{{ submission.company || '-' }}</td>
                        <td>
                            <v-chip
                                size="small"
                                :color="statusColor(submission.status)"
                                >{{ submission.status }}</v-chip
                            >
                        </td>
                        <td>
                            {{
                                new Date(
                                    submission.submitted_at,
                                ).toLocaleString()
                            }}
                        </td>
                        <td class="text-right">
                            <v-btn
                                :href="`/admin/kyc/${submission.id}`"
                                icon="mdi-eye-outline"
                                size="small"
                                variant="text"
                                title="Review KYC"
                                aria-label="Review KYC"
                            />
                        </td>
                    </tr>
                    <tr v-if="!submissions.data.length">
                        <td colspan="5" class="text-grey py-10 text-center">
                            No KYC submissions found.
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </div>
        <div
            class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3 mt-4"
        >
            <span class="text-body-2 text-grey"
                >{{ submissions.total }} submissions</span
            >
            <v-pagination
                :model-value="submissions.current_page"
                :length="submissions.last_page"
                :total-visible="5"
                density="compact"
                @update:model-value="filter"
            />
        </div>
    </AdminLayout>
</template>

<style scoped>
.kyc-table-wrap {
    overflow: auto;
    background: #fff;
    border-top: 1px solid #dfe4e9;
}
.kyc-table-wrap td {
    min-width: 130px;
    overflow-wrap: anywhere;
}
.kyc-table-wrap td:first-child {
    min-width: 200px;
}
</style>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    verification: any;
    service: any;
    result: { success: boolean; data?: any; error_message?: string };
    searchParameter: string;
    providedInputs?: Record<string, any>;
}>();

const providedInputRows = computed(() => Object.entries(props.providedInputs || {}));
const resultDataRows = computed(() => Object.entries(props.result.data || {}));

const formatInputLabel = (key: string) => key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const formatValue = (value: any) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

const formatCurrency = (amount: any) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(Number(amount || 0));

const formatDateTime = (value: string | null | undefined) => value ? new Date(value).toLocaleString() : '-';
</script>

<template>
    <Head title="Verification Result - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/admin/verifications" class="mb-2">Back to Verifications</v-btn>
            <div class="d-flex align-start">
                <div>
                    <h1 class="text-h4 font-weight-bold mb-1">Verification Result</h1>
                    <p class="text-body-2 text-grey">{{ service?.name || 'Verification' }} for {{ searchParameter }}</p>
                </div>
                <v-spacer />
                <v-chip :color="verification.status === 'completed' ? 'success' : verification.status === 'pending' ? 'warning' : 'error'" size="large">
                    <v-icon start>{{ verification.status === 'completed' ? 'mdi-check-circle' : verification.status === 'pending' ? 'mdi-clock' : 'mdi-close-circle' }}</v-icon>
                    {{ verification.status }}
                </v-chip>
            </div>
        </div>

        <v-row>
            <v-col cols="12" lg="8">
                <v-card :color="result.success ? 'success-lighten-5' : 'error-lighten-5'" class="mb-6">
                    <v-card-text class="text-center py-8">
                        <v-avatar :color="result.success ? 'success' : 'error'" size="80" class="mb-4">
                            <v-icon size="48" color="white">{{ result.success ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
                        </v-avatar>
                        <h2 class="text-h5 font-weight-bold mb-2" :class="result.success ? 'text-success' : 'text-error'">
                            {{ result.success ? 'Verification Successful' : 'Verification Failed' }}
                        </h2>
                        <p class="text-body-2 text-grey">
                            {{ result.success ? 'The identity has been verified successfully.' : (result.error_message || 'Unable to verify the provided information.') }}
                        </p>
                    </v-card-text>
                </v-card>

                <v-card v-if="providedInputRows.length" class="mb-6">
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-form-textbox</v-icon>
                        Provided Inputs
                    </v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <tbody>
                                <tr v-for="[key, value] in providedInputRows" :key="key">
                                    <td class="font-weight-medium" style="width: 220px;">{{ formatInputLabel(key) }}</td>
                                    <td class="text-break">
                                        <pre v-if="typeof value === 'object' && value !== null" class="text-body-2 bg-grey-lighten-4 pa-2 rounded">{{ formatValue(value) }}</pre>
                                        <template v-else>{{ formatValue(value) }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>

                <v-card v-if="result.success && resultDataRows.length" class="mb-6">
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-account-details</v-icon>
                        Verification Data
                    </v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <tbody>
                                <tr v-for="[key, value] in resultDataRows" :key="key">
                                    <td class="font-weight-medium" style="width: 220px;">{{ formatInputLabel(key) }}</td>
                                    <td class="text-break">
                                        <pre v-if="typeof value === 'object' && value !== null" class="text-body-2 bg-grey-lighten-4 pa-2 rounded">{{ formatValue(value) }}</pre>
                                        <template v-else>{{ formatValue(value) }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>

                <v-card v-if="!result.success">
                    <v-card-title class="d-flex align-center">
                        <v-icon color="error" class="mr-2">mdi-alert-circle</v-icon>
                        Error Details
                    </v-card-title>
                    <v-card-text>
                        <v-alert type="error" variant="tonal">
                            {{ result.error_message || 'An unknown error occurred during verification.' }}
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="4">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-shield-search</v-icon>
                        Request Details
                    </v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item>
                                <v-list-item-title class="text-caption">Reference</v-list-item-title>
                                <v-list-item-subtitle>{{ verification.reference }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Customer</v-list-item-title>
                                <v-list-item-subtitle>{{ verification.user?.name || 'N/A' }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Email</v-list-item-title>
                                <v-list-item-subtitle>{{ verification.user?.email || 'N/A' }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Service</v-list-item-title>
                                <v-list-item-subtitle>{{ service?.name || 'N/A' }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Provider</v-list-item-title>
                                <v-list-item-subtitle>{{ verification.service_provider?.name || 'N/A' }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Amount Charged</v-list-item-title>
                                <v-list-item-subtitle>{{ formatCurrency(verification.amount_charged) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Source</v-list-item-title>
                                <v-list-item-subtitle>{{ verification.source || 'N/A' }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Created</v-list-item-title>
                                <v-list-item-subtitle>{{ formatDateTime(verification.created_at) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Completed</v-list-item-title>
                                <v-list-item-subtitle>{{ formatDateTime(verification.completed_at) }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>

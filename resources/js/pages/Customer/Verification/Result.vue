<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';

defineProps<{
    user: { name: string; email: string };
    service: any;
    result: { success: boolean; data?: any; error_message?: string; reference?: string };
    searchParameter: string;
    verification?: any;
    cached?: boolean;
}>();
</script>

<template>
    <Head :title="`Verification Result - EaseVerifier`" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/verify" class="mb-2">Back to Services</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Verification Result</h1>
            <p class="text-body-2 text-grey">{{ service.name }} verification for {{ searchParameter }}</p>
        </div>

        <v-row justify="center">
            <v-col cols="12" md="8">
                <!-- Cached Result Banner -->
                <v-alert v-if="cached" type="info" variant="tonal" class="mb-4" density="compact" icon="mdi-cached">
                    <strong>Cached Result:</strong> This verification was previously completed. No additional charge was applied.
                </v-alert>

                <!-- Status Card -->
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

                <!-- Result Data -->
                <v-card v-if="result.success && result.data">
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-account-details</v-icon>
                        Verification Data
                        <v-spacer />
                        <v-btn variant="outlined" size="small" prepend-icon="mdi-download">Download</v-btn>
                    </v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <tbody>
                                <tr v-for="(value, key) in result.data" :key="key">
                                    <td class="text-capitalize font-weight-medium" style="width: 200px;">{{ String(key).replace(/_/g, ' ') }}</td>
                                    <td>
                                        <template v-if="typeof value === 'object' && value !== null">
                                            <pre class="text-body-2 bg-grey-lighten-4 pa-2 rounded">{{ JSON.stringify(value, null, 2) }}</pre>
                                        </template>
                                        <template v-else>{{ value || '-' }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>

                <!-- Error Details -->
                <v-card v-if="!result.success">
                    <v-card-title class="d-flex align-center">
                        <v-icon color="error" class="mr-2">mdi-alert-circle</v-icon>
                        Error Details
                    </v-card-title>
                    <v-card-text>
                        <v-alert type="error" variant="tonal">
                            {{ result.error_message || 'An unknown error occurred during verification.' }}
                        </v-alert>
                        <div class="mt-4">
                            <p class="text-body-2 text-grey mb-2">Possible reasons:</p>
                            <ul class="text-body-2 text-grey pl-4">
                                <li>The provided number may be incorrect</li>
                                <li>The record may not exist in the database</li>
                                <li>There may be a temporary service issue</li>
                            </ul>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Actions -->
                <div class="d-flex justify-center ga-4 mt-6">
                    <v-btn variant="outlined" :href="`/customer/verify/${service.id}`" prepend-icon="mdi-refresh">
                        Verify Another
                    </v-btn>
                    <v-btn color="primary" href="/customer/history" prepend-icon="mdi-history">
                        View History
                    </v-btn>
                </div>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>


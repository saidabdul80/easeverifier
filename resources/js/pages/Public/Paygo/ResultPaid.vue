<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    paygoService: {
        name: string;
        price: number;
        board: string;
        customer_name?: string | null;
    };
    intent: {
        reference: string;
        status: string;
        lookup_label?: string | null;
        paid_at?: string | null;
        fetches_used: number;
        fetches_allowed: number;
        fetches_remaining: number;
        pull_url: string;
    };
    verification?: any;
    result: {
        success: boolean;
        data?: any;
        error?: string | null;
    };
}>();

const candidateRows = computed(() => Object.entries(props.result.data?.candidate || {}));
const subjects = computed(() => props.result.data?.subjects || props.result.data?.result?.subjects || []);

const copyReference = async () => {
    await navigator.clipboard.writeText(props.intent.reference);
};

const copyPullUrl = async () => {
    await navigator.clipboard.writeText(props.intent.pull_url);
};
</script>

<template>
    <Head :title="`${paygoService.board} Result - PayGo`" />

    <v-app>
        <v-main class="paygo-main">
            <v-container class="paygo-container">
                <v-row justify="center">
                    <v-col cols="12" lg="9">
                        <v-card class="paygo-card mb-5" elevation="0">
                            <v-card-text class="pa-6">
                                <v-chip :color="result.success ? 'success' : 'error'" variant="flat" class="mb-4">
                                    {{ result.success ? 'Result ready' : 'Result unavailable' }}
                                </v-chip>
                                <h1 class="text-h4 font-weight-bold mb-2">{{ paygoService.board }} Result Verification</h1>
                                <p class="text-body-2 text-grey-darken-1 mb-5">{{ intent.lookup_label || paygoService.name }}</p>

                                <div class="reference-row">
                                    <div>
                                        <span>Payment reference</span>
                                        <strong>{{ intent.reference }}</strong>
                                    </div>
                                    <v-btn icon variant="outlined" title="Copy reference" @click="copyReference">
                                        <v-icon>mdi-content-copy</v-icon>
                                    </v-btn>
                                </div>

                                <div class="reference-row mt-3">
                                    <div>
                                        <span>Open result endpoint</span>
                                        <code>{{ intent.pull_url }}</code>
                                    </div>
                                    <v-btn icon variant="outlined" title="Copy endpoint" @click="copyPullUrl">
                                        <v-icon>mdi-content-copy</v-icon>
                                    </v-btn>
                                </div>

                                <v-alert type="info" variant="tonal" class="mt-4">
                                    Endpoint pulls remaining: {{ intent.fetches_remaining }} of {{ intent.fetches_allowed }}.
                                </v-alert>
                            </v-card-text>
                        </v-card>

                        <v-alert v-if="!result.success" type="error" variant="tonal" class="mb-5">
                            {{ result.error || 'Result verification failed. Please contact the service owner with the payment reference.' }}
                        </v-alert>

                        <v-card v-if="result.success" class="paygo-card mb-5" elevation="0">
                            <v-card-title>Candidate</v-card-title>
                            <v-card-text>
                                <v-table density="comfortable">
                                    <tbody>
                                        <tr v-for="[key, value] in candidateRows" :key="key">
                                            <td class="label-cell">{{ String(key).replace(/_/g, ' ') }}</td>
                                            <td>{{ value || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-card-text>
                        </v-card>

                        <v-card v-if="result.success && subjects.length" class="paygo-card" elevation="0">
                            <v-card-title>Subjects</v-card-title>
                            <v-card-text>
                                <v-table density="comfortable">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Grade</th>
                                            <th>Score</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(subject, index) in subjects" :key="index">
                                            <td>{{ subject.subject || subject.name || '-' }}</td>
                                            <td>{{ subject.grade || '-' }}</td>
                                            <td>{{ subject.score || '-' }}</td>
                                            <td>{{ subject.remark || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.paygo-main {
    min-height: 100vh;
    background: #f3f6f4;
}

.paygo-container {
    padding-top: 3rem;
    padding-bottom: 3rem;
}

.paygo-card {
    border-radius: 8px;
    border: 1px solid #dfe7e2;
}

.reference-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    background: #f6f8f6;
}

.reference-row span {
    display: block;
    margin-bottom: 0.25rem;
    color: #5f6f65;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
}

.reference-row strong,
.reference-row code {
    color: #0f3e20;
    font-weight: 800;
    word-break: break-word;
}

.label-cell {
    width: 240px;
    font-weight: 700;
    text-transform: capitalize;
}
</style>

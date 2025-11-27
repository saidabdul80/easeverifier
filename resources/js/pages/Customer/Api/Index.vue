<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref, computed } from 'vue';

interface ApiKey {
    id: number;
    name: string;
    key: string;
    environment: 'live' | 'test';
    is_active: boolean;
    rate_limit: number;
    last_used_at: string | null;
    created_at: string;
}

const props = defineProps<{
    apiKeys: ApiKey[];
    customer?: any;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as any);

const showCreateDialog = ref(false);
const showCredentialsDialog = ref(false);
const newCredentials = ref<{ key: string; secret: string; bearer_token: string } | null>(null);

const createForm = useForm({
    name: 'Default',
    environment: 'live' as 'live' | 'test',
});

const webhookForm = useForm({ webhook_url: props.customer?.webhook_url || '' });

const createKey = () => {
    createForm.post('/customer/api/keys', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
            if (flash.value?.newKey) {
                newCredentials.value = flash.value.newKey;
                showCredentialsDialog.value = true;
            }
        }
    });
};

const regenerateKey = (keyId: number) => {
    if (confirm('This will invalidate the current secret. Continue?')) {
        router.post(`/customer/api/keys/${keyId}/regenerate`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (flash.value?.newKey) {
                    newCredentials.value = flash.value.newKey;
                    showCredentialsDialog.value = true;
                }
            }
        });
    }
};

const toggleKey = (keyId: number) => {
    router.post(`/customer/api/keys/${keyId}/toggle`, {}, { preserveScroll: true });
};

const deleteKey = (keyId: number) => {
    if (confirm('Are you sure you want to delete this API key? This cannot be undone.')) {
        router.delete(`/customer/api/keys/${keyId}`, { preserveScroll: true });
    }
};

const updateWebhook = () => webhookForm.post('/customer/api/webhook');

const copyToClipboard = async (text: string) => {
    await navigator.clipboard.writeText(text);
};
</script>

<template>
    <Head title="API Keys - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">API Integration</h1>
                <p class="text-body-2 text-grey">Manage your API keys for programmatic access</p>
            </div>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-plus" @click="showCreateDialog = true">
                Create API Key
            </v-btn>
        </div>

        <!-- API Keys List -->
        <v-card class="mb-6">
            <v-card-title class="d-flex align-center">
                <v-icon color="primary" class="mr-2">mdi-key-variant</v-icon>
                Your API Keys
                <v-chip size="small" class="ml-2">{{ apiKeys.length }}/5</v-chip>
            </v-card-title>
            <v-card-text v-if="!apiKeys.length">
                <v-alert type="info" variant="tonal">
                    You haven't created any API keys yet. Create one to start using the API.
                </v-alert>
            </v-card-text>
            <v-table v-else density="comfortable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="key in apiKeys" :key="key.id">
                        <td class="font-weight-medium">{{ key.name }}</td>
                        <td>
                            <code class="bg-grey-lighten-4 pa-1 rounded">{{ key.key.substring(0, 20) }}...</code>
                            <v-btn icon size="x-small" variant="text" @click="copyToClipboard(key.key)">
                                <v-icon size="16">mdi-content-copy</v-icon>
                            </v-btn>
                        </td>
                        <td>
                            <v-chip :color="key.environment === 'live' ? 'success' : 'warning'" size="small">
                                {{ key.environment }}
                            </v-chip>
                        </td>
                        <td>
                            <v-chip :color="key.is_active ? 'success' : 'grey'" size="small">
                                {{ key.is_active ? 'Active' : 'Inactive' }}
                            </v-chip>
                        </td>
                        <td class="text-grey">{{ key.last_used_at || 'Never' }}</td>
                        <td class="text-grey">{{ key.created_at }}</td>
                        <td class="text-right">
                            <v-btn icon size="small" variant="text" @click="toggleKey(key.id)" :title="key.is_active ? 'Deactivate' : 'Activate'">
                                <v-icon>{{ key.is_active ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" color="warning" @click="regenerateKey(key.id)" title="Regenerate Secret">
                                <v-icon>mdi-refresh</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" color="error" @click="deleteKey(key.id)" title="Delete">
                                <v-icon>mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <!-- Webhook -->
        <v-row>
            <v-col cols="12" md="6">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-webhook</v-icon>
                        Webhook URL
                    </v-card-title>
                    <v-card-text>
                        <p class="text-body-2 text-grey mb-4">Receive real-time notifications for verification results.</p>
                        <v-text-field v-model="webhookForm.webhook_url" label="Webhook URL" variant="outlined" placeholder="https://your-domain.com/webhook" :error-messages="webhookForm.errors.webhook_url" />
                        <v-btn color="primary" :loading="webhookForm.processing" @click="updateWebhook" class="mt-2">Save</v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card>
                    <v-card-title class="d-flex align-center">
                        <v-icon color="primary" class="mr-2">mdi-book-open-variant</v-icon>
                        API Documentation
                    </v-card-title>
                    <v-card-text>
                        <p class="text-body-2 text-grey mb-4">Learn how to integrate EaseVerifier API into your application.</p>
                        <v-btn variant="outlined" href="/documentation" target="_blank">View Documentation</v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Create Dialog -->
        <v-dialog v-model="showCreateDialog" max-width="500">
            <v-card>
                <v-card-title>Create API Key</v-card-title>
                <v-card-text>
                    <v-text-field v-model="createForm.name" label="Key Name" variant="outlined" placeholder="e.g., Production Server" class="mb-4" :error-messages="createForm.errors.name" />
                    <v-select v-model="createForm.environment" :items="[{ title: 'Live', value: 'live' }, { title: 'Test', value: 'test' }]" label="Environment" variant="outlined" :error-messages="createForm.errors.environment" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="createForm.processing" @click="createKey">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Credentials Dialog -->
        <v-dialog v-model="showCredentialsDialog" max-width="600" persistent>
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="warning" class="mr-2">mdi-alert</v-icon>
                    Save Your Credentials
                </v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">
                        Copy these credentials now. The secret will not be shown again!
                    </v-alert>
                    <v-text-field :model-value="newCredentials?.key" label="API Key" variant="outlined" readonly append-inner-icon="mdi-content-copy" @click:append-inner="copyToClipboard(newCredentials?.key || '')" class="mb-4" />
                    <v-text-field :model-value="newCredentials?.secret" label="API Secret" variant="outlined" readonly append-inner-icon="mdi-content-copy" @click:append-inner="copyToClipboard(newCredentials?.secret || '')" class="mb-4" />
                    <v-text-field :model-value="newCredentials?.bearer_token" label="Bearer Token (Base64)" variant="outlined" readonly append-inner-icon="mdi-content-copy" @click:append-inner="copyToClipboard(newCredentials?.bearer_token || '')" hint="Use this token in Authorization header: Bearer {token}" persistent-hint />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" @click="showCredentialsDialog = false; newCredentials = null">I've saved my credentials</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </CustomerLayout>
</template>


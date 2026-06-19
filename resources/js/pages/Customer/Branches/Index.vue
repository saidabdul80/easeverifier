<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';

const props = defineProps<{
    primaryWallet?: { balance: number; bonus_balance: number; total_balance: number; currency: string } | null;
    branches: Array<{
        id: number;
        name: string;
        code: string;
        contact_email?: string | null;
        contact_phone?: string | null;
        address?: string | null;
        is_active: boolean;
        wallet?: { balance: number; bonus_balance: number; total_balance: number; currency: string } | null;
        api_keys: Array<{
            id: number;
            name: string;
            key: string;
            environment: 'live' | 'test';
            is_active: boolean;
            last_used_at?: string | null;
            created_at: string;
        }>;
    }>;
    stats: {
        branch_count: number;
        active_branch_count: number;
        total_branch_balance: number;
    };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as any);
const { smAndDown } = useDisplay();

const showCreateDialog = ref(false);
const showTransferDialog = ref(false);
const showKeyDialog = ref(false);
const showCredentialsDialog = ref(false);
const editingBranch = ref<any | null>(null);
const selectedBranchForKey = ref<any | null>(null);
const newCredentials = ref<{ key: string; secret: string; bearer_token: string } | null>(null);

const createBranchForm = useForm({
    name: '',
    contact_email: '',
    contact_phone: '',
    address: '',
});

const editBranchForm = useForm({
    name: '',
    contact_email: '',
    contact_phone: '',
    address: '',
    is_active: true,
});

const transferForm = useForm({
    from_branch_id: null as number | null,
    to_branch_id: null as number | null,
    amount: 0,
});

const keyForm = useForm({
    branch_id: null as number | null,
    name: 'Branch Key',
    environment: 'live' as 'live' | 'test',
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const walletTargets = computed(() => [
    { title: 'Primary wallet', value: null },
    ...props.branches.map((branch) => ({
        title: `${branch.name} (${branch.code})`,
        value: branch.id,
    })),
]);

const openEditDialog = (branch: any) => {
    editingBranch.value = branch;
    editBranchForm.name = branch.name;
    editBranchForm.contact_email = branch.contact_email || '';
    editBranchForm.contact_phone = branch.contact_phone || '';
    editBranchForm.address = branch.address || '';
    editBranchForm.is_active = branch.is_active;
};

const openKeyDialog = (branch: any) => {
    selectedBranchForKey.value = branch;
    keyForm.branch_id = branch.id;
    keyForm.name = `${branch.name} Key`;
    keyForm.environment = 'live';
    showKeyDialog.value = true;
};

const createBranch = () => {
    createBranchForm.post('/customer/branches', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            createBranchForm.reset();
        },
    });
};

const updateBranch = () => {
    if (!editingBranch.value) return;

    editBranchForm.put(`/customer/branches/${editingBranch.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingBranch.value = null;
        },
    });
};

const transferFunds = () => {
    transferForm.post('/customer/branches/transfer', {
        preserveScroll: true,
        onSuccess: () => {
            showTransferDialog.value = false;
            transferForm.reset();
        },
    });
};

const createBranchKey = () => {
    keyForm.post('/customer/api/keys', {
        preserveScroll: true,
        onSuccess: () => {
            showKeyDialog.value = false;
            selectedBranchForKey.value = null;
            if (flash.value?.newKey) {
                newCredentials.value = flash.value.newKey;
                showCredentialsDialog.value = true;
            }
            keyForm.reset();
            keyForm.environment = 'live';
        },
    });
};

const regenerateKey = (keyId: number) => {
    if (confirm('This will invalidate the current branch secret. Continue?')) {
        router.post(`/customer/api/keys/${keyId}/regenerate`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (flash.value?.newKey) {
                    newCredentials.value = flash.value.newKey;
                    showCredentialsDialog.value = true;
                }
            },
        });
    }
};

const toggleKey = (keyId: number) => {
    router.post(`/customer/api/keys/${keyId}/toggle`, {}, { preserveScroll: true });
};

const deleteKey = (keyId: number) => {
    if (confirm('Delete this branch API key?')) {
        router.delete(`/customer/api/keys/${keyId}`, { preserveScroll: true });
    }
};

const copyToClipboard = async (text: string) => {
    await navigator.clipboard.writeText(text);
};
</script>

<template>
    <Head title="Branches - EaseVerifier" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <v-alert v-if="flash?.success" type="success" variant="tonal" class="mb-4">{{ flash.success }}</v-alert>
        <v-alert v-if="flash?.error" type="error" variant="tonal" class="mb-4">{{ flash.error }}</v-alert>

        <div class="d-flex flex-column flex-md-row align-md-center mb-6 ga-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Branches</h1>
                <p class="text-body-2 text-grey">Manage branch wallets and branch-scoped verification credentials.</p>
            </div>
            <v-spacer />
            <div class="d-flex ga-2 flex-wrap">
                <v-btn variant="outlined" prepend-icon="mdi-swap-horizontal" @click="showTransferDialog = true">Transfer Funds</v-btn>
                <v-btn color="primary" prepend-icon="mdi-plus" @click="showCreateDialog = true">Create Branch</v-btn>
            </div>
        </div>

        <v-row class="mb-6">
            <v-col cols="12" md="4">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Primary Wallet</p>
                        <p class="text-h4 font-weight-bold mb-0">{{ formatCurrency(primaryWallet?.total_balance || 0) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="4">
                <v-card>
                    <v-card-text>
                        <p class="text-overline text-grey">Branches</p>
                        <p class="text-h4 font-weight-bold mb-0">{{ stats.branch_count }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="6" md="4">
                <v-card>
                    <v-card-text>
                        <p class="text-overline text-grey">Branch Wallet Balance</p>
                        <p class="text-h4 font-weight-bold text-primary mb-0">{{ formatCurrency(stats.total_branch_balance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row>
            <v-col v-for="branch in branches" :key="branch.id" cols="12" xl="6">
                <v-card class="h-100">
                    <v-card-title class="d-flex flex-column flex-sm-row align-sm-center ga-2">
                        <div>
                            <div class="text-h6">{{ branch.name }}</div>
                            <div class="text-caption text-grey">{{ branch.code }}</div>
                        </div>
                        <v-spacer />
                        <div class="d-flex ga-2 flex-wrap">
                            <v-chip :color="branch.is_active ? 'success' : 'grey'" size="small">{{ branch.is_active ? 'Active' : 'Inactive' }}</v-chip>
                            <v-btn size="small" variant="text" prepend-icon="mdi-pencil" @click="openEditDialog(branch)">Edit</v-btn>
                            <v-btn size="small" variant="text" prepend-icon="mdi-key-plus" @click="openKeyDialog(branch)">New Key</v-btn>
                        </div>
                    </v-card-title>
                    <v-card-text>
                        <v-row class="mb-2">
                            <v-col cols="6">
                                <div class="text-caption text-grey">Wallet</div>
                                <div class="text-h6 font-weight-bold">{{ formatCurrency(branch.wallet?.total_balance || 0) }}</div>
                            </v-col>
                            <v-col cols="6">
                                <div class="text-caption text-grey">API Keys</div>
                                <div class="text-h6 font-weight-bold">{{ branch.api_keys.length }}</div>
                            </v-col>
                        </v-row>

                        <div v-if="branch.contact_email || branch.contact_phone || branch.address" class="text-body-2 text-grey mb-4">
                            <div v-if="branch.contact_email">{{ branch.contact_email }}</div>
                            <div v-if="branch.contact_phone">{{ branch.contact_phone }}</div>
                            <div v-if="branch.address">{{ branch.address }}</div>
                        </div>

                        <div v-if="branch.api_keys.length && smAndDown" class="grid gap-2">
                            <v-card v-for="key in branch.api_keys" :key="key.id" variant="outlined">
                                <v-card-text class="pa-3">
                                    <div class="d-flex align-start justify-space-between ga-3">
                                        <div>
                                            <div class="font-weight-medium">{{ key.name }}</div>
                                            <div class="text-caption text-grey">{{ key.key.slice(0, 18) }}...</div>
                                        </div>
                                        <div class="d-flex flex-wrap ga-1 justify-end">
                                            <v-chip size="x-small" :color="key.environment === 'live' ? 'success' : 'warning'">{{ key.environment }}</v-chip>
                                            <v-chip size="x-small" :color="key.is_active ? 'success' : 'grey'">{{ key.is_active ? 'Active' : 'Inactive' }}</v-chip>
                                        </div>
                                    </div>
                                </v-card-text>
                                <v-divider />
                                <v-card-actions class="justify-end px-2 py-1">
                                    <v-btn size="small" variant="text" @click="toggleKey(key.id)">{{ key.is_active ? 'Pause' : 'Activate' }}</v-btn>
                                    <v-btn size="small" variant="text" color="warning" @click="regenerateKey(key.id)">Regenerate</v-btn>
                                    <v-btn size="small" variant="text" color="error" @click="deleteKey(key.id)">Delete</v-btn>
                                </v-card-actions>
                            </v-card>
                        </div>
                        <v-table v-else-if="branch.api_keys.length" density="compact">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Env</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="key in branch.api_keys" :key="key.id">
                                    <td>
                                        <div class="font-weight-medium">{{ key.name }}</div>
                                        <div class="text-caption text-grey">{{ key.key.slice(0, 18) }}...</div>
                                    </td>
                                    <td>
                                        <v-chip size="x-small" :color="key.environment === 'live' ? 'success' : 'warning'">{{ key.environment }}</v-chip>
                                    </td>
                                    <td>
                                        <v-chip size="x-small" :color="key.is_active ? 'success' : 'grey'">{{ key.is_active ? 'Active' : 'Inactive' }}</v-chip>
                                    </td>
                                    <td class="text-right">
                                        <v-btn icon size="x-small" variant="text" @click="toggleKey(key.id)"><v-icon size="16">{{ key.is_active ? 'mdi-pause' : 'mdi-play' }}</v-icon></v-btn>
                                        <v-btn icon size="x-small" variant="text" color="warning" @click="regenerateKey(key.id)"><v-icon size="16">mdi-refresh</v-icon></v-btn>
                                        <v-btn icon size="x-small" variant="text" color="error" @click="deleteKey(key.id)"><v-icon size="16">mdi-delete</v-icon></v-btn>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                        <v-alert v-else type="info" variant="tonal" density="compact">No branch API keys yet.</v-alert>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col v-if="!branches.length" cols="12">
                <v-card>
                    <v-card-text class="text-center py-10">
                        <v-icon size="56" color="grey-lighten-1" class="mb-3">mdi-source-branch</v-icon>
                        <div class="text-h6 mb-2">No branches yet</div>
                        <v-btn color="primary" @click="showCreateDialog = true">Create your first branch</v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="showCreateDialog" max-width="520">
            <v-card>
                <v-card-title>Create Branch</v-card-title>
                <v-card-text>
                    <v-text-field v-model="createBranchForm.name" label="Branch name" variant="outlined" class="mb-3" :error-messages="createBranchForm.errors.name" />
                    <v-text-field v-model="createBranchForm.contact_email" label="Contact email" variant="outlined" class="mb-3" :error-messages="createBranchForm.errors.contact_email" />
                    <v-text-field v-model="createBranchForm.contact_phone" label="Contact phone" variant="outlined" class="mb-3" :error-messages="createBranchForm.errors.contact_phone" />
                    <v-textarea v-model="createBranchForm.address" label="Address" variant="outlined" rows="3" :error-messages="createBranchForm.errors.address" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="createBranchForm.processing" @click="createBranch">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog :model-value="!!editingBranch" max-width="520" @update:model-value="editingBranch = null">
            <v-card v-if="editingBranch">
                <v-card-title>Edit Branch</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editBranchForm.name" label="Branch name" variant="outlined" class="mb-3" :error-messages="editBranchForm.errors.name" />
                    <v-text-field v-model="editBranchForm.contact_email" label="Contact email" variant="outlined" class="mb-3" :error-messages="editBranchForm.errors.contact_email" />
                    <v-text-field v-model="editBranchForm.contact_phone" label="Contact phone" variant="outlined" class="mb-3" :error-messages="editBranchForm.errors.contact_phone" />
                    <v-textarea v-model="editBranchForm.address" label="Address" variant="outlined" rows="3" class="mb-3" :error-messages="editBranchForm.errors.address" />
                    <v-switch v-model="editBranchForm.is_active" label="Branch is active" color="primary" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="editingBranch = null">Cancel</v-btn>
                    <v-btn color="primary" :loading="editBranchForm.processing" @click="updateBranch">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showTransferDialog" max-width="520">
            <v-card>
                <v-card-title>Transfer Funds</v-card-title>
                <v-card-text>
                    <v-select v-model="transferForm.from_branch_id" :items="walletTargets" label="From" variant="outlined" class="mb-3" :error-messages="transferForm.errors.from_branch_id" />
                    <v-select v-model="transferForm.to_branch_id" :items="walletTargets" label="To" variant="outlined" class="mb-3" :error-messages="transferForm.errors.to_branch_id" />
                    <v-text-field v-model="transferForm.amount" label="Amount" type="number" variant="outlined" :error-messages="transferForm.errors.amount" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showTransferDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="transferForm.processing" @click="transferFunds">Transfer</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showKeyDialog" max-width="500">
            <v-card>
                <v-card-title>Create Branch API Key</v-card-title>
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        This key will charge the wallet for <strong>{{ selectedBranchForKey?.name }}</strong>.
                    </v-alert>
                    <v-text-field v-model="keyForm.name" label="Key name" variant="outlined" class="mb-3" :error-messages="keyForm.errors.name" />
                    <v-select
                        v-model="keyForm.environment"
                        :items="[{ title: 'Live', value: 'live' }, { title: 'Test', value: 'test' }]"
                        label="Environment"
                        variant="outlined"
                        :error-messages="keyForm.errors.environment"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showKeyDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="keyForm.processing" @click="createBranchKey">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showCredentialsDialog" max-width="600" persistent>
            <v-card>
                <v-card-title>Save Branch Credentials</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">Copy these credentials now. The secret will not be shown again.</v-alert>
                    <v-text-field :model-value="newCredentials?.key" label="API Key" variant="outlined" readonly append-inner-icon="mdi-content-copy" class="mb-3" @click:append-inner="copyToClipboard(newCredentials?.key || '')" />
                    <v-text-field :model-value="newCredentials?.secret" label="API Secret" variant="outlined" readonly append-inner-icon="mdi-content-copy" class="mb-3" @click:append-inner="copyToClipboard(newCredentials?.secret || '')" />
                    <v-text-field :model-value="newCredentials?.bearer_token" label="Bearer Token" variant="outlined" readonly append-inner-icon="mdi-content-copy" @click:append-inner="copyToClipboard(newCredentials?.bearer_token || '')" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" @click="showCredentialsDialog = false; newCredentials = null">Done</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </CustomerLayout>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    wallets?: { data: any[]; links: any; meta: any };
    totalBalance?: number;
    totalBonusBalance?: number;
    filters?: { search?: string };
}>();

const search = ref(props.filters?.search || '');
const creditDialog = ref(false);
const debitDialog = ref(false);
const selectedWallet = ref<any>(null);

const creditForm = useForm({ amount: 0, description: '', is_bonus: false });
const debitForm = useForm({ amount: 0, description: '' });

watch(search, (value) => {
    router.get('/admin/wallets', { search: value }, { preserveState: true, replace: true });
});

const openCredit = (wallet: any) => { selectedWallet.value = wallet; creditDialog.value = true; };
const openDebit = (wallet: any) => { selectedWallet.value = wallet; debitDialog.value = true; };

const submitCredit = () => {
    creditForm.post(`/admin/customers/${selectedWallet.value.user_id}/wallet/credit`, {
        onSuccess: () => { creditDialog.value = false; creditForm.reset(); }
    });
};

const submitDebit = () => {
    debitForm.post(`/admin/customers/${selectedWallet.value.user_id}/wallet/debit`, {
        onSuccess: () => { debitDialog.value = false; debitForm.reset(); }
    });
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Customer', key: 'user' },
    { title: 'Balance', key: 'balance' },
    { title: 'Bonus', key: 'bonus_balance' },
    { title: 'Last Updated', key: 'updated_at' },
    { title: 'Actions', key: 'actions', sortable: false },
];
</script>

<template>
    <Head title="Wallets - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Wallets</h1>
                <p class="text-body-2 text-grey">Manage customer wallet balances</p>
            </div>
        </div>

        <!-- Summary -->
        <v-row class="mb-6">
            <v-col cols="12" md="6">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Total Balance (All Wallets)</p>
                        <p class="text-h3 font-weight-bold mb-0">{{ formatCurrency(totalBalance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card color="secondary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Total Bonus Balance</p>
                        <p class="text-h3 font-weight-bold mb-0">{{ formatCurrency(totalBonusBalance) }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-card>
            <v-card-text>
                <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search wallets..." variant="outlined" density="compact" hide-details class="mb-4" style="max-width: 400px;" />
                
                <v-data-table :headers="headers" :items="wallets?.data || []" hover>
                    <template #item.user="{ item }">
                        <div class="d-flex align-center py-2">
                            <v-avatar color="primary" size="36" class="mr-3">{{ item.user?.name?.charAt(0) || '?' }}</v-avatar>
                            <div>
                                <p class="font-weight-medium mb-0">{{ item.user?.name || 'N/A' }}</p>
                                <p class="text-caption text-grey">{{ item.user?.email }}</p>
                            </div>
                        </div>
                    </template>
                    <template #item.balance="{ item }">
                        <span class="font-weight-bold text-primary">{{ formatCurrency(item.balance) }}</span>
                    </template>
                    <template #item.bonus_balance="{ item }">
                        <span class="text-success">{{ formatCurrency(item.bonus_balance) }}</span>
                    </template>
                    <template #item.updated_at="{ item }">
                        {{ new Date(item.updated_at).toLocaleString() }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn size="small" variant="tonal" color="success" class="mr-2" @click="openCredit(item)">
                            <v-icon start>mdi-plus</v-icon>Credit
                        </v-btn>
                        <v-btn size="small" variant="tonal" color="error" @click="openDebit(item)">
                            <v-icon start>mdi-minus</v-icon>Debit
                        </v-btn>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <!-- Credit Dialog -->
        <v-dialog v-model="creditDialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center"><v-icon color="success" class="mr-2">mdi-wallet-plus</v-icon>Credit Wallet</v-card-title>
                <v-card-text>
                    <p class="mb-4">Credit wallet for <strong>{{ selectedWallet?.user?.name }}</strong></p>
                    <v-text-field v-model="creditForm.amount" label="Amount (NGN)" type="number" prepend-inner-icon="mdi-currency-ngn" variant="outlined" class="mb-4" />
                    <v-textarea v-model="creditForm.description" label="Description" variant="outlined" rows="2" class="mb-4" />
                    <v-checkbox v-model="creditForm.is_bonus" label="Credit as bonus" color="primary" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="creditDialog = false">Cancel</v-btn><v-btn color="success" :loading="creditForm.processing" @click="submitCredit">Credit</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Debit Dialog -->
        <v-dialog v-model="debitDialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center"><v-icon color="error" class="mr-2">mdi-wallet-minus</v-icon>Debit Wallet</v-card-title>
                <v-card-text>
                    <p class="mb-4">Debit wallet for <strong>{{ selectedWallet?.user?.name }}</strong></p>
                    <v-text-field v-model="debitForm.amount" label="Amount (NGN)" type="number" prepend-inner-icon="mdi-currency-ngn" variant="outlined" class="mb-4" />
                    <v-textarea v-model="debitForm.description" label="Reason" variant="outlined" rows="2" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="debitDialog = false">Cancel</v-btn><v-btn color="error" :loading="debitForm.processing" @click="submitDebit">Debit</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>


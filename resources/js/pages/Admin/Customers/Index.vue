<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    customers?: { data: any[]; links: any; meta: any };
    filters?: { search?: string; status?: string };
}>();

const search = ref(props.filters?.search || '');
const creditDialog = ref(false);
const selectedCustomer = ref<any>(null);

const creditForm = useForm({ amount: 0, description: '', is_bonus: false });

watch(search, (value) => {
    router.get('/admin/customers', { search: value }, { preserveState: true, replace: true });
});

const openCreditDialog = (customer: any) => {
    selectedCustomer.value = customer;
    creditDialog.value = true;
};

const submitCredit = () => {
    creditForm.post(`/admin/customers/${selectedCustomer.value.id}/wallet/credit`, {
        onSuccess: () => { creditDialog.value = false; creditForm.reset(); }
    });
};

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const headers = [
    { title: 'Customer', key: 'name' },
    { title: 'Company', key: 'company' },
    { title: 'Wallet Balance', key: 'balance' },
    { title: 'Status', key: 'status' },
    { title: 'Joined', key: 'created_at' },
    { title: 'Actions', key: 'actions', sortable: false },
];
</script>

<template>
    <Head title="Customers - Admin" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Customers</h1>
                <p class="text-body-2 text-grey">Manage your platform customers</p>
            </div>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-plus" href="/admin/customers/create">Add Customer</v-btn>
        </div>

        <v-card>
            <v-card-text>
                <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search customers..." variant="outlined" density="compact" hide-details class="mb-4" style="max-width: 400px;" />

                <v-data-table :headers="headers" :items="customers?.data || []" hover>
                    <template #item.name="{ item }">
                        <div class="d-flex align-center py-2">
                            <v-avatar color="primary" size="36" class="mr-3">{{ item.name?.charAt(0) || '?' }}</v-avatar>
                            <div>
                                <p class="font-weight-medium mb-0">{{ item.name }}</p>
                                <p class="text-caption text-grey">{{ item.email }}</p>
                            </div>
                        </div>
                    </template>
                    <template #item.company="{ item }">
                        {{ item.customer?.company_name || '-' }}
                    </template>
                    <template #item.balance="{ item }">
                        <span class="font-weight-bold text-primary">{{ formatCurrency(item.wallet?.balance) }}</span>
                    </template>
                    <template #item.status="{ item }">
                        <v-chip :color="item.is_active ? 'success' : 'error'" size="small">{{ item.is_active ? 'Active' : 'Inactive' }}</v-chip>
                    </template>
                    <template #item.created_at="{ item }">
                        {{ new Date(item.created_at).toLocaleDateString() }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn icon variant="text" size="small" :href="`/admin/customers/${item.id}`"><v-icon>mdi-eye</v-icon></v-btn>
                        <v-btn icon variant="text" size="small" :href="`/admin/customers/${item.id}/edit`"><v-icon>mdi-pencil</v-icon></v-btn>
                        <v-btn icon variant="text" size="small" color="success" @click="openCreditDialog(item)"><v-icon>mdi-wallet-plus</v-icon></v-btn>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <!-- Credit Dialog -->
        <v-dialog v-model="creditDialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center"><v-icon color="success" class="mr-2">mdi-wallet-plus</v-icon>Credit Wallet</v-card-title>
                <v-card-text>
                    <p class="mb-4">Credit wallet for <strong>{{ selectedCustomer?.name }}</strong></p>
                    <v-text-field v-model="creditForm.amount" label="Amount (NGN)" type="number" prepend-inner-icon="mdi-currency-ngn" variant="outlined" class="mb-4" />
                    <v-textarea v-model="creditForm.description" label="Description" variant="outlined" rows="2" class="mb-4" />
                    <v-checkbox v-model="creditForm.is_bonus" label="Credit as bonus" color="primary" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="creditDialog = false">Cancel</v-btn>
                    <v-btn color="success" :loading="creditForm.processing" @click="submitCredit">Credit</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>


<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    customer: any;
    verificationStats?: any;
    services?: any[];
    customPricing?: any;
    resultPinProducts?: any[];
    resultPinPricing?: any;
    paygoResultServices?: any[];
}>();

const pricingDialog = ref(false);
const pricingForm = useForm({ service_id: null, price: 0 });
const resultPinPricingDialog = ref(false);
const resultPinPricingForm = useForm({ product_id: null, price: 0 });
const resultFetchAccessForm = useForm({
    enabled: props.customer.customer?.result_fetch_enabled ?? true,
    paygo_result_reference_fetch_limit: props.customer.customer?.paygo_result_reference_fetch_limit ?? 3,
});

const buildPaygoResultRows = (services: any[] = []) => services.map((row: any) => ({
    ...row,
    form: {
        name: row.paygo_service?.name || `${String(row.board).toUpperCase()} Result Verification`,
        price: row.paygo_service?.price ?? row.suggested_price,
        is_active: row.paygo_service?.is_active ?? false,
    },
}));
const paygoResultRows = ref(buildPaygoResultRows(props.paygoResultServices));
const savingPaygoServiceId = ref<number | null>(null);
const paygoResultErrors = ref<Record<number, string>>({});

watch(
    () => props.paygoResultServices,
    (services) => {
        paygoResultRows.value = buildPaygoResultRows(services);
    },
);

const openPricingDialog = (service: any) => {
    pricingForm.service_id = service.id;
    pricingForm.price = props.customPricing?.[service.id]?.price || service.default_price;
    pricingDialog.value = true;
};

const submitPricing = () => {
    pricingForm.post(`/admin/customers/${props.customer.id}/pricing`, {
        onSuccess: () => { pricingDialog.value = false; }
    });
};

const openResultPinPricingDialog = (product: any) => {
    resultPinPricingForm.product_id = product.id;
    resultPinPricingForm.price = props.resultPinPricing?.[product.id]?.price || product.price;
    resultPinPricingDialog.value = true;
};

const submitResultPinPricing = () => {
    resultPinPricingForm.post(`/admin/customers/${props.customer.id}/result-pin-pricing`, {
        onSuccess: () => { resultPinPricingDialog.value = false; }
    });
};

const submitResultFetchAccess = () => {
    resultFetchAccessForm.post(`/admin/customers/${props.customer.id}/result-fetch-access`, {
        preserveScroll: true,
    });
};

const savePaygoResultService = (row: any) => {
    savingPaygoServiceId.value = row.service_id;
    paygoResultErrors.value[row.service_id] = '';

    router.post(`/admin/customers/${props.customer.id}/paygo-result-services/${row.service_id}`, row.form, {
        preserveScroll: true,
        onError: (errors) => {
            paygoResultErrors.value[row.service_id] = errors.price || errors.name || 'Unable to save PayGo result page.';
        },
        onFinish: () => {
            savingPaygoServiceId.value = null;
        },
    });
};

const copyToClipboard = async (value?: string) => {
    if (!value) return;
    await navigator.clipboard?.writeText(value);
};

const impersonateUrl = `/impersonate/take/${props.customer.id}`;

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);
</script>

<template>
    <Head :title="`${customer.name} - Admin`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/admin/customers" class="mb-2">Back</v-btn>
                <h1 class="text-h4 font-weight-bold mb-1">{{ customer.name }}</h1>
                <p class="text-body-2 text-grey">{{ customer.email }}</p>
            </div>
            <v-spacer />
            <v-btn color="primary" class="mr-2" :href="impersonateUrl" prepend-icon="mdi-account-switch">Impersonate</v-btn>
            <v-btn variant="outlined" class="mr-2" :href="`/admin/customers/${customer.id}/edit`" prepend-icon="mdi-pencil">Edit</v-btn>
            <v-chip :color="customer.is_active ? 'success' : 'error'" size="large">{{ customer.is_active ? 'Active' : 'Inactive' }}</v-chip>
        </div>

        <v-row>
            <!-- Customer Info -->
            <v-col cols="12" md="4">
                <v-card class="mb-4">
                    <v-card-title>Customer Details</v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item><v-list-item-title class="text-caption">Account Type</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.account_type || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Company</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.company_name || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Business Type</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.business_type || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">RC/BN Number</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.registration_number || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Website</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.website || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Expected Volume</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.expected_monthly_volume || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Use Case</v-list-item-title><v-list-item-subtitle>{{ customer.customer?.use_case || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Phone</v-list-item-title><v-list-item-subtitle>{{ customer.phone || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Location</v-list-item-title><v-list-item-subtitle>{{ [customer.customer?.city, customer.customer?.state].filter(Boolean).join(', ') || '-' }}</v-list-item-subtitle></v-list-item>
                            <v-list-item><v-list-item-title class="text-caption">Joined</v-list-item-title><v-list-item-subtitle>{{ new Date(customer.created_at).toLocaleDateString() }}</v-list-item-subtitle></v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-overline opacity-80">Wallet Balance</p>
                        <p class="text-h4 font-weight-bold mb-2">{{ formatCurrency(customer.wallet?.balance) }}</p>
                        <p class="text-caption opacity-80">Bonus: {{ formatCurrency(customer.wallet?.bonus_balance) }}</p>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>Feature Access</v-card-title>
                    <v-card-text>
                        <div class="d-flex align-start ga-3">
                            <v-icon color="primary" size="28">mdi-certificate-outline</v-icon>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold">Result Board Fetch</div>
                                <p class="text-body-2 text-grey mb-3">
                                    Controls whether this customer can see and use WAEC, NECO, NABTEB, and NBAIS result fetch services.
                                </p>
                                <v-switch
                                    v-model="resultFetchAccessForm.enabled"
                                    color="primary"
                                    inset
                                    hide-details
                                    :label="resultFetchAccessForm.enabled ? 'Enabled' : 'Disabled'"
                                    :disabled="resultFetchAccessForm.processing"
                                    @change="submitResultFetchAccess"
                                />
                                <v-text-field
                                    v-model="resultFetchAccessForm.paygo_result_reference_fetch_limit"
                                    type="number"
                                    min="1"
                                    max="50"
                                    label="PayGo result reference pulls"
                                    variant="outlined"
                                    density="compact"
                                    class="mt-4"
                                    :error-messages="resultFetchAccessForm.errors.paygo_result_reference_fetch_limit"
                                    :disabled="resultFetchAccessForm.processing"
                                    @change="submitResultFetchAccess"
                                />
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Stats & Pricing -->
            <v-col cols="12" md="8">
                <v-row class="mb-4">
                    <v-col cols="4">
                        <v-card><v-card-text class="text-center">
                            <p class="text-h4 font-weight-bold text-primary mb-0">{{ verificationStats?.total || 0 }}</p>
                            <p class="text-caption text-grey">Total Verifications</p>
                        </v-card-text></v-card>
                    </v-col>
                    <v-col cols="4">
                        <v-card><v-card-text class="text-center">
                            <p class="text-h4 font-weight-bold text-success mb-0">{{ verificationStats?.completed || 0 }}</p>
                            <p class="text-caption text-grey">Successful</p>
                        </v-card-text></v-card>
                    </v-col>
                    <v-col cols="4">
                        <v-card><v-card-text class="text-center">
                            <p class="text-h4 font-weight-bold text-error mb-0">{{ verificationStats?.failed || 0 }}</p>
                            <p class="text-caption text-grey">Failed</p>
                        </v-card-text></v-card>
                    </v-col>
                </v-row>

                <v-card>
                    <v-card-title>Custom Pricing</v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <thead><tr><th>Service</th><th>Default Price</th><th>Custom Price</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="service in services" :key="service.id">
                                    <td>{{ service.name }}</td>
                                    <td>{{ formatCurrency(service.default_price) }}</td>
                                    <td><v-chip v-if="customPricing?.[service.id]" color="primary" size="small">{{ formatCurrency(customPricing[service.id].price) }}</v-chip><span v-else class="text-grey">Default</span></td>
                                    <td><v-btn size="small" variant="text" @click="openPricingDialog(service)">Set Price</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title class="d-flex align-center">
                        PayGo Result Pages
                        <v-spacer />
                        <v-chip size="small" color="primary" variant="tonal">{{ paygoResultRows.length }} boards</v-chip>
                    </v-card-title>
                    <v-card-text>
                        <v-alert
                            v-if="!resultFetchAccessForm.enabled"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            class="mb-4"
                        >
                            Result fetch access is disabled for this customer.
                        </v-alert>
                        <v-table density="comfortable">
                            <thead>
                                <tr>
                                    <th>Board</th>
                                    <th>System Price</th>
                                    <th>Public Name</th>
                                    <th>Public Price</th>
                                    <th>Status</th>
                                    <th>Links</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in paygoResultRows" :key="row.service_id">
                                    <td>
                                        <div class="font-weight-medium">{{ String(row.board).toUpperCase() }}</div>
                                        <div class="text-caption text-grey">{{ row.service_name }}</div>
                                    </td>
                                    <td>{{ formatCurrency(row.system_price) }}</td>
                                    <td style="min-width: 220px">
                                        <v-text-field
                                            v-model="row.form.name"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                        />
                                    </td>
                                    <td style="width: 150px">
                                        <v-text-field
                                            v-model="row.form.price"
                                            type="number"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                        />
                                    </td>
                                    <td>
                                        <v-switch
                                            v-model="row.form.is_active"
                                            color="primary"
                                            inset
                                            hide-details
                                            density="compact"
                                        />
                                    </td>
                                    <td>
                                        <div v-if="row.paygo_service?.result_url" class="d-flex ga-2">
                                            <v-btn
                                                icon
                                                size="small"
                                                variant="text"
                                                title="Copy board page"
                                                @click="copyToClipboard(row.paygo_service.result_url)"
                                            >
                                                <v-icon>mdi-link-variant</v-icon>
                                            </v-btn>
                                            <v-btn
                                                icon
                                                size="small"
                                                variant="text"
                                                title="Open board page"
                                                :href="row.paygo_service.result_url"
                                                target="_blank"
                                            >
                                                <v-icon>mdi-open-in-new</v-icon>
                                            </v-btn>
                                        </div>
                                        <span v-else class="text-grey text-caption">Not published</span>
                                    </td>
                                    <td class="text-right">
                                        <v-btn
                                            color="primary"
                                            size="small"
                                            :loading="savingPaygoServiceId === row.service_id"
                                            @click="savePaygoResultService(row)"
                                        >
                                            Save
                                        </v-btn>
                                        <div v-if="paygoResultErrors[row.service_id]" class="text-error text-caption mt-1">
                                            {{ paygoResultErrors[row.service_id] }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>Result PIN Pricing</v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <thead><tr><th>Product</th><th>General Price</th><th>Customer Price</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="product in resultPinProducts" :key="product.id">
                                    <td>{{ product.name }}</td>
                                    <td>{{ formatCurrency(product.price) }}</td>
                                    <td><v-chip v-if="resultPinPricing?.[product.id]" color="primary" size="small">{{ formatCurrency(resultPinPricing[product.id].price) }}</v-chip><span v-else class="text-grey">General</span></td>
                                    <td><v-btn size="small" variant="text" @click="openResultPinPricingDialog(product)">Set Price</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Pricing Dialog -->
        <v-dialog v-model="pricingDialog" max-width="400">
            <v-card>
                <v-card-title>Set Custom Price</v-card-title>
                <v-card-text>
                    <v-text-field v-model="pricingForm.price" label="Price (NGN)" type="number" variant="outlined" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="pricingDialog = false">Cancel</v-btn><v-btn color="primary" :loading="pricingForm.processing" @click="submitPricing">Save</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="resultPinPricingDialog" max-width="400">
            <v-card>
                <v-card-title>Set Result PIN Price</v-card-title>
                <v-card-text>
                    <v-text-field v-model="resultPinPricingForm.price" label="Price (NGN)" type="number" variant="outlined" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn variant="text" @click="resultPinPricingDialog = false">Cancel</v-btn><v-btn color="primary" :loading="resultPinPricingForm.processing" @click="submitResultPinPricing">Save</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>

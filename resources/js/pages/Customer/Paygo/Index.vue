<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

interface VerificationService {
    id: number | string;
    name: string;
    slug: string;
    service_type?: 'identity' | 'result';
    board?: string | null;
    system_price: number;
}

interface PaygoService {
    id: number | string;
    name: string;
    public_slug: string;
    price: number;
    is_active: boolean;
    success_url?: string | null;
    failure_url?: string | null;
    response_mode: 'redirect' | 'json';
    service_type?: 'identity' | 'result';
    board?: string | null;
    system_price: number;
    initiate_url: string;
    verify_url: string;
    result_url?: string | null;
    result_selector_url?: string | null;
    intents_count: number;
    paid_intents_count: number;
    used_intents_count: number;
    service: { id: number | string; name: string; slug: string };
    is_group?: boolean;
    grouped_services?: PaygoService[];
}

interface ServicePaymentIntent {
    reference: string;
    amount: number;
    system_price: number;
    earning: number;
    status: string;
    verification_attempts: number;
    max_fetches?: number;
    reference_fetches?: number;
    nin_last4?: string | null;
    lookup_label?: string | null;
    created_at: string;
}

interface ServiceWalletTransaction {
    reference: string;
    type: string;
    category: string;
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string;
    status: string;
    created_at: string;
}

const props = defineProps<{
    paygoServices: PaygoService[];
    verificationServices: VerificationService[];
    paygoWallet: {
        balance: number;
        pending_withdrawal: number;
        currency: string;
    };
    withdrawalRequests: Array<{
        reference: string;
        amount: number;
        bank_name: string;
        account_number: string;
        account_name: string;
        status: string;
        requested_at?: string | null;
    }>;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as any);
const { smAndDown } = useDisplay();

const showFormDialog = ref(false);
const showWithdrawDialog = ref(false);
const showLinksDialog = ref(false);
const showTransactionsDialog = ref(false);
const editingService = ref<PaygoService | null>(null);
const selectedLinksService = ref<PaygoService | null>(null);
const selectedTransactionService = ref<PaygoService | null>(null);
const transactionsLoading = ref(false);
const servicePaymentIntents = ref<ServicePaymentIntent[]>([]);
const serviceWalletTransactions = ref<ServiceWalletTransaction[]>([]);
const transactionTab = ref('payments');

const resultVerificationOption = computed(() => props.verificationServices.find((service) => service.id === 'result'));
const defaultServiceId = computed(() => resultVerificationOption.value?.id || props.verificationServices[0]?.id || null);
const selectedVerificationService = computed(() => props.verificationServices.find((service) => service.id === form.verification_service_id));
const resultPaygoServices = computed(() => props.paygoServices.filter((service) => service.service_type === 'result'));
const visiblePaygoServices = computed(() => {
    const identityServices = props.paygoServices.filter((service) => service.service_type !== 'result');

    if (!resultPaygoServices.value.length) {
        return identityServices;
    }

    const activeResultServices = resultPaygoServices.value.filter((service) => service.is_active);
    const source = activeResultServices[0] || resultPaygoServices.value[0];

    return [
        ...identityServices,
        {
            ...source,
            id: 'result-group',
            name: 'Result Verification',
            public_slug: 'result-verification',
            is_active: activeResultServices.length > 0,
            board: null,
            service_type: 'result',
            service: {
                id: 'result',
                name: 'Result Verification',
                slug: 'result-verification',
            },
            price: Math.max(...resultPaygoServices.value.map((service) => Number(service.price || 0))),
            system_price: Math.max(...resultPaygoServices.value.map((service) => Number(service.system_price || 0))),
            intents_count: resultPaygoServices.value.reduce((total, service) => total + service.intents_count, 0),
            paid_intents_count: resultPaygoServices.value.reduce((total, service) => total + service.paid_intents_count, 0),
            used_intents_count: resultPaygoServices.value.reduce((total, service) => total + service.used_intents_count, 0),
            result_url: source.result_selector_url || source.result_url,
            result_selector_url: source.result_selector_url,
            is_group: true,
            grouped_services: resultPaygoServices.value,
        } as PaygoService,
    ];
});
const activeServices = computed(() => visiblePaygoServices.value.filter((service) => service.is_active).length);
const paidCount = computed(() => props.paygoServices.reduce((total, service) => total + service.paid_intents_count, 0));
const usedCount = computed(() => props.paygoServices.reduce((total, service) => total + service.used_intents_count, 0));
const firstService = computed(() => visiblePaygoServices.value[0] || null);

const form = useForm({
    name: '',
    verification_service_id: defaultServiceId.value as number | string | null,
    price: 0,
    success_url: '',
    failure_url: '',
    response_mode: 'redirect' as 'redirect' | 'json',
    is_active: true,
});

const withdrawForm = useForm({
    amount: 0,
    bank_name: '',
    account_number: '',
    account_name: '',
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const serviceMargin = (service: PaygoService) => Math.max(0, service.price - service.system_price);

const applySelectedServiceDefaults = () => {
    if (!editingService.value && selectedVerificationService.value?.id === 'result') {
        form.name = 'Result Verification';
    }

    if (!editingService.value && selectedVerificationService.value && (!form.price || Number(form.price) <= selectedVerificationService.value.system_price)) {
        form.price = selectedVerificationService.value.system_price + 1;
    }
};

const openCreateDialog = () => {
    editingService.value = null;
    form.reset();
    form.verification_service_id = defaultServiceId.value;
    applySelectedServiceDefaults();
    form.is_active = true;
    showFormDialog.value = true;
};

watch(() => form.verification_service_id, applySelectedServiceDefaults);

const openEditDialog = (service: PaygoService) => {
    editingService.value = service;
    form.name = service.name;
    form.verification_service_id = service.is_group ? 'result' : service.service.id;
    form.price = service.price;
    form.success_url = service.success_url || '';
    form.failure_url = service.failure_url || '';
    form.response_mode = service.response_mode || 'redirect';
    form.is_active = service.is_active;
    showFormDialog.value = true;
};

const openLinksDialog = (service: PaygoService) => {
    selectedLinksService.value = service;
    showLinksDialog.value = true;
};

const openTransactionsDialog = async (service: PaygoService) => {
    selectedTransactionService.value = service;
    showTransactionsDialog.value = true;
    transactionsLoading.value = true;
    servicePaymentIntents.value = [];
    serviceWalletTransactions.value = [];

    try {
        const servicesToLoad = service.grouped_services?.length ? service.grouped_services : [service];
        const responses = await Promise.all(servicesToLoad.map(async (item) => {
            const response = await fetch(`/customer/paygo-services/${item.id}/transactions`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) throw new Error('Unable to load PayGo transactions.');

            return response.json();
        }));

        servicePaymentIntents.value = responses.flatMap((payload) => payload.payment_intents || []);
        serviceWalletTransactions.value = responses.flatMap((payload) => payload.wallet_transactions || []);
    } finally {
        transactionsLoading.value = false;
    }
};

const submitForm = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showFormDialog.value = false;
        },
    };

    if (editingService.value) {
        if (editingService.value.is_group) {
            form.post('/customer/paygo-services', options);
            return;
        }

        form.put(`/customer/paygo-services/${editingService.value.id}`, options);
        return;
    }

    form.post('/customer/paygo-services', options);
};

const toggleService = (service: PaygoService) => {
    if (service.is_group) return;
    router.post(`/customer/paygo-services/${service.id}/toggle`, {}, { preserveScroll: true });
};

const deleteService = (service: PaygoService) => {
    if (service.is_group) return;
    if (!confirm('Delete this PayGo service? Existing initiate links will stop working.')) return;
    router.delete(`/customer/paygo-services/${service.id}`, { preserveScroll: true });
};

const submitWithdrawal = () => {
    withdrawForm.post('/customer/paygo-wallet/withdraw', {
        preserveScroll: true,
        onSuccess: () => {
            showWithdrawDialog.value = false;
            withdrawForm.reset();
        },
    });
};

const copyToClipboard = async (text: string) => {
    await navigator.clipboard.writeText(text);
};

const statusColor = (status: string) => {
    if (['paid', 'completed', 'used'].includes(status)) return 'success';
    if (['pending', 'verifying'].includes(status)) return 'warning';
    if (['failed', 'expired', 'reversed'].includes(status)) return 'error';
    return 'grey';
};

const formatDate = (date?: string | null) => date ? new Date(date).toLocaleString() : '-';

const initiateExample = (service: PaygoService) => service.service_type === 'result' ? (service.result_url || service.initiate_url) : `${service.initiate_url}/12345678901`;
const verifyGetExample = (service: PaygoService) => `${service.verify_url}/12345678901`;
const verifyPostBody = `{
  "nin": "12345678901",
  "consent": true
}`;
</script>

<template>
    <Head title="PayGo Services - EaseVerifier" />

    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="paygo-page">
            <v-alert v-if="flash?.success" type="success" variant="tonal" class="mb-4">{{ flash.success }}</v-alert>
            <v-alert v-if="flash?.error" type="error" variant="tonal" class="mb-4">{{ flash.error }}</v-alert>

            <section class="paygo-header">
                <div class="header-copy">
                    <div class="header-kicker">
                        <v-icon size="18">mdi-cash-fast</v-icon>
                        PayGo verification
                    </div>
                    <h1>Sell prepaid verification from your own link.</h1>
                    <p>Set a public price above your system price, collect payment first, then run NIN or result verification from a paid reference.</p>
                </div>

                <div class="header-actions">
                    <v-btn
                        color="primary"
                        size="large"
                        prepend-icon="mdi-plus"
                        :block="smAndDown"
                        :disabled="!verificationServices.length"
                        @click="openCreateDialog"
                    >
                        Create Service
                    </v-btn>
                </div>
            </section>

            <v-alert v-if="!verificationServices.length" type="warning" variant="tonal" class="mb-5">
                No eligible PayGo verification service is currently available.
            </v-alert>

            <section class="wallet-strip">
                <div class="wallet-panel wallet-panel-primary">
                    <div class="wallet-icon">
                        <v-icon>mdi-wallet-outline</v-icon>
                    </div>
                    <div>
                        <p>Available PayGo earnings</p>
                        <strong>{{ formatCurrency(paygoWallet?.balance) }}</strong>
                        <span>Markup wallet. Not fundable.</span>
                    </div>
                </div>

                <div class="wallet-panel">
                    <div class="wallet-icon wallet-icon-muted">
                        <v-icon>mdi-timer-sand</v-icon>
                    </div>
                    <div>
                        <p>Pending withdrawal</p>
                        <strong>{{ formatCurrency(paygoWallet?.pending_withdrawal) }}</strong>
                        <span>Submitted requests awaiting settlement.</span>
                    </div>
                </div>

                <div class="wallet-panel wallet-actions">
                    <div>
                        <p>Wallet action</p>
                        <strong>Withdraw earnings</strong>
                        <span>Only PayGo markup is withdrawable.</span>
                    </div>
                    <v-btn
                        color="secondary"
                        variant="flat"
                        prepend-icon="mdi-bank-transfer-out"
                        :disabled="!paygoWallet?.balance"
                        @click="showWithdrawDialog = true"
                    >
                        Request
                    </v-btn>
                </div>
            </section>

            <section class="summary-grid">
                <div class="summary-item">
                    <span>Services</span>
                    <strong>{{ visiblePaygoServices.length }}</strong>
                    <small>{{ activeServices }} active</small>
                </div>
                <div class="summary-item">
                    <span>Paid requests</span>
                    <strong>{{ paidCount }}</strong>
                    <small>Ready or already verified</small>
                </div>
                <div class="summary-item">
                    <span>Used payments</span>
                    <strong>{{ usedCount }}</strong>
                    <small>Consumed after configured pulls</small>
                </div>
            </section>

            <section class="services-surface">
                <div class="surface-header">
                    <div>
                        <h2>Services</h2>
                        <p>Manage public payment links and API response mode.</p>
                    </div>
                    <v-chip variant="tonal" color="primary">{{ visiblePaygoServices.length }} total</v-chip>
                </div>

                <div v-if="!visiblePaygoServices.length" class="empty-state">
                    <v-icon size="44">mdi-link-plus</v-icon>
                    <h3>No PayGo service yet</h3>
                    <p>Create a service to get a public initiate URL and verify endpoint.</p>
                    <v-btn color="primary" prepend-icon="mdi-plus" :disabled="!verificationServices.length" @click="openCreateDialog">Create Service</v-btn>
                </div>

                <div v-else class="service-list">
                    <article v-for="service in visiblePaygoServices" :key="service.id" class="service-row">
                        <div class="service-main">
                            <div class="service-avatar">
                                <v-icon>mdi-card-account-details-outline</v-icon>
                            </div>
                            <div class="service-title">
                                <div class="service-name-line">
                                    <h3>{{ service.name }}</h3>
                                    <v-chip :color="service.is_active ? 'success' : 'grey'" size="small" variant="tonal">
                                        {{ service.is_active ? 'Active' : 'Paused' }}
                                    </v-chip>
                                </div>
                                <p>{{ service.service_type === 'result' ? 'Exam selector page' : (service.response_mode === 'json' ? 'JSON response' : 'Redirect/UI response') }}</p>
                            </div>
                        </div>

                        <div class="service-money">
                            <span>Public price</span>
                            <strong>{{ formatCurrency(service.price) }}</strong>
                            <small>System {{ formatCurrency(service.system_price) }} · Margin {{ formatCurrency(serviceMargin(service)) }}</small>
                        </div>

                        <div class="service-usage">
                            <div>
                                <strong>{{ service.paid_intents_count }}</strong>
                                <span>paid</span>
                            </div>
                            <div>
                                <strong>{{ service.used_intents_count }}</strong>
                                <span>used</span>
                            </div>
                        </div>

                        <div class="service-actions">
                            <v-btn icon size="small" variant="text" title="View URLs" @click="openLinksDialog(service)">
                                <v-icon>mdi-link-variant</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" title="View transactions" @click="openTransactionsDialog(service)">
                                <v-icon>mdi-receipt-text-outline</v-icon>
                            </v-btn>
                            <v-btn icon size="small" variant="text" title="Edit service" @click="openEditDialog(service)">
                                <v-icon>mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn v-if="!service.is_group" icon size="small" variant="text" :title="service.is_active ? 'Pause service' : 'Activate service'" @click="toggleService(service)">
                                <v-icon>{{ service.is_active ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                            </v-btn>
                            <v-btn v-if="!service.is_group" icon size="small" variant="text" color="error" title="Delete service" @click="deleteService(service)">
                                <v-icon>mdi-delete-outline</v-icon>
                            </v-btn>
                        </div>
                    </article>
                </div>
            </section>

            <section class="integration-guide">
                <div class="guide-title">
                    <div class="guide-mark">
                        <v-icon>mdi-book-open-page-variant-outline</v-icon>
                    </div>
                    <div>
                        <h2>PayGo integration guide</h2>
                        <p>Redirect to payment, wait for paid status, then return the verification result from the paid reference.</p>
                    </div>
                </div>

                <div class="guide-callout">
                    <v-icon>mdi-information-outline</v-icon>
                    <span>The initiate URL can open a payment form or return JSON. Add <code>response=json</code> or <code>response=ui</code> to override the service response mode.</span>
                </div>

                <div class="guide-steps">
                    <div class="guide-step">
                        <div class="step-number">1</div>
                        <div class="step-body">
                            <h3>Send the buyer to payment</h3>
                            <p>Use the result page URL for result checks, or pass the NIN in the URL path for NIN checks.</p>
                            <div class="code-row">
                                <code>{{ firstService ? initiateExample(firstService) : '/paygo/{slug}/initiate/12345678901' }}</code>
                                <v-btn icon variant="outlined" size="small" title="Copy initiate example" @click="copyToClipboard(firstService ? initiateExample(firstService) : '/paygo/{slug}/initiate/12345678901')">
                                    <v-icon>mdi-content-copy</v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <div class="guide-step">
                        <div class="step-number">2</div>
                        <div class="step-body">
                            <h3>Payment succeeds</h3>
                            <p>The request now has a paid verification reference.</p>
                            <span class="paid-pill"><v-icon size="18">mdi-check-circle-outline</v-icon>status = paid</span>
                        </div>
                    </div>

                    <div class="guide-step">
                        <div class="step-number">3</div>
                        <div class="step-body">
                            <h3>Fetch the paid verification</h3>
                            <p>NIN links use the verify endpoint. Result links show the result page and expose a limited open pull endpoint.</p>
                            <div class="code-row">
                                <code>{{ firstService ? verifyGetExample(firstService) : '/api/paygo/{slug}/verify/12345678901' }}</code>
                                <v-btn icon variant="outlined" size="small" title="Copy verify example" @click="copyToClipboard(firstService ? verifyGetExample(firstService) : '/api/paygo/{slug}/verify/12345678901')">
                                    <v-icon>mdi-content-copy</v-icon>
                                </v-btn>
                            </div>
                            <div class="code-row code-row-pre">
                                <pre>{{ verifyPostBody }}</pre>
                                <v-btn icon variant="outlined" size="small" title="Copy POST body" @click="copyToClipboard(verifyPostBody)">
                                    <v-icon>mdi-content-copy</v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="guide-warning">
                    <v-icon>mdi-alert-outline</v-icon>
                    <span>NIN links keep the existing 3-call behavior. Result reference pulls use the admin-configured limit snapshotted on the payment.</span>
                </div>
            </section>

        </div>

        <v-dialog v-model="showLinksDialog" max-width="720">
            <v-card class="dialog-card">
                <v-card-title>{{ selectedLinksService?.name }} URLs</v-card-title>
                <v-card-subtitle>Copy the public payment, result page, or verify endpoint URL.</v-card-subtitle>
                <v-card-text v-if="selectedLinksService" class="link-dialog-body">
                    <div v-if="selectedLinksService.service_type !== 'result'" class="modal-link-row">
                        <div>
                            <span>Initiate payment</span>
                            <code>{{ selectedLinksService.initiate_url }}</code>
                        </div>
                        <v-btn icon variant="outlined" title="Copy initiate URL" @click="copyToClipboard(selectedLinksService.initiate_url)">
                            <v-icon>mdi-content-copy</v-icon>
                        </v-btn>
                    </div>
                    <div v-if="selectedLinksService.service_type !== 'result'" class="modal-link-row">
                        <div>
                            <span>Verify paid NIN</span>
                            <code>{{ selectedLinksService.verify_url }}</code>
                        </div>
                        <v-btn icon variant="outlined" title="Copy verify URL" @click="copyToClipboard(selectedLinksService.verify_url)">
                            <v-icon>mdi-content-copy</v-icon>
                        </v-btn>
                    </div>
                    <div v-if="selectedLinksService.service_type === 'result' && selectedLinksService.result_selector_url" class="modal-link-row">
                        <div>
                            <span>Exam selector page</span>
                            <code>{{ selectedLinksService.result_selector_url }}</code>
                        </div>
                        <v-btn icon variant="outlined" title="Copy selector URL" @click="copyToClipboard(selectedLinksService.result_selector_url || '')">
                            <v-icon>mdi-content-copy</v-icon>
                        </v-btn>
                    </div>
                    <div v-if="selectedLinksService.service_type === 'result' && !selectedLinksService.result_selector_url" class="modal-link-row">
                        <div>
                            <span>Result page</span>
                            <code>{{ selectedLinksService.result_url }}</code>
                        </div>
                        <v-btn icon variant="outlined" title="Copy result page URL" @click="copyToClipboard(selectedLinksService.result_url || '')">
                            <v-icon>mdi-content-copy</v-icon>
                        </v-btn>
                    </div>
                    <div v-if="selectedLinksService.service_type !== 'result'" class="modal-link-note">
                        Add the NIN to the path, for example <code>/12345678901</code>. The verify endpoint works with GET or POST.
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showLinksDialog = false">Close</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showTransactionsDialog" max-width="1100">
            <v-card class="dialog-card">
                <v-card-title>{{ selectedTransactionService?.name }} transactions</v-card-title>
                <v-card-subtitle>Payments and PayGo wallet entries for this service.</v-card-subtitle>
                <v-card-text>
                    <v-progress-linear v-if="transactionsLoading" indeterminate color="primary" class="mb-4" />

                    <v-tabs v-model="transactionTab" color="primary" density="comfortable">
                        <v-tab value="payments">
                            <v-icon start>mdi-credit-card-check-outline</v-icon>
                            Payments
                        </v-tab>
                        <v-tab value="wallet">
                            <v-icon start>mdi-wallet-outline</v-icon>
                            Wallet ledger
                        </v-tab>
                    </v-tabs>

                    <v-window v-model="transactionTab" class="mt-4">
                        <v-window-item value="payments">
                            <div class="modal-table-wrap">
                                <v-table density="comfortable">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Amount</th>
                                            <th>System Price</th>
                                            <th>Earning</th>
                                            <th>Lookup</th>
                                            <th>Attempts</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="intent in servicePaymentIntents" :key="intent.reference">
                                            <td class="font-weight-bold">{{ intent.reference }}</td>
                                            <td>{{ formatCurrency(intent.amount) }}</td>
                                            <td>{{ formatCurrency(intent.system_price) }}</td>
                                            <td class="text-success font-weight-bold">{{ formatCurrency(intent.earning) }}</td>
                                            <td>{{ intent.lookup_label || (intent.nin_last4 ? `****${intent.nin_last4}` : '-') }}</td>
                                            <td>{{ intent.reference_fetches ?? intent.verification_attempts }}/{{ intent.max_fetches || 3 }}</td>
                                            <td><v-chip size="small" variant="tonal" :color="statusColor(intent.status)">{{ intent.status }}</v-chip></td>
                                            <td>{{ formatDate(intent.created_at) }}</td>
                                        </tr>
                                        <tr v-if="!transactionsLoading && !servicePaymentIntents.length">
                                            <td colspan="8" class="text-center text-grey py-8">No payment transactions found for this service.</td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>
                        </v-window-item>

                        <v-window-item value="wallet">
                            <div class="modal-table-wrap">
                                <v-table density="comfortable">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Before</th>
                                            <th>After</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="transaction in serviceWalletTransactions" :key="transaction.reference">
                                            <td class="font-weight-bold">{{ transaction.reference }}</td>
                                            <td><v-chip size="small" variant="tonal" :color="transaction.type === 'credit' ? 'success' : 'warning'">{{ transaction.type }}</v-chip></td>
                                            <td>{{ transaction.category }}</td>
                                            <td>{{ formatCurrency(transaction.amount) }}</td>
                                            <td>{{ formatCurrency(transaction.balance_before) }}</td>
                                            <td>{{ formatCurrency(transaction.balance_after) }}</td>
                                            <td>{{ transaction.description }}</td>
                                            <td><v-chip size="small" variant="tonal" :color="statusColor(transaction.status)">{{ transaction.status }}</v-chip></td>
                                            <td>{{ formatDate(transaction.created_at) }}</td>
                                        </tr>
                                        <tr v-if="!transactionsLoading && !serviceWalletTransactions.length">
                                            <td colspan="9" class="text-center text-grey py-8">No wallet entries found for this service.</td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>
                        </v-window-item>
                    </v-window>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showTransactionsDialog = false">Close</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showFormDialog" max-width="580">
            <v-card class="dialog-card">
                <v-card-title>{{ editingService ? 'Edit PayGo Service' : 'Create PayGo Service' }}</v-card-title>
                <v-card-subtitle>Set a public price above your system service price.</v-card-subtitle>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Service Name" variant="outlined" class="mb-4" :error-messages="form.errors.name" />
                    <v-select
                        v-model="form.verification_service_id"
                        :items="verificationServices.map(service => ({ title: `${service.name} - minimum above ${formatCurrency(service.system_price)}`, value: service.id }))"
                        label="Verification Service"
                        variant="outlined"
                        class="mb-4"
                        :error-messages="form.errors.verification_service_id"
                    />
                    <v-text-field v-model="form.price" label="Public Price (NGN)" type="number" variant="outlined" prepend-inner-icon="mdi-currency-ngn" class="mb-4" :error-messages="form.errors.price" />
                    <v-select
                        v-model="form.response_mode"
                        :items="[
                            { title: 'Redirect/UI response', value: 'redirect' },
                            { title: 'JSON response', value: 'json' },
                        ]"
                        label="Initiate Response Mode"
                        variant="outlined"
                        class="mb-4"
                        :error-messages="form.errors.response_mode"
                    />
                    <v-text-field v-model="form.success_url" label="Success Redirect URL" variant="outlined" class="mb-4" :error-messages="form.errors.success_url" />
                    <v-text-field v-model="form.failure_url" label="Failure Redirect URL" variant="outlined" class="mb-4" :error-messages="form.errors.failure_url" />
                    <v-switch v-if="editingService" v-model="form.is_active" label="Service active" color="primary" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showFormDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="form.processing" @click="submitForm">{{ editingService ? 'Save' : 'Create' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showWithdrawDialog" max-width="520">
            <v-card class="dialog-card">
                <v-card-title>Request PayGo Withdrawal</v-card-title>
                <v-card-subtitle>Withdraw from your non-fundable PayGo earnings wallet.</v-card-subtitle>
                <v-card-text>
                    <v-alert type="info" variant="tonal" class="mb-4">
                        PayGo wallet receives only your markup above the system service price.
                    </v-alert>
                    <v-text-field v-model="withdrawForm.amount" label="Amount (NGN)" type="number" variant="outlined" prepend-inner-icon="mdi-currency-ngn" class="mb-4" :error-messages="withdrawForm.errors.amount" />
                    <v-text-field v-model="withdrawForm.bank_name" label="Bank Name" variant="outlined" class="mb-4" :error-messages="withdrawForm.errors.bank_name" />
                    <v-text-field v-model="withdrawForm.account_number" label="Account Number" variant="outlined" class="mb-4" :error-messages="withdrawForm.errors.account_number" />
                    <v-text-field v-model="withdrawForm.account_name" label="Account Name" variant="outlined" :error-messages="withdrawForm.errors.account_name" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showWithdrawDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="withdrawForm.processing" @click="submitWithdrawal">Submit Request</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </CustomerLayout>
</template>

<style scoped>
.paygo-page {
    display: grid;
    gap: 1.25rem;
}

.paygo-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.5rem;
    border: 1px solid #dfe7e2;
    border-radius: 8px;
    background: linear-gradient(135deg, #ffffff 0%, #f5faf7 100%);
}

.header-copy {
    max-width: 720px;
}

.header-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
    color: #146b3a;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
}

.paygo-header h1 {
    margin: 0;
    color: #101817;
    font-size: 2rem;
    font-weight: 850;
    line-height: 1.15;
}

.paygo-header p {
    max-width: 680px;
    margin: 0.65rem 0 0;
    color: #5b6661;
    font-size: 1rem;
    line-height: 1.55;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: 0 0 auto;
}

.wallet-strip {
    display: grid;
    grid-template-columns: 1.15fr 1fr 1fr;
    gap: 1rem;
}

.wallet-panel {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-height: 126px;
    padding: 1.25rem;
    border: 1px solid #e3e6e2;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(20, 32, 28, 0.06);
}

.wallet-panel-primary {
    border-color: #146b3a;
    background: #146b3a;
    color: #ffffff;
}

.wallet-icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.16);
    color: inherit;
    flex: 0 0 auto;
}

.wallet-icon-muted {
    background: #eef6f1;
    color: #146b3a;
}

.wallet-panel p,
.wallet-panel span {
    margin: 0;
    color: inherit;
    opacity: 0.72;
}

.wallet-panel p {
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
}

.wallet-panel strong {
    display: block;
    margin: 0.18rem 0;
    color: inherit;
    font-size: 1.7rem;
    font-weight: 850;
    line-height: 1.1;
}

.wallet-panel span {
    display: block;
    font-size: 0.88rem;
}

.wallet-actions {
    justify-content: space-between;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.summary-item {
    padding: 1rem 1.15rem;
    border: 1px solid #e4e7eb;
    border-radius: 8px;
    background: #ffffff;
}

.summary-item span,
.summary-item small {
    display: block;
    color: #69716d;
}

.summary-item span {
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
}

.summary-item strong {
    display: block;
    margin: 0.2rem 0;
    color: #131817;
    font-size: 1.8rem;
    line-height: 1;
}

.summary-item small {
    font-size: 0.86rem;
}

.services-surface,
.integration-guide,
.withdrawal-surface {
    padding: 1.25rem;
    border: 1px solid #e3e6e2;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(20, 32, 28, 0.05);
}

.surface-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.1rem;
}

.surface-header h2,
.guide-title h2 {
    margin: 0;
    color: #101817;
    font-size: 1.25rem;
    font-weight: 850;
}

.surface-header p,
.guide-title p {
    margin: 0.3rem 0 0;
    color: #66706b;
    font-size: 0.94rem;
}

.empty-state {
    display: grid;
    justify-items: center;
    gap: 0.6rem;
    padding: 3rem 1rem;
    border: 1px dashed #cfd8d3;
    border-radius: 8px;
    background: #fbfcfb;
    color: #5f6b66;
    text-align: center;
}

.empty-state h3,
.empty-state p {
    margin: 0;
}

.empty-state h3 {
    color: #111817;
    font-size: 1.15rem;
}

.service-list {
    display: grid;
    gap: 0.85rem;
}

.service-row {
    display: grid;
    grid-template-columns: minmax(260px, 1.3fr) minmax(180px, 0.7fr) minmax(120px, 0.45fr) auto;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e5e9e6;
    border-radius: 8px;
    background: #ffffff;
}

.service-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.service-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: #edf7f1;
    color: #146b3a;
    flex: 0 0 auto;
}

.service-title,
.service-money {
    min-width: 0;
}

.service-name-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.service-name-line h3 {
    overflow: hidden;
    margin: 0;
    color: #101817;
    font-size: 1rem;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.service-title p,
.service-money span,
.service-money small,
.service-usage span {
    color: #6a736e;
    font-size: 0.86rem;
}

.service-title p,
.service-money span,
.service-money small {
    display: block;
    margin: 0.18rem 0 0;
}

.service-money strong {
    display: block;
    color: #101817;
    font-size: 1.15rem;
}

.service-usage {
    display: flex;
    gap: 0.65rem;
}

.service-usage div {
    min-width: 52px;
    padding: 0.55rem 0.65rem;
    border-radius: 8px;
    background: #f4f6f5;
}

.service-usage strong,
.service-usage span {
    display: block;
    line-height: 1.1;
}

.service-usage strong {
    color: #101817;
    font-size: 1.05rem;
}

.service-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.15rem;
}

.guide-title {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    margin-bottom: 1rem;
}

.guide-mark {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 8px;
    background: #dceeff;
    color: #1b5ea8;
    flex: 0 0 auto;
}

.guide-callout,
.guide-warning {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
    line-height: 1.6;
}

.guide-callout {
    border: 1px solid #9bc7ff;
    background: #e6f2ff;
    color: #15559a;
}

.guide-warning {
    margin-top: 1rem;
    border: 1px solid #f0bc51;
    background: #fff1ce;
    color: #765000;
}

.guide-steps {
    display: grid;
    gap: 1rem;
    margin-top: 1rem;
}

.guide-step {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 0.9rem;
    align-items: flex-start;
}

.step-number {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #e8f2ff;
    color: #1b5ea8;
    font-weight: 850;
}

.step-body {
    min-width: 0;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid #edf0ee;
}

.guide-step:last-child .step-body {
    border-bottom: 0;
    padding-bottom: 0;
}

.step-body h3 {
    margin: 0;
    color: #101817;
    font-size: 1rem;
    font-weight: 850;
}

.step-body p {
    margin: 0.25rem 0 0.75rem;
    color: #626c67;
}

.code-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.55rem;
    padding: 0.75rem;
    border: 1px solid #dfe4e1;
    border-radius: 8px;
    background: #fbfcfb;
}

.code-row code,
.code-row pre {
    min-width: 0;
    margin: 0;
    overflow-x: auto;
    color: #293430;
    font-size: 0.9rem;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

.code-row-pre {
    align-items: start;
}

.paid-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    background: #d9f3df;
    color: #086b1b;
    font-weight: 800;
}

.dialog-card {
    border-radius: 8px;
}

.link-dialog-body {
    display: grid;
    gap: 0.85rem;
}

.modal-link-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.85rem;
    align-items: center;
    padding: 0.85rem;
    border: 1px solid #e2e8e4;
    border-radius: 8px;
    background: #fbfcfb;
}

.modal-link-row span {
    display: block;
    margin-bottom: 0.3rem;
    color: #66706b;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.modal-link-row code {
    display: block;
    overflow-x: auto;
    color: #17211d;
    font-size: 0.95rem;
    white-space: nowrap;
}

.modal-link-note {
    padding: 0.85rem;
    border-radius: 8px;
    background: #e8f2ff;
    color: #1b5ea8;
    line-height: 1.55;
}

.modal-table-wrap {
    overflow-x: auto;
    border: 1px solid #e5e9e6;
    border-radius: 8px;
}

@media (max-width: 1180px) {
    .service-row {
        grid-template-columns: minmax(220px, 1fr) minmax(160px, 0.7fr) auto;
    }

    .service-usage {
        grid-column: 1 / -1;
    }

    .service-actions {
        grid-column: 3;
        grid-row: 1;
    }
}

@media (max-width: 900px) {
    .paygo-header,
    .wallet-actions {
        flex-direction: column;
    }

    .wallet-strip,
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .header-actions,
    .header-actions :deep(.v-btn) {
        width: 100%;
    }

    .header-actions {
        flex-direction: column;
    }

    .service-row {
        grid-template-columns: 1fr;
    }

    .service-actions {
        grid-column: auto;
        grid-row: auto;
        justify-content: flex-start;
    }
}

@media (max-width: 640px) {
    .paygo-page {
        gap: 1rem;
    }

    .paygo-header,
    .services-surface,
    .integration-guide,
    .withdrawal-surface {
        padding: 1rem;
    }

    .paygo-header h1 {
        font-size: 1.55rem;
    }

    .wallet-panel {
        align-items: flex-start;
    }

    .guide-title {
        flex-direction: column;
    }

    .guide-step {
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 0.65rem;
    }

    .step-number {
        width: 30px;
        height: 30px;
    }
}
</style>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    service: any;
    price: number;
    walletBalance: number;
    branches?: Array<{ id: number; name: string; code: string; wallet_balance: number }>;
    isResultBoard?: boolean;
    resultBoard?: string | null;
    formEndpoint?: string | null;
}>();

const form = useForm({ search_parameter: '', branch_id: null as number | null });
const resultForm = useForm<Record<string, any>>({ branch_id: null });
const loading = ref(false);
const loadingFields = ref(false);
const resultFields = ref<any[]>([]);
const fieldOptions = ref<Record<string, any[]>>({});
const fieldLoadError = ref<string | null>(null);
const resultSubmitError = ref<string | null>(null);

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount || 0);

const chargeBranchId = computed({
    get: () => props.isResultBoard ? resultForm.branch_id : form.branch_id,
    set: (value) => {
        if (props.isResultBoard) {
            resultForm.branch_id = value;
            return;
        }

        form.branch_id = value;
    },
});

const activeWalletBalance = computed(() => {
    if (!chargeBranchId.value) {
        return props.walletBalance;
    }

    return props.branches?.find((branch) => branch.id === chargeBranchId.value)?.wallet_balance || 0;
});

const canVerify = () => activeWalletBalance.value >= props.price;

const getPlaceholder = () => {
    const placeholders: Record<string, string> = {
        'nin': 'Enter 11-digit NIN',
        'bvn': 'Enter 11-digit BVN',
        'cac': 'Enter RC Number',
        'drivers-license': 'Enter License Number',
        'passport': 'Enter Passport Number',
    };
    return placeholders[props.service.slug] || 'Enter search parameter';
};

const normalizeOptions = (options?: any[]) => (options || []).map((option) => ({
    title: option.title ?? option.label ?? option.name ?? option.value ?? option.id,
    value: option.value ?? option.id ?? option.name ?? option.label,
}));

const optionsForField = (field: any) => normalizeOptions(fieldOptions.value[field.name] || field.options);

const resultFormIsComplete = computed(() => {
    if (!props.isResultBoard || !resultFields.value.length) {
        return false;
    }

    return resultFields.value.every((field) => {
        if (!field.required) {
            return true;
        }

        const value = resultForm[field.name];
        return value !== null && value !== undefined && String(value).trim() !== '';
    });
});

const resultFormError = computed(() => resultSubmitError.value || (resultForm.errors as Record<string, string>).result);

const fieldError = (field: any) => (resultForm.errors as Record<string, string>)[field.name];

const loadDependentOptions = async (field: any) => {
    if (!field.options_endpoint || !field.depends_on) return;

    const parentValue = resultForm[field.depends_on];
    resultForm[field.name] = '';
    fieldOptions.value[field.name] = [];

    if (!parentValue) return;

    const params = new URLSearchParams({ [field.depends_on]: String(parentValue) });
    const response = await fetch(`${field.options_endpoint}?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Unable to load options.');
    }

    fieldOptions.value[field.name] = payload.data || [];
};

const loadResultFields = async () => {
    if (!props.isResultBoard || !props.formEndpoint) return;

    loadingFields.value = true;
    fieldLoadError.value = null;

    try {
        const response = await fetch(props.formEndpoint, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Unable to load result fields.');
        }

        resultFields.value = payload.data.fields || [];

        resultFields.value.forEach((field) => {
            if (resultForm[field.name] === undefined) {
                resultForm[field.name] = '';
            }
        });
    } catch (error) {
        fieldLoadError.value = error instanceof Error ? error.message : 'Unable to load result fields.';
    } finally {
        loadingFields.value = false;
    }
};

const resultPayload = () => {
    const payload: Record<string, any> = {
        branch_id: resultForm.branch_id,
    };

    resultFields.value.forEach((field) => {
        payload[field.name] = resultForm[field.name];
    });

    return payload;
};

const submit = () => {
    if (!canVerify()) return;
    loading.value = true;

    if (props.isResultBoard) {
        resultSubmitError.value = null;

        resultForm
            .transform(() => resultPayload())
            .post(`/customer/verify/${props.service.id}`, {
                preserveScroll: true,
                onFinish: () => loading.value = false,
                onSuccess: () => resultSubmitError.value = null,
                onError: (errors) => {
                    resultSubmitError.value = (errors.result || Object.values(errors)[0] || 'Result verification failed. Please try again.') as string;
                    console.error('Form errors:', errors);
                },
            });

        return;
    }

    form.post(`/customer/verify/${props.service.id}`, {
        preserveScroll: true,
        onFinish: () => loading.value = false,
        onError: (errors) => {
            console.error('Form errors:', errors);
        },
    });
};

onMounted(loadResultFields);

watch(
    () => resultFields.value.map((field) => field.depends_on ? resultForm[field.depends_on] : null),
    async () => {
        for (const field of resultFields.value) {
            if (field.depends_on && field.options_endpoint) {
                try {
                    await loadDependentOptions(field);
                } catch (error) {
                    fieldLoadError.value = error instanceof Error ? error.message : 'Unable to load options.';
                }
            }
        }
    },
);
</script>

<template>
    <Head :title="`${service.name} - EaseVerifier`" />
    <CustomerLayout :user="$page.props.auth.user" :wallet="$page.props.auth.wallet">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/verify" class="mb-2">Back to Services</v-btn>
            <div class="d-flex align-center">
                <v-avatar color="primary-lighten-5" size="56" class="mr-4">
                    <v-icon color="primary" size="28">{{ service.icon || 'mdi-shield-check' }}</v-icon>
                </v-avatar>
                <div>
                    <h1 class="text-h4 font-weight-bold mb-0">{{ service.name }}</h1>
                    <p class="text-body-2 text-grey">{{ service.description }}</p>
                </div>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="8">
                <v-card>
                    <v-card-title>Enter Details</v-card-title>
                    <v-card-text>
                        <v-alert v-if="!canVerify()" type="warning" variant="tonal" class="mb-4">
                            <v-alert-title>Insufficient Balance</v-alert-title>
                            Your selected wallet balance ({{ formatCurrency(activeWalletBalance) }}) is less than the verification cost ({{ formatCurrency(price) }}).
                            <template #append>
                                <v-btn color="warning" variant="tonal" href="/customer/wallet/fund">Fund Wallet</v-btn>
                            </template>
                        </v-alert>

                        <v-form @submit.prevent="submit">
                            <template v-if="isResultBoard">
                                <v-alert v-if="fieldLoadError" type="error" variant="tonal" class="mb-4">
                                    {{ fieldLoadError }}
                                </v-alert>

                                <v-alert v-if="resultFormError" type="error" variant="tonal" class="mb-4">
                                    {{ resultFormError }}
                                </v-alert>

                                <v-progress-linear v-if="loadingFields" indeterminate color="primary" class="mb-4" />

                                <template v-else>
                                    <template v-for="field in resultFields" :key="field.name">
                                        <v-autocomplete
                                            v-if="field.type === 'select'"
                                            v-model="resultForm[field.name]"
                                            :items="optionsForField(field)"
                                            item-title="title"
                                            item-value="value"
                                            :label="field.label"
                                            variant="outlined"
                                            clearable
                                            auto-select-first
                                            no-data-text="No matching option found"
                                            :disabled="!!field.depends_on && !resultForm[field.depends_on]"
                                            :error-messages="fieldError(field)"
                                            class="mb-4"
                                        />

                                        <v-text-field
                                            v-else
                                            v-model="resultForm[field.name]"
                                            :label="field.label"
                                            :type="field.type || 'text'"
                                            variant="outlined"
                                            :error-messages="fieldError(field)"
                                            class="mb-4"
                                        />
                                    </template>
                                </template>
                            </template>

                            <v-text-field
                                v-else
                                v-model="form.search_parameter"
                                :label="service.name + ' Number'"
                                :placeholder="getPlaceholder()"
                                variant="outlined"
                                :error-messages="form.errors.search_parameter"
                                prepend-inner-icon="mdi-magnify"
                                class="mb-4"
                            />

                            <v-autocomplete
                                v-if="branches?.length"
                                v-model="chargeBranchId"
                                :items="[{ title: 'Primary account wallet', value: null }, ...branches.map(branch => ({ title: `${branch.name} (${formatCurrency(branch.wallet_balance)})`, value: branch.id }))]"
                                label="Charge wallet"
                                variant="outlined"
                                auto-select-first
                                class="mb-4"
                            />

                            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                                <strong>Cost:</strong> {{ formatCurrency(price) }} will be deducted from your wallet.
                            </v-alert>

                            <v-btn
                                type="submit"
                                color="primary"
                                size="large"
                                block
                                :loading="loading || form.processing || resultForm.processing"
                                :disabled="!canVerify() || (isResultBoard ? !resultFormIsComplete : !form.search_parameter) || loadingFields"
                            >
                                <v-icon start>mdi-shield-search</v-icon>
                                {{ isResultBoard ? 'Check Result' : 'Verify Now' }}
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card>
                    <v-card-title>Service Info</v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item>
                                <v-list-item-title class="text-caption">Price</v-list-item-title>
                                <v-list-item-subtitle class="text-h6 font-weight-bold text-primary">{{ formatCurrency(price) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Your Balance</v-list-item-title>
                                <v-list-item-subtitle :class="canVerify() ? 'text-success' : 'text-error'" class="font-weight-medium">{{ formatCurrency(activeWalletBalance) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item>
                                <v-list-item-title class="text-caption">Response Time</v-list-item-title>
                                <v-list-item-subtitle>~2-5 seconds</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card class="mt-4">
                    <v-card-title>Tips</v-card-title>
                    <v-card-text>
                        <ul class="text-body-2 text-grey pl-4">
                            <li class="mb-2">Ensure the number is correct before verifying</li>
                            <li class="mb-2">Verification charges are non-refundable</li>
                            <li>Results are stored in your history</li>
                        </ul>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </CustomerLayout>
</template>

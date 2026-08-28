<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface PaygoResultService {
    name: string;
    public_slug: string;
    price: number;
    service_name?: string | null;
    board: string;
    customer_name?: string | null;
    result_url: string;
    selector_url?: string | null;
}

interface ResultField {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    options?: Array<{ value: string; label: string }>;
    options_endpoint?: string;
    depends_on?: string;
}

const props = defineProps<{
    customer: { name?: string | null; selector_url?: string | null };
    services: PaygoResultService[];
    paygoService?: PaygoResultService | null;
    fields: ResultField[];
    prefill?: {
        email?: string | null;
        phone?: string | null;
        candidate_id?: string | null;
        portal_ref?: string | null;
        state?: string | null;
    };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as any);
const selectedSlug = ref(props.paygoService?.public_slug || '');
const fieldOptions = ref<Record<string, any[]>>({});
const fieldLoadError = ref<string | null>(null);
const confirmationOpen = ref(false);
const consentChecked = ref(false);

const resultFieldDefaults = props.fields.reduce<Record<string, any>>((defaults, field) => {
    defaults[field.name] = '';

    return defaults;
}, {});

const form = useForm<Record<string, any>>({
    ...resultFieldDefaults,
    email: props.prefill?.email || '',
    phone: props.prefill?.phone || '',
    candidate_id: props.prefill?.candidate_id || '',
    portal_ref: props.prefill?.portal_ref || '',
    state: props.prefill?.state || '',
});

const formatCurrency = (amount: number) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
}).format(amount || 0);

const selectedService = computed(() => props.paygoService || props.services.find((service) => service.public_slug === selectedSlug.value) || null);
const selectorUrl = computed(() => props.customer?.selector_url || selectedService.value?.selector_url || null);
const confirmationEntries = computed(() => {
    const entries = props.fields
        .map((field) => {
            const value = form[field.name];

            if (value === null || value === undefined || value === '') {
                return null;
            }

            const displayValue = field.type === 'select'
                ? optionsForField(field).find((option) => String(option.value) === String(value))?.title ?? String(value)
                : String(value);

            return {
                label: field.label,
                value: displayValue,
            };
        })
        .filter((entry): entry is { label: string; value: string } => entry !== null);

    if (form.email) {
        entries.push({
            label: 'Email',
            value: String(form.email),
        });
    }

    if (form.phone) {
        entries.push({
            label: 'Phone number',
            value: String(form.phone),
        });
    }

    return entries;
});

const withPortalContext = (url?: string | null) => {
    if (!url) return '';

    const query = new URLSearchParams();

    ['candidate_id', 'portal_ref', 'state', 'email', 'phone'].forEach((key) => {
        const value = form[key];

        if (value) {
            query.set(key, String(value));
        }
    });

    const queryString = query.toString();

    return queryString ? `${url}${url.includes('?') ? '&' : '?'}${queryString}` : url;
};

const normalizedOptions = (options?: any[]) => (options || []).map((option) => ({
    title: option.title ?? option.label ?? option.name ?? option.value ?? option.id,
    value: option.value ?? option.id ?? option.name ?? option.label,
}));

const optionsForField = (field: ResultField) => normalizedOptions(fieldOptions.value[field.name] || field.options);

const chooseService = () => {
    const service = props.services.find((item) => item.public_slug === selectedSlug.value);

    if (service) {
        window.location.href = withPortalContext(service.result_url);
    }
};

const loadDependentOptions = async (field: ResultField) => {
    if (!field.options_endpoint || !field.depends_on) return;

    const parentValue = form[field.depends_on];
    form[field.name] = '';
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

const openConfirmation = () => {
    if (!selectedService.value || form.processing) return;

    consentChecked.value = false;
    confirmationOpen.value = true;
};

const submit = () => {
    if (!selectedService.value) return;

    confirmationOpen.value = false;
    consentChecked.value = false;

    form.post(selectedService.value.result_url, {
        preserveScroll: true,
    });
};

watch(
    () => props.fields.map((field) => field.depends_on ? form[field.depends_on] : null),
    async () => {
        for (const field of props.fields) {
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
    <Head :title="`${selectedService?.name || 'Result Verification'} - PayGo`" />

    <v-app>
        <v-main class="paygo-main">
            <v-container class="paygo-container">
                <v-row justify="center">
                    <v-col cols="12" md="8" lg="6">
                        <v-card class="paygo-card" elevation="0">
                            <v-card-text class="pa-6">
                                <v-chip color="secondary" variant="flat" class="mb-4">Result Verification</v-chip>
                                <h1 class="text-h4 font-weight-bold mb-2">{{ selectedService?.name || 'Select exam result' }}</h1>
                                

                                <v-btn
                                    v-if="paygoService && selectorUrl && services.length > 1"
                                    :href="withPortalContext(selectorUrl)"
                                    variant="text"
                                    color="primary"
                                    prepend-icon="mdi-arrow-left"
                                    class="px-0 mb-4"
                                >
                                    Change exam
                                </v-btn>

                                <v-alert v-if="flash?.error" type="error" variant="tonal" class="mb-4">{{ flash.error }}</v-alert>
                                <v-alert v-if="fieldLoadError" type="error" variant="tonal" class="mb-4">{{ fieldLoadError }}</v-alert>
                                <v-alert v-if="form.errors.result" type="error" variant="tonal" class="mb-4">{{ form.errors.result }}</v-alert>

                                <v-select
                                    v-if="!paygoService"
                                    v-model="selectedSlug"
                                    :items="services.map(service => ({ title: `${service.board} - ${formatCurrency(service.price)}`, value: service.public_slug }))"
                                    label="Exam"
                                    variant="outlined"
                                    class="mb-4"
                                    @update:model-value="chooseService"
                                />

                                <template v-if="selectedService && fields.length">
                                    <div class="price-strip mb-5">
                                        <span>{{ selectedService.board }} amount</span>
                                        <strong>{{ formatCurrency(selectedService.price) }}</strong>
                                    </div>

                                    <v-form @submit.prevent="openConfirmation">
                                        <template v-for="field in fields" :key="field.name">
                                            <v-autocomplete
                                                v-if="field.type === 'select'"
                                                v-model="form[field.name]"
                                                :items="optionsForField(field)"
                                                item-title="title"
                                                item-value="value"
                                                :label="field.label"
                                                variant="outlined"
                                                clearable
                                                auto-select-first
                                                no-data-text="No matching option found"
                                                :disabled="!!field.depends_on && !form[field.depends_on]"
                                                :error-messages="form.errors[field.name]"
                                                class="mb-4"
                                            />
                                            <v-text-field
                                                v-else
                                                v-model="form[field.name]"
                                                :label="field.label"
                                                :type="field.type || 'text'"
                                                variant="outlined"
                                                :error-messages="form.errors[field.name]"
                                                class="mb-4"
                                            />
                                        </template>

                                        <v-text-field
                                            v-model="form.email"
                                            label="Email"
                                            type="email"
                                            variant="outlined"
                                            :error-messages="form.errors.email"
                                            class="mb-4"
                                        />
                                        <v-text-field
                                            v-model="form.phone"
                                            label="Phone number"
                                            variant="outlined"
                                            :error-messages="form.errors.phone"
                                            class="mb-4"
                                        />
                                        <v-btn
                                            type="submit"
                                            color="secondary"
                                            size="large"
                                            block
                                            :loading="form.processing"
                                        >
                                            Proceed to Payment
                                        </v-btn>
                                    </v-form>

                                    <v-dialog v-model="confirmationOpen" max-width="560">
                                        <v-card>
                                            <v-card-text class="pa-6">
                                                <h2 class="text-h6 font-weight-bold mb-2">Confirm your details</h2>
                                                <p class="text-body-2 text-grey-darken-1 mb-4">
                                                    Please confirm that the result-check details below are correct before we continue to payment.
                                                </p>

                                                <div class="confirmation-list mb-4">
                                                    <div
                                                        v-for="entry in confirmationEntries"
                                                        :key="entry.label"
                                                        class="confirmation-row"
                                                    >
                                                        <span>{{ entry.label }}</span>
                                                        <strong>{{ entry.value }}</strong>
                                                    </div>
                                                </div>

                                                <v-checkbox
                                                    v-model="consentChecked"
                                                    color="secondary"
                                                    hide-details
                                                    class="mb-2"
                                                    label="I confirm that the information provided is correct and belongs to me."
                                                />

                                                <div class="d-flex flex-column flex-sm-row ga-3">
                                                    <v-btn
                                                        variant="outlined"
                                                        color="primary"
                                                        class="confirmation-action"
                                                        @click="confirmationOpen = false"
                                                    >
                                                        Review again
                                                    </v-btn>
                                                    <v-btn
                                                        color="secondary"
                                                        class="confirmation-action"
                                                        :disabled="!consentChecked"
                                                        :loading="form.processing"
                                                        @click="submit"
                                                    >
                                                        Confirm and Pay
                                                    </v-btn>
                                                </div>
                                            </v-card-text>
                                        </v-card>
                                    </v-dialog>
                                </template>

                                <v-alert v-else-if="!services.length" type="warning" variant="tonal">
                                    No PayGo result verification service is available for this customer.
                                </v-alert>
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
    background: linear-gradient(135deg, #0f3e20 0%, #082716 58%, #04150d 100%);
}

.paygo-container {
    padding-top: 4rem;
    padding-bottom: 3rem;
}

.paygo-card {
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.price-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-radius: 8px;
    background: #f6f8f6;
}

.price-strip span {
    color: #5f6f65;
}

.price-strip strong {
    color: #0f3e20;
    font-size: 1.25rem;
}

.confirmation-list {
    border: 1px solid rgba(15, 62, 32, 0.12);
    border-radius: 8px;
    overflow: hidden;
}

.confirmation-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(15, 62, 32, 0.08);
}

.confirmation-row:last-child {
    border-bottom: 0;
}

.confirmation-row span {
    color: #5f6f65;
}

.confirmation-row strong {
    color: #0f3e20;
    text-align: right;
}

.confirmation-action {
    flex: 1 1 0;
    min-width: 0;
}
</style>

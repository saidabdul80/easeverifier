<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Template = {
    id: number;
    name: string;
    category: string;
    subject: string;
    heading?: string | null;
    body: string;
    cta_label?: string | null;
    cta_url?: string | null;
};

const props = defineProps<{
    templates: Template[];
    customers: Array<{
        id: number;
        name: string;
        email: string;
        company_name?: string | null;
    }>;
    counts: { all: number };
    previewCustomer?: {
        name: string;
        email: string;
        company_name?: string | null;
        wallet_balance: number;
    } | null;
    availablePlaceholders: string[];
    appUrl: string;
    supportEmail: string;
    supportName: string;
}>();

const initialTemplate = props.templates[0];

const form = useForm({
    template_id: initialTemplate?.id ?? null,
    title: initialTemplate?.name ?? '',
    subject: initialTemplate?.subject ?? '',
    heading: initialTemplate?.heading ?? '',
    body: initialTemplate?.body ?? '',
    cta_label: initialTemplate?.cta_label ?? '',
    cta_url: initialTemplate?.cta_url ?? '',
    recipient_scope: 'all',
    customer_ids: [] as number[],
    additional_emails: [] as string[],
});

const sampleCustomer = computed(
    () =>
        props.previewCustomer ?? {
            name: 'Customer Name',
            email: 'customer@example.com',
            company_name: 'Sample Company',
            wallet_balance: 0,
        },
);

const selectedTemplate = computed(() => {
    return (
        props.templates.find((template) => template.id === form.template_id) ??
        props.templates[0]
    );
});

const templateCategories = computed(() => {
    return [
        'all',
        ...new Set(props.templates.map((template) => template.category)),
    ];
});
const browseCategory = ref('all');

const visibleTemplates = computed(() => {
    if (browseCategory.value === 'all') {
        return props.templates;
    }

    return props.templates.filter(
        (template) => template.category === browseCategory.value,
    );
});

const selectedCustomerCount = computed(() => {
    return form.recipient_scope === 'selected'
        ? form.customer_ids.length
        : props.counts.all;
});

const additionalEmailCount = computed(() => form.additional_emails.length);

const totalRecipientCount = computed(() => {
    return selectedCustomerCount.value + additionalEmailCount.value;
});

const audienceLabel = computed(() => {
    if (form.recipient_scope === 'selected') {
        return 'Selected customers';
    }

    return 'All active customers';
});

const sendDisabled = computed(() => {
    if (form.processing || !selectedTemplate.value) {
        return true;
    }

    if (
        form.recipient_scope === 'selected' &&
        totalRecipientCount.value === 0
    ) {
        return true;
    }

    return totalRecipientCount.value === 0;
});

const selectedCustomerItems = computed(() => {
    return props.customers.map((customer) => ({
        title: `${customer.name} (${customer.email})`,
        value: customer.id,
    }));
});

const sampleResolvedSubject = computed(() => replacePlaceholders(form.subject));
const sampleResolvedHeading = computed(() => replacePlaceholders(form.heading));
const sampleResolvedBody = computed(() => replacePlaceholders(form.body));
const sampleResolvedCtaLabel = computed(() =>
    replacePlaceholders(form.cta_label),
);
const sampleResolvedCtaUrl = computed(() => replacePlaceholders(form.cta_url));

const additionalEmailInput = computed({
    get: () => form.additional_emails.join(', '),
    set: (value: string) => {
        form.additional_emails = value
            .split(/[,\n]/)
            .map((email) => email.trim())
            .filter(Boolean);
    },
});

const formatCategoryLabel = (value?: string | null) => {
    if (!value) {
        return 'General';
    }

    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const templateExcerpt = (body: string) => {
    return body.length > 120 ? `${body.slice(0, 120)}...` : body;
};

const replacePlaceholders = (value?: string | null) => {
    if (!value) return '';

    const firstName =
        sampleCustomer.value.name.split(' ')[0] || sampleCustomer.value.name;

    return value
        .replaceAll('{{customer_name}}', sampleCustomer.value.name)
        .replaceAll('{{first_name}}', firstName)
        .replaceAll(
            '{{company_name}}',
            sampleCustomer.value.company_name || 'your team',
        )
        .replaceAll('{{email}}', sampleCustomer.value.email)
        .replaceAll('{{phone}}', 'N/A')
        .replaceAll('{{joined_date}}', new Date().toLocaleDateString())
        .replaceAll(
            '{{wallet_balance}}',
            new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency: 'NGN',
                minimumFractionDigits: 0,
            }).format(sampleCustomer.value.wallet_balance || 0),
        )
        .replaceAll('{{dashboard_url}}', `${props.appUrl}/customer`)
        .replaceAll('{{services_url}}', `${props.appUrl}/services`)
        .replaceAll('{{pricing_url}}', `${props.appUrl}/pricing`)
        .replaceAll(
            '{{api_docs_url}}',
            `${props.appUrl}/customer/api/documentation`,
        )
        .replaceAll('{{support_email}}', props.supportEmail)
        .replaceAll('{{support_name}}', props.supportName);
};

const applyTemplate = (templateId: number | null) => {
    const template = props.templates.find((item) => item.id === templateId);

    if (!template) {
        return;
    }

    form.title = template.name;
    form.subject = template.subject;
    form.heading = template.heading ?? '';
    form.body = template.body;
    form.cta_label = template.cta_label ?? '';
    form.cta_url = template.cta_url ?? '';
};

watch(
    () => form.recipient_scope,
    (scope) => {
        if (scope !== 'selected') {
            form.customer_ids = [];
        }
    },
);

watch(
    () => form.template_id,
    (templateId) => {
        applyTemplate(templateId);
    },
);

const submit = () => {
    form.post('/admin/campaign-emails');
};
</script>

<template>
    <Head title="New Campaign Email - Admin" />

    <AdminLayout :user="$page.props.auth.user">
        <div class="campaign-composer">
            <section class="composer-hero mb-6">
                <div
                    class="d-flex flex-column flex-lg-row ga-6 align-lg-center"
                >
                    <div class="flex-grow-1">
                        <v-btn
                            variant="text"
                            prepend-icon="mdi-arrow-left"
                            href="/admin/campaign-emails"
                            class="mb-3 px-0"
                        >
                            Back to Campaign Emails
                        </v-btn>
                        <div class="composer-kicker mb-2">Campaign Studio</div>
                        <h1 class="text-h3 font-weight-bold mb-2">
                            Build a campaign that is easy to review before send
                        </h1>
                        <p class="text-body-1 composer-hero-copy mb-0">
                            Pick a starting template, tune the message, choose
                            the audience, and validate the exact preview before
                            it reaches customers.
                        </p>
                    </div>

                    <div class="composer-metrics">
                        <div class="metric-card">
                            <div class="metric-label">Selected Template</div>
                            <div class="metric-value">
                                {{ selectedTemplate?.name || 'None' }}
                            </div>
                            <div class="metric-subtle">
                                {{
                                    formatCategoryLabel(
                                        selectedTemplate?.category,
                                    )
                                }}
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-label">Recipients</div>
                            <div class="metric-value">
                                {{ totalRecipientCount }}
                            </div>
                            <div class="metric-subtle">{{ audienceLabel }}</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-label">Support Inbox</div>
                            <div class="metric-value">
                                {{ props.supportEmail }}
                            </div>
                            <div class="metric-subtle">CTA merge tag ready</div>
                        </div>
                    </div>
                </div>
            </section>

            <v-row align="start">
                <v-col cols="12" lg="7">
                    <v-card class="composer-panel mb-5" rounded="xl">
                        <v-card-text class="pa-6 pa-md-8">
                            <div class="step-header mb-5">
                                <div>
                                    <div class="step-badge">Step 1</div>
                                    <h2
                                        class="text-h5 font-weight-bold mt-2 mb-1"
                                    >
                                        Choose a starting template
                                    </h2>
                                    <p
                                        class="text-body-2 text-medium-emphasis mb-0"
                                    >
                                        Start from a campaign theme, then refine
                                        the content below.
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex ga-2 mb-5 flex-wrap">
                                <v-chip
                                    v-for="category in templateCategories"
                                    :key="category"
                                    :color="
                                        browseCategory === category
                                            ? 'primary'
                                            : undefined
                                    "
                                    :variant="
                                        browseCategory === category
                                            ? 'flat'
                                            : 'outlined'
                                    "
                                    size="small"
                                    @click="browseCategory = category"
                                >
                                    {{
                                        category === 'all'
                                            ? 'All Templates'
                                            : formatCategoryLabel(category)
                                    }}
                                </v-chip>
                            </div>

                            <div class="template-grid">
                                <button
                                    v-for="template in visibleTemplates"
                                    :key="template.id"
                                    type="button"
                                    class="template-card"
                                    :class="{
                                        'template-card--active':
                                            form.template_id === template.id,
                                    }"
                                    @click="form.template_id = template.id"
                                >
                                    <div class="template-card__top">
                                        <div>
                                            <div class="template-card__title">
                                                {{ template.name }}
                                            </div>
                                            <div class="template-card__subject">
                                                {{ template.subject }}
                                            </div>
                                        </div>
                                        <v-icon
                                            :color="
                                                form.template_id === template.id
                                                    ? 'primary'
                                                    : 'grey'
                                            "
                                        >
                                            {{
                                                form.template_id === template.id
                                                    ? 'mdi-check-decagram'
                                                    : 'mdi-email-outline'
                                            }}
                                        </v-icon>
                                    </div>

                                    <div class="template-card__body">
                                        {{ templateExcerpt(template.body) }}
                                    </div>

                                    <div
                                        class="d-flex align-center justify-space-between mt-4"
                                    >
                                        <v-chip
                                            size="small"
                                            color="primary"
                                            variant="outlined"
                                        >
                                            {{
                                                formatCategoryLabel(
                                                    template.category,
                                                )
                                            }}
                                        </v-chip>
                                        <span class="template-card__cta">{{
                                            template.cta_label ||
                                            'No CTA button'
                                        }}</span>
                                    </div>
                                </button>
                            </div>

                            <div
                                v-if="form.errors.template_id"
                                class="text-error text-caption mt-3"
                            >
                                {{ form.errors.template_id }}
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card class="composer-panel mb-5" rounded="xl">
                        <v-card-text class="pa-6 pa-md-8">
                            <div class="step-header mb-5">
                                <div>
                                    <div class="step-badge">Step 2</div>
                                    <h2
                                        class="text-h5 font-weight-bold mt-2 mb-1"
                                    >
                                        Define the audience
                                    </h2>
                                    <p
                                        class="text-body-2 text-medium-emphasis mb-0"
                                    >
                                        Send to all active customers, a chosen
                                        subset, or include external recipients.
                                    </p>
                                </div>
                            </div>

                            <div class="audience-toggle mb-5">
                                <button
                                    type="button"
                                    class="audience-choice"
                                    :class="{
                                        'audience-choice--active':
                                            form.recipient_scope === 'all',
                                    }"
                                    @click="form.recipient_scope = 'all'"
                                >
                                    <div class="audience-choice__title">
                                        All active customers
                                    </div>
                                    <div class="audience-choice__meta">
                                        {{ props.counts.all }} reachable
                                        customer accounts
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="audience-choice"
                                    :class="{
                                        'audience-choice--active':
                                            form.recipient_scope === 'selected',
                                    }"
                                    @click="form.recipient_scope = 'selected'"
                                >
                                    <div class="audience-choice__title">
                                        Selected customers
                                    </div>
                                    <div class="audience-choice__meta">
                                        Use a curated list plus optional
                                        external emails
                                    </div>
                                </button>
                            </div>

                            <v-autocomplete
                                v-if="form.recipient_scope === 'selected'"
                                v-model="form.customer_ids"
                                :items="selectedCustomerItems"
                                label="Pick customer accounts"
                                variant="outlined"
                                multiple
                                chips
                                closable-chips
                                prepend-inner-icon="mdi-account-multiple-outline"
                                class="mb-4"
                                :error-messages="form.errors.customer_ids"
                            />

                            <v-textarea
                                v-model="additionalEmailInput"
                                label="Add extra email addresses"
                                hint="Separate addresses with commas or new lines. These recipients do not need customer accounts."
                                persistent-hint
                                variant="outlined"
                                rows="4"
                                prepend-inner-icon="mdi-email-plus-outline"
                                class="mb-4"
                                :error-messages="form.errors.additional_emails"
                            />

                            <div class="audience-summary">
                                <div class="audience-summary__item">
                                    <span class="audience-summary__label"
                                        >Customer recipients</span
                                    >
                                    <strong>{{ selectedCustomerCount }}</strong>
                                </div>
                                <div class="audience-summary__item">
                                    <span class="audience-summary__label"
                                        >External emails</span
                                    >
                                    <strong>{{ additionalEmailCount }}</strong>
                                </div>
                                <div
                                    class="audience-summary__item audience-summary__item--total"
                                >
                                    <span class="audience-summary__label"
                                        >Total send count</span
                                    >
                                    <strong>{{ totalRecipientCount }}</strong>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card class="composer-panel mb-5" rounded="xl">
                        <v-card-text class="pa-6 pa-md-8">
                            <div class="step-header mb-5">
                                <div>
                                    <div class="step-badge">Step 3</div>
                                    <h2
                                        class="text-h5 font-weight-bold mt-2 mb-1"
                                    >
                                        Refine the campaign copy
                                    </h2>
                                    <p
                                        class="text-body-2 text-medium-emphasis mb-0"
                                    >
                                        Adjust the selected template so the
                                        campaign fits the exact message you want
                                        to send.
                                    </p>
                                </div>
                            </div>

                            <v-text-field
                                v-model="form.title"
                                label="Internal campaign title"
                                variant="outlined"
                                prepend-inner-icon="mdi-tag-outline"
                                class="mb-4"
                                :error-messages="form.errors.title"
                            />

                            <v-text-field
                                v-model="form.subject"
                                label="Email subject"
                                variant="outlined"
                                prepend-inner-icon="mdi-format-title"
                                class="mb-4"
                                :error-messages="form.errors.subject"
                            />

                            <v-text-field
                                v-model="form.heading"
                                label="Email heading"
                                variant="outlined"
                                prepend-inner-icon="mdi-format-header-1"
                                class="mb-4"
                                :error-messages="form.errors.heading"
                            />

                            <v-textarea
                                v-model="form.body"
                                label="Email body"
                                variant="outlined"
                                rows="10"
                                auto-grow
                                prepend-inner-icon="mdi-text-box-outline"
                                class="mb-4"
                                :error-messages="form.errors.body"
                            />

                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.cta_label"
                                        label="CTA label"
                                        variant="outlined"
                                        prepend-inner-icon="mdi-gesture-tap-button"
                                        :error-messages="form.errors.cta_label"
                                    />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.cta_url"
                                        label="CTA URL"
                                        variant="outlined"
                                        prepend-inner-icon="mdi-link-variant"
                                        :error-messages="form.errors.cta_url"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <v-card class="composer-panel" rounded="xl">
                        <v-card-text class="pa-6 pa-md-8">
                            <div class="step-header mb-4">
                                <div>
                                    <div class="step-badge">Helper</div>
                                    <h2
                                        class="text-h6 font-weight-bold mt-2 mb-1"
                                    >
                                        Available merge tags
                                    </h2>
                                    <p
                                        class="text-body-2 text-medium-emphasis mb-0"
                                    >
                                        These placeholders are replaced at send
                                        time using customer or email-recipient
                                        data.
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex ga-2 flex-wrap">
                                <v-chip
                                    v-for="placeholder in availablePlaceholders"
                                    :key="placeholder"
                                    size="small"
                                    color="grey-darken-1"
                                    variant="outlined"
                                >
                                    {{ placeholder }}
                                </v-chip>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="5">
                    <div class="preview-stack">
                        <v-card class="preview-shell mb-5" rounded="xl">
                            <v-card-text class="pa-0">
                                <div class="preview-toolbar">
                                    <div class="preview-dots">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                    <div class="preview-toolbar__label">
                                        Live Preview
                                    </div>
                                </div>

                                <div class="preview-envelope">
                                    <div class="preview-envelope__meta">
                                        <div>
                                            <div
                                                class="preview-envelope__small"
                                            >
                                                To
                                            </div>
                                            <div
                                                class="preview-envelope__strong"
                                            >
                                                {{ sampleCustomer.name }} &lt;{{
                                                    sampleCustomer.email
                                                }}&gt;
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="preview-envelope__small"
                                            >
                                                Campaign
                                            </div>
                                            <div
                                                class="preview-envelope__strong"
                                            >
                                                {{
                                                    form.title ||
                                                    'Untitled campaign'
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="preview-envelope__subject">
                                        {{
                                            sampleResolvedSubject ||
                                            'No subject yet'
                                        }}
                                    </div>

                                    <div class="preview-email">
                                        <div class="preview-email__brand">
                                            EaseVerifier
                                        </div>
                                        <h3 class="preview-email__heading">
                                            {{
                                                sampleResolvedHeading ||
                                                'No heading yet'
                                            }}
                                        </h3>
                                        <p class="preview-email__body">
                                            {{
                                                sampleResolvedBody ||
                                                'Your email body preview will appear here.'
                                            }}
                                        </p>

                                        <a
                                            v-if="
                                                form.cta_label && form.cta_url
                                            "
                                            :href="sampleResolvedCtaUrl || '#'"
                                            class="preview-email__cta"
                                        >
                                            {{ sampleResolvedCtaLabel }}
                                        </a>

                                        <div class="preview-email__footer">
                                            Need help? Contact
                                            {{ props.supportEmail }}
                                        </div>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>

                        <v-card class="summary-shell" rounded="xl">
                            <v-card-text class="pa-6">
                                <div class="step-header mb-4">
                                    <div>
                                        <div class="step-badge">
                                            Ready Check
                                        </div>
                                        <h2
                                            class="text-h6 font-weight-bold mt-2 mb-1"
                                        >
                                            Final campaign summary
                                        </h2>
                                        <p
                                            class="text-body-2 text-medium-emphasis mb-0"
                                        >
                                            Confirm the essentials before you
                                            send the message.
                                        </p>
                                    </div>
                                </div>

                                <div class="summary-list mb-5">
                                    <div class="summary-row">
                                        <span>Template</span>
                                        <strong>{{
                                            selectedTemplate?.name || '-'
                                        }}</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Audience</span>
                                        <strong>{{ audienceLabel }}</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Total recipients</span>
                                        <strong>{{
                                            totalRecipientCount
                                        }}</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>CTA</span>
                                        <strong>{{
                                            form.cta_label || 'No CTA button'
                                        }}</strong>
                                    </div>
                                </div>

                                <v-alert
                                    :type="sendDisabled ? 'warning' : 'success'"
                                    variant="tonal"
                                    class="mb-5"
                                >
                                    <template v-if="sendDisabled">
                                        Add at least one valid recipient and
                                        finish the campaign draft before
                                        sending.
                                    </template>
                                    <template v-else>
                                        This campaign is ready to send based on
                                        the current draft and recipient
                                        selection.
                                    </template>
                                </v-alert>

                                <v-btn
                                    color="primary"
                                    size="large"
                                    block
                                    prepend-icon="mdi-send"
                                    :loading="form.processing"
                                    :disabled="sendDisabled"
                                    @click="submit"
                                >
                                    Send Campaign Email
                                </v-btn>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-col>
            </v-row>
        </div>
    </AdminLayout>
</template>

<style scoped>
.campaign-composer {
    --composer-ink: #0f172a;
    --composer-muted: #5b6476;
    --composer-line: rgba(15, 23, 42, 0.08);
    --composer-card: #ffffff;
    --composer-accent: #0b63f6;
    --composer-accent-soft: #eef5ff;
}

.composer-hero {
    background:
        radial-gradient(
            circle at top left,
            rgba(11, 99, 246, 0.18),
            transparent 32%
        ),
        linear-gradient(135deg, #fffefa 0%, #ffffff 52%, #f3f8ff 100%);
    border: 1px solid rgba(11, 99, 246, 0.08);
    border-radius: 28px;
    padding: 32px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
}

.composer-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 8px 12px;
    background: rgba(11, 99, 246, 0.08);
    color: var(--composer-accent);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.composer-hero-copy {
    max-width: 640px;
    color: var(--composer-muted);
}

.composer-metrics {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 12px;
    width: min(360px, 100%);
}

.metric-card {
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 20px;
    padding: 18px 18px 16px;
    backdrop-filter: blur(12px);
}

.metric-label {
    font-size: 12px;
    color: var(--composer-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--composer-ink);
    line-height: 1.3;
}

.metric-subtle {
    font-size: 13px;
    color: var(--composer-muted);
    margin-top: 6px;
}

.composer-panel,
.summary-shell,
.preview-shell {
    border: 1px solid var(--composer-line);
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.05);
}

.step-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.step-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 7px 11px;
    background: var(--composer-accent-soft);
    color: var(--composer-accent);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.template-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.template-card {
    width: 100%;
    text-align: left;
    border: 1px solid var(--composer-line);
    border-radius: 22px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    padding: 18px;
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}

.template-card:hover {
    transform: translateY(-2px);
    border-color: rgba(11, 99, 246, 0.24);
    box-shadow: 0 16px 30px rgba(11, 99, 246, 0.08);
}

.template-card--active {
    border-color: rgba(11, 99, 246, 0.4);
    box-shadow: 0 18px 36px rgba(11, 99, 246, 0.14);
    background: linear-gradient(180deg, #ffffff 0%, #edf5ff 100%);
}

.template-card__top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.template-card__title {
    font-size: 16px;
    font-weight: 700;
    color: var(--composer-ink);
    margin-bottom: 6px;
}

.template-card__subject {
    color: var(--composer-muted);
    font-size: 13px;
    line-height: 1.5;
}

.template-card__body {
    margin-top: 16px;
    color: #344054;
    font-size: 14px;
    line-height: 1.6;
    min-height: 68px;
}

.template-card__cta {
    color: var(--composer-muted);
    font-size: 12px;
}

.audience-toggle {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.audience-choice {
    border: 1px solid var(--composer-line);
    background: #fff;
    border-radius: 20px;
    padding: 18px;
    text-align: left;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
}

.audience-choice--active {
    border-color: rgba(11, 99, 246, 0.36);
    background: #f3f8ff;
    box-shadow: 0 12px 28px rgba(11, 99, 246, 0.08);
}

.audience-choice__title {
    color: var(--composer-ink);
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 6px;
}

.audience-choice__meta {
    color: var(--composer-muted);
    font-size: 13px;
    line-height: 1.5;
}

.audience-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.audience-summary__item {
    border-radius: 18px;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid var(--composer-line);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.audience-summary__item--total {
    background: #eef5ff;
    border-color: rgba(11, 99, 246, 0.16);
}

.audience-summary__label {
    color: var(--composer-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.preview-stack {
    position: sticky;
    top: 24px;
}

.preview-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid var(--composer-line);
    background: #fbfcfe;
}

.preview-dots {
    display: flex;
    gap: 6px;
}

.preview-dots span {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #d0d5dd;
}

.preview-toolbar__label {
    font-size: 12px;
    color: var(--composer-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.preview-envelope {
    padding: 22px;
    background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
}

.preview-envelope__meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.preview-envelope__small {
    font-size: 11px;
    color: var(--composer-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 4px;
}

.preview-envelope__strong {
    font-size: 13px;
    color: var(--composer-ink);
    font-weight: 600;
    line-height: 1.5;
}

.preview-envelope__subject {
    font-size: 20px;
    color: var(--composer-ink);
    font-weight: 700;
    line-height: 1.4;
    margin-bottom: 18px;
}

.preview-email {
    background: white;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 24px;
    padding: 28px 24px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

.preview-email__brand {
    color: var(--composer-accent);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 14px;
}

.preview-email__heading {
    color: var(--composer-ink);
    font-size: 28px;
    line-height: 1.2;
    margin-bottom: 18px;
}

.preview-email__body {
    white-space: pre-line;
    color: #334155;
    font-size: 15px;
    line-height: 1.8;
    margin-bottom: 20px;
}

.preview-email__cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--composer-accent);
    color: white;
    text-decoration: none;
    border-radius: 999px;
    padding: 12px 18px;
    font-weight: 700;
    box-shadow: 0 14px 24px rgba(11, 99, 246, 0.2);
}

.preview-email__footer {
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    font-size: 13px;
    color: var(--composer-muted);
}

.summary-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--composer-line);
    color: var(--composer-muted);
}

.summary-row strong {
    color: var(--composer-ink);
    text-align: right;
}

@media (max-width: 1264px) {
    .preview-stack {
        position: static;
    }
}

@media (max-width: 960px) {
    .composer-hero {
        padding: 24px;
    }

    .template-grid,
    .audience-toggle,
    .audience-summary,
    .preview-envelope__meta {
        grid-template-columns: 1fr;
    }
}
</style>

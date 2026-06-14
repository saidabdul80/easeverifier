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
const showTemplatePicker = ref(false);

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

const recipientSummary = computed(() => {
    if (totalRecipientCount.value === 0) {
        return 'No recipients selected yet';
    }

    if (form.recipient_scope === 'all' && additionalEmailCount.value === 0) {
        return `${props.counts.all} active customers will receive this campaign`;
    }

    return `${selectedCustomerCount.value} customer recipients and ${additionalEmailCount.value} external email${additionalEmailCount.value === 1 ? '' : 's'}`;
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
        if (templateId) {
            showTemplatePicker.value = false;
        }
    },
);

const submit = () => {
    form.post('/admin/campaign-emails');
};
</script>

<template>
    <Head title="New Campaign" />

    <AdminLayout :user="$page.props.auth.user">
        <div class="campaign-composer">
            <!-- Minimal Header -->
            <div class="composer-header">
                <div>
                    <h1 class="composer-title">New campaign</h1>
                </div>
                <div class="composer-actions">
                    <v-btn
                        variant="text"
                        prepend-icon="mdi-arrow-left"
                        href="/admin/campaign-emails"
                    >
                        Cancel
                    </v-btn>
                </div>
            </div>

            <div class="composer-grid">
                <!-- Left Column: Core Content -->
                <div class="composer-main">
                    <!-- Template Selection -->
                    <div class="composer-section template-section !p-3">
                        <div class="section-header">
                            <div class="section-label">Template</div>
                            <button
                                type="button"
                                class="change-template-btn"
                                @click="showTemplatePicker = true"
                            >
                                Change
                            </button>
                        </div>

                        <div class="selected-template-panel">
                            <div class="selected-template-info">
                                <div class="selected-template-icon">
                                    <v-icon
                                        icon="mdi-check-decagram"
                                        size="18"
                                        color="#2563eb"
                                    />
                                </div>
                                <div>
                                    <div class="selected-template-name">
                                        {{
                                            selectedTemplate?.name ||
                                            'Choose a template'
                                        }}
                                    </div>
                                </div>
                            </div>
                            <span class="selected-template-category">{{
                                formatCategoryLabel(selectedTemplate?.category)
                            }}</span>
                        </div>
                    </div>

                    <!-- Audience Section - Moved Above Message -->
                    <div class="composer-section !p-3">
                        <div class="section-header">
                            <div class="section-label">Audience</div>
                            <div class="audience-total-pill">
                                <span>{{ totalRecipientCount }}</span>
                                recipients
                            </div>
                        </div>

                        <div class="audience-tabs">
                            <button
                                type="button"
                                class="audience-tab"
                                :class="{
                                    'audience-tab--active':
                                        form.recipient_scope === 'all',
                                }"
                                @click="form.recipient_scope = 'all'"
                            >
                                All ({{ props.counts.all }})
                            </button>
                            <button
                                type="button"
                                class="audience-tab"
                                :class="{
                                    'audience-tab--active':
                                        form.recipient_scope === 'selected',
                                }"
                                @click="form.recipient_scope = 'selected'"
                            >
                                Select customers
                            </button>
                        </div>

                        <div class="audience-helper">
                            {{ recipientSummary }}
                        </div>

                        <div v-if="form.recipient_scope === 'selected'">
                            <v-autocomplete
                                v-model="form.customer_ids"
                                :items="selectedCustomerItems"
                                label="Customer accounts"
                                variant="outlined"
                                multiple
                                chips
                                class="customer-selector"
                            />
                        </div>

                        <v-textarea
                            v-model="additionalEmailInput"
                            label="Extra email addresses"
                            variant="outlined"
                            rows="3"
                            auto-grow
                            class="external-email-field"
                        />
                    </div>

                    <!-- Message Editor - Clean -->
                    <div class="composer-section !p-3">
                        <div class="section-label">Message</div>
                        <input
                            v-model="form.subject"
                            type="text"
                            placeholder="Subject line"
                            class="composer-input subject-input"
                        />
                        <input
                            v-model="form.heading"
                            type="text"
                            placeholder="Heading (optional)"
                            class="composer-input heading-input"
                        />
                        <textarea
                            v-model="form.body"
                            placeholder="Write your message here..."
                            class="composer-input body-input"
                            rows="6"
                        ></textarea>
                        <div class="cta-row">
                            <input
                                v-model="form.cta_label"
                                type="text"
                                placeholder="Button label"
                                class="composer-input cta-input"
                            />
                            <input
                                v-model="form.cta_url"
                                type="text"
                                placeholder="Button URL"
                                class="composer-input cta-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Preview -->
                <div class="composer-preview">
                    <div class="preview-card">
                        <div class="preview-header">
                            <span class="preview-badge">Live preview</span>
                        </div>

                        <div class="preview-content">
                            <div class="preview-subject">
                                {{ sampleResolvedSubject || 'Subject' }}
                            </div>
                            <div class="preview-heading">
                                {{ sampleResolvedHeading || 'Hello' }}
                            </div>
                            <div class="preview-body">
                                {{ sampleResolvedBody || 'Your message here.' }}
                            </div>
                            <a
                                v-if="form.cta_label && form.cta_url"
                                :href="sampleResolvedCtaUrl || '#'"
                                class="preview-button"
                            >
                                {{ sampleResolvedCtaLabel }}
                            </a>
                        </div>

                        <div class="send-summary">
                            <div>
                                <div class="send-summary__count">
                                    {{ totalRecipientCount }} recipients
                                </div>
                                <div class="send-summary__meta">
                                    {{ recipientSummary }}
                                </div>
                            </div>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-send"
                                :loading="form.processing"
                                :disabled="sendDisabled"
                                @click="submit"
                            >
                                Send campaign
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>

            <v-dialog v-model="showTemplatePicker" max-width="760">
                <v-card rounded="xl">
                    <v-card-text class="pa-4 pa-md-5">
                        <div class="section-header">
                            <div class="section-label">Choose template</div>
                            <v-btn
                                icon="mdi-close"
                                size="small"
                                variant="text"
                                @click="showTemplatePicker = false"
                            />
                        </div>

                        <div class="template-categories">
                            <button
                                v-for="category in templateCategories"
                                :key="category"
                                class="category-chip"
                                :class="{
                                    'category-chip--active':
                                        browseCategory === category,
                                }"
                                @click="browseCategory = category"
                            >
                                {{
                                    category === 'all'
                                        ? 'All'
                                        : formatCategoryLabel(category)
                                }}
                            </button>
                        </div>

                        <div class="template-list template-list--dialog">
                            <button
                                v-for="template in visibleTemplates"
                                :key="template.id"
                                type="button"
                                class="template-row"
                                :class="{
                                    'template-row--active':
                                        form.template_id === template.id,
                                }"
                                @click="
                                    form.template_id = template.id;
                                    showTemplatePicker = false;
                                "
                            >
                                <div class="template-row__main">
                                    <div class="template-row__title">
                                        {{ template.name }}
                                    </div>
                                </div>
                                <div class="template-row__meta">
                                    <span class="template-row__category">{{
                                        formatCategoryLabel(template.category)
                                    }}</span>
                                    <v-icon
                                        :icon="
                                            form.template_id === template.id
                                                ? 'mdi-check-circle'
                                                : 'mdi-chevron-right'
                                        "
                                        size="18"
                                        :color="
                                            form.template_id === template.id
                                                ? '#2563eb'
                                                : '#94a3b8'
                                        "
                                    />
                                </div>
                            </button>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>
        </div>
    </AdminLayout>
</template>

<style scoped>
.campaign-composer {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

/* Header */
.composer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.composer-title {
    font-size: 28px;
    font-weight: 600;
    color: #111827;
    margin: 0;
    letter-spacing: -0.01em;
}

.composer-actions {
    display: flex;
    gap: 12px;
}

/* Grid Layout */
.composer-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
}

.composer-main {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

/* Sections */
.composer-section {
    background: #ffffff;
    border-radius: 20px;
    padding: 4px 0;
}

.section-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #9ca3af;
    margin-bottom: 6px;
}

/* Template Section */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.selected-template-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%);
    border-radius: 16px;
    border: 1px solid #dbeafe;
}

.selected-template-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.selected-template-icon {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #dbeafe;
}

.selected-template-name {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}

.selected-template-category {
    flex-shrink: 0;
    font-size: 11px;
    color: #1d4ed8;
    background: #ffffff;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
}

.change-template-btn {
    font-size: 12px;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 20px;
}

.change-template-btn:hover {
    background: #dbeafe;
}

.template-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 4px;
}

.template-list--dialog {
    max-height: 420px;
    margin-top: 8px;
}

.template-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
}

.template-row:hover {
    border-color: #93c5fd;
    background: #f8fbff;
}

.template-row--active {
    border-color: #60a5fa;
    background: #eff6ff;
}

.template-row__main {
    min-width: 0;
}

.template-row__title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.template-row__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.template-row__category {
    font-size: 11px;
    color: #6b7280;
}

.template-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}

.category-chip {
    padding: 4px 12px;
    border-radius: 20px;
    border: none;
    background: transparent;
    font-size: 12px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.1s ease;
}

.category-chip:hover {
    color: #111827;
}

.category-chip--active {
    background: #f3f4f6;
    color: #111827;
    font-weight: 500;
}

/* Inputs */
.composer-input {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.15s ease;
    margin-bottom: 12px;
}

.composer-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.composer-input::placeholder {
    color: #9ca3af;
}

.subject-input {
    font-size: 16px;
    font-weight: 500;
}

.heading-input {
    font-size: 15px;
}

.body-input {
    resize: vertical;
    line-height: 1.5;
}

.cta-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.cta-input {
    margin-bottom: 0;
}

.email-input {
    margin-top: 8px;
    margin-bottom: 0;
}

/* Audience Tabs */
.audience-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    background: #f9fafb;
    padding: 4px;
    border-radius: 48px;
    width: fit-content;
}

.audience-tab {
    padding: 8px 20px;
    border-radius: 40px;
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.1s ease;
}

.audience-tab--active {
    background: #ffffff;
    color: #111827;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.customer-selector {
    margin-bottom: 10px;
}

.audience-helper {
    margin-bottom: 10px;
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
}

.audience-total-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 500;
}

.audience-total-pill span {
    font-size: 15px;
    font-weight: 700;
}

.external-email-field {
    margin-top: 6px;
}

/* Preview Card */
.composer-preview {
    position: sticky;
    top: 24px;
    height: fit-content;
}

.preview-card {
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #f0f0f0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.preview-header {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f0;
    background: #fafbfc;
}

.preview-badge {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
}

.preview-content {
    padding: 24px;
}

.preview-subject {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 12px;
    line-height: 1.3;
}

.preview-heading {
    font-size: 24px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 16px;
    line-height: 1.2;
}

.preview-body {
    font-size: 14px;
    line-height: 1.6;
    color: #4b5563;
    margin-bottom: 24px;
}

.preview-button {
    display: inline-block;
    background: #111827;
    color: white;
    padding: 10px 20px;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s ease;
}

.preview-button:hover {
    background: #1f2937;
}

.send-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 18px 24px 22px;
    border-top: 1px solid #f0f0f0;
    background: #ffffff;
}

.send-summary__count {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}

.send-summary__meta {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

/* Responsive */
@media (max-width: 900px) {
    .composer-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .composer-preview {
        position: static;
    }

    .composer-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .composer-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .send-summary {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 640px) {
    .campaign-composer {
        padding: 16px;
    }

    .cta-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .cta-input {
        margin-bottom: 0;
    }

    .preview-content {
        padding: 18px;
    }

    .preview-heading {
        font-size: 20px;
    }

    .audience-summary-cards {
        grid-template-columns: 1fr;
    }

    .selected-template-panel {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script setup lang="ts">
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Field = {
    key: string;
    label: string;
    type: string;
    required: boolean;
    options?: string[];
};
type Section = { title: string; fields: Field[] };
type Document = { name: string; label: string; url: string };
const props = defineProps<{
    status: string;
    approvedAt: string | null;
    accountType: string;
    sections: Section[];
    documentFields: { key: string; label: string; required: boolean }[];
    defaults: Record<string, string | null>;
    submission: {
        id: number;
        questions: Section[];
        answers: Record<string, string | boolean | null>;
        documents: Record<string, Document>;
        submitted_at: string;
        rejection_reason: string | null;
    } | null;
}>();

const editable = computed(() =>
    ['not_submitted', 'rejected'].includes(props.status),
);
const displaySections = computed(() =>
    editable.value
        ? props.sections
        : props.submission?.questions || props.sections,
);
const statusLabel = computed(
    () =>
        ({
            not_submitted: 'Not submitted',
            pending: 'Awaiting review',
            approved: 'Approved',
            rejected: 'Changes required',
        })[props.status] || props.status,
);
const statusColor = computed(
    () =>
        ({
            not_submitted: 'grey',
            pending: 'warning',
            approved: 'success',
            rejected: 'error',
        })[props.status] || 'grey',
);
const initialAnswers: Record<string, string | boolean | null> = {};
displaySections.value.forEach((section) =>
    section.fields.forEach((field) => {
        initialAnswers[field.key] =
            props.submission?.answers[field.key] ??
            props.defaults[field.key] ??
            (field.type === 'boolean' ? null : '');
    }),
);
const form = useForm({
    answers: initialAnswers,
    documents: Object.fromEntries(
        props.documentFields.map((field) => [field.key, null]),
    ) as Record<string, File | null>,
    declaration: false,
});
const errorFor = (key: string) => (form.errors as Record<string, string>)[key];
const textAnswer = (key: string): string => {
    const value = form.answers[key];
    return typeof value === 'string' ? value : '';
};
const documentRequired = (field: { key: string; required: boolean }) =>
    field.required ||
    (field.key === 'operating_license' &&
        form.answers.requires_license === true);
const submit = () => {
    if (!editable.value || form.processing) return;
    form.post('/customer/kyc', { forceFormData: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Customer KYC - EaseVerifier" />
    <CustomerLayout
        :user="$page.props.auth.user"
        :wallet="$page.props.auth.wallet"
    >
        <div class="kyc-heading">
            <div>
                <h1>Customer KYC</h1>
                <p class="text-body-2 text-grey">
                    {{ accountType === 'business' ? 'Business' : 'Individual' }}
                    account verification
                </p>
            </div>
            <v-chip :color="statusColor" variant="tonal" size="small">{{
                statusLabel
            }}</v-chip>
        </div>
        <v-alert
            v-if="status === 'pending'"
            type="info"
            variant="tonal"
            class="mb-5"
            >Submitted
            {{
                submission
                    ? new Date(submission.submitted_at).toLocaleString()
                    : ''
            }}. Your API access will unlock after admin approval.</v-alert
        >
        <v-alert
            v-if="status === 'approved'"
            type="success"
            variant="tonal"
            class="mb-5"
            >KYC approved
            {{ approvedAt ? new Date(approvedAt).toLocaleString() : '' }}. Your
            account is cleared for API access.</v-alert
        >
        <v-alert
            v-if="status === 'rejected'"
            type="error"
            variant="tonal"
            class="mb-5"
            ><strong>Review feedback:</strong>
            {{ submission?.rejection_reason }}</v-alert
        >
        <v-alert
            v-if="errorFor('submission')"
            type="error"
            variant="tonal"
            class="mb-5"
            >{{ errorFor('submission') }}</v-alert
        >

        <v-form class="kyc-form" @submit.prevent="submit">
            <section
                v-for="section in displaySections"
                :key="section.title"
                class="kyc-section"
            >
                <h2>{{ section.title }}</h2>
                <div class="kyc-fields">
                    <div
                        v-for="field in section.fields"
                        :key="field.key"
                        class="kyc-field"
                    >
                        <template v-if="field.type === 'boolean'">
                            <p
                                :id="`label-${field.key}`"
                                class="question-label"
                            >
                                {{ field.label }}
                                <span
                                    v-if="field.required && editable"
                                    class="text-error"
                                    >*</span
                                >
                            </p>
                            <v-radio-group
                                v-model="form.answers[field.key]"
                                :aria-labelledby="`label-${field.key}`"
                                inline
                                :disabled="!editable"
                                :error-messages="
                                    errorFor(`answers.${field.key}`)
                                "
                                density="compact"
                                hide-details="auto"
                            >
                                <v-radio label="Yes" :value="true" /><v-radio
                                    label="No"
                                    :value="false"
                                />
                            </v-radio-group>
                        </template>
                        <v-select
                            v-else-if="field.type === 'select'"
                            :model-value="textAnswer(field.key)"
                            @update:model-value="
                                form.answers[field.key] = $event
                            "
                            :label="field.label + (field.required ? ' *' : '')"
                            :items="field.options"
                            :readonly="!editable"
                            :error-messages="errorFor(`answers.${field.key}`)"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                        />
                        <v-textarea
                            v-else-if="field.type === 'textarea'"
                            :model-value="textAnswer(field.key)"
                            @update:model-value="
                                form.answers[field.key] = $event
                            "
                            :label="field.label + (field.required ? ' *' : '')"
                            :readonly="!editable"
                            :error-messages="errorFor(`answers.${field.key}`)"
                            variant="outlined"
                            rows="2"
                            auto-grow
                            hide-details="auto"
                        />
                        <v-text-field
                            v-else
                            v-model="form.answers[field.key]"
                            :label="field.label + (field.required ? ' *' : '')"
                            :type="field.type"
                            :readonly="!editable"
                            :error-messages="errorFor(`answers.${field.key}`)"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                        />
                    </div>
                </div>
            </section>

            <section class="kyc-section">
                <h2>Documents</h2>
                <div class="kyc-fields">
                    <div v-for="field in documentFields" :key="field.key">
                        <v-file-input
                            v-if="editable"
                            :model-value="form.documents[field.key]"
                            @update:model-value="
                                form.documents[field.key] = Array.isArray(
                                    $event,
                                )
                                    ? $event[0] || null
                                    : $event
                            "
                            :label="
                                field.label +
                                (documentRequired(field) ? ' *' : '')
                            "
                            accept="application/pdf,image/jpeg,image/png"
                            :error-messages="errorFor(`documents.${field.key}`)"
                            variant="outlined"
                            density="comfortable"
                            prepend-icon=""
                            prepend-inner-icon="mdi-paperclip"
                            show-size
                            hide-details="auto"
                        />
                        <p v-else class="text-body-2 font-weight-medium">
                            {{ field.label }}
                        </p>
                        <v-btn
                            v-if="submission?.documents[field.key]"
                            :href="submission.documents[field.key].url"
                            variant="text"
                            size="small"
                            prepend-icon="mdi-download"
                            class="document-download"
                            :title="submission.documents[field.key].name"
                            >{{ submission.documents[field.key].name }}</v-btn
                        >
                        <span
                            v-else-if="!editable"
                            class="text-body-2 text-grey"
                            >Not provided</span
                        >
                    </div>
                </div>
            </section>
            <div v-if="editable" class="kyc-submit">
                <v-checkbox
                    v-model="form.declaration"
                    :error-messages="errorFor('declaration')"
                    hide-details="auto"
                >
                    <template #label
                        ><span class="text-body-2"
                            >I confirm these details are true and correct and
                            agree to the
                            <a
                                href="/terms"
                                target="_blank"
                                rel="noopener"
                                @click.stop
                                >Terms of Service</a
                            >
                            and
                            <a
                                href="/privacy"
                                target="_blank"
                                rel="noopener"
                                @click.stop
                                >Privacy Policy</a
                            >.</span
                        ></template
                    >
                </v-checkbox>
                <v-progress-linear
                    v-if="form.progress"
                    :model-value="form.progress.percentage"
                    color="primary"
                    class="mt-3"
                    aria-label="Upload progress"
                />
                <v-btn
                    type="submit"
                    color="primary"
                    :loading="form.processing"
                    :disabled="form.processing || !form.declaration"
                    prepend-icon="mdi-send-outline"
                    class="mt-4"
                    >{{
                        status === 'rejected' ? 'Resubmit KYC' : 'Submit KYC'
                    }}</v-btn
                >
            </div>
        </v-form>
    </CustomerLayout>
</template>

<style scoped>
.kyc-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}
.kyc-heading h1 {
    font-size: 1.5rem;
    margin: 0 0 4px;
}
.kyc-form {
    background: #fff;
    padding: 0 24px;
    border-top: 1px solid #dde3e8;
}
.kyc-section {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 24px;
    padding: 28px 0;
    border-bottom: 1px solid #e4e8ec;
}
.kyc-section h2 {
    font-size: 0.95rem;
    margin: 0;
    color: #344054;
}
.kyc-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}
.kyc-field {
    min-width: 0;
}
.question-label {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.5;
    margin: 0 0 8px;
}
.kyc-submit {
    padding: 20px 0 28px;
}
.document-download {
    max-width: 100%;
    height: auto;
    min-height: 32px;
}
.document-download :deep(.v-btn__content) {
    white-space: normal;
    overflow-wrap: anywhere;
    text-align: left;
}
a {
    color: #14673a;
}
@media (max-width: 960px) {
    .kyc-section {
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
    }
}
@media (max-width: 600px) {
    .kyc-form {
        padding: 0 16px;
    }
    .kyc-fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
    }
    .kyc-heading {
        align-items: flex-start;
    }
    .kyc-heading h1 {
        font-size: 1.25rem;
    }
}
</style>

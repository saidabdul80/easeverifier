<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed } from 'vue';

interface AuthType {
    value: string;
    label: string;
}

const props = defineProps<{
    provider: any;
    service: any;
    authTypes: AuthType[];
    httpMethods: string[];
}>();

// Convert objects to arrays for editing
const objectToArray = (obj: Record<string, string> | null) => {
    if (!obj) return [];
    return Object.entries(obj).map(([key, value]) => ({ key, value }));
};

const form = useForm({
    name: props.provider.name,
    base_url: props.provider.base_url,
    endpoint: props.provider.endpoint,
    http_method: props.provider.http_method,
    auth_type: props.provider.auth_type,
    auth_config: props.provider.auth_config || {},
    request_headers: objectToArray(props.provider.request_headers),
    request_body_template: objectToArray(props.provider.request_body_template),
    response_mapping: objectToArray(props.provider.response_mapping),
    timeout: props.provider.timeout,
    priority: props.provider.priority,
    is_active: props.provider.is_active,
    environment: props.provider.environment || 'live',
});

const environments = [
    { value: 'live', label: 'Live (Production)', color: 'success' },
    { value: 'test', label: 'Test (Sandbox)', color: 'warning' },
];

// Dynamic auth config fields based on auth_type
const authConfigFields = computed(() => {
    switch (form.auth_type) {
        case 'bearer':
            return [{ key: 'token', label: 'Bearer Token', type: 'password', hint: 'The bearer token for Authorization header' }];
        case 'api_key_header':
            return [
                { key: 'header_name', label: 'Header Name', type: 'text', hint: 'e.g., X-API-Key, Authorization' },
                { key: 'header_value', label: 'Header Value', type: 'password', hint: 'The API key value' },
            ];
        case 'api_key_body':
            return [
                { key: 'key_name', label: 'Key Name', type: 'text', hint: 'e.g., api_key, apiKey' },
                { key: 'key_value', label: 'Key Value', type: 'password', hint: 'The API key value' },
            ];
        case 'basic':
            return [
                { key: 'username', label: 'Username', type: 'text', hint: 'Basic auth username' },
                { key: 'password', label: 'Password', type: 'password', hint: 'Basic auth password' },
            ];
        case 'custom':
            return [{ key: 'custom_headers', label: 'Custom Headers (JSON)', type: 'textarea', hint: '{"Header-Name": "value"}' }];
        default:
            return [];
    }
});

const addHeader = () => form.request_headers.push({ key: '', value: '' });
const removeHeader = (index: number) => form.request_headers.splice(index, 1);

const addBodyParam = () => form.request_body_template.push({ key: '', value: '' });
const removeBodyParam = (index: number) => form.request_body_template.splice(index, 1);

const addResponseMapping = () => form.response_mapping.push({ key: '', value: '' });
const removeResponseMapping = (index: number) => form.response_mapping.splice(index, 1);

const transformArrayToObject = (arr: { key: string; value: string }[]) => {
    return arr.reduce((acc, item) => {
        if (item.key) acc[item.key] = item.value;
        return acc;
    }, {} as Record<string, string>);
};

const submit = () => {
    const data = {
        ...form.data(),
        request_headers: transformArrayToObject(form.request_headers),
        request_body_template: transformArrayToObject(form.request_body_template),
        response_mapping: transformArrayToObject(form.response_mapping),
    };
    router.put(`/admin/providers/${props.provider.id}`, data);
};

const deleteProvider = () => {
    if (confirm('Are you sure you want to delete this provider?')) {
        router.delete(`/admin/providers/${props.provider.id}`);
    }
};
</script>

<template>
    <Head :title="`Edit Provider - ${provider.name}`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <v-btn variant="text" prepend-icon="mdi-arrow-left" :href="`/admin/services/${service.id}`" class="mb-2">Back to {{ service.name }}</v-btn>
                <h1 class="text-h4 font-weight-bold mb-1">Edit Provider</h1>
                <p class="text-body-2 text-grey">{{ provider.name }} for {{ service.name }}</p>
            </div>
            <v-spacer />
            <v-btn color="error" variant="outlined" prepend-icon="mdi-delete" @click="deleteProvider">Delete</v-btn>
        </div>

        <v-form @submit.prevent="submit">
            <v-row>
                <!-- Basic Info -->
                <v-col cols="12" md="6">
                    <v-card class="mb-4">
                        <v-card-title><v-icon class="mr-2">mdi-information</v-icon>Basic Information</v-card-title>
                        <v-card-text>
                            <v-text-field v-model="form.name" label="Provider Name *" variant="outlined" :error-messages="form.errors.name" class="mb-4" />
                            <v-text-field v-model="form.base_url" label="Base URL *" variant="outlined" :error-messages="form.errors.base_url" class="mb-4" />
                            <v-text-field v-model="form.endpoint" label="Endpoint *" variant="outlined" hint="Use {{search_parameter}} for dynamic values" :error-messages="form.errors.endpoint" class="mb-4" />
                            <v-select v-model="form.http_method" :items="httpMethods" label="HTTP Method" variant="outlined" class="mb-4" />
                            <v-row>
                                <v-col cols="6"><v-text-field v-model.number="form.timeout" label="Timeout (seconds)" type="number" variant="outlined" /></v-col>
                                <v-col cols="6"><v-text-field v-model.number="form.priority" label="Priority" type="number" variant="outlined" hint="Lower = higher priority" /></v-col>
                            </v-row>
                            <v-select v-model="form.environment" :items="environments" item-title="label" item-value="value" label="Environment *" variant="outlined" class="mb-4">
                                <template #item="{ item, props }">
                                    <v-list-item v-bind="props">
                                        <template #prepend>
                                            <v-icon :color="item.raw.color">{{ item.raw.value === 'live' ? 'mdi-rocket-launch' : 'mdi-flask' }}</v-icon>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-select>
                            <v-alert v-if="form.environment === 'test'" type="warning" variant="tonal" density="compact" class="mb-4">
                                <strong>Test Mode:</strong> API calls to this provider will return mock data and won't charge customers using test API keys.
                            </v-alert>
                            <v-switch v-model="form.is_active" label="Active" color="primary" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Authentication -->
                <v-col cols="12" md="6">
                    <v-card class="mb-4">
                        <v-card-title><v-icon class="mr-2">mdi-shield-key</v-icon>Authentication</v-card-title>
                        <v-card-text>
                            <v-select v-model="form.auth_type" :items="authTypes" item-title="label" item-value="value" label="Authentication Type" variant="outlined" class="mb-4" />
                            <template v-for="field in authConfigFields" :key="field.key">
                                <v-textarea v-if="field.type === 'textarea'" v-model="form.auth_config[field.key]" :label="field.label" :hint="field.hint" variant="outlined" rows="3" class="mb-4" />
                                <v-text-field v-else v-model="form.auth_config[field.key]" :label="field.label" :type="field.type" :hint="field.hint" variant="outlined" persistent-hint class="mb-4" />
                            </template>
                            <v-alert v-if="form.auth_type === 'none'" type="info" variant="tonal" density="compact">No authentication will be used.</v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <!-- Request Headers -->
                <v-col cols="12" md="6">
                    <v-card class="mb-4">
                        <v-card-title class="d-flex align-center">
                            <v-icon class="mr-2">mdi-code-tags</v-icon>Request Headers
                            <v-spacer />
                            <v-btn size="small" variant="tonal" @click="addHeader">+ Add Header</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <div v-for="(header, index) in form.request_headers" :key="index" class="d-flex ga-2 mb-2">
                                <v-text-field v-model="header.key" label="Header Name" variant="outlined" density="compact" hide-details placeholder="Content-Type" />
                                <v-text-field v-model="header.value" label="Value" variant="outlined" density="compact" hide-details placeholder="application/json" />
                                <v-btn icon size="small" color="error" variant="text" @click="removeHeader(index)"><v-icon>mdi-close</v-icon></v-btn>
                            </div>
                            <v-alert v-if="!form.request_headers.length" type="info" variant="tonal" density="compact">No custom headers configured.</v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Request Body Template -->
                <v-col cols="12" md="6">
                    <v-card class="mb-4">
                        <v-card-title class="d-flex align-center">
                            <v-icon class="mr-2">mdi-code-braces</v-icon>Request Body Template
                            <v-spacer />
                            <v-btn size="small" variant="tonal" @click="addBodyParam">+ Add Parameter</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <div v-for="(param, index) in form.request_body_template" :key="index" class="d-flex ga-2 mb-2">
                                <v-text-field v-model="param.key" label="Key" variant="outlined" density="compact" hide-details placeholder="nin" />
                                <v-text-field v-model="param.value" label="Value" variant="outlined" density="compact" hide-details placeholder="{{search_parameter}}" />
                                <v-btn icon size="small" color="error" variant="text" @click="removeBodyParam(index)"><v-icon>mdi-close</v-icon></v-btn>
                            </div>
                            <v-alert type="info" variant="tonal" density="compact" class="mt-2">
                                Use <code v-pre>{{search_parameter}}</code> to insert the verification value.
                            </v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <!-- Response Mapping -->
                <v-col cols="12">
                    <v-card class="mb-4">
                        <v-card-title class="d-flex align-center">
                            <v-icon class="mr-2">mdi-swap-horizontal</v-icon>Response Mapping
                            <v-spacer />
                            <v-btn size="small" variant="tonal" @click="addResponseMapping">+ Add Mapping</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <p class="text-body-2 text-grey mb-4">Map the API response fields to standard field names. Use dot notation for nested values.</p>
                            <v-row v-for="(mapping, index) in form.response_mapping" :key="index" class="mb-2">
                                <v-col cols="5">
                                    <v-text-field v-model="mapping.key" label="Our Field" variant="outlined" density="compact" hide-details placeholder="first_name" />
                                </v-col>
                                <v-col cols="1" class="d-flex align-center justify-center"><v-icon>mdi-arrow-left</v-icon></v-col>
                                <v-col cols="5">
                                    <v-text-field v-model="mapping.value" label="API Response Path" variant="outlined" density="compact" hide-details placeholder="data.firstName" />
                                </v-col>
                                <v-col cols="1" class="d-flex align-center">
                                    <v-btn icon size="small" color="error" variant="text" @click="removeResponseMapping(index)"><v-icon>mdi-close</v-icon></v-btn>
                                </v-col>
                            </v-row>
                            <v-alert v-if="!form.response_mapping.length" type="info" variant="tonal" density="compact">No response mappings configured.</v-alert>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Submit -->
            <div class="d-flex justify-end ga-2">
                <v-btn variant="outlined" :href="`/admin/services/${service.id}`">Cancel</v-btn>
                <v-btn color="primary" type="submit" :loading="form.processing" prepend-icon="mdi-content-save">Update Provider</v-btn>
            </div>
        </v-form>
    </AdminLayout>
</template>


<script setup lang="ts">
import FoldableCode from '@/components/FoldableCode.vue';
import {
    getLibraryExample,
    libraryExampleCode,
    libraryExamples,
} from '@/lib/resultLibraryExamples';
import { run as runLibrary } from '@/routes/documentation/libraries';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ResultEvaluator } from '../../libraries/easeverifier-result-evaluator.mjs';

const props = defineProps<{
    language: 'php' | 'javascript';
    exampleId?: string;
}>();
const emit = defineEmits<{
    'update:language': [value: 'php' | 'javascript'];
}>();
const selected = ref('ready');
const tab = ref('code');
const portal = ref('');
const result = ref('');
const adapter = ref('');
const outcome = ref<any>(null);
const error = ref('');
const busy = ref(false);
const copied = ref(false);
let request: AbortController | undefined;
let generation = 0;
const example = computed(
    () => libraryExamples.find((item) => item.id === selected.value)!,
);
const fields = computed(() => ({
    portal: portal.value,
    result: result.value,
    adapter: adapter.value,
}));
const inputValue = computed({
    get: () => fields.value[tab.value as keyof typeof fields.value] ?? '',
    set: (value: string) => {
        if (tab.value === 'portal') portal.value = value;
        if (tab.value === 'result') result.value = value;
        if (tab.value === 'adapter') adapter.value = value;
    },
});

function inputs(): { portal: any; result: any; adapter: any } {
    return Object.fromEntries(
        Object.entries(fields.value).map(([key, json]) => {
            if (json.length > 20000)
                throw new Error(
                    `${key}: keep the input below 20,000 characters.`,
                );
            let value;
            try {
                value = JSON.parse(json);
            } catch {
                throw new Error(
                    `${key}: use valid JSON. Check quotes, commas, and brackets.`,
                );
            }
            if (!value || typeof value !== 'object' || Array.isArray(value))
                throw new Error(`${key}: use a JSON object.`);
            return [key, value];
        }),
    ) as { portal: any; result: any; adapter: any };
}

const code = computed(() => {
    try {
        const data = inputs();
        return libraryExampleCode(
            props.language,
            data.portal,
            data.result,
            data.adapter,
        );
    } catch {
        return '// Fix the JSON inputs to generate the runnable example.';
    }
});

function reset(): void {
    generation++;
    request?.abort();
    busy.value = false;
    outcome.value = null;
    error.value = '';
    copied.value = false;
    const data = getLibraryExample(selected.value);
    portal.value = JSON.stringify(data.portal, null, 2);
    result.value = JSON.stringify(data.result, null, 2);
    adapter.value = JSON.stringify(data.adapter, null, 2);
    tab.value = 'code';
}

function loadExample(id: string): void {
    selected.value = id;
    reset();
}
defineExpose({ loadExample });

watch(selected, reset, { immediate: true, flush: 'sync' });
watch(
    () => props.exampleId,
    (id) => {
        if (id) loadExample(id);
    },
    { immediate: true },
);
watch(
    [portal, result, adapter, () => props.language],
    () => {
        generation++;
        request?.abort();
        busy.value = false;
        outcome.value = null;
        error.value = '';
        copied.value = false;
    },
    { flush: 'sync' },
);
onBeforeUnmount(() => {
    generation++;
    request?.abort();
});

async function run(): Promise<void> {
    const current = ++generation;
    request?.abort();
    error.value = '';
    outcome.value = null;
    busy.value = true;
    const controller = new AbortController();
    request = controller;
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
        const data = inputs();
        if (props.language === 'javascript') {
            outcome.value = ResultEvaluator.evaluate(
                data.portal,
                data.result,
                data.adapter,
            );
        } else {
            const csrf = document.cookie
                .split('; ')
                .find((part) => part.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);
            const response = await fetch(runLibrary.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) }
                        : {}),
                },
                body: JSON.stringify(fields.value),
                signal: controller.signal,
            });
            if (current !== generation) return;
            if (response.status === 419)
                throw new Error(
                    'Your documentation session expired. Refresh this page and run the example again.',
                );
            if (response.status === 429)
                throw new Error(
                    'Too many PHP runs. Wait a minute or try JavaScript.',
                );
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(
                    Object.entries(payload.errors ?? {})
                        .map(
                            ([key, messages]) =>
                                `${key}: ${(messages as string[]).join(' ')}`,
                        )
                        .join(' ') ||
                        'The PHP example could not run. Try again.',
                );
            }
            const payload = await response.json();
            if (current === generation) outcome.value = payload.outcome;
        }
    } catch (caught) {
        if (current !== generation) return;
        error.value = controller.signal.aborted
            ? 'The run timed out. Try again.'
            : caught instanceof Error
              ? caught.message
              : 'Unable to run this example.';
    } finally {
        clearTimeout(timeout);
        if (current === generation) busy.value = false;
    }
}

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(code.value);
        copied.value = true;
    } catch {
        error.value =
            'Copy is unavailable in this browser. Expand the code and select it to copy.';
    }
}
</script>

<template>
    <div class="library-playground">
        <div class="playground-heading">
            <div>
                <span class="playground-eyebrow">INTERACTIVE EXAMPLE</span>
                <h3>Try it yourself</h3>
            </div>
            <div class="runtime-switch" aria-label="Example language">
                <button
                    v-for="runtime in ['javascript', 'php'] as const"
                    :key="runtime"
                    type="button"
                    :aria-pressed="language === runtime"
                    @click="emit('update:language', runtime)"
                >
                    {{ runtime === 'php' ? 'PHP' : 'JavaScript' }}
                </button>
            </div>
        </div>
        <label v-if="!exampleId" class="scenario-label"
            >Choose a student scenario
            <select v-model="selected" aria-label="Student scenario">
                <option
                    v-for="item in libraryExamples"
                    :key="item.id"
                    :value="item.id"
                >
                    {{ item.title }}
                </option>
            </select>
        </label>
        <p class="scenario-description">{{ example.description }}</p>
        <div class="playground-panes">
            <div class="input-pane">
                <div
                    class="input-tabs"
                    role="tablist"
                    aria-label="Example files"
                >
                    <button
                        v-for="[value, title] in [
                            ['code', 'Code'],
                            ['portal', 'Portal data'],
                            ['result', 'Verified result'],
                            ['adapter', 'Field mapping'],
                        ]"
                        :key="value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === value"
                        @click="tab = value"
                    >
                        {{ title }}
                    </button>
                </div>
                <FoldableCode
                    v-if="tab === 'code'"
                    :code="code"
                    :label="language === 'php' ? 'example.php' : 'example.mjs'"
                />
                <textarea
                    v-else
                    v-model="inputValue"
                    :aria-label="`Edit ${tab} JSON`"
                    spellcheck="false"
                    class="json-editor"
                ></textarea>
                <div class="run-toolbar">
                    <span>Edit the JSON inputs, then run.</span>
                    <div>
                        <button type="button" class="copy-button" @click="copy">
                            {{ copied ? 'Copied!' : 'Copy code' }}</button
                        ><button
                            type="button"
                            class="run-button"
                            :disabled="busy"
                            @click="run"
                        >
                            {{ busy ? 'Running…' : '▶ Run' }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="output-pane" aria-live="polite" :aria-busy="busy">
                <div class="output-heading">
                    <span>OUTPUT</span
                    ><span>{{
                        language === 'php'
                            ? 'PHP · server'
                            : 'JavaScript · browser'
                    }}</span>
                </div>
                <p v-if="error" class="playground-error" role="alert">
                    {{ error }}
                </p>
                <template v-else-if="outcome">
                    <div
                        :class="[
                            'outcome-summary',
                            { passed: outcome.can_continue },
                        ]"
                    >
                        <span class="outcome-status">{{ outcome.status }}</span>
                        <h4>{{ outcome.message }}</h4>
                        <p>{{ outcome.next_action }}</p>
                        <div class="outcome-flags">
                            <span
                                >Auto-fill:
                                <b>{{
                                    outcome.can_autofill ? 'Yes' : 'No'
                                }}</b></span
                            ><span
                                >Continue:
                                <b>{{
                                    outcome.can_continue ? 'Yes' : 'No'
                                }}</b></span
                            >
                        </div>
                    </div>
                    <FoldableCode
                        :code="JSON.stringify(outcome, null, 2)"
                        label="Full returned object"
                    />
                </template>
                <div v-else class="output-placeholder">
                    <span aria-hidden="true">⌘</span>
                    <h4>
                        {{
                            busy
                                ? 'Running your example…'
                                : 'Your result appears here'
                        }}
                    </h4>
                    <p>
                        Click Run to see the student message, checks, and full
                        returned object.
                    </p>
                </div>
            </div>
        </div>
        <div class="playground-footer">
            <button type="button" @click="reset">Reset example</button
            ><span>Fictional data · no payment or verification charge</span>
        </div>
    </div>
</template>

<style scoped>
.library-playground {
    container-type: inline-size;
    border: 1px solid var(--guide-border, #e2e2e3);
    border-radius: 12px;
    overflow: hidden;
    background: var(--guide-bg, #fff);
}
.playground-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px;
}
.playground-eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: var(--guide-accent, #3eaf7c);
}
.playground-heading h3 {
    font-size: 21px;
    font-weight: 650;
    margin-top: 5px;
}
.runtime-switch {
    display: flex;
    padding: 4px;
    border-radius: 7px;
    background: var(--guide-soft, #f6f6f7);
}
.runtime-switch button {
    padding: 7px 10px;
    border-radius: 5px;
    font-size: 12px;
}
.runtime-switch button[aria-pressed='true'] {
    background: var(--guide-bg, white);
    color: var(--guide-accent, #3eaf7c);
    box-shadow: 0 1px 4px #0001;
}
button,
select {
    min-width: 0;
    width: 100%;
    cursor: pointer;
}
.scenario-label {
    display: grid;
    gap: 7px;
    padding: 0 22px;
    font-size: 12px;
    font-weight: 600;
}
select {
    padding: 10px 12px;
    border: 1px solid var(--guide-border, #e2e2e3);
    border-radius: 6px;
    color: inherit;
    background: var(--guide-soft, #f6f6f7);
    appearance: auto;
}
.scenario-description {
    padding: 12px 22px 20px;
    font-size: 13px;
    color: var(--guide-muted, #666);
}
.playground-panes {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    border-top: 1px solid var(--guide-border, #e2e2e3);
}
.input-pane {
    min-width: 0;
    border-right: 1px solid var(--guide-border, #e2e2e3);
}
.input-tabs {
    display: flex;
    overflow-x: auto;
    background: #252529;
    color: #b8b8c0;
}
.input-tabs button {
    flex-shrink: 0;
    padding: 13px 12px;
    font-size: 11px;
    border-bottom: 2px solid transparent;
}
.input-tabs button[aria-selected='true'] {
    color: #42d392;
    border-color: #42d392;
}
.input-pane :deep(.foldable-code) {
    border-radius: 0;
}
.input-pane :deep(.code-scroll) {
    height: 390px;
}
.json-editor {
    display: block;
    width: 100%;
    height: 433px;
    resize: vertical;
    padding: 18px;
    background: #212121;
    color: #b8e3ce;
    font: 12px/1.7 monospace;
    tab-size: 2;
}
.run-toolbar,
.output-heading,
.playground-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    font-size: 11px;
    color: var(--guide-muted, #666);
}
.run-toolbar {
    border-top: 1px solid var(--guide-border, #e2e2e3);
    flex-wrap: wrap;
}
.run-toolbar > div {
    display: flex;
    gap: 14px;
    align-items: center;
}
.run-button {
    padding: 8px 18px;
    border-radius: 6px;
    background: #3eaf7c;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}
.run-button:disabled {
    opacity: 0.6;
    cursor: wait;
}
.output-pane {
    min-width: 0;
}
.output-heading {
    border-bottom: 1px solid var(--guide-border, #e2e2e3);
    min-height: 45px;
}
.output-heading > span:first-child {
    letter-spacing: 0.08em;
    font-weight: 600;
}
.output-placeholder {
    padding: 75px 30px;
    text-align: center;
    color: var(--guide-muted, #666);
}
.output-placeholder > span {
    font-size: 36px;
    opacity: 0.45;
}
.output-placeholder h4 {
    margin: 16px 0 8px;
    font-size: 14px;
    color: var(--guide-text, #3c3c43);
}
.output-placeholder p {
    font-size: 12px;
    line-height: 1.7;
}
.outcome-summary {
    padding: 22px;
    border-bottom: 1px solid var(--guide-border, #e2e2e3);
}
.outcome-status {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 4px;
    background: #eab30820;
    color: #a16207;
    font-size: 11px;
}
.passed .outcome-status {
    background: #3eaf7c20;
    color: var(--guide-accent, #3eaf7c);
}
.outcome-summary h4 {
    margin: 12px 0 8px;
    font-size: 15px;
    line-height: 1.6;
}
.outcome-summary p {
    color: var(--guide-muted, #666);
    font-size: 12px;
    line-height: 1.7;
}
.outcome-flags {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 16px;
    font-size: 11px;
}
.output-pane :deep(.foldable-code) {
    margin: 14px;
}
.output-pane :deep(.code-scroll) {
    max-height: 280px;
}
.playground-error {
    margin: 20px;
    padding: 14px;
    border-radius: 6px;
    background: #ef444415;
    color: #c24141;
    font-size: 13px;
}
.playground-footer {
    border-top: 1px solid var(--guide-border, #e2e2e3);
    flex-wrap: wrap;
}
.playground-footer button {
    color: var(--guide-accent, #3eaf7c);
}
button:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 2px solid #3eaf7c;
    outline-offset: -2px;
}
@media (max-width: 850px) {
    .playground-panes {
        grid-template-columns: 1fr;
    }
    .input-pane {
        border-right: 0;
    }
    .output-pane {
        border-top: 1px solid var(--guide-border, #e2e2e3);
    }
}
@container (max-width: 760px) {
    .playground-panes {
        grid-template-columns: 1fr;
    }
    .input-pane {
        border-right: 0;
    }
    .output-pane {
        border-top: 1px solid var(--guide-border, #e2e2e3);
    }
}
@media (max-width: 480px) {
    .playground-heading {
        flex-wrap: wrap;
    }
}
</style>

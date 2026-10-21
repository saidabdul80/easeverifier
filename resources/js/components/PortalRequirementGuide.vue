<script setup lang="ts">
import LibraryPlayground from '@/components/LibraryPlayground.vue';
import {
    getRequirementStorageExample,
    requirementStorageFormats,
} from '@/lib/resultLibraryExamples';
import { computed, ref } from 'vue';

defineProps<{ language: 'php' | 'javascript' }>();
const emit = defineEmits<{
    'update:language': [value: 'php' | 'javascript'];
}>();
const selected = ref('names');
const format = computed(
    () => requirementStorageFormats.find((item) => item.id === selected.value)!,
);
const example = computed(() => getRequirementStorageExample(selected.value));
const hasIds = computed(() => 'subjects' in example.value.portal);
</script>

<template>
    <div class="requirement-guide">
        <h2>Start with the rule your school wants to check</h2>
        <p>
            For this example, the student must pass these
            <strong>three checks</strong>, each at C6 or better:
        </p>
        <ol class="school-rules">
            <li><strong>English Language</strong><span>Compulsory.</span></li>
            <li><strong>Mathematics</strong><span>Compulsory.</span></li>
            <li>
                <strong>Biology OR Agricultural Science</strong
                ><span
                    >Either one is enough. The student does not need both.</span
                >
            </li>
        </ol>
        <p class="plain-note">
            These are sample programme requirements. Use your own school’s
            subjects and grades in your integration.
        </p>

        <section class="mapping-step">
            <h3>
                <span class="step-number">1</span>How does your portal store
                those subjects?
            </h3>
            <p>
                Choose the one format that looks like your existing query
                result. You only need one of these examples.
            </p>
            <label class="format-label"
                >My portal returns
                <select v-model="selected" aria-label="Your portal stores">
                    <option
                        v-for="item in requirementStorageFormats"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.title }}
                    </option>
                </select>
            </label>
            <p>{{ format.explanation }}</p>
            <p v-if="hasIds" class="reading-help">
                <strong
                    >Subject ID always means your portal’s subject ID.</strong
                >
                If Mathematics is ID 27 in your database, use 27 and map it to
                Mathematics. The IDs below are examples, not numbers assigned by
                the library.
            </p>
            <div class="data-caption">Example data from your portal</div>
            <pre
                class="mapping-code"
            ><code>{{ JSON.stringify(example.portal, null, 2) }}</code></pre>
            <div class="reading-help">
                <strong>Read it like this</strong>
                <p>{{ format.instruction }}</p>
            </div>
            <div v-if="hasIds" class="id-lookup">
                <strong>What the IDs mean in this example</strong>
                <ul>
                    <li v-for="(name, id) in example.portal.subjects" :key="id">
                        <code>{{ id }}</code
                        ><span>→ {{ name }}</span>
                    </li>
                </ul>
                <p>
                    The library has no built-in subject IDs. The
                    <code>subjects</code> list supplies that lookup. Use your
                    real subject IDs and names.
                </p>
            </div>
            <p v-if="selected === 'names'" class="plain-note">
                If Biology and Agricultural Science are both compulsory, put
                them as separate items in the main list instead of together in
                the smaller list.
            </p>
        </section>

        <section class="mapping-step">
            <h3>
                <span class="step-number">2</span>Tell the library where to find
                that data
            </h3>
            <p>
                A mapping simply connects your field names to the library’s
                inputs. In this example:
            </p>
            <ul class="field-explanations">
                <li>Read the student’s name from <code>full_name</code>.</li>
                <li>
                    Read the programme’s subject requirements from
                    <code>course_subjects</code>.
                </li>
                <li v-if="hasIds">
                    Look up subject IDs in <code>subjects</code>.
                </li>
                <li v-if="selected === 'rows'">
                    Within each requirement row, <code>rule_name</code> is its
                    label and <code>allowed_subject_ids</code> lists the
                    accepted subjects.
                </li>
                <li v-if="selected === 'csv'">
                    Use <code>,</code> between required checks and
                    <code>/</code> between subjects where either one is
                    accepted.
                </li>
            </ul>
            <p class="plain-note">
                Change these field names to match your portal. You do not need
                to change your database tables.
            </p>
            <details>
                <summary>
                    See the exact mapping passed as the third argument
                </summary>
                <pre
                    class="mapping-code"
                ><code>{{ JSON.stringify(example.adapter, null, 2) }}</code></pre>
                <p class="plain-note">
                    This is the mapping data shown as JSON. The runnable example
                    below includes it in your selected PHP or JavaScript syntax.
                </p>
            </details>
        </section>

        <section class="mapping-step">
            <h3>
                <span class="step-number">3</span>Compare it with a student’s
                verified result
            </h3>
            <p>Amina’s result contains:</p>
            <ul class="student-grades">
                <li
                    v-for="row in example.result.data.subjects"
                    :key="row.subject"
                >
                    <span>{{ row.subject }}</span
                    ><strong>{{ row.grade }}</strong>
                </li>
            </ul>
            <div class="reading-help">
                <strong>Expected result: requirements met.</strong>
                <p>
                    English and Mathematics pass. Agricultural Science also
                    passes, so it satisfies the “Biology OR Agricultural
                    Science” check. Biology can be absent.
                </p>
            </div>
            <p>
                Click <strong>Run</strong> below to check that with the library.
                Open <strong>Code</strong> for the complete PHP or JavaScript
                example; <strong>Field mapping</strong> shows the third
                argument.
            </p>
            <LibraryPlayground
                :language="language"
                :example-id="`storage-${selected}`"
                @update:language="emit('update:language', $event)"
            />
            <p class="try-next">
                <strong>Try a failing case:</strong> open “Verified result”,
                change Agricultural Science’s grade from C5 to D7, then click
                Run again. The library should say the grade is below the
                requirement.
            </p>
        </section>
    </div>
</template>

<style scoped>
.requirement-guide {
    font-size: 14px;
    line-height: 1.8;
}
h2 {
    margin-bottom: 14px;
    font-size: 22px;
    font-weight: 650;
    line-height: 1.5;
}
p {
    margin: 12px 0;
    color: var(--guide-muted, #67676c);
}
.school-rules {
    display: grid;
    gap: 10px;
    padding: 0;
    list-style: none;
    counter-reset: rules;
    margin: 20px 0;
}
.school-rules li {
    display: grid;
    grid-template-columns: 24px 1fr;
    gap: 1px 10px;
    background: var(--guide-soft, #f6f6f7);
    border-radius: 7px;
    padding: 14px 16px;
}
.school-rules li::before {
    counter-increment: rules;
    content: counter(rules);
    grid-row: span 2;
    color: var(--guide-accent, #23835b);
    font-weight: 700;
}
.school-rules li > span {
    grid-column: 2;
    color: var(--guide-muted, #67676c);
    font-size: 12px;
}
.plain-note {
    font-size: 12px;
}
.mapping-step {
    border-top: 1px solid var(--guide-border, #e2e2e3);
    padding-top: 24px;
    margin-top: 28px;
}
h3 {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 17px;
    font-weight: 650;
    line-height: 1.6;
}
.step-number {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #3eaf7c20;
    color: var(--guide-accent, #23835b);
    font-size: 12px;
}
.format-label {
    display: grid;
    gap: 7px;
    margin: 18px 0;
    font-size: 12px;
    font-weight: 600;
}
select {
    appearance: auto;
    width: 100%;
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--guide-border, #e2e2e3);
    border-radius: 6px;
    background: var(--guide-soft, #f6f6f7);
    color: inherit;
    cursor: pointer;
}
select:focus-visible,
summary:focus-visible {
    outline: 2px solid var(--guide-accent, #23835b);
}
.data-caption {
    font-size: 11px;
    font-weight: 600;
    margin-top: 20px;
}
.mapping-code {
    max-height: 360px;
    overflow: auto;
    padding: 18px;
    border-radius: 7px;
    margin-top: 8px;
    background: #212121;
    color: #b8e3ce;
    font: 12px/1.8 monospace;
    white-space: pre;
}
.mapping-code code {
    font: inherit;
}
.reading-help {
    padding: 16px 18px;
    margin: 18px 0;
    border-left: 3px solid var(--guide-accent, #23835b);
    border-radius: 4px;
    background: var(--guide-soft, #f6f6f7);
    font-size: 13px;
}
.reading-help p {
    margin: 6px 0 0;
}
.id-lookup {
    padding: 16px;
    border: 1px solid var(--guide-border, #e2e2e3);
    border-radius: 6px;
    font-size: 12px;
}
.id-lookup ul {
    display: grid;
    gap: 5px;
    margin: 10px 0;
    padding: 0;
    list-style: none;
}
.id-lookup li {
    display: flex;
    gap: 10px;
}
.id-lookup p {
    margin-bottom: 0;
}
.field-explanations {
    padding-left: 20px;
    font-size: 13px;
}
.field-explanations li + li {
    margin-top: 8px;
}
details {
    margin-top: 18px;
    font-size: 12px;
}
summary {
    cursor: pointer;
    font-weight: 600;
}
.student-grades {
    list-style: none;
    padding: 0;
    border: 1px solid var(--guide-border, #e2e2e3);
    border-radius: 6px;
}
.student-grades li {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 16px;
    font-size: 13px;
}
.student-grades li + li {
    border-top: 1px solid var(--guide-border, #e2e2e3);
}
.student-grades strong {
    color: var(--guide-accent, #23835b);
}
.try-next {
    font-size: 12px;
    margin-top: 16px;
}
</style>

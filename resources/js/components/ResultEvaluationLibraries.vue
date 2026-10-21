<script setup lang="ts">
import FoldableCode from '@/components/FoldableCode.vue';
import LibraryPlayground from '@/components/LibraryPlayground.vue';
import PortalRequirementGuide from '@/components/PortalRequirementGuide.vue';
import { download } from '@/routes/documentation/libraries';
import { computed, onMounted, ref, watch } from 'vue';
import { ResultEvaluator } from '../../libraries/easeverifier-result-evaluator.mjs';

const language = ref<'php' | 'javascript'>('javascript');
const emit = defineEmits<{ 'theme-change': [value: boolean] }>();
const page = ref('introduction');
const playground = ref<InstanceType<typeof LibraryPlayground>>();
const search = ref('');
const dark = ref(false);
const article = ref<HTMLElement>();
const chapters = [
    {
        id: 'introduction',
        title: 'Introduction',
        group: 'Getting started',
        description: 'Download, installation, PHP and JavaScript',
    },
    {
        id: 'quick-start',
        title: 'Quick start',
        group: 'Getting started',
        description: 'Readable subjects, either subject, requirements',
    },
    {
        id: 'playground',
        title: 'Interactive examples',
        group: 'Getting started',
        description: 'Run, output, name mismatch, grade, missing, two sittings',
    },
    {
        id: 'mapping',
        title: 'Connect your portal',
        group: 'Integration',
        description:
            'Database IDs, columns, adapter, CSV, JSON, custom functions',
    },
    {
        id: 'subjects',
        title: 'Match subjects',
        group: 'Integration',
        description: 'Aliases, spelling, similarity, codes, review',
    },
    {
        id: 'rules',
        title: 'Configure your rules',
        group: 'Integration',
        description: 'Names, ownership, duplicate, credits, grades, sittings',
    },
    {
        id: 'methods',
        title: 'API reference',
        group: 'Reference',
        description:
            'evaluate, adaptPortal, normalizeResult, checkOwnership, checkRequirements, bestSubjects, normalizeSubject, subjectSimilarity, matchSubject, gradeRank, messageFor',
    },
    {
        id: 'outcomes',
        title: 'Messages & outcomes',
        group: 'Reference',
        description:
            'Status codes, can_autofill, can_continue, configuration errors',
    },
];
const visibleChapters = computed(() =>
    chapters.filter((chapter) =>
        `${chapter.title} ${chapter.description}`
            .toLowerCase()
            .includes(search.value.trim().toLowerCase()),
    ),
);
const currentChapter = computed(
    () => chapters.find((chapter) => chapter.id === page.value)!,
);
const chapterIndex = computed(() =>
    chapters.findIndex((chapter) => chapter.id === page.value),
);
function navigate(id: string): void {
    page.value = id;
    search.value = '';
    article.value?.scrollIntoView({ block: 'start', behavior: 'smooth' });
}
function tryExample(id = 'ready'): void {
    playground.value?.loadExample(id);
    navigate('playground');
}
onMounted(() => {
    try {
        dark.value =
            localStorage.getItem('result-library-theme') === 'dark' ||
            (!localStorage.getItem('result-library-theme') &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);
    } catch {
        /* Theme preference is optional when browser storage is unavailable. */
    }
    emit('theme-change', dark.value);
});
watch(dark, (value) => {
    emit('theme-change', value);
    try {
        localStorage.setItem('result-library-theme', value ? 'dark' : 'light');
    } catch {
        /* Keep the selected theme for this visit. */
    }
});
const portalRecord = {
    profile: {
        first_name: 'Amina',
        surname: 'Ibrahim',
        student_no: 'STU-12345',
    },
    application: {
        reference: 'APP-90210',
    },
    programme: {
        subject_rules: [
            {
                slot: 'english',
                allowed_ids: [101],
                minimum_level: 4,
                compulsory: 1,
            },
            {
                slot: 'maths',
                allowed_ids: [102],
                minimum_level: 4,
                compulsory: 1,
            },
            {
                slot: 'physics',
                allowed_ids: [103],
                minimum_level: 4,
                compulsory: 1,
            },
            {
                slot: 'chemistry',
                allowed_ids: [104],
                minimum_level: 4,
                compulsory: 1,
            },
            {
                slot: 'science',
                allowed_ids: [105, 106],
                minimum_level: 4,
                compulsory: 1,
            },
        ],
    },
    subject_rows: [
        {
            sub_id: 101,
            title: 'Eng. Lang.',
            short_code: 'ENG',
        },
        {
            sub_id: 102,
            title: 'Mathema tics',
            short_code: 'MTH',
        },
        {
            sub_id: 103,
            title: 'PHYSICS',
            short_code: 'PHY',
        },
        {
            sub_id: 104,
            title: 'Chemistry',
            short_code: 'CHE',
        },
        {
            sub_id: 105,
            title: 'Biology',
            short_code: 'BIO',
        },
        {
            sub_id: 106,
            title: 'Agricultural Science',
            short_code: 'AGR',
        },
    ],
};
const adapter = {
    fields: {
        'student.name': ['profile.first_name', 'profile.surname'],
        candidate_id: 'profile.student_no',
        reference: 'application.reference',
        portal_ref: 'application.reference',
        requirements: 'programme.subject_rules',
        subject_catalog: 'subject_rows',
    },
    requirement_fields: {
        key: 'slot',
        subjects: 'allowed_ids',
        minimum_grade: 'minimum_level',
        required: 'compulsory',
    },
    subject_fields: {
        id: 'sub_id',
        name: 'title',
        code: 'short_code',
    },
    grade_map: {
        '4': 'C6',
    },
    defaults: {
        minimum_grade: 'C6',
        minimum_credits: 5,
        max_sittings: 2,
    },
};
const verifiedResult = {
    success: true,
    reference: 'APP-90210',
    portal_ref: 'APP-90210',
    candidate_id: 'STU-12345',
    sitting: 1,
    data: {
        board: 'WAEC',
        candidate: {
            name: 'Amina Ibrahim',
        },
        subjects: [
            {
                subject: 'English Language',
                grade: 'A1',
            },
            {
                subject: 'Mathematics',
                grade: 'B2',
            },
            {
                subject: 'Physics',
                grade: 'B3',
            },
            {
                subject: 'Chemistry',
                grade: 'C4',
            },
            {
                subject: 'Biology',
                grade: 'C5',
            },
        ],
    },
};
const exampleOutcome = JSON.stringify(
    ResultEvaluator.evaluate(portalRecord, verifiedResult, adapter),
    null,
    2,
);
const phpExample = `<?php
require_once __DIR__ . '/EaseVerifierResultEvaluator.php';
use EaseVerifier\\ResultEvaluator;

// 1. Use your existing database/query output. These are sample portal records.
$portalRecord = [
    'profile' => [
        'first_name' => 'Amina',
        'surname' => 'Ibrahim',
        'student_no' => 'STU-12345',
    ],
    'application' => [
        'reference' => 'APP-90210',
    ],
    'programme' => [
        'subject_rules' => [
            [
                'slot' => 'english',
                'allowed_ids' => [101],
                'minimum_level' => 4,
                'compulsory' => 1,
            ],
            [
                'slot' => 'maths',
                'allowed_ids' => [102],
                'minimum_level' => 4,
                'compulsory' => 1,
            ],
            [
                'slot' => 'physics',
                'allowed_ids' => [103],
                'minimum_level' => 4,
                'compulsory' => 1,
            ],
            [
                'slot' => 'chemistry',
                'allowed_ids' => [104],
                'minimum_level' => 4,
                'compulsory' => 1,
            ],
            [
                'slot' => 'science',
                'allowed_ids' => [105, 106],
                'minimum_level' => 4,
                'compulsory' => 1,
            ],
        ],
    ],
    'subject_rows' => [
        [
            'sub_id' => 101,
            'title' => 'Eng. Lang.',
            'short_code' => 'ENG',
        ],
        [
            'sub_id' => 102,
            'title' => 'Mathema tics',
            'short_code' => 'MTH',
        ],
        [
            'sub_id' => 103,
            'title' => 'PHYSICS',
            'short_code' => 'PHY',
        ],
        [
            'sub_id' => 104,
            'title' => 'Chemistry',
            'short_code' => 'CHE',
        ],
        [
            'sub_id' => 105,
            'title' => 'Biology',
            'short_code' => 'BIO',
        ],
        [
            'sub_id' => 106,
            'title' => 'Agricultural Science',
            'short_code' => 'AGR',
        ],
    ],
];

// 2. Define this adapter once for your portal. Dot paths read nested records.
// Name paths are joined; minimum_level 4 is explicitly mapped to C6.
$adapter = [
    'fields' => [
        'student.name' => ['profile.first_name', 'profile.surname'],
        'candidate_id' => 'profile.student_no',
        'reference' => 'application.reference',
        'portal_ref' => 'application.reference',
        'requirements' => 'programme.subject_rules',
        'subject_catalog' => 'subject_rows',
    ],
    'requirement_fields' => [
        'key' => 'slot',
        'subjects' => 'allowed_ids',
        'minimum_grade' => 'minimum_level',
        'required' => 'compulsory',
    ],
    'subject_fields' => [
        'id' => 'sub_id',
        'name' => 'title',
        'code' => 'short_code',
    ],
    'grade_map' => [
        '4' => 'C6',
    ],
    'defaults' => [
        'minimum_grade' => 'C6',
        'minimum_credits' => 5,
        'max_sittings' => 2,
    ],
];

// 3. Pass the complete response retrieved by your server (sample below).
// The library also accepts a JSON response string directly.
$verifiedResult = [
    'success' => true,
    'reference' => 'APP-90210',
    'portal_ref' => 'APP-90210',
    'candidate_id' => 'STU-12345',
    'sitting' => 1,
    'data' => [
        'board' => 'WAEC',
        'candidate' => [
            'name' => 'Amina Ibrahim',
        ],
        'subjects' => [
            [
                'subject' => 'English Language',
                'grade' => 'A1',
            ],
            [
                'subject' => 'Mathematics',
                'grade' => 'B2',
            ],
            [
                'subject' => 'Physics',
                'grade' => 'B3',
            ],
            [
                'subject' => 'Chemistry',
                'grade' => 'C4',
            ],
            [
                'subject' => 'Biology',
                'grade' => 'C5',
            ],
        ],
    ],
];

// 4. Call this for each student. The database schema remains your own.
$outcome = ResultEvaluator::evaluate($portalRecord, $verifiedResult, $adapter);
echo htmlspecialchars($outcome['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// can_autofill: whether grades may be applied; can_continue: configured checks passed.
// requirements.rows contains your portal_subject_id, matched label, score, and grade.
// configuration_errors explains mapping mistakes; show these to your integrator.
`;
const jsExample = `import { ResultEvaluator } from './easeverifier-result-evaluator.mjs';

// 1. Use your existing database/query output. These are sample portal records.
const portalRecord = {
    "profile": {
        "first_name": "Amina",
        "surname": "Ibrahim",
        "student_no": "STU-12345"
    },
    "application": {
        "reference": "APP-90210"
    },
    "programme": {
        "subject_rules": [
            {
                "slot": "english",
                "allowed_ids": [
                    101
                ],
                "minimum_level": 4,
                "compulsory": 1
            },
            {
                "slot": "maths",
                "allowed_ids": [
                    102
                ],
                "minimum_level": 4,
                "compulsory": 1
            },
            {
                "slot": "physics",
                "allowed_ids": [
                    103
                ],
                "minimum_level": 4,
                "compulsory": 1
            },
            {
                "slot": "chemistry",
                "allowed_ids": [
                    104
                ],
                "minimum_level": 4,
                "compulsory": 1
            },
            {
                "slot": "science",
                "allowed_ids": [
                    105,
                    106
                ],
                "minimum_level": 4,
                "compulsory": 1
            }
        ]
    },
    "subject_rows": [
        {
            "sub_id": 101,
            "title": "Eng. Lang.",
            "short_code": "ENG"
        },
        {
            "sub_id": 102,
            "title": "Mathema tics",
            "short_code": "MTH"
        },
        {
            "sub_id": 103,
            "title": "PHYSICS",
            "short_code": "PHY"
        },
        {
            "sub_id": 104,
            "title": "Chemistry",
            "short_code": "CHE"
        },
        {
            "sub_id": 105,
            "title": "Biology",
            "short_code": "BIO"
        },
        {
            "sub_id": 106,
            "title": "Agricultural Science",
            "short_code": "AGR"
        }
    ]
};

// 2. Define this adapter once for your portal. Dot paths read nested records.
// Name paths are joined; minimum_level 4 is explicitly mapped to C6.
const adapter = {
    "fields": {
        "student.name": [
            "profile.first_name",
            "profile.surname"
        ],
        "candidate_id": "profile.student_no",
        "reference": "application.reference",
        "portal_ref": "application.reference",
        "requirements": "programme.subject_rules",
        "subject_catalog": "subject_rows"
    },
    "requirement_fields": {
        "key": "slot",
        "subjects": "allowed_ids",
        "minimum_grade": "minimum_level",
        "required": "compulsory"
    },
    "subject_fields": {
        "id": "sub_id",
        "name": "title",
        "code": "short_code"
    },
    "grade_map": {
        "4": "C6"
    },
    "defaults": {
        "minimum_grade": "C6",
        "minimum_credits": 5,
        "max_sittings": 2
    }
};

// 3. Pass the complete response retrieved by your server (sample below).
// The library also accepts a JSON response string directly.
const verifiedResult = {
    "success": true,
    "reference": "APP-90210",
    "portal_ref": "APP-90210",
    "candidate_id": "STU-12345",
    "sitting": 1,
    "data": {
        "board": "WAEC",
        "candidate": {
            "name": "Amina Ibrahim"
        },
        "subjects": [
            {
                "subject": "English Language",
                "grade": "A1"
            },
            {
                "subject": "Mathematics",
                "grade": "B2"
            },
            {
                "subject": "Physics",
                "grade": "B3"
            },
            {
                "subject": "Chemistry",
                "grade": "C4"
            },
            {
                "subject": "Biology",
                "grade": "C5"
            }
        ]
    }
};

// 4. Call this for each student. The database schema remains your own.
const outcome = ResultEvaluator.evaluate(portalRecord, verifiedResult, adapter);
console.log(outcome.message, outcome.next_action);
// can_autofill: whether grades may be applied; can_continue: configured checks passed.
// requirements.rows contains your portal_subject_id, matched label, score, and grade.
// configuration_errors explains mapping mistakes; show these to your integrator.
// In a browser, display student messages with textContent.
`;
const simplePhp = `<?php
require_once __DIR__ . '/EaseVerifierResultEvaluator.php';
use EaseVerifier\\ResultEvaluator;

// Read the student's name from your own portal fields.
$checks = [
    'student' => [
        'name' => $portalRecord['first_name'] . ' ' . $portalRecord['surname'],
    ],
    'requirements' => [
        ['key' => 'English Language', 'subjects' => ['English Language']],
        ['key' => 'Mathematics', 'subjects' => ['Mathematics']],
        ['key' => 'Physics', 'subjects' => ['Physics']],
        ['key' => 'Chemistry', 'subjects' => ['Chemistry']],
        // ONE of these two subjects is enough for this requirement.
        [
            'key' => 'Biology or Agricultural Science',
            'subjects' => ['Biology', 'Agricultural Science'],
        ],
    ],
    'minimum_grade' => 'C6',
    'max_sittings' => 2,
];

// $verifiedResult is the result response retrieved by your server.
$outcome = ResultEvaluator::evaluate($checks, $verifiedResult);
echo htmlspecialchars($outcome['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');`;
const simpleJs = `import { ResultEvaluator } from './easeverifier-result-evaluator.mjs';

// Read the student's name from your own portal fields.
const checks = {
    student: {
        name: portalRecord.first_name + ' ' + portalRecord.surname,
    },
    requirements: [
        { key: 'English Language', subjects: ['English Language'] },
        { key: 'Mathematics', subjects: ['Mathematics'] },
        { key: 'Physics', subjects: ['Physics'] },
        { key: 'Chemistry', subjects: ['Chemistry'] },
        // ONE of these two subjects is enough for this requirement.
        {
            key: 'Biology or Agricultural Science',
            subjects: ['Biology', 'Agricultural Science'],
        },
    ],
    minimum_grade: 'C6',
    max_sittings: 2,
};

// verifiedResult is the result response retrieved by your server.
const outcome = ResultEvaluator.evaluate(checks, verifiedResult);
console.log(outcome.message, outcome.can_autofill, outcome.can_continue);`;
const quickPhp = `<?php
require_once __DIR__ . '/EaseVerifierResultEvaluator.php';
use EaseVerifier\\ResultEvaluator;

// Define once: change these paths/columns to the ones in your portal.
$adapter = [
    'fields' => [
        'student.name' => ['first_name', 'surname'],
        'requirements' => 'required_subjects',
        'subject_catalog' => 'all_subjects',
    ],
    'subject_fields' => ['id' => 'subject_id', 'name' => 'subject_name'],
    'defaults' => ['minimum_grade' => 'C6', 'max_sittings' => 2],
];

// $portalRecord is your query output; $verifiedResult is the trusted API response.
$outcome = ResultEvaluator::evaluate($portalRecord, $verifiedResult, $adapter);
echo htmlspecialchars($outcome['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');`;
const quickJs = `import { ResultEvaluator } from './easeverifier-result-evaluator.mjs';

// Define once: change these paths/columns to the ones in your portal.
const adapter = {
    fields: {
        'student.name': ['first_name', 'surname'],
        requirements: 'required_subjects',
        subject_catalog: 'all_subjects',
    },
    subject_fields: { id: 'subject_id', name: 'subject_name' },
    defaults: { minimum_grade: 'C6', max_sittings: 2 },
};

// portalRecord is your query output; verifiedResult is the trusted API response.
const outcome = ResultEvaluator.evaluate(portalRecord, verifiedResult, adapter);
console.log(outcome.message, outcome.can_autofill, outcome.can_continue);`;
const customPhp = `$adapter = function (array $record): array {
    // Run your existing programme/rule logic here, then return these fields.
    return [
        'student' => ['name' => $record['student']['legal_name']],
        'requirements' => array_map(
            fn (string $label): array => ['key' => $label, 'subjects' => [$label]],
            $record['programme']['required_labels']
        ),
        'minimum_grade' => 'C6',
        'max_sittings' => 2,
    ];
};
$outcome = ResultEvaluator::evaluate($portalRecord, $verifiedResult, $adapter);

// Alternatively, unwrap a result inside a field-mapping adapter:
// $adapter['result_adapter'] = fn ($response) => $response['verification']['result'];`;
const customJs = `const adapter = (record) => ({
    // Run your existing programme/rule logic here, then return these fields.
    student: { name: record.student.legal_name },
    requirements: record.programme.required_labels.map((label) => ({
        key: label,
        subjects: [label],
    })),
    minimum_grade: 'C6',
    max_sittings: 2,
});
const outcome = ResultEvaluator.evaluate(portalRecord, verifiedResult, adapter);

// Alternatively, unwrap a result inside a field-mapping adapter:
// adapter.result_adapter = (response) => response.verification.result;`;
const methods = [
    [
        'adaptPortal(record, adapter)',
        'Map existing field paths, requirement rows, subject records, and grade codes once. A callable supports custom portal logic.',
    ],
    [
        'matchSubject(subject, catalogue, options?)',
        'Resolve an ID, code, record, or similar label to your portal subject ID. Returns matched, review, or unmatched with suggestions.',
    ],
    [
        'subjectSimilarity(left, right)',
        'Similarity score from 0 to 1. Does not decide eligibility; use matchSubject for ambiguity handling.',
    ],
    [
        'evaluate(portalRecord, result, adapter?)',
        'Full decision, message, next action, ownership, and requirements. Start here.',
    ],
    [
        'normalizeResult(result)',
        'Read a direct result, API data envelope, or webhook result envelope.',
    ],
    [
        'checkOwnership(portal, result)',
        'Check every sitting’s name and the portal’s duplicate-use flag.',
    ],
    [
        'checkRequirements(portal, result)',
        'Match distinct subjects to slots and check grades, credits, and sitting count. Invalid rules throw an exception.',
    ],
    [
        'bestSubjects(result, portal?)',
        'Best grade per distinct subject, with its source sitting. Ties prefer the earlier sitting.',
    ],
    [
        'normalizeSubject(subject)',
        'Normalize punctuation, spaces, common abbreviations, and known subject equivalents. Use matchSubject to resolve database IDs.',
    ],
    [
        'gradeRank(grade)',
        'A1 = 9 through F9 = 1; unknown grades = 0. P7/P8 are supported.',
    ],
    ['messageFor(code)', 'Get the plain-text message for an outcome code.'],
];
const outcomeCodes = [
    [
        'REQUIREMENTS_MET',
        'Ownership was accepted or explicitly skipped, and the configured rules passed.',
    ],
    [
        'OWNERSHIP_MISMATCH / OWNERSHIP_REVIEW',
        'A different, partial, or missing name blocks auto-fill.',
    ],
    [
        'DUPLICATE_RESULT / RESULT_CONTEXT_MISMATCH',
        'Another student or portal request is associated with this result.',
    ],
    [
        'SUBJECT_MATCH_REVIEW',
        'Subject identity is uncertain. No grades are auto-filled until the mapping is resolved.',
    ],
    ['SUBJECTS_MISSING', 'A required subject slot could not be filled.'],
    [
        'GRADES_BELOW_REQUIREMENT / INSUFFICIENT_CREDITS',
        'Grade or distinct-credit requirements were not met.',
    ],
    [
        'SITTING_LIMIT_EXCEEDED',
        'More sittings were supplied than the programme permits.',
    ],
    [
        'RESULT_PENDING / RESULT_FAILED / INVALID_RESULT',
        'Wait, review the result error, or retrieve a usable result.',
    ],
    ['CONFIGURATION_ERROR', 'Fix the portal rules before applying a result.'],
];
</script>

<template>
    <div :class="['library-guide', { 'guide-dark': dark }]">
        <header class="guide-header">
            <div class="guide-brand">
                <span class="guide-mark">{ }</span
                ><strong>Result Evaluator</strong
                ><span class="guide-version">v1.1.0</span>
            </div>
            <div class="guide-tools">
                <div class="guide-language" aria-label="Documentation language">
                    <button
                        v-for="runtime in ['javascript', 'php'] as const"
                        :key="runtime"
                        type="button"
                        :aria-pressed="language === runtime"
                        @click="language = runtime"
                    >
                        {{ runtime === 'php' ? 'PHP' : 'JavaScript' }}
                    </button>
                </div>
                <button
                    type="button"
                    class="theme-toggle"
                    :aria-label="dark ? 'Use light theme' : 'Use dark theme'"
                    @click="dark = !dark"
                >
                    {{ dark ? '☀' : '☾' }}
                </button>
            </div>
        </header>
        <div class="guide-layout">
            <aside class="guide-sidebar" aria-label="Library documentation">
                <label class="guide-search"
                    ><span aria-hidden="true">⌕</span
                    ><input
                        v-model="search"
                        type="search"
                        placeholder="Search the guide"
                        aria-label="Search library documentation"
                /></label>
                <nav class="guide-nav">
                    <template
                        v-for="(chapter, index) in visibleChapters"
                        :key="chapter.id"
                    >
                        <span
                            v-if="
                                index === 0 ||
                                visibleChapters[index - 1].group !==
                                    chapter.group
                            "
                            class="guide-group"
                            >{{ chapter.group }}</span
                        >
                        <button
                            type="button"
                            :aria-current="
                                page === chapter.id ? 'page' : undefined
                            "
                            :class="{ selected: page === chapter.id }"
                            @click="navigate(chapter.id)"
                        >
                            {{ chapter.title
                            }}<span
                                v-if="chapter.id === 'playground'"
                                class="live-badge"
                                >RUN</span
                            >
                        </button>
                    </template>
                    <p v-if="!visibleChapters.length" class="no-results">
                        No matching topics. Try “subjects”, “grades”, or
                        “adapter”.
                    </p>
                </nav>
                <div class="guide-sidebar-note">
                    <span class="guide-status-dot"></span>Framework
                    independent<br /><span>PHP 8.1+ · JavaScript ES2022</span>
                </div>
            </aside>
            <main ref="article" class="guide-article">
                <div class="guide-breadcrumb">
                    Library guide <span>/</span> {{ currentChapter.title }}
                </div>
                <h1>{{ currentChapter.title }}</h1>
                <section v-show="page === 'introduction'" class="guide-section">
                    <h2>Result checks, made simple.</h2>
                    <p class="text-body-2 mb-4">
                        Download one file, map your existing portal fields once,
                        and call evaluate for each student. Keep your own
                        database tables, subject IDs, and grade codes. Both
                        libraries work without a framework or package manager.
                    </p>
                    <div class="d-flex ga-3 mb-4 flex-wrap">
                        <v-btn
                            :href="download.url({ language: 'php' })"
                            download="EaseVerifierResultEvaluator.php"
                            color="primary"
                            prepend-icon="mdi-download"
                            >Download PHP · v1.1.0</v-btn
                        >
                        <v-btn
                            :href="download.url({ language: 'javascript' })"
                            download="easeverifier-result-evaluator.mjs"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-download"
                            >Download JavaScript · v1.1.0</v-btn
                        >
                    </div>
                    <p class="text-caption text-medium-emphasis mb-4">
                        PHP 8.1+ using require_once. JavaScript is an ES module
                        for Node.js or modern browsers; load browser usage
                        inside a script with type="module".
                    </p>
                    <v-alert type="info" variant="tonal" class="mb-4">
                        Use result data retrieved by your server or from a
                        webhook whose signature your server has verified. Run
                        authoritative clearance decisions on the server. These
                        helpers evaluate supplied data; they do not verify
                        signatures, fetch results, confirm payment, or grant
                        admission.
                    </v-alert>

                    <div class="guide-features">
                        <div>
                            <strong>Your portal. Your rules.</strong>
                            <p>
                                Use your existing database, subject names, IDs,
                                and programme requirements.
                            </p>
                        </div>
                        <div>
                            <strong>Clear student messages.</strong>
                            <p>
                                Separate verified results, name checks, and
                                subject requirements.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="guide-primary"
                        @click="navigate('quick-start')"
                    >
                        Get started →
                    </button>
                    <button
                        type="button"
                        class="guide-secondary"
                        @click="tryExample()"
                    >
                        Try an example ↗
                    </button>
                </section>
                <section v-show="page === 'quick-start'" class="guide-section">
                    <h3 class="text-subtitle-1 font-weight-bold mb-2">
                        Start with readable subject names
                    </h3>
                    <p class="text-body-2 mb-3">
                        This example requires English Language, Mathematics,
                        Physics, Chemistry, and either Biology or Agricultural
                        Science, each at C6 or better, across at most two
                        sittings. These are example programme rules; replace
                        them with your own.
                    </p>
                    <v-alert type="info" variant="tonal" class="mb-3">
                        Each requirement needs one of its listed subjects. For
                        “Biology or Agricultural Science”, either subject is
                        enough. To require both, give Biology and Agricultural
                        Science separate requirements, just like English and
                        Mathematics below.
                    </v-alert>
                    <FoldableCode
                        :code="language === 'php' ? simplePhp : simpleJs"
                        label="Simple example using subject names"
                        class="mb-4"
                    />

                    <button
                        type="button"
                        class="guide-primary"
                        @click="tryExample()"
                    >
                        Run this example →
                    </button>
                </section>
                <section v-show="page === 'playground'" class="guide-section">
                    <p>
                        Choose a student scenario, edit its inputs, and click
                        <strong>Run</strong>. The output comes from the actual
                        library in your selected language.
                    </p>
                    <LibraryPlayground
                        ref="playground"
                        v-model:language="language"
                    />
                    <div class="guide-callout">
                        <strong>Use the same inputs in your portal.</strong>
                        <p>
                            “Code” is generated from the JSON you edit. PHP runs
                            on the documentation server; JavaScript runs in your
                            browser. The sandbox only evaluates supplied example
                            data. Use fictional details here.
                        </p>
                    </div>
                </section>
                <section v-show="page === 'mapping'" class="guide-section">
                    <PortalRequirementGuide v-model:language="language" />
                    <h2 class="mt-8">More integration examples</h2>
                    <p>
                        Use these when you need additional field mappings or
                        custom portal logic.
                    </p>
                    <details class="mb-4">
                        <summary>
                            Map your existing database fields and subject IDs
                        </summary>
                        <p class="text-body-2 mt-3 mb-3">
                            If your query already returns subject IDs or
                            programme requirements, map those fields once.
                            Change the paths and column names below to yours. A
                            name can come from one field or several fields. Your
                            catalogue connects each subject ID to its readable
                            name.
                        </p>
                        <FoldableCode
                            :code="language === 'php' ? quickPhp : quickJs"
                            label="Map existing portal fields"
                        />
                    </details>
                    <details class="mb-4">
                        <summary>
                            Complete runnable example: nested records, custom
                            columns, and numeric grade codes
                        </summary>
                        <p class="text-body-2 mt-3">
                            This example keeps the portal’s own subject IDs and
                            column names. The verified result uses different
                            labels. The adapter joins the student’s name, reads
                            the programme rules, and explicitly translates grade
                            code 4 to C6. In this sample catalogue, 105 means
                            Biology and 106 means Agricultural Science. The
                            science rule accepts either one at C6 or better.
                            They are example IDs; use your portal’s IDs.
                        </p>
                        <FoldableCode
                            :code="language === 'php' ? phpExample : jsExample"
                            label="Complete runnable example"
                            class="mt-3"
                        />
                    </details>
                    <details class="mb-4">
                        <summary>
                            My portal calculates requirements dynamically or
                            wraps the result differently
                        </summary>
                        <p class="text-body-2 mt-3">
                            Pass a function as the third argument to evaluate
                            when field mapping is not enough. It receives your
                            original portal record and returns the canonical
                            portal object: student, requirements, and configured
                            rules. Your existing programme lookup or requirement
                            builder can run inside that function.
                        </p>
                        <p class="text-body-2 mt-3">
                            If your application wraps the verified result, set
                            adapter.result_adapter to a function returning the
                            actual response. For individual checks, first call
                            adaptPortal(record, adapter), then pass that adapted
                            object to checkOwnership or checkRequirements.
                            Canonical objects from earlier integrations still
                            work directly.
                        </p>
                        <FoldableCode
                            :code="language === 'php' ? customPhp : customJs"
                            label="Custom adapter"
                            class="mt-3"
                        />
                    </details>

                    <button
                        type="button"
                        class="guide-primary"
                        @click="tryExample('mapped-ids')"
                    >
                        Try the database ID example →
                    </button>
                </section>
                <section v-show="page === 'subjects'" class="guide-section">
                    <details open class="mb-4">
                        <summary>How subject matching works</summary>
                        <p class="text-body-2 mt-3">
                            The matcher tries IDs and codes, then labels and
                            configured aliases, then normalized spacing,
                            punctuation and word order, then close spelling and
                            multiword abbreviations. Each row preserves the
                            matched portal_subject_id. Short opaque codes need a
                            catalogue code or alias.
                        </p>
                        <p class="text-body-2 mt-3">
                            Examples: Mathema tics → Mathematics, Eng. Lang. →
                            English Language, Chemstry → Chemistry. Further
                            Mathematics, Home Economics, and Literature in
                            English remain distinct from Mathematics, Economics,
                            and English Language.
                        </p>
                        <p class="text-body-2 mt-3">
                            subject_matching.threshold defaults to 0.88 (allowed
                            range 0.85–1); margin defaults to 0.06. Close
                            competing matches return SUBJECT_MATCH_REVIEW with
                            suggestions and block auto-fill. Scores are
                            similarity measurements, not probabilities. Add an
                            explicit alias or correct the catalogue to resolve a
                            review.
                        </p>
                    </details>
                </section>
                <section v-show="page === 'rules'" class="guide-section">
                    <details open class="mb-4">
                        <summary>
                            Configure names, subjects, grades, and sittings
                        </summary>
                        <ul class="library-notes">
                            <li>
                                <code>student.name</code> is required. Name
                                order, common titles, punctuation, and ASCII
                                letter case are ignored. Partial matches or
                                spelling variants need review; fuzzy matches are
                                not automatically accepted.
                            </li>
                            <li>
                                <code>ownership_check_enabled</code> defaults to
                                true. Set it to false only when your portal
                                handles ownership separately; the output records
                                <code>ownership.status: skipped</code>.
                            </li>
                            <li>
                                <code>result_used_by_another_student</code>
                                defaults to false. Your server must look up
                                duplicate result use and supply this boolean;
                                the library has no database access. A true value
                                blocks auto-fill even if name comparison is
                                disabled.
                            </li>
                            <li>
                                The adapted <code>requirements</code> must
                                contain at least one required slot with a unique
                                <code>key</code> and a nonempty
                                <code>subjects</code> array. Subjects within one
                                slot are alternatives. Use separate slots when
                                both are required. One distinct subject cannot
                                fill two slots.
                            </li>
                            <li>
                                <code>minimum_grade</code> defaults to C6;
                                override it per slot with the same key. These
                                are your portal’s rules, not a universal school
                                cutoff. Unknown grades require review.
                            </li>
                            <li>
                                <code>minimum_credits</code> defaults to 0. It
                                counts distinct returned subjects at the overall
                                minimum grade. With a catalogue, only resolved
                                subject identities count; uncertain identities
                                needed for the total require review. Supply the
                                full school catalogue if credits can come from
                                subjects outside the required slots.
                            </li>
                            <li>
                                <code>max_sittings</code> accepts 1 or 2 and
                                defaults to 2. Combine results under
                                <code>data.sittings</code>, each with a unique
                                integer <code>sitting_no</code>, its own
                                <code>candidate</code>, and
                                <code>subjects</code>. Every supplied sitting
                                must pass the ownership check.
                            </li>
                            <li>
                                <code>reference</code>, <code>portal_ref</code>,
                                and <code>candidate_id</code> are optional
                                expected context. A different returned value
                                blocks auto-fill. Direct results may lack this
                                context, so your server must bind the result to
                                the correct request.
                            </li>
                            <li>
                                <code>evaluate</code> returns
                                <code>CONFIGURATION_ERROR</code> for invalid
                                rules. Read
                                <code>configuration_errors</code> for the
                                missing field or incorrect mapping. Calling
                                <code>checkRequirements</code> directly throws
                                <code>InvalidArgumentException</code> in PHP or
                                <code>TypeError</code> in JavaScript.
                            </li>
                        </ul>
                    </details>
                </section>
                <section v-show="page === 'methods'" class="guide-section">
                    <h3 class="text-subtitle-1 font-weight-bold mb-2">
                        Available methods
                    </h3>
                    <p class="text-caption mb-3">
                        Use ResultEvaluator::method(...) in PHP and
                        ResultEvaluator.method(...) in JavaScript. Inputs are
                        not modified. evaluate accepts existing records plus an
                        adapter. The individual check methods accept an already
                        adapted portal object.
                    </p>
                    <v-table density="compact" class="mb-4">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Returns</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="[method, description] in methods"
                                :key="method"
                            >
                                <td>
                                    <code>{{ method }}</code>
                                </td>
                                <td>{{ description }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </section>
                <section v-show="page === 'outcomes'" class="guide-section">
                    <p class="text-body-2 mb-4">
                        <strong
                            >Auto-fill and requirement completion are
                            separate.</strong
                        >
                        A result with missing subjects or low grades can allow
                        partial auto-fill while
                        <code>can_continue</code> remains false. Name conflicts,
                        duplicate use, unknown grades, or too many sittings
                        block auto-fill.
                    </p>
                    <details open class="mb-4">
                        <summary>
                            See the returned object for this example
                        </summary>
                        <FoldableCode
                            :code="exampleOutcome"
                            label="Returned object"
                            class="mt-3"
                        />
                    </details>

                    <details open>
                        <summary>Outcome codes and student messages</summary>
                        <v-table density="compact" class="mt-3">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="[code, meaning] in outcomeCodes"
                                    :key="code"
                                >
                                    <td>
                                        <code>{{ code }}</code>
                                    </td>
                                    <td>{{ meaning }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </details>
                </section>
                <footer class="guide-pagination">
                    <button
                        v-if="chapterIndex > 0"
                        type="button"
                        @click="navigate(chapters[chapterIndex - 1].id)"
                    >
                        <span>Previous</span>←
                        {{ chapters[chapterIndex - 1].title }}</button
                    ><span v-else></span>
                    <button
                        v-if="chapterIndex < chapters.length - 1"
                        type="button"
                        @click="navigate(chapters[chapterIndex + 1].id)"
                    >
                        <span>Next</span
                        >{{ chapters[chapterIndex + 1].title }} →
                    </button>
                </footer>
            </main>
        </div>
    </div>
</template>

<style scoped>
.library-guide {
    container-type: inline-size;
    --guide-bg: #fff;
    --guide-soft: #f6f6f7;
    --guide-text: #3c3c43;
    --guide-muted: #67676c;
    --guide-border: #e2e2e3;
    --guide-accent: #23835b;
    background: var(--guide-bg);
    color: var(--guide-text);
    border: 1px solid var(--guide-border);
    border-radius: 10px;
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
}
.library-guide.guide-dark {
    --guide-bg: #1b1b1f;
    --guide-soft: #252529;
    --guide-text: #dfdfd6;
    --guide-muted: #a8a8b2;
    --guide-border: #36363a;
    --guide-accent: #42d392;
    color-scheme: dark;
}
.guide-header {
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid var(--guide-border);
}
.guide-brand,
.guide-tools,
.guide-language {
    display: flex;
    align-items: center;
    gap: 12px;
}
.guide-brand strong {
    font-size: 15px;
    letter-spacing: -0.02em;
}
.guide-mark {
    color: var(--guide-accent);
    font: 700 21px monospace;
}
.guide-version {
    color: var(--guide-muted);
    font-size: 10px;
    background: var(--guide-soft);
    padding: 3px 6px;
    border-radius: 4px;
}
.guide-language {
    gap: 0;
    border: 1px solid var(--guide-border);
    border-radius: 6px;
    padding: 3px;
}
.guide-language button {
    font-size: 11px;
    padding: 6px 9px;
    border-radius: 4px;
}
.guide-language button[aria-pressed='true'] {
    color: var(--guide-accent);
    background: var(--guide-soft);
}
.theme-toggle {
    border: 1px solid var(--guide-border);
    width: 30px;
    height: 30px;
    border-radius: 50%;
}
button {
    cursor: pointer;
}
button:focus-visible,
input:focus-visible {
    outline: 2px solid var(--guide-accent);
    outline-offset: 2px;
}
.guide-layout {
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr);
    align-items: start;
}
.guide-sidebar {
    position: sticky;
    top: 80px;
    padding: 28px 18px;
    background: var(--guide-soft);
    border-right: 1px solid var(--guide-border);
    border-radius: 0 0 0 10px;
}
.guide-search {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 9px;
    border: 1px solid var(--guide-border);
    border-radius: 6px;
    color: var(--guide-muted);
    background: var(--guide-bg);
}
.guide-search input {
    width: 100%;
    min-width: 0;
    font-size: 11px;
    outline: none;
}
.guide-search > span {
    font-size: 18px;
}
.guide-nav {
    display: flex;
    flex-direction: column;
    padding-top: 10px;
}
.guide-group {
    padding: 22px 10px 9px;
    font-weight: 650;
    font-size: 12px;
}
.guide-nav button {
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
    padding: 9px 10px;
    font-size: 12px;
    border-left: 2px solid transparent;
    color: var(--guide-muted);
}
.guide-nav button:hover {
    color: var(--guide-text);
}
.guide-nav button.selected {
    color: var(--guide-accent);
    border-left-color: var(--guide-accent);
    background: color-mix(in srgb, var(--guide-accent) 8%, transparent);
    font-weight: 600;
}
.live-badge {
    font-size: 8px;
    letter-spacing: 0.07em;
    border-radius: 3px;
    padding: 1px 4px;
    background: #3eaf7c18;
    color: var(--guide-accent);
}
.guide-sidebar-note {
    margin: 32px 10px 0;
    border-top: 1px solid var(--guide-border);
    padding-top: 18px;
    font-size: 10px;
    line-height: 1.9;
}
.guide-sidebar-note > span:last-child {
    color: var(--guide-muted);
}
.guide-status-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    background: var(--guide-accent);
    border-radius: 50%;
    margin-right: 5px;
}
.no-results {
    padding: 14px 6px;
    font-size: 12px;
    color: var(--guide-muted);
}
.guide-article {
    min-width: 0;
    padding: 38px 40px;
    scroll-margin-top: 85px;
}
.guide-breadcrumb {
    font-size: 11px;
    color: var(--guide-muted);
    margin-bottom: 18px;
}
.guide-breadcrumb span {
    padding: 0 9px;
    opacity: 0.5;
}
.guide-article h1 {
    margin: 0 0 28px;
    font-size: clamp(27px, 3vw, 34px);
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.035em;
}
.guide-section {
    font-size: 14px;
    line-height: 1.8;
}
.guide-section > p {
    color: var(--guide-muted);
    margin-bottom: 24px;
    line-height: 1.8;
}
.guide-section h2 {
    font-size: 22px;
    line-height: 1.5;
    font-weight: 650;
    letter-spacing: -0.02em;
    margin-bottom: 16px;
}
.guide-section h3 {
    line-height: 1.6;
}
.guide-section details {
    border-top: 1px solid var(--guide-border);
    padding-top: 20px;
}
.guide-section summary {
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
}
.guide-section :deep(.v-table) {
    background: var(--guide-bg);
    color: var(--guide-text);
    border: 1px solid var(--guide-border);
    border-radius: 6px;
}
.guide-section :deep(th),
.guide-section :deep(td) {
    padding: 12px 14px !important;
    border-color: var(--guide-border) !important;
    font-size: 12px;
    line-height: 1.8;
}
.guide-section :deep(th) {
    color: var(--guide-text);
    background: var(--guide-soft);
    font-weight: 650;
}
.guide-section :deep(td code),
.library-notes code {
    padding: 2px 4px;
    border-radius: 4px;
    font-size: 11px;
    background: var(--guide-soft);
    color: var(--guide-accent);
    overflow-wrap: anywhere;
}
.guide-section :deep(.v-alert) {
    padding: 18px;
    margin: 22px 0;
    border-left: 3px solid var(--guide-accent);
    border-radius: 5px;
    font-size: 12px;
    color: var(--guide-text) !important;
    background: color-mix(in srgb, var(--guide-accent) 7%, var(--guide-bg));
}
.guide-section :deep(.v-btn) {
    text-transform: none;
    letter-spacing: 0;
    font-size: 12px;
}
.guide-section :deep(.v-alert__prepend) {
    display: none;
}
.library-notes {
    padding-left: 20px;
    font-size: 13px;
    line-height: 1.9;
}
.library-notes li + li {
    margin-top: 16px;
}
.guide-features {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin: 26px 0;
}
.guide-features > div {
    border-radius: 8px;
    background: var(--guide-soft);
    padding: 20px;
    font-size: 13px;
}
.guide-features p {
    font-size: 12px;
    line-height: 1.8;
    color: var(--guide-muted);
    margin-top: 8px;
}
.guide-primary,
.guide-secondary {
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin: 8px 10px 8px 0;
}
.guide-primary {
    background: #3eaf7c;
    color: #fff;
}
.guide-secondary {
    background: var(--guide-soft);
    border: 1px solid var(--guide-border);
}
.guide-callout {
    margin-top: 24px;
    padding: 18px;
    border-left: 3px solid var(--guide-accent);
    background: var(--guide-soft);
    border-radius: 5px;
    font-size: 12px;
}
.guide-callout p {
    margin-top: 6px;
    color: var(--guide-muted);
}
.guide-pagination {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    margin-top: 48px;
    padding-top: 24px;
    border-top: 1px solid var(--guide-border);
}
.guide-pagination button {
    border: 1px solid var(--guide-border);
    border-radius: 7px;
    padding: 12px 16px;
    font-size: 12px;
    color: var(--guide-accent);
    text-align: left;
}
.guide-pagination button:last-child {
    text-align: right;
}
.guide-pagination button span {
    display: block;
    font-size: 10px;
    color: var(--guide-muted);
    margin-bottom: 4px;
}
@media (max-width: 1100px) {
    .guide-article {
        padding: 28px 24px;
    }
    .guide-layout {
        grid-template-columns: 185px minmax(0, 1fr);
    }
}
@media (max-width: 720px) {
    .guide-layout {
        grid-template-columns: 1fr;
    }
    .guide-sidebar {
        position: static;
        border-right: 0;
        border-bottom: 1px solid var(--guide-border);
        padding: 16px;
    }
    .guide-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 3px 8px;
    }
    .guide-group,
    .guide-sidebar-note {
        display: none;
    }
    .guide-nav button {
        padding: 7px 8px;
        font-size: 11px;
    }
    .guide-article {
        padding: 25px 18px;
    }
    .guide-header {
        padding: 14px 16px;
        flex-wrap: wrap;
    }
    .guide-features {
        grid-template-columns: 1fr;
    }
}
@container (max-width: 760px) {
    .guide-layout {
        grid-template-columns: 1fr;
    }
    .guide-sidebar {
        position: static;
        border-right: 0;
        border-bottom: 1px solid var(--guide-border);
        padding: 16px;
    }
    .guide-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 3px 8px;
    }
    .guide-group,
    .guide-sidebar-note {
        display: none;
    }
    .guide-nav button {
        padding: 7px 8px;
        font-size: 11px;
    }
    .guide-article {
        padding: 25px 18px;
    }
    .guide-features {
        grid-template-columns: 1fr;
    }
}
</style>

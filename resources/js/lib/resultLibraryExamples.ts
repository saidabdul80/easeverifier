export const requirementStorageFormats = [
    {
        id: 'names',
        title: 'Subject names',
        explanation:
            'Your portal already has readable names such as English Language. A subject-name list is enough; no ID lookup is needed.',
        instruction:
            'Each item is a requirement. The smaller list containing Biology and Agricultural Science means either subject can satisfy the last requirement.',
    },
    {
        id: 'ids',
        title: 'Subject IDs from my portal',
        explanation:
            'These are IDs from your own portal database. The library has no built-in subject IDs. Supply the names that your IDs refer to.',
        instruction:
            '101 is English, 102 is Mathematics, 105 is Biology, and 106 is Agricultural Science in this example only. [105, 106] means either of those two subjects.',
    },
    {
        id: 'groups',
        title: 'Named groups of subjects',
        explanation:
            'Your portal gives each requirement its own label. The labels help you identify the checks; the listed subjects tell the library what to accept.',
        instruction:
            'english_check and maths_check each accept one compulsory subject. science_choice accepts either Biology or Agricultural Science.',
    },
    {
        id: 'rows',
        title: 'Rows returned by a database query',
        explanation:
            'Your query returns records with its own column names. Tell the library which column contains the requirement name and which contains the accepted subject IDs.',
        instruction:
            'rule_name identifies the check. allowed_subject_ids contains the subjects accepted for that check. The third record accepts ID 105 or ID 106.',
    },
    {
        id: 'json',
        title: 'JSON saved in a text column',
        explanation:
            'Your database returns the requirements as JSON text. The adapter reads that text as a list automatically.',
        instruction:
            'The escaped quotes show that course_subjects contains text. After reading it, the library applies the same three requirements shown above.',
    },
    {
        id: 'csv',
        title: 'Comma-separated text',
        explanation:
            'Your portal stores one text value. Configure commas to separate requirements and a slash to separate acceptable alternatives.',
        instruction:
            'Read 101,102,105/106 as: require English AND Mathematics AND either Biology OR Agricultural Science. The slash means either; it does not require both.',
    },
];

export const libraryExamples = [
    {
        id: 'ready',
        title: 'All requirements met',
        description:
            'The name matches and the student passes every required subject.',
        expected: 'REQUIREMENTS_MET',
    },
    {
        id: 'either-subject',
        title: 'Either science subject',
        description:
            'Agricultural Science satisfies the Biology OR Agricultural Science requirement.',
        expected: 'REQUIREMENTS_MET',
    },
    {
        id: 'name-mismatch',
        title: 'Different student name',
        description:
            'A verified result can still belong to a different person.',
        expected: 'OWNERSHIP_MISMATCH',
    },
    {
        id: 'name-review',
        title: 'Name needs review',
        description: 'A partial name match needs a person to review it.',
        expected: 'OWNERSHIP_REVIEW',
    },
    {
        id: 'missing-subject',
        title: 'A required subject is missing',
        description:
            'The student has no Chemistry result to fill the compulsory Chemistry requirement.',
        expected: 'SUBJECTS_MISSING',
    },
    {
        id: 'low-grade',
        title: 'A grade is below C6',
        description:
            'Chemistry is present, but D7 does not meet this programme’s C6 cutoff.',
        expected: 'GRADES_BELOW_REQUIREMENT',
    },
    {
        id: 'two-sittings',
        title: 'Combine two sittings',
        description:
            'Use the best grade in each subject across two results for the same student.',
        expected: 'REQUIREMENTS_MET',
    },
    {
        id: 'pending',
        title: 'The result is still processing',
        description:
            'Wait for the result before deciding whether requirements are met.',
        expected: 'RESULT_PENDING',
    },
    {
        id: 'duplicate',
        title: 'Result already used',
        description:
            'The portal has found that another student already used this result.',
        expected: 'DUPLICATE_RESULT',
    },
    {
        id: 'mapped-ids',
        title: 'Map existing database IDs',
        description:
            'Connect a portal’s own subject IDs and column names to verified subject labels.',
        expected: 'REQUIREMENTS_MET',
    },
    ...requirementStorageFormats.map((format) => ({
        id: `storage-${format.id}`,
        title: `Portal mapping: ${format.title}`,
        description:
            'English and Mathematics are compulsory. Either Biology or Agricultural Science satisfies the third requirement. Every accepted grade must be C6 or better.',
        expected: 'REQUIREMENTS_MET',
    })),
];

export function getLibraryExample(id: string): {
    portal: any;
    result: any;
    adapter: any;
} {
    if (id.startsWith('storage-'))
        return getRequirementStorageExample(id.slice('storage-'.length));
    let portal: any = {
        student: { name: 'Amina Ibrahim' },
        requirements: [
            { key: 'English Language', subjects: ['English Language'] },
            { key: 'Mathematics', subjects: ['Mathematics'] },
            { key: 'Physics', subjects: ['Physics'] },
            { key: 'Chemistry', subjects: ['Chemistry'] },
            {
                key: 'Biology or Agricultural Science',
                subjects: ['Biology', 'Agricultural Science'],
            },
        ],
        minimum_grade: 'C6',
        minimum_credits: 5,
        max_sittings: 2,
    };
    let result: any = {
        success: true,
        data: {
            candidate: { name: 'Amina Ibrahim' },
            subjects: [
                { subject: 'English Language', grade: 'A1' },
                { subject: 'Mathematics', grade: 'B2' },
                { subject: 'Physics', grade: 'B3' },
                { subject: 'Chemistry', grade: 'C4' },
                { subject: 'Biology', grade: 'C5' },
            ],
        },
    };
    let adapter: any = {};
    if (id === 'either-subject')
        result.data.subjects[4].subject = 'Agricultural Science';
    if (id === 'name-mismatch') result.data.candidate.name = 'Chinedu Okafor';
    if (id === 'name-review') result.data.candidate.name = 'Amina';
    if (id === 'missing-subject') result.data.subjects.splice(3, 1);
    if (id === 'low-grade') result.data.subjects[3].grade = 'D7';
    if (id === 'duplicate') portal.result_used_by_another_student = true;
    if (id === 'pending')
        result = { success: true, result_status: 'processing', status: 202 };
    if (id === 'two-sittings') {
        const second = structuredClone(result.data);
        result.data.subjects[3].grade = 'D7';
        second.subjects = [{ subject: 'Chemistry', grade: 'B3' }];
        result = {
            success: true,
            data: {
                sittings: [
                    { sitting_no: 1, ...result.data },
                    { sitting_no: 2, ...second },
                ],
            },
        };
    }
    if (id === 'mapped-ids') {
        portal = {
            profile: { first_name: 'Amina', surname: 'Ibrahim' },
            programme: {
                required_subject_ids: [101, 102, 103, 104, [105, 106]],
            },
            subjects: [
                { subject_id: 101, subject_name: 'Eng. Lang.' },
                { subject_id: 102, subject_name: 'Mathema tics' },
                { subject_id: 103, subject_name: 'Physics' },
                { subject_id: 104, subject_name: 'Chemistry' },
                { subject_id: 105, subject_name: 'Biology' },
                { subject_id: 106, subject_name: 'Agricultural Science' },
            ],
        };
        adapter = {
            fields: {
                'student.name': ['profile.first_name', 'profile.surname'],
                requirements: 'programme.required_subject_ids',
                subject_catalog: 'subjects',
            },
            subject_fields: { id: 'subject_id', name: 'subject_name' },
            defaults: {
                minimum_grade: 'C6',
                minimum_credits: 5,
                max_sittings: 2,
            },
        };
    }
    return { portal, result, adapter };
}

export function getRequirementStorageExample(format: string): {
    portal: any;
    result: any;
    adapter: any;
} {
    const names = [
        'English Language',
        'Mathematics',
        ['Biology', 'Agricultural Science'],
    ];
    const numeric = ['ids', 'rows', 'csv'].includes(format);
    const subjects = {
        '101': 'English Language',
        '102': 'Mathematics',
        '105': 'Biology',
        '106': 'Agricultural Science',
    };
    let requirements: any = names;
    if (format === 'ids') requirements = [101, 102, [105, 106]];
    if (format === 'groups')
        requirements = {
            english_check: ['English Language'],
            maths_check: ['Mathematics'],
            science_choice: ['Biology', 'Agricultural Science'],
        };
    if (format === 'rows')
        requirements = [
            { rule_name: 'English check', allowed_subject_ids: [101] },
            { rule_name: 'Mathematics check', allowed_subject_ids: [102] },
            { rule_name: 'Science choice', allowed_subject_ids: [105, 106] },
        ];
    if (format === 'json') requirements = JSON.stringify(names);
    if (format === 'csv') requirements = '101,102,105/106';
    return {
        portal: {
            full_name: 'Amina Ibrahim',
            course_subjects: requirements,
            ...(numeric ? { subjects } : {}),
        },
        result: {
            success: true,
            data: {
                candidate: { name: 'Amina Ibrahim' },
                subjects: [
                    { subject: 'English Language', grade: 'B2' },
                    { subject: 'Mathematics', grade: 'C4' },
                    { subject: 'Agricultural Science', grade: 'C5' },
                ],
            },
        },
        adapter: {
            fields: {
                'student.name': 'full_name',
                requirements: 'course_subjects',
                ...(numeric ? { subject_catalog: 'subjects' } : {}),
            },
            defaults: { minimum_grade: 'C6', max_sittings: 2 },
            ...(format === 'rows'
                ? {
                      requirement_fields: {
                          key: 'rule_name',
                          subjects: 'allowed_subject_ids',
                      },
                  }
                : {}),
            ...(format === 'csv'
                ? { requirements_separator: ',', alternatives_separator: '/' }
                : {}),
        },
    };
}

function phpValue(value: any, depth = 0): string {
    if (value === null) return 'null';
    if (typeof value === 'boolean' || typeof value === 'number')
        return String(value);
    if (typeof value === 'string')
        return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
    const entries = Object.entries(value);
    if (!entries.length) return '[]';
    const indent = '    '.repeat(depth + 1);
    return `[\n${entries.map(([key, entry]) => `${indent}${Array.isArray(value) ? '' : `${phpValue(key)} => `}${phpValue(entry, depth + 1)},`).join('\n')}\n${'    '.repeat(depth)}]`;
}

export function libraryExampleCode(
    language: string,
    portal: any,
    result: any,
    adapter: any,
): string {
    if (language === 'php')
        return `<?php\nrequire_once __DIR__ . '/EaseVerifierResultEvaluator.php';\nuse EaseVerifier\\ResultEvaluator;\n\n$portal = ${phpValue(portal)};\n\n$verifiedResult = ${phpValue(result)};\n\n$adapter = ${phpValue(adapter)};\n\n$outcome = ResultEvaluator::evaluate($portal, $verifiedResult, $adapter);\necho json_encode($outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);`;
    return `import { ResultEvaluator } from './easeverifier-result-evaluator.mjs';\n\nconst portal = ${JSON.stringify(portal, null, 2)};\n\nconst verifiedResult = ${JSON.stringify(result, null, 2)};\n\nconst adapter = ${JSON.stringify(adapter, null, 2)};\n\nconst outcome = ResultEvaluator.evaluate(portal, verifiedResult, adapter);\nconsole.log(JSON.stringify(outcome, null, 2));`;
}

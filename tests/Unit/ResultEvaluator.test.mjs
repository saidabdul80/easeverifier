import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { ResultEvaluator } from '../../resources/libraries/easeverifier-result-evaluator.mjs';

const fixture = JSON.parse(
    readFileSync(
        new URL('../Fixtures/result-evaluator-cases.json', import.meta.url),
        'utf8',
    ),
);
const cases = fixture.cases.map((scenario) => {
    const input = structuredClone({
        portal: fixture.portal,
        result: fixture.result,
    });
    for (const [path, value] of Object.entries(scenario.set)) {
        const keys = path.split('.');
        let target = input;
        for (const key of keys.slice(0, -1)) target = target[key];
        target[keys.at(-1)] = value;
    }
    return { ...scenario, ...input };
});

for (const scenario of cases) {
    test(scenario.name, () => {
        const before = JSON.stringify([scenario.portal, scenario.result]);
        const outcome = ResultEvaluator.evaluate(
            scenario.portal,
            scenario.result,
        );
        for (const [key, value] of Object.entries(scenario.expected))
            assert.deepEqual(outcome[key], value);
        assert.equal(outcome.message, ResultEvaluator.messageFor(outcome.code));
        assert.equal(outcome.can_continue, outcome.code === 'REQUIREMENTS_MET');
        assert.equal(
            JSON.stringify([scenario.portal, scenario.result]),
            before,
        );
        if (
            ['RESULT_PENDING', 'RESULT_FAILED', 'INVALID_RESULT'].includes(
                outcome.code,
            )
        ) {
            assert.deepEqual(outcome.requirements.rows, []);
            assert.deepEqual(outcome.requirements.missing_subjects, []);
        }
    });
}

test('all public checks produce identical PHP and JavaScript outputs', () => {
    const php = spawnSync(
        'php',
        [
            '-r',
            `
        require $argv[1];
        $inputs = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $output = [];
        foreach ($inputs as $input) {
            try {
                $requirements = \\EaseVerifier\\ResultEvaluator::checkRequirements($input['portal'], $input['result']);
            } catch (InvalidArgumentException) {
                $requirements = ['error' => 'Invalid portal rules'];
            }
            $output[] = [
                'outcome' => \\EaseVerifier\\ResultEvaluator::evaluate($input['portal'], $input['result']),
                'normalized' => \\EaseVerifier\\ResultEvaluator::normalizeResult($input['result']),
                'best' => \\EaseVerifier\\ResultEvaluator::bestSubjects($input['result']),
                'ownership' => \\EaseVerifier\\ResultEvaluator::checkOwnership($input['portal'], $input['result']),
                'requirements' => $requirements,
            ];
        }
        echo json_encode($output, JSON_THROW_ON_ERROR);
    `,
            fileURLToPath(
                new URL(
                    '../../resources/libraries/EaseVerifierResultEvaluator.php',
                    import.meta.url,
                ),
            ),
        ],
        {
            input: JSON.stringify(cases),
            encoding: 'utf8',
            maxBuffer: 4 * 1024 * 1024,
        },
    );
    assert.equal(php.status, 0, php.stderr);
    const phpResults = JSON.parse(php.stdout);
    cases.forEach((scenario, index) => {
        let requirements;
        try {
            requirements = ResultEvaluator.checkRequirements(
                scenario.portal,
                scenario.result,
            );
        } catch {
            requirements = { error: 'Invalid portal rules' };
        }
        assert.deepEqual(
            {
                outcome: ResultEvaluator.evaluate(
                    scenario.portal,
                    scenario.result,
                ),
                normalized: ResultEvaluator.normalizeResult(scenario.result),
                best: ResultEvaluator.bestSubjects(scenario.result),
                ownership: ResultEvaluator.checkOwnership(
                    scenario.portal,
                    scenario.result,
                ),
                requirements,
            },
            phpResults[index],
            scenario.name,
        );
    });
});

test('normalization and grade helper contracts', () => {
    assert.equal(
        ResultEvaluator.normalizeSubject(' General Mathematics '),
        'mathematics',
    );
    assert.equal(ResultEvaluator.normalizeSubject('ICT'), 'computer studies');
    assert.equal(ResultEvaluator.gradeRank(' c6 '), 4);
    assert.equal(ResultEvaluator.gradeRank('WITHHELD'), 0);
    assert.equal(
        ResultEvaluator.messageFor('unknown'),
        'The result needs school review before continuing.',
    );
    assert.throws(() => ResultEvaluator.checkRequirements({}, {}), TypeError);
});

for (const scenario of fixture.subject_matches) {
    test(`subject match: ${scenario.name}`, () => {
        const match = ResultEvaluator.matchSubject(
            scenario.subject,
            scenario.catalogue,
            scenario.options,
        );
        for (const [key, value] of Object.entries(scenario.expected))
            assert.deepEqual(match[key], value);
        if (match.status === 'review') assert.equal(match.subject_id, null);
    });
}

for (const scenario of fixture.adapters) {
    test(`portal adapter: ${scenario.name}`, () => {
        const before = JSON.stringify(scenario);
        const outcome = ResultEvaluator.evaluate(
            scenario.source,
            scenario.result,
            scenario.adapter,
        );
        assert.equal(outcome.code, scenario.expected.code);
        assert.equal(JSON.stringify(scenario), before);
        if (outcome.code === 'CONFIGURATION_ERROR')
            assert.ok(outcome.configuration_errors.length);
    });
}

test('subject matching and structure adapters have identical PHP and JavaScript outputs', () => {
    const php = spawnSync(
        'php',
        [
            '-r',
            `
        require $argv[1];
        $fixture = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $output = ['matches' => [], 'adapters' => []];
        foreach ($fixture['subject_matches'] as $case) {
            $output['matches'][] = \\EaseVerifier\\ResultEvaluator::matchSubject($case['subject'], $case['catalogue'], $case['options']);
        }
        foreach ($fixture['adapters'] as $case) {
            $outcome = \\EaseVerifier\\ResultEvaluator::evaluate($case['source'], $case['result'], $case['adapter']);
            try { $adapted = \\EaseVerifier\\ResultEvaluator::adaptPortal($case['source'], $case['adapter']); }
            catch (InvalidArgumentException $error) { $adapted = ['error' => $error->getMessage()]; }
            $output['adapters'][] = ['outcome' => $outcome, 'adapted' => $adapted];
        }
        echo json_encode($output, JSON_THROW_ON_ERROR);
    `,
            fileURLToPath(
                new URL(
                    '../../resources/libraries/EaseVerifierResultEvaluator.php',
                    import.meta.url,
                ),
            ),
        ],
        {
            input: JSON.stringify(fixture),
            encoding: 'utf8',
            maxBuffer: 4 * 1024 * 1024,
        },
    );
    assert.equal(php.status, 0, php.stderr);
    const actual = JSON.parse(php.stdout);
    fixture.subject_matches.forEach((scenario, index) =>
        assert.deepEqual(
            ResultEvaluator.matchSubject(
                scenario.subject,
                scenario.catalogue,
                scenario.options,
            ),
            actual.matches[index],
            scenario.name,
        ),
    );
    fixture.adapters.forEach((scenario, index) => {
        let adapted;
        try {
            adapted = ResultEvaluator.adaptPortal(
                scenario.source,
                scenario.adapter,
            );
        } catch (error) {
            adapted = { error: error.message };
        }
        assert.deepEqual(
            {
                outcome: ResultEvaluator.evaluate(
                    scenario.source,
                    scenario.result,
                    scenario.adapter,
                ),
                adapted,
            },
            actual.adapters[index],
            scenario.name,
        );
    });
});

test('custom portal and result adapter functions handle arbitrary application logic', () => {
    assert.equal(
        ResultEvaluator.evaluate(
            { custom_record: fixture.portal },
            fixture.result,
            (source) => source.custom_record,
        ).code,
        'REQUIREMENTS_MET',
    );
    assert.equal(
        ResultEvaluator.evaluate(
            fixture.portal,
            { cache: fixture.result },
            { result_adapter: (response) => response.cache },
        ).code,
        'REQUIREMENTS_MET',
    );
});

test('simple, mapped, and custom adapter documentation examples work with existing portal records', async () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/ResultEvaluationLibraries.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const { runInNewContext } = await import('node:vm');
    const libraryUrl = new URL(
        '../../resources/libraries/easeverifier-result-evaluator.mjs',
        import.meta.url,
    ).href;
    const phpPath = fileURLToPath(
        new URL(
            '../../resources/libraries/EaseVerifierResultEvaluator.php',
            import.meta.url,
        ),
    );
    const subjects = fixture.result.data.subjects.map((row, index) => ({
        subject_id: 101 + index,
        subject_name: row.subject,
    }));
    const records = {
        quick: {
            first_name: 'Amina',
            surname: 'Ibrahim',
            required_subjects: [101, 102, 103, 104, 105],
            all_subjects: subjects,
        },
        custom: {
            student: { legal_name: 'Amina Ibrahim' },
            programme: {
                required_labels: subjects.map((row) => row.subject_name),
            },
        },
    };
    records.simple = records.quick;
    for (const variant of ['simple', 'quick', 'custom']) {
        const phpLiteral = source.match(
            new RegExp(`const ${variant}Php = (\x60[\\s\\S]*?\x60);`),
        )[1];
        const jsLiteral = source.match(
            new RegExp(`const ${variant}Js = (\x60[\\s\\S]*?\x60);`),
        )[1];
        const phpExample = runInNewContext(phpLiteral)
            .replace('<?php', '')
            .replace(
                "__DIR__ . '/EaseVerifierResultEvaluator.php'",
                '$argv[1]',
            );
        const phpPrefix =
            variant === 'custom'
                ? 'require $argv[1]; use EaseVerifier\\ResultEvaluator;'
                : '';
        const php = spawnSync(
            'php',
            [
                '-r',
                `${phpPrefix} $portalRecord = json_decode($argv[2], true); $verifiedResult = json_decode($argv[3], true); ${phpExample}\necho "\\n" . json_encode($outcome, JSON_THROW_ON_ERROR);`,
                phpPath,
                JSON.stringify(records[variant]),
                JSON.stringify(fixture.result),
            ],
            { encoding: 'utf8' },
        );
        assert.equal(php.status, 0, php.stderr);
        const phpOutcome = JSON.parse(php.stdout.trim().split('\n').at(-1));
        const jsExample = runInNewContext(jsLiteral).replace(
            "'./easeverifier-result-evaluator.mjs'",
            JSON.stringify(libraryUrl),
        );
        const jsPrefix =
            variant === 'custom'
                ? `import { ResultEvaluator } from ${JSON.stringify(libraryUrl)};`
                : '';
        const js = spawnSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                `${jsPrefix} const portalRecord = ${JSON.stringify(records[variant])}; const verifiedResult = ${JSON.stringify(fixture.result)}; ${jsExample}\nconsole.log(JSON.stringify(outcome));`,
            ],
            { encoding: 'utf8' },
        );
        assert.equal(js.status, 0, js.stderr);
        assert.equal(phpOutcome.code, 'REQUIREMENTS_MET');
        assert.deepEqual(
            JSON.parse(js.stdout.trim().split('\n').at(-1)),
            phpOutcome,
        );
    }
});

test('copyable PHP and JavaScript documentation examples execute successfully', async () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/ResultEvaluationLibraries.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const phpLiteral = source.match(/const phpExample = (`[\s\S]*?`);/)[1];
    const jsLiteral = source.match(/const jsExample = (`[\s\S]*?`);/)[1];
    const { runInNewContext } = await import('node:vm');
    const phpExample = runInNewContext(phpLiteral);
    const jsExample = runInNewContext(jsLiteral);
    const phpLibrary = fileURLToPath(
        new URL(
            '../../resources/libraries/EaseVerifierResultEvaluator.php',
            import.meta.url,
        ),
    );
    const phpCode = phpExample
        .replace('<?php', '')
        .replace("__DIR__ . '/EaseVerifierResultEvaluator.php'", '$argv[1]');
    const php = spawnSync(
        'php',
        [
            '-r',
            `${phpCode}\necho "\\n" . json_encode($outcome, JSON_THROW_ON_ERROR);`,
            phpLibrary,
        ],
        { encoding: 'utf8' },
    );
    assert.equal(php.status, 0, php.stderr);
    const phpOutcome = JSON.parse(php.stdout.trim().split('\n').at(-1));
    const jsCode = jsExample.replace(
        "'./easeverifier-result-evaluator.mjs'",
        JSON.stringify(
            new URL(
                '../../resources/libraries/easeverifier-result-evaluator.mjs',
                import.meta.url,
            ).href,
        ),
    );
    const js = spawnSync(
        process.execPath,
        [
            '--input-type=module',
            '-e',
            `${jsCode}\nconsole.log(JSON.stringify(outcome));`,
        ],
        { encoding: 'utf8' },
    );
    assert.equal(js.status, 0, js.stderr);
    const jsOutcome = JSON.parse(js.stdout.trim().split('\n').at(-1));
    assert.equal(phpOutcome.code, 'REQUIREMENTS_MET');
    assert.deepEqual(jsOutcome, phpOutcome);
    assert.equal(phpOutcome.requirements.rows.length, 5);
});

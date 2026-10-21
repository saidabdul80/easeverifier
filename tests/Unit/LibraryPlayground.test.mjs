import { build } from 'esbuild';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { nextTick } from 'vue';
import { ResultEvaluator } from '../../resources/libraries/easeverifier-result-evaluator.mjs';
import {
    loadVueComponent,
    mountVueComponent,
} from '../Fixtures/vueComponentHarness.mjs';

const data = await build({
    entryPoints: [
        fileURLToPath(
            new URL(
                '../../resources/js/lib/resultLibraryExamples.ts',
                import.meta.url,
            ),
        ),
    ],
    bundle: true,
    write: false,
    format: 'esm',
    platform: 'node',
});
const {
    getLibraryExample,
    libraryExamples,
    libraryExampleCode,
    getRequirementStorageExample,
    requirementStorageFormats,
} = await import(
    `data:text/javascript;base64,${Buffer.from(data.outputFiles[0].text).toString('base64')}`
);
const Playground = await loadVueComponent(
    'resources/js/components/LibraryPlayground.vue',
);
const Guide = await loadVueComponent(
    'resources/js/components/ResultEvaluationLibraries.vue',
);
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
globalThis.document = {
    activeElement: null,
    cookie: 'XSRF-TOKEN=example%3Dtoken',
};

for (const example of libraryExamples) {
    test(`${example.title}: both downloadable example languages return the real expected outcome`, () => {
        const { portal, result, adapter } = getLibraryExample(example.id);
        const expected = ResultEvaluator.evaluate(portal, result, adapter);
        assert.equal(expected.code, example.expected);
        const php = spawnSync(
            'php',
            [
                '-r',
                libraryExampleCode('php', portal, result, adapter)
                    .replace('<?php', '')
                    .replace(
                        "__DIR__ . '/EaseVerifierResultEvaluator.php'",
                        '$argv[1]',
                    ),
                phpPath,
            ],
            { encoding: 'utf8' },
        );
        assert.equal(php.status, 0, php.stderr);
        assert.deepEqual(JSON.parse(php.stdout), expected);
        const js = spawnSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                libraryExampleCode(
                    'javascript',
                    portal,
                    result,
                    adapter,
                ).replace(
                    "'./easeverifier-result-evaluator.mjs'",
                    JSON.stringify(libraryUrl),
                ),
            ],
            { encoding: 'utf8' },
        );
        assert.equal(js.status, 0, js.stderr);
        assert.deepEqual(JSON.parse(js.stdout), expected);
    });
}

test('Run evaluates edited inputs, clears stale output, and reset restores the scenario', async () => {
    const view = mountVueComponent(Playground, { language: 'javascript' });
    assert.ok(view.text().includes('Your result appears here'));
    await view.click('▶ Run');
    assert.ok(view.text().includes('REQUIREMENTS_MET'));
    await view.click('Reset example');
    assert.ok(view.text().includes('Your result appears here'));
    await view.click('Verified result');
    const result = getLibraryExample('name-mismatch').result;
    await view.model('Edit result JSON', JSON.stringify(result));
    assert.ok(view.text().includes('Your result appears here'));
    await view.click('▶ Run');
    assert.ok(view.text().includes('OWNERSHIP_MISMATCH'));
    await view.click('Reset example');
    await view.click('▶ Run');
    assert.ok(view.text().includes('REQUIREMENTS_MET'));
    view.app.unmount();
});

test('invalid JSON gives a visible error without making a PHP request', async () => {
    const original = globalThis.fetch;
    globalThis.fetch = () => {
        throw new Error('Must not send invalid input');
    };
    try {
        const view = mountVueComponent(Playground, { language: 'php' });
        await view.click('Portal data');
        await view.model('Edit portal JSON', '{broken');
        await view.click('▶ Run');
        assert.ok(view.text().includes('portal: use valid JSON'));
        view.app.unmount();
    } finally {
        globalThis.fetch = original;
    }
});

test('PHP Run sends the displayed inputs with CSRF protection and displays the server outcome', async () => {
    const original = globalThis.fetch;
    const input = getLibraryExample('ready');
    let request;
    globalThis.fetch = async (url, options) => {
        request = { url, options };
        return {
            ok: true,
            status: 200,
            json: async () => ({
                outcome: ResultEvaluator.evaluate(
                    input.portal,
                    input.result,
                    input.adapter,
                ),
            }),
        };
    };
    try {
        const view = mountVueComponent(Playground, { language: 'php' });
        await view.click('▶ Run');
        assert.equal(request.url, '/documentation/libraries/run');
        assert.equal(request.options.headers['X-XSRF-TOKEN'], 'example=token');
        assert.deepEqual(
            JSON.parse(JSON.parse(request.options.body).portal),
            input.portal,
        );
        assert.ok(view.text().includes('REQUIREMENTS_MET'));
        await view.click('JavaScript');
        assert.ok(view.text().includes('Your result appears here'));
        view.app.unmount();
    } finally {
        globalThis.fetch = original;
    }
});

for (const [status, message] of [
    [419, 'session expired'],
    [429, 'Too many PHP runs'],
    [500, 'could not run'],
]) {
    test(`PHP response ${status} gives a useful retry message`, async () => {
        const original = globalThis.fetch;
        globalThis.fetch = async () => ({
            ok: false,
            status,
            json: async () => ({}),
        });
        try {
            const view = mountVueComponent(Playground, { language: 'php' });
            await view.click('▶ Run');
            assert.ok(view.text().includes(message));
            view.app.unmount();
        } finally {
            globalThis.fetch = original;
        }
    });
}

test('changing a pending PHP example prevents a late response from replacing the new example', async () => {
    const original = globalThis.fetch;
    let resolve;
    globalThis.fetch = () =>
        new Promise((done) => {
            resolve = done;
        });
    try {
        const view = mountVueComponent(Playground, { language: 'php' });
        const running = view.click('▶ Run');
        await view.model('Student scenario', 'name-mismatch');
        resolve({
            ok: true,
            status: 200,
            json: async () => ({ outcome: { code: 'STALE_RESPONSE' } }),
        });
        await running;
        assert.ok(!view.text().includes('STALE_RESPONSE'));
        assert.ok(view.text().includes('Your result appears here'));
        view.app.unmount();
    } finally {
        globalThis.fetch = original;
    }
});

test('guide navigation, topic search, theme, and ID example shortcut work', async () => {
    const view = mountVueComponent(Guide, {});
    await view.click('Connect your portal');
    await view.click('Try the database ID example →');
    await view.click('▶ Run');
    assert.ok(view.text().includes('REQUIREMENTS_MET'));
    await view.click('Use dark theme');
    assert.ok(
        view
            .nodes()
            .some((node) => String(node.props.class).includes('guide-dark')),
    );
    await view.model('Search library documentation', 'spelling');
    assert.ok(
        view
            .nodes()
            .some(
                (node) =>
                    node.type === 'button' &&
                    view.text(node).trim() === 'Match subjects',
            ),
    );
    assert.ok(
        !view
            .nodes()
            .some(
                (node) =>
                    node.type === 'button' &&
                    view.text(node).trim() === 'Quick start',
            ),
    );
    await nextTick();
    view.app.unmount();
});

for (const format of requirementStorageFormats) {
    test(`${format.title} uses the same rule: one science is enough, a missing or low-grade science fails`, () => {
        const { portal, result, adapter } = getRequirementStorageExample(
            format.id,
        );
        assert.equal(
            ResultEvaluator.evaluate(portal, result, adapter).code,
            'REQUIREMENTS_MET',
        );
        result.data.subjects[2].grade = 'D7';
        assert.equal(
            ResultEvaluator.evaluate(portal, result, adapter).code,
            'GRADES_BELOW_REQUIREMENT',
        );
        result.data.subjects.pop();
        assert.equal(
            ResultEvaluator.evaluate(portal, result, adapter).code,
            'SUBJECTS_MISSING',
        );
    });
}

test('the guided mapping example shows one format, explains its IDs, and runs that exact data', async () => {
    const RequirementGuide = await loadVueComponent(
        'resources/js/components/PortalRequirementGuide.vue',
    );
    const view = mountVueComponent(RequirementGuide, {
        language: 'javascript',
    });
    await nextTick();
    assert.equal(
        view.nodes().filter((node) => node.type === 'select').length,
        1,
    );
    assert.ok(view.text().includes('Either one is enough'));
    await view.model('Your portal stores', 'ids');
    assert.ok(view.text().includes('What the IDs mean in this example'));
    assert.ok(
        view
            .text()
            .includes('Subject ID always means your portal’s subject ID.'),
    );
    assert.ok(view.text().includes('→ Agricultural Science'));
    await view.click('▶ Run');
    assert.ok(view.text().includes('REQUIREMENTS_MET'));
    await view.model('Your portal stores', 'csv');
    assert.ok(view.text().includes('Your result appears here'));
    assert.ok(view.text().includes('The slash means either'));
    await view.click('▶ Run');
    assert.ok(view.text().includes('REQUIREMENTS_MET'));
    await view.click('Verified result');
    const { result } = getRequirementStorageExample('csv');
    result.data.subjects[2].grade = 'D7';
    await view.model('Edit result JSON', JSON.stringify(result));
    await view.click('▶ Run');
    assert.ok(view.text().includes('GRADES_BELOW_REQUIREMENT'));
    view.app.unmount();
});

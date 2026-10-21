import { compileScript, parse } from '@vue/compiler-sfc';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { createRenderer, h, nextTick, ref } from 'vue';

const source = readFileSync(
    new URL('../../resources/js/components/FoldableCode.vue', import.meta.url),
    'utf8',
);
const { descriptor } = parse(source);
const compiled = compileScript(descriptor, {
    id: 'foldable-code-test',
    inlineTemplate: true,
});
const { outputText } = ts.transpileModule(compiled.content, {
    compilerOptions: {
        target: ts.ScriptTarget.ES2022,
        module: ts.ModuleKind.ESNext,
    },
});
const { default: FoldableCode } = await import(
    `data:text/javascript;base64,${Buffer.from(
        outputText.replace(
            /from ['"]vue['"]/g,
            `from "${import.meta.resolve('vue')}"`,
        ),
    ).toString('base64')}`
);

// Exercise the rendered controls with Vue's renderer, without a browser or database.
function mount(code) {
    const node = (type, text = '') => ({ type, text, children: [], props: {} });
    const renderer = createRenderer({
        createElement: node,
        createText: (text) => node('text', text),
        createComment: () => node('comment'),
        setText: (node, text) => {
            node.text = text;
        },
        setElementText: (node, text) => {
            node.text = text;
            node.children = [];
        },
        patchProp: (node, key, previous, value) => {
            node.props[key] = value;
        },
        parentNode: (node) => node.parent,
        nextSibling: (node) =>
            node.parent?.children[node.parent.children.indexOf(node) + 1],
        insert(child, parent, anchor) {
            if (child.parent) this.remove(child);
            const index = anchor ? parent.children.indexOf(anchor) : -1;
            parent.children.splice(
                index < 0 ? parent.children.length : index,
                0,
                child,
            );
            child.parent = parent;
        },
        remove(child) {
            const siblings = child.parent?.children;
            if (siblings) siblings.splice(siblings.indexOf(child), 1);
            child.parent = null;
        },
    });
    const root = node('root');
    const input = ref(code);
    const app = renderer.createApp({
        render: () => h(FoldableCode, { code: input.value }),
    });
    app.mount(root);
    function nodes(element = root) {
        return [element, ...element.children.flatMap((child) => nodes(child))];
    }
    function text(element) {
        return element.text + element.children.map(text).join('');
    }
    return {
        input,
        app,
        buttons: () => nodes().filter((node) => node.type === 'button'),
        lines: () =>
            nodes()
                .filter((node) => node.type === 'code')
                .map(text),
        async click(label) {
            const button = this.buttons().find(
                (node) =>
                    (node.props['aria-label'] || text(node)).trim() === label,
            );
            assert.ok(button, `Missing control: ${label}`);
            button.props.onClick();
            await nextTick();
        },
    };
}

for (const [language, code] of [
    [
        'PHP',
        "$portal = [\n    'profile' => [\n        'name' => 'Amina',\n    ],\n];",
    ],
    [
        'JavaScript',
        "const portal = {\n    profile: {\n        name: 'Amina',\n    },\n};",
    ],
]) {
    test(`${language} objects fold independently and expand back to the exact example`, async () => {
        const view = mount(code);
        assert.equal(view.lines().length, 3);
        assert.ok(view.lines()[1].includes('…'));
        await view.click('Expand block at line 2');
        assert.deepEqual(view.lines(), code.split('\n'));
        await view.click('Collapse block at line 2');
        await view.click('Collapse block at line 1');
        assert.equal(view.lines().length, 1);
        await view.click('Expand block at line 1');
        assert.equal(view.lines().length, 3);
        await view.click('Expand all');
        assert.deepEqual(view.lines(), code.split('\n'));
        await view.click('Collapse all');
        assert.equal(view.lines().length, 1);
        view.app.unmount();
    });
}

test('quoted, escaped, inline, and commented brackets do not corrupt folding', async () => {
    const code = String.raw`const value = {
    text: "[ } \\\" {",
    list: [101, 102], // [ {
    /* [
       } */
    nested: {
        text: 'literal ] }',
    },
};`;
    const view = mount(code);
    assert.equal(
        view.buttons().filter((node) => 'aria-expanded' in node.props).length,
        2,
    );
    await view.click('Expand all');
    assert.deepEqual(view.lines(), code.split('\n'));
    await view.click('Collapse all');
    assert.equal(view.lines().length, 1);
    assert.ok(view.lines()[0].endsWith('};'));
    view.app.unmount();
});

test('changing language or content resets folds to the new example', async () => {
    const view = mount('const portal = {\n    name: "Amina",\n};');
    await view.click('Collapse all');
    view.input.value =
        '$record = [\n    "profile" => [\n        "id" => 12,\n    ],\n];';
    await nextTick();
    assert.equal(view.lines().length, 3);
    await view.click('Expand all');
    assert.deepEqual(view.lines(), view.input.value.split('\n'));
    view.app.unmount();
});

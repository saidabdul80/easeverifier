import { compileScript, parse } from '@vue/compiler-sfc';
import { build } from 'esbuild';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRenderer, h, nextTick, ref } from 'vue';

const root = fileURLToPath(new URL('../../', import.meta.url));

export async function loadVueComponent(filename) {
    const output = await build({
        entryPoints: [path.join(root, filename)],
        write: false,
        bundle: true,
        format: 'esm',
        platform: 'node',
        plugins: [
            {
                name: 'vue-unit-test',
                setup(builder) {
                    builder.onResolve({ filter: /^vue$/ }, () => ({
                        path: import.meta.resolve('vue'),
                        external: true,
                    }));
                    builder.onResolve({ filter: /^@\// }, async (args) =>
                        builder.resolve(
                            path.join(root, 'resources/js', args.path.slice(2)),
                            { resolveDir: args.resolveDir, kind: args.kind },
                        ),
                    );
                    builder.onLoad({ filter: /\.vue$/ }, (args) => {
                        const { descriptor } = parse(
                            readFileSync(args.path, 'utf8'),
                        );
                        return {
                            contents: compileScript(descriptor, {
                                id: path.basename(args.path),
                                inlineTemplate: true,
                                templateOptions: {
                                    compilerOptions: { hoistStatic: false },
                                },
                            }).content,
                            loader: 'ts',
                            resolveDir: path.dirname(args.path),
                        };
                    });
                },
            },
        ],
    });
    return (
        await import(
            `data:text/javascript;base64,${Buffer.from(output.outputFiles[0].text).toString('base64')}`
        )
    ).default;
}

export function mountVueComponent(component, initialProps) {
    const makeNode = (type, text = '') => ({
        type,
        text,
        children: [],
        props: {},
        style: {},
        tagName: type.toUpperCase(),
        get options() {
            return this.children.filter((node) => node.type === 'option');
        },
        addEventListener() {},
        removeEventListener() {},
        setAttribute(name, value) {
            this.props[name] = value;
        },
        removeAttribute(name) {
            delete this.props[name];
        },
        scrollIntoView() {},
    });
    const remove = (child) => {
        if (child.parent)
            child.parent.children.splice(
                child.parent.children.indexOf(child),
                1,
            );
        child.parent = null;
    };
    const renderer = createRenderer({
        createElement: makeNode,
        createText: (text) => makeNode('text', text),
        createComment: () => makeNode('comment'),
        setText: (node, text) => {
            node.text = text;
        },
        setElementText: (node, text) => {
            node.text = text;
            node.children = [];
        },
        patchProp: (node, key, previous, value) => {
            node.props[key] = value;
            if (key === 'value') node.value = value;
        },
        parentNode: (node) => node.parent,
        nextSibling: (node) =>
            node.parent?.children[node.parent.children.indexOf(node) + 1],
        insert(child, parent, anchor) {
            if (child.parent) remove(child);
            const index = anchor ? parent.children.indexOf(anchor) : -1;
            parent.children.splice(
                index < 0 ? parent.children.length : index,
                0,
                child,
            );
            child.parent = parent;
        },
        remove,
    });
    const rootNode = makeNode('root');
    const props = ref(initialProps);
    const app = renderer.createApp({
        render: () =>
            h(component, {
                ...props.value,
                'onUpdate:language': (language) => {
                    props.value = { ...props.value, language };
                },
            }),
    });
    app.config.warnHandler = (warning) => {
        if (!warning.startsWith('Failed to resolve component: v-'))
            throw new Error(warning);
    };
    app.mount(rootNode);
    function nodes(node = rootNode) {
        return [node, ...node.children.flatMap((child) => nodes(child))];
    }
    function text(node = rootNode) {
        return node.text + node.children.map((child) => text(child)).join('');
    }
    return {
        app,
        props,
        nodes,
        text,
        async click(label) {
            const target = nodes().find(
                (node) =>
                    node.type === 'button' &&
                    (node.props['aria-label'] || text(node)).trim() === label,
            );
            if (!target) throw new Error(`Missing button: ${label}`);
            await target.props.onClick();
            await nextTick();
        },
        async model(label, value) {
            const node = nodes().find(
                (node) => node.props['aria-label'] === label,
            );
            if (!node) throw new Error(`Missing input: ${label}`);
            node.props['onUpdate:modelValue'](value);
            await nextTick();
        },
    };
}

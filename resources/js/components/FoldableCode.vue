<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps<{ code: string; label?: string }>();

type Fold = { start: number; end: number; column: number; depth: number };

// Only structural brackets create folds; brackets inside strings/comments are text.
const document = computed(() => {
    const lines = props.code.split('\n');
    const folds = new Map<number, Fold>();
    const stack: { bracket: string; line: number; depth: number }[] = [];
    let quote = '';
    let blockComment = false;
    let escaped = false;

    lines.forEach((line, lineIndex) => {
        for (let column = 0; column < line.length; column++) {
            const character = line[column];
            const next = line[column + 1];
            if (blockComment) {
                if (character === '*' && next === '/') {
                    blockComment = false;
                    column++;
                }
                continue;
            }
            if (quote) {
                if (escaped) escaped = false;
                else if (character === '\\') escaped = true;
                else if (character === quote) quote = '';
                continue;
            }
            if (character === '/' && next === '/') break;
            if (character === '/' && next === '*') {
                blockComment = true;
                column++;
                continue;
            }
            if (['"', "'", '`'].includes(character)) {
                quote = character;
                continue;
            }
            if (character === '[' || character === '{') {
                stack.push({
                    bracket: character,
                    line: lineIndex,
                    depth: stack.length,
                });
            } else if (character === ']' || character === '}') {
                const opening = stack.at(-1);
                if (
                    !opening ||
                    (opening.bracket === '[' ? ']' : '}') !== character
                )
                    continue;
                stack.pop();
                if (lineIndex > opening.line) {
                    // When blocks share an opening line, fold the outermost one.
                    folds.set(opening.line, {
                        start: opening.line,
                        end: lineIndex,
                        column,
                        depth: opening.depth,
                    });
                }
            }
        }
    });
    return { lines, folds };
});

const collapsed = ref(new Set<number>());
watch(
    () => props.code,
    () => {
        collapsed.value = new Set(
            [...document.value.folds.values()]
                .filter((fold) => fold.depth > 0)
                .map((fold) => fold.start),
        );
    },
    { immediate: true },
);

const visibleLines = computed(() => {
    const { lines, folds } = document.value;
    const visible = [];
    for (let index = 0; index < lines.length; index++) {
        const fold = folds.get(index);
        const isCollapsed = !!fold && collapsed.value.has(index);
        visible.push({
            number: index + 1,
            text: lines[index],
            fold,
            isCollapsed,
            closing: isCollapsed ? lines[fold.end].slice(fold.column) : '',
        });
        if (isCollapsed) index = fold.end;
    }
    return visible;
});

function toggle(line: number): void {
    if (collapsed.value.has(line)) collapsed.value.delete(line);
    else collapsed.value.add(line);
}
</script>

<template>
    <div class="foldable-code">
        <div class="code-toolbar">
            <span>{{ label || 'Code example' }} · Fold with the arrows</span>
            <div class="code-actions">
                <button type="button" @click="collapsed.clear()">
                    Expand all
                </button>
                <button
                    type="button"
                    @click="collapsed = new Set(document.folds.keys())"
                >
                    Collapse all
                </button>
            </div>
        </div>
        <div
            class="code-scroll"
            tabindex="0"
            :aria-label="label || 'Code example'"
        >
            <div class="code-lines">
                <div
                    v-for="line in visibleLines"
                    :key="line.number"
                    class="code-line"
                >
                    <button
                        v-if="line.fold"
                        type="button"
                        class="fold-toggle"
                        :aria-expanded="!line.isCollapsed"
                        :aria-label="`${line.isCollapsed ? 'Expand' : 'Collapse'} block at line ${line.number}`"
                        :title="`${line.isCollapsed ? 'Expand' : 'Collapse'} block`"
                        @click="toggle(line.number - 1)"
                    >
                        {{ line.isCollapsed ? '▸' : '▾' }}
                    </button>
                    <span v-else class="fold-spacer" aria-hidden="true"></span>
                    <code
                        >{{ line.text
                        }}<template v-if="line.isCollapsed"
                            ><button
                                type="button"
                                class="fold-preview"
                                :aria-label="`Expand ${line.fold!.end - line.fold!.start} hidden lines`"
                                @click="toggle(line.number - 1)"
                            >
                                …</button
                            >{{ line.closing }}</template
                        ></code
                    >
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.foldable-code {
    overflow: hidden;
    border-radius: 6px;
    background: #212121;
    color: #81c784;
}
.code-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    padding: 10px 16px;
    border-bottom: 1px solid #444;
    color: #ccc;
    font-size: 12px;
}
.code-actions {
    display: flex;
    gap: 16px;
}
button {
    cursor: pointer;
}
button:hover {
    color: #fff;
}
button:focus-visible,
.code-scroll:focus-visible {
    outline: 2px solid #90caf9;
    outline-offset: -2px;
}
.code-scroll {
    max-height: 560px;
    overflow: auto;
    padding: 12px 8px;
}
.code-lines {
    min-width: max-content;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.8;
}
.code-line {
    display: flex;
    min-height: 22px;
}
.code-line code {
    font: inherit;
    white-space: pre;
}
.fold-toggle,
.fold-spacer {
    flex: 0 0 28px;
    width: 28px;
    user-select: none;
}
.fold-toggle {
    color: #bbb;
}
.fold-preview {
    border-radius: 3px;
    background: #3a3a3a;
    color: #90caf9;
    font: inherit;
}
</style>

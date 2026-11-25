<script setup lang="ts">
import { computed, ref } from 'vue';

type Id = number | string;
export type PickerOption = { id: Id; name: string };

/** Pick several options by typing; picked ones show as removable chips. */
const model = defineModel<Id[]>({ required: true });
const props = defineProps<{ options: PickerOption[]; placeholder?: string; invalid?: boolean }>();

const query = ref('');
const open = ref(false);
const active = ref(0);

const same = (a: Id, b: Id) => String(a) === String(b);
const picked = computed(() => model.value.map(id => props.options.find(o => same(o.id, id)) ?? { id, name: `#${id}` }));
const matches = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.options.filter(o => !model.value.some(id => same(id, o.id)) && (!q || o.name.toLowerCase().includes(q))).slice(0, 50);
});

const add = (o: PickerOption) => {
    model.value = [...model.value, o.id];
    query.value = '';
    active.value = 0;
};
const remove = (id: Id) => (model.value = model.value.filter(v => !same(v, id)));
const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
        active.value = Math.min(active.value + 1, matches.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Enter' && matches.value[active.value]) {
        add(matches.value[active.value]);
    } else if (e.key === 'Backspace' && !query.value && model.value.length) {
        model.value = model.value.slice(0, -1);
    } else if (e.key === 'Escape') {
        open.value = false;

        return;
    } else {
        return;
    }

    e.preventDefault();
};
</script>

<template>
    <div
        class="picker"
        :class="{ invalid }"
        @focusout="open = false"
    >
        <div class="box">
            <span
                v-for="o in picked"
                :key="o.id"
                class="chip"
            >{{ o.name }}<button
                type="button"
                :aria-label="`Remove ${o.name}`"
                @mousedown.prevent
                @click="remove(o.id)"
            ><i class="ki-outline ki-cross" /></button></span>
            <input
                v-model="query"
                type="text"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open"
                :placeholder="picked.length ? '' : placeholder ?? 'Search…'"
                @focus="open = true"
                @input="open = true; active = 0"
                @keydown="onKey"
            >
        </div>
        <ul
            v-if="open && matches.length"
            class="menu-list"
            role="listbox"
        >
            <li
                v-for="(o, i) in matches"
                :key="o.id"
                role="option"
                :aria-selected="i === active"
                :class="{ on: i === active }"
                @mousedown.prevent="add(o)"
                @mouseenter="active = i"
            >{{ o.name }}</li>
        </ul>
    </div>
</template>

<style scoped>
.picker { position: relative; }
.box { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; min-height: 38px; padding: 4px 6px; border: 1px solid #dbe4ea; border-radius: 7px; background: #fff; }
.picker:focus-within .box { border-color: #2aa2a9; box-shadow: 0 0 0 3px rgba(42, 162, 169, .15); }
.picker.invalid .box { border-color: #d9534f; }
.box input { flex: 1; min-width: 110px; padding: 3px 4px; border: 0; outline: 0; font-size: 13px; }
.chip { display: inline-flex; align-items: center; gap: 3px; padding: 2px 3px 2px 9px; border-radius: 6px; background: #e2f2f2; color: #135e64; font-size: 12px; font-weight: 600; }
.chip button { display: grid; place-items: center; width: 18px; height: 18px; border: 0; border-radius: 4px; background: none; color: inherit; cursor: pointer; }
.chip button:hover { background: rgba(19, 94, 100, .14); }
.menu-list { position: absolute; z-index: 5; inset-inline: 0; top: calc(100% + 4px); max-height: 230px; margin: 0; padding: 4px; overflow-y: auto; list-style: none; border: 1px solid #e5edf1; border-radius: 8px; background: #fff; box-shadow: 0 12px 28px rgba(28, 65, 89, .12); }
.menu-list li { padding: 7px 10px; border-radius: 6px; font-size: 13px; cursor: pointer; }
.menu-list li.on { background: #f0f8f9; color: #135e64; }
</style>

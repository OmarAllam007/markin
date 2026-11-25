<script setup lang="ts">
import { computed, ref } from 'vue';
import { index as approvalRolesIndex } from '@/routes/ticketing/admin/approval-roles';

export type ApprovalRoleOption = { id: number; name: string; employee: { id: number; english_name: string } | null };

/** null = use the level above; [] = no approvals on this level. */
const steps = defineModel<string[] | null>({ required: true });

const props = defineProps<{
    roles: ApprovalRoleOption[];
    inherited: string[] | null;
    /** Categories have no level above. */
    topLevel: boolean;
    errors: Record<string, string>;
}>();

const options = computed(() => [
    { value: 'direct_manager', label: 'Direct manager', holder: "Set on each employee" },
    { value: 'department_manager', label: 'Department manager', holder: "The manager of the requester's department" },
    ...props.roles.map(r => ({ value: `role:${r.id}`, label: r.name, holder: r.employee?.english_name ?? null })),
]);
const option = (value: string) => options.value.find(o => o.value === value) ?? { value, label: 'Deleted role', holder: null };
const available = computed(() => options.value.filter(o => !steps.value?.includes(o.value)));

const adding = ref('');
const add = () => {
    if (adding.value) {
        steps.value = [...(steps.value ?? []), adding.value];
    }

    adding.value = '';
};
const move = (i: number, by: number) => {
    const list = [...steps.value!];
    [list[i], list[i + by]] = [list[i + by], list[i]];
    steps.value = list;
};
const remove = (i: number) => (steps.value = steps.value!.filter((_, j) => j !== i));

const stepError = computed(() => Object.entries(props.errors).find(([k]) => k.startsWith('approval_steps'))?.[1]);
</script>

<template>
    <div class="card mb-6">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title flex-column align-items-start">
                <span class="fw-bold">Approvals</span>
                <span class="text-muted fs-7 fw-normal mt-1">Each step is a stage, in order. When a request is submitted, every role is matched to the person who holds it.</span>
            </h3>
            <div class="card-toolbar">
                <button
                    v-if="steps === null"
                    type="button"
                    class="btn btn-sm btn-light-primary"
                    @click="steps = [...(inherited ?? [])]"
                >{{ topLevel ? 'Add approvals' : 'Set steps for this level' }}</button>
                <button
                    v-else-if="!topLevel"
                    type="button"
                    class="btn btn-sm btn-light"
                    @click="steps = null"
                >Use the level above</button>
            </div>
        </div>
        <div class="card-body pt-0">
            <!-- Inherited -->
            <div
                v-if="steps === null"
                class="text-muted fs-7"
            >
                <template v-if="inherited?.length">
                    Uses the level above:
                    <template
                        v-for="(s, i) in inherited"
                        :key="s"
                    ><span v-if="i"> → </span><span class="fw-semibold text-gray-700">{{ option(s).label }}</span></template>
                </template>
                <template v-else>No approvals. Requests go straight to the technician.</template>
            </div>

            <!-- Own steps -->
            <template v-else>
                <ol class="steps">
                    <li
                        v-for="(s, i) in steps"
                        :key="s"
                    >
                        <span class="step-no">{{ i + 1 }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-gray-800">{{ option(s).label }}</div>
                            <div
                                class="fs-8"
                                :class="option(s).holder ? 'text-muted' : 'text-warning'"
                            >{{ option(s).holder ?? 'Nobody holds this role — the step will be skipped' }}</div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-light"
                            :disabled="i === 0"
                            title="Move up"
                            @click="move(i, -1)"
                        ><i class="ki-outline ki-arrow-up" /></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-light"
                            :disabled="i === steps.length - 1"
                            title="Move down"
                            @click="move(i, 1)"
                        ><i class="ki-outline ki-arrow-down" /></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-light btn-active-light-danger"
                            title="Remove"
                            @click="remove(i)"
                        ><i class="ki-outline ki-cross" /></button>
                    </li>
                </ol>
                <div
                    v-if="!steps.length"
                    class="text-muted fs-7 mb-3"
                >No approvals on this level. Requests go straight to the technician.</div>

                <select
                    v-if="available.length"
                    v-model="adding"
                    class="form-select form-select-sm"
                    @change="add"
                >
                    <option value="">+ Add approval step…</option>
                    <option
                        v-for="o in available"
                        :key="o.value"
                        :value="o.value"
                    >{{ o.label }}</option>
                </select>
                <div class="fs-8 text-muted mt-2">
                    Manage roles and who holds them in <a :href="approvalRolesIndex.url()">Approval roles</a>. Direct managers are set on each employee, department managers on each department.
                </div>
                <div
                    v-if="stepError"
                    class="text-danger fs-7 mt-2"
                >{{ stepError }}</div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.steps { margin: 0 0 12px; padding: 0; list-style: none; }
.steps li { position: relative; display: flex; align-items: center; gap: 10px; padding: 8px 0; }
.steps li:not(:last-child)::after { content: ''; position: absolute; top: 38px; bottom: -8px; inset-inline-start: 13px; width: 2px; background: #e5edf1; }
.step-no { display: grid; flex: 0 0 28px; place-items: center; height: 28px; border-radius: 50%; background: #e2f2f2; color: #16838b; font-size: 12px; font-weight: 800; }
</style>

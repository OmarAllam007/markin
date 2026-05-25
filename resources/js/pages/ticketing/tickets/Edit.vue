<script setup lang="ts">
import { update, show } from '@/routes/ticketing/tickets';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Option = { value: string; label: string };
type CategoryOption = { id: number; name: string; color: string; icon: string | null; subcategories: { id: number; category_id: number; name: string }[] };
type PriorityOption = { id: number; name: string; color: string };
type SlaOption = { id: number; name: string };
type GroupOption = { id: number; name: string };
type TechnicianOption = { id: number; name: string };

type TicketEdit = {
    id: number;
    subject: string;
    description: string;
    category_id: number;
    subcategory_id: number | null;
    type: string | null;
    status: string;
    priority_id: number | null;
    sla_id: number | null;
    group_id: number | null;
    technician_id: number | null;
    employee_id: number | null;
    due_date: string | null;
    form_data: Record<string, unknown> | null;
};

const props = defineProps<{
    ticket: TicketEdit;
    categories: CategoryOption[];
    priorities: PriorityOption[];
    slas: SlaOption[];
    groups: GroupOption[];
    technicians: TechnicianOption[];
    statuses: Option[];
    types: Option[];
}>();

const form = useForm({
    subject: props.ticket.subject,
    description: props.ticket.description,
    category_id: props.ticket.category_id,
    subcategory_id: props.ticket.subcategory_id ?? '',
    type: props.ticket.type ?? '',
    status: props.ticket.status,
    priority_id: props.ticket.priority_id ?? '',
    sla_id: props.ticket.sla_id ?? '',
    group_id: props.ticket.group_id ?? '',
    technician_id: props.ticket.technician_id ?? '',
    employee_id: props.ticket.employee_id ?? '',
    due_date: props.ticket.due_date ?? '',
});

const availableSubcategories = computed(() => {
    if (!form.category_id) return [];
    const cat = props.categories.find(c => c.id === Number(form.category_id));
    return cat?.subcategories ?? [];
});

const submit = () => form.put(update.url({ ticket: props.ticket.id }));
</script>

<template>
    <div>
        <Head :title="`Edit Ticket #${ticket.id}`" />
        <div class="mb-5">
            <Link
                :href="show.url({ ticket: ticket.id })"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to ticket
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">Edit Ticket #{{ ticket.id }}</h2></div>
            </div>
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5">
                        <div class="col-md-12">
                            <label
                                class="form-label required"
                                for="tk-subject"
                            >Subject</label>
                            <input
                                id="tk-subject"
                                v-model="form.subject"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.subject }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.subject"
                                class="invalid-feedback"
                            >{{ form.errors.subject }}</div>
                        </div>
                        <div class="col-md-12">
                            <label
                                class="form-label required"
                                for="tk-desc"
                            >Description</label>
                            <textarea
                                id="tk-desc"
                                v-model="form.description"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.description }"
                                rows="5"
                            />
                            <div
                                v-if="form.errors.description"
                                class="invalid-feedback"
                            >{{ form.errors.description }}</div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="tk-cat"
                            >Category</label>
                            <select
                                id="tk-cat"
                                v-model="form.category_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.category_id }"
                                @change="form.subcategory_id = ''"
                            >
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >{{ c.name }}</option>
                            </select>
                        </div>
                        <div
                            v-if="availableSubcategories.length"
                            class="col-md-6"
                        >
                            <label
                                class="form-label"
                                for="tk-subcat"
                            >Subcategory</label>
                            <select
                                id="tk-subcat"
                                v-model="form.subcategory_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="s in availableSubcategories"
                                    :key="s.id"
                                    :value="s.id"
                                >{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-status"
                            >Status</label>
                            <select
                                id="tk-status"
                                v-model="form.status"
                                class="form-select"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >{{ s.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-priority"
                            >Priority</label>
                            <select
                                id="tk-priority"
                                v-model="form.priority_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="p in priorities"
                                    :key="p.id"
                                    :value="p.id"
                                >{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-sla"
                            >SLA Policy</label>
                            <select
                                id="tk-sla"
                                v-model="form.sla_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="s in slas"
                                    :key="s.id"
                                    :value="s.id"
                                >{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-group"
                            >Group</label>
                            <select
                                id="tk-group"
                                v-model="form.group_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="g in groups"
                                    :key="g.id"
                                    :value="g.id"
                                >{{ g.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-tech"
                            >Assignee</label>
                            <select
                                id="tk-tech"
                                v-model="form.technician_id"
                                class="form-select"
                            >
                                <option value="">Unassigned</option>
                                <option
                                    v-for="u in technicians"
                                    :key="u.id"
                                    :value="u.id"
                                >{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-due"
                            >Due Date</label>
                            <input
                                id="tk-due"
                                v-model="form.due_date"
                                type="datetime-local"
                                class="form-control"
                            />
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link
                        :href="show.url({ ticket: ticket.id })"
                        class="btn btn-light"
                    >Cancel</Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

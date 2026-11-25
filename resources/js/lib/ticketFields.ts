/** Labels in other content languages, keyed by locale code (e.g. `ar`). */
export type Translations = Record<string, string>;

export type FieldOption = { value: string; label: string; translations?: Translations };

export type TicketField = {
    key: string;
    label: string;
    translations?: Translations;
    type: string;
    required?: boolean;
    width?: 'half' | 'full';
    options?: FieldOption[] | null;
    formula?: string;
    args?: string[];
    locked?: boolean;
};

/** Field keys stored on the ticket itself; everything else goes to form_data. */
export const STANDARD_KEYS = ['subject', 'description', 'priority_id', 'attachments'];

export const FIELD_TYPES = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Long text' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'time', label: 'Time' },
    { value: 'select', label: 'Dropdown' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'computed', label: 'Auto-calculated' },
    { value: 'priority', label: 'Priority' },
    { value: 'attachments', label: 'Attachments' },
];

export const FORMULAS = [
    { value: 'days_between', label: 'Days between two dates (inclusive)', argType: 'date' },
    { value: 'hours_between', label: 'Hours between two times', argType: 'time' },
];

/** Mirrors TicketService::evaluate() — the server recomputes on submit. */
export function evaluateFormula(formula: string | undefined, from: unknown, to: unknown): number | null {
    if (!from || !to) {
return null;
}

    if (formula === 'days_between') {
        const a = Date.parse(`${from}T00:00:00Z`);
        const b = Date.parse(`${to}T00:00:00Z`);

        if (Number.isNaN(a) || Number.isNaN(b) || b < a) {
return null;
}

        return Math.round((b - a) / 86_400_000) + 1;
    }

    if (formula === 'hours_between') {
        const mins = (t: string) => {
            const [h, m] = t.split(':').map(Number);

            return h * 60 + m;
        };
        let diff = mins(String(to)) - mins(String(from));

        if (Number.isNaN(diff)) {
return null;
}

        if (diff < 0) {
diff += 1440;
}

        return Math.round((diff / 60) * 100) / 100;
    }

    return null;
}

/** Label in `locale`, falling back to the default label. */
export const labelIn = (x: { label: string; translations?: Translations }, locale: string) =>
    x.translations?.[locale]?.trim() || x.label;

/** Native language name, e.g. `ar` → "Arabic". */
export const languageName = (code: string) =>
    new Intl.DisplayNames([navigator.language, 'en'], { type: 'language' }).of(code) ?? code;

export const isRtl = (code: string) => ['ar', 'fa', 'he', 'ur'].includes(code.split('-')[0]);

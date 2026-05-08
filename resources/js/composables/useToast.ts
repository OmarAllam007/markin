declare const toastr: {
    options: Record<string, unknown>;
    success(message: string, title?: string): void;
    error(message: string, title?: string): void;
    warning(message: string, title?: string): void;
    info(message: string, title?: string): void;
};

const TOASTR_OPTIONS = {
    closeButton: false,
    debug: false,
    newestOnTop: false,
    progressBar: false,
    positionClass: 'toastr-bottom-right',
    preventDuplicates: false,
    onclick: null,
    showDuration: '3000',
    hideDuration: '1000',
    timeOut: '5000',
    extendedTimeOut: '1000',
    showEasing: 'swing',
    hideEasing: 'linear',
    showMethod: 'fadeIn',
    hideMethod: 'fadeOut',
};

let configured = false;

function call(method: 'success' | 'error' | 'warning' | 'info', message: string, title?: string) {
    if (typeof window === 'undefined' || typeof toastr === 'undefined') return;
    if (!configured) {
        toastr.options = TOASTR_OPTIONS;
        configured = true;
    }
    toastr[method](message, title ?? '');
}

export function useToast() {
    const success = (message: string, title?: string) => call('success', message, title);
    const error = (message: string, title?: string) => call('error', message, title);
    const warning = (message: string, title?: string) => call('warning', message, title);
    const info = (message: string, title?: string) => call('info', message, title);

    return { success, error, warning, info };
}

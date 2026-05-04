declare const toastr: {
    options: Record<string, unknown>;
    success(message: string, title?: string): void;
    error(message: string, title?: string): void;
    warning(message: string, title?: string): void;
    info(message: string, title?: string): void;
};

toastr.options = {
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

export function useToast() {
    const success = (message: string, title?: string) => toastr.success(message, title ?? '');
    const error = (message: string, title?: string) => toastr.error(message, title ?? '');
    const warning = (message: string, title?: string) => toastr.warning(message, title ?? '');
    const info = (message: string, title?: string) => toastr.info(message, title ?? '');

    return { success, error, warning, info };
}

import { readonly, ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    tone: 'info' | 'error' | 'success';
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

/**
 * Transient, non-blocking feedback. Errors stay longer because they usually
 * describe something the user has to act on.
 */
export function useToasts() {
    function push(message: string, tone: Toast['tone'] = 'info'): void {
        const id = nextId++;
        toasts.value = [...toasts.value, { id, message, tone }];

        window.setTimeout(() => dismiss(id), tone === 'error' ? 7000 : 3500);
    }

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        notify: (message: string) => push(message, 'info'),
        success: (message: string) => push(message, 'success'),
        fail: (message: string) => push(message, 'error'),
    };
}

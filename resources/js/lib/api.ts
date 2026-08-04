/**
 * Thin fetch wrapper: JSON in, JSON out, session cookies, CSRF token, and a
 * typed error that carries the server's actionable message.
 */

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly payload: Record<string, unknown> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /**
     * Laravel validation errors, flattened to one message per field.
     */
    get fieldErrors(): Record<string, string> {
        const errors = this.payload.errors as Record<string, string[]> | undefined;

        if (!errors) {
            return {};
        }

        return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
    }
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function request<T>(method: string, url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const isJson = response.headers.get('content-type')?.includes('application/json');
    const payload = isJson ? await response.json() : {};

    if (!response.ok) {
        if (response.status === 419) {
            throw new ApiError('Tu sesión expiró. Recarga la página para continuar.', 419, payload);
        }

        if (response.status === 401) {
            throw new ApiError('Necesitas iniciar sesión.', 401, payload);
        }

        throw new ApiError(
            (payload as { message?: string }).message ?? 'Algo salió mal. Intenta de nuevo.',
            response.status,
            payload as Record<string, unknown>,
        );
    }

    return payload as T;
}

export const api = {
    get: <T>(url: string) => request<T>('GET', url),
    post: <T>(url: string, body?: unknown) => request<T>('POST', url, body ?? {}),
    patch: <T>(url: string, body?: unknown) => request<T>('PATCH', url, body ?? {}),
    delete: <T>(url: string) => request<T>('DELETE', url),
};

export function query(params: Record<string, string | number | undefined | null>): string {
    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    }

    const encoded = search.toString();

    return encoded === '' ? '' : `?${encoded}`;
}

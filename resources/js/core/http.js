import $ from 'jquery';
import { toast } from '../components/toast';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const messages = {
    401: 'Your session has ended. Please sign in again.',
    403: 'You do not have permission for this action.',
    404: 'The requested item was not found.',
    409: 'This record was changed by someone else. Reload and try again.',
    419: 'Your session expired. Reload the page and try again.',
    429: 'Too many requests. Please wait and try again.',
    500: 'Something went wrong. Please try again.',
};

export class HttpError extends Error {
    constructor(status, payload) {
        super(payload?.message || messages[status] || messages[500]);
        this.status = status;
        this.payload = payload || {};
    }

    get errors() {
        return this.payload.errors || {};
    }
}

function handleGlobal(error, { silent }) {
    if (error.status === 401) {
        window.location.assign(document.querySelector('meta[name="login-url"]')?.content || '/');
        return;
    }
    if (error.status === 0 || silent || error.status === 422) return;
    toast.error(error.message);
}

/**
 * Promise-based request. Returns the parsed JSON body; rejects with HttpError.
 * Options: method, url, data, signal (AbortController), silent (no toast).
 */
export function request({ method = 'GET', url, data, signal, silent = false }) {
    return new Promise((resolve, reject) => {
        const isForm = data instanceof FormData;
        const jqXHR = $.ajax({
            url,
            method,
            data,
            processData: !isForm,
            contentType: isForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json',
        });

        signal?.addEventListener('abort', () => jqXHR.abort());

        jqXHR
            .done((body) => resolve(body))
            .fail((xhr, status) => {
                if (status === 'abort') {
                    reject(new HttpError(0, { message: 'aborted' }));
                    return;
                }
                const error = new HttpError(xhr.status || 500, xhr.responseJSON);
                handleGlobal(error, { silent });
                reject(error);
            });
    });
}

export const http = {
    request,
    get: (url, data, opts = {}) => request({ ...opts, method: 'GET', url, data }),
    post: (url, data, opts = {}) => request({ ...opts, method: 'POST', url, data }),
    put: (url, data, opts = {}) => request({ ...opts, method: 'POST', url, data: withMethod(data, 'PUT') }),
    patch: (url, data, opts = {}) => request({ ...opts, method: 'POST', url, data: withMethod(data, 'PATCH') }),
    delete: (url, data, opts = {}) => request({ ...opts, method: 'POST', url, data: withMethod(data, 'DELETE') }),
};

function withMethod(data, method) {
    if (data instanceof FormData) {
        data.set('_method', method);
        return data;
    }
    return { ...(data || {}), _method: method };
}

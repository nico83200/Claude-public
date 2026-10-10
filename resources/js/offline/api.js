// Client HTTP de synchronisation : cookie de session + jeton CSRF.
export class AuthRequired extends Error {}
export class NetworkError extends Error {}

export function createApi({ fetchImpl = globalThis.fetch?.bind(globalThis), getToken, setToken } = {}) {
    async function raw(method, url, body) {
        let res;
        try {
            res = await fetchImpl(url, {
                method,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getToken() ?? '', 'X-Requested-With': 'XMLHttpRequest' },
                body: body ? JSON.stringify(body) : undefined,
            });
        } catch (e) {
            throw new NetworkError(e.message);
        }
        return res;
    }

    async function refreshSession() {
        const res = await raw('GET', '/sync/session');
        if (res.status === 401) throw new AuthRequired('Session expirée');
        if (!res.ok) throw new NetworkError(`HTTP ${res.status}`);
        const data = await res.json();
        setToken(data.csrf_token);
        return data;
    }

    async function call(method, url, body, retried = false) {
        const res = await raw(method, url, body);
        if (res.status === 419 && !retried) {
            await refreshSession();
            return call(method, url, body, true);
        }
        if (res.status === 401 || res.status === 419) throw new AuthRequired('Session expirée');
        if (res.status === 403) {
            const data = await res.json().catch(() => ({}));
            if (data.wipe) return { wipe: true };
            throw new AuthRequired(data.message ?? 'Accès refusé');
        }
        if (!res.ok) throw new NetworkError(`HTTP ${res.status}`);
        return res.json();
    }

    return {
        refreshSession,
        pull: (body) => call('POST', '/sync/pull', body),
        push: (body) => call('POST', '/sync/push', body),
        conflicts: () => call('GET', '/sync/conflits'),
        resolve: (id, choice) => call('POST', `/sync/conflits/${id}`, { choice }),
    };
}

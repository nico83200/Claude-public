// Client HTTP de l'API (JSON, CSRF, contournement PUT/DELETE pour hébergements restrictifs).

let csrf = '';

export class ApiError extends Error {
  constructor(message, status, fields = null) {
    super(message);
    this.status = status;
    this.fields = fields;
  }
}

export function setCsrf(token) {
  csrf = token || '';
}

async function request(method, route, body = null, query = {}) {
  const qs = new URLSearchParams({ r: route });
  for (const [k, v] of Object.entries(query)) {
    if (v === undefined || v === null || v === '') continue;
    if (Array.isArray(v)) v.forEach((x) => qs.append(k + '[]', x));
    else qs.append(k, v);
  }
  const headers = { Accept: 'application/json' };
  let realMethod = method;
  if (method === 'PUT' || method === 'DELETE') {
    headers['X-HTTP-Method-Override'] = method;
    realMethod = 'POST';
  }
  if (realMethod !== 'GET') {
    headers['Content-Type'] = 'application/json';
    headers['X-CSRF-Token'] = csrf;
  }
  let res;
  try {
    res = await fetch('api.php?' + qs.toString(), {
      method: realMethod,
      headers,
      credentials: 'same-origin',
      body: realMethod === 'GET' ? undefined : JSON.stringify(body || {}),
    });
  } catch {
    throw new ApiError('Connexion au serveur impossible', 0);
  }
  let json = null;
  try {
    json = await res.json();
  } catch {
    throw new ApiError(`Réponse invalide du serveur (HTTP ${res.status})`, res.status);
  }
  if (!res.ok) {
    if (res.status === 401 && route !== 'auth/login') window.dispatchEvent(new CustomEvent('vs:unauthorized'));
    throw new ApiError(json?.error || 'Erreur', res.status, json?.fields || null);
  }
  return json.data;
}

export const api = {
  get: (route, query) => request('GET', route, null, query),
  post: (route, body) => request('POST', route, body),
  put: (route, body) => request('PUT', route, body),
  del: (route) => request('DELETE', route),
  /** Envoi multipart (fichier ZIP de mise à jour). */
  upload: async (route, formData) => {
    let res;
    try {
      res = await fetch('api.php?' + new URLSearchParams({ r: route }).toString(), {
        method: 'POST', body: formData, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
      });
    } catch {
      throw new ApiError('Connexion au serveur impossible', 0);
    }
    const json = await res.json().catch(() => null);
    if (!res.ok) throw new ApiError(json?.error || `Erreur HTTP ${res.status}`, res.status);
    return json.data;
  },
  download: (route, query = {}) => {
    window.location.href = 'api.php?' + new URLSearchParams({ r: route, ...query }).toString();
  },
};

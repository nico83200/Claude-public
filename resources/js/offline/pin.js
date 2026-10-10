// Code PIN local facultatif (verrouillage après inactivité). Seul un dérivé
// PBKDF2 salé est conservé ; il ne donne accès à aucune donnée serveur.
const enc = new TextEncoder();

export async function hashPin(pin, salt = crypto.getRandomValues(new Uint8Array(16))) {
    const key = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', salt, iterations: 150000, hash: 'SHA-256' }, key, 256);
    return { salt: [...salt], hash: [...new Uint8Array(bits)] };
}

export async function verifyPin(pin, stored) {
    if (!stored) return true;
    const { hash } = await hashPin(pin, new Uint8Array(stored.salt));
    return hash.length === stored.hash.length && hash.every((v, i) => v === stored.hash[i]);
}

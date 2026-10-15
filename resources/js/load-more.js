// « Afficher plus » : ajoute la page suivante d'une liste sans recharger la page.
// L'adresse renvoie du JSON { <clé>: html, next: adresse suivante | null } ; targets associe un sélecteur à une clé.
// Les lignes ajoutées sont initialisées par Alpine (glisser pour supprimer, etc.). Sans JavaScript, le lien mène à la page suivante.
export function loadMore(next, targets) {
    return {
        next,
        busy: false,
        error: '',
        async load() {
            if (!this.next || this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const res = await fetch(this.next, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error();
                const data = await res.json();
                for (const [selector, key] of Object.entries(targets)) {
                    document.querySelector(selector)?.insertAdjacentHTML('beforeend', data[key] || '');
                }
                this.next = data.next || null;
            } catch {
                this.error = navigator.onLine ? 'Chargement impossible. Réessayez.' : 'Pas de connexion.';
            } finally {
                this.busy = false;
            }
        },
    };
}

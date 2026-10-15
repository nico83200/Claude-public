// Glisser une ligne vers la gauche pour la supprimer (téléphone et tablette, au doigt).
// - glissé court : la ligne reste ouverte sur le bouton « Supprimer » ;
// - glissé long (plus de 45 % de la largeur) : suppression directe ;
// - un message « … supprimée · Annuler » permet de revenir en arrière quelques secondes.
// À la souris, rien ne change : la ligne reste un lien classique.
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const OPEN = 96; // largeur du bouton révélé (px)
const COMMIT = 0.45; // part de la largeur au-delà de laquelle on supprime directement
const TOAST_MS = 6000;

/** Conteneur de la liste : astuce de première utilisation et message d'annulation. */
export function swipeList({ hintKey, label = 'Élément supprimé', undoLabel = 'Annuler' } = {}) {
    return {
        touch: window.matchMedia('(pointer: coarse)').matches || 'ontouchstart' in window,
        hint: false,
        toast: null, // { text, restoreUrl, linked: { text, delete_url, done } | null }
        _timer: null,
        init() {
            try { this.hint = this.touch && !localStorage.getItem(hintKey); } catch { this.hint = this.touch; }
            // Première visite : la première ligne s'entrouvre brièvement pour montrer le geste.
            if (this.hint) setTimeout(() => this.$el.querySelector('[data-swipe-row]')?.dispatchEvent(new CustomEvent('swipe-peek')), 700);
        },
        dismissHint() {
            this.hint = false;
            try { localStorage.setItem(hintKey, '1'); } catch { /* sans effet */ }
        },
        deleted(detail) {
            // Le geste a été compris : l'astuce n'est plus utile.
            this.dismissHint();
            clearTimeout(this._timer);
            this.toast = { text: detail.text || label, restoreUrl: detail.restoreUrl, linked: detail.linked || null, error: false };
            // Une proposition liée (supprimer aussi le soin) laisse plus de temps pour répondre.
            this._timer = setTimeout(() => { this.toast = null; }, detail.linked ? TOAST_MS * 2 : TOAST_MS);
        },
        /** Suppression de l'élément lié proposé (ex. le soin rattaché à la dépense), avec « Annuler » pour lui. */
        async deleteLinked() {
            const linked = this.toast?.linked;
            if (!linked) return;
            clearTimeout(this._timer);
            this.toast = { text: 'Suppression…', restoreUrl: null, linked: null, error: false };
            try {
                const res = await fetch(linked.delete_url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                if (!res.ok) throw new Error();
                const data = await res.json().catch(() => ({}));
                this.deleted({ text: linked.done, restoreUrl: data.restore_url });
            } catch {
                this.failed('Suppression du soin impossible. Réessayez depuis le carnet de santé.');
            }
        },
        failed(message) {
            clearTimeout(this._timer);
            this.toast = { text: message, restoreUrl: null, linked: null, error: true };
            this._timer = setTimeout(() => { this.toast = null; }, TOAST_MS);
        },
        async undo() {
            const url = this.toast?.restoreUrl;
            if (!url) return;
            clearTimeout(this._timer);
            this.toast = { text: 'Restauration…', restoreUrl: null, linked: null, error: false };
            try {
                const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                if (!res.ok) throw new Error();
                window.location.reload();
            } catch {
                this.failed('Restauration impossible. Vérifiez la connexion.');
            }
        },
        undoLabel,
    };
}

/** Une ligne : suit le doigt, révèle « Supprimer », supprime puis se replie. */
export function swipeRow({ deleteUrl, text }) {
    return {
        x: 0,
        dragging: false,
        open: false,
        busy: false,
        gone: false,
        _start: null,
        _moved: false,
        _width: 0,
        init() {
            const el = this.$el;
            el.addEventListener('pointerdown', (e) => this.down(e));
            el.addEventListener('pointermove', (e) => this.move(e));
            el.addEventListener('pointerup', (e) => this.up(e));
            el.addEventListener('pointercancel', () => this.cancel());
            // Un glissé ne doit pas ouvrir l'élément (lien de la ligne).
            el.addEventListener('click', (e) => {
                if (e.target.closest('[data-swipe-action]')) return; // bouton « Supprimer » révélé
                if (this._moved || this.open) { e.preventDefault(); e.stopPropagation(); if (this.open) this.close(); }
            }, true);
            el.addEventListener('swipe-peek', () => this.peek());
            window.addEventListener('swipe-open', (e) => { if (e.detail !== el && this.open) this.close(); });
        },
        // Objet (et non chaîne) : Alpine fusionne avec le style déjà posé (touch-action), au lieu de le remplacer.
        style() {
            return { transform: `translateX(${this.x}px)`, transition: this.dragging ? 'none' : 'transform .25s ease' };
        },
        down(e) {
            if (e.pointerType === 'mouse' || this.busy || e.target.closest('[data-swipe-action]')) return;
            this._start = { x: e.clientX, y: e.clientY, base: this.x, axis: null };
            this._moved = false;
            this._width = this.$el.offsetWidth;
        },
        move(e) {
            const s = this._start;
            if (!s) return;
            const dx = e.clientX - s.x;
            const dy = e.clientY - s.y;
            if (!s.axis) {
                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
                s.axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (s.axis === 'x') { this.dragging = true; this.$el.setPointerCapture?.(e.pointerId); }
            }
            if (s.axis !== 'x') return;
            this._moved = true;
            // Vers la gauche seulement ; un léger rebond si l'on tire vers la droite.
            const next = s.base + dx;
            this.x = next > 0 ? next / 4 : Math.max(next, -this._width);
        },
        up() {
            const s = this._start;
            this._start = null;
            if (!s || s.axis !== 'x') { this.dragging = false; return; }
            this.dragging = false;
            if (-this.x > this._width * COMMIT) return this.remove();
            if (-this.x > OPEN * 0.6) {
                this.x = -OPEN; this.open = true;
                window.dispatchEvent(new CustomEvent('swipe-open', { detail: this.$el }));
            } else {
                this.close();
            }
            // Le clic qui suit le relâchement est ignoré, puis les clics redeviennent normaux.
            setTimeout(() => { this._moved = false; }, 50);
        },
        cancel() { this._start = null; this.dragging = false; if (!this.open) this.x = 0; },
        close() { this.x = 0; this.open = false; },
        peek() {
            if (this.open || this.busy || this.dragging) return;
            this.x = -56;
            // Ne pas reprendre la main si l'utilisateur a commencé à glisser entre-temps.
            setTimeout(() => { if (!this.open && !this.busy && !this._start) this.x = 0; }, 650);
        },
        async remove() {
            if (this.busy) return;
            this.busy = true;
            this.x = -(this._width || this.$el.offsetWidth);
            try {
                const res = await fetch(deleteUrl, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                if (!res.ok) throw new Error(res.status === 403 ? 'Vous n\'avez pas le droit de supprimer cet élément.' : '');
                const data = await res.json().catch(() => ({}));
                this.gone = true; // repli de la ligne (animation de hauteur)
                this.$dispatch('swipe-deleted', { text, restoreUrl: data.restore_url, linked: data.care || null });
            } catch (err) {
                this.busy = false;
                this.close();
                this.$dispatch('swipe-failed', err?.message || (navigator.onLine ? 'Suppression impossible. Réessayez.' : 'Suppression impossible sans connexion.'));
            }
        },
    };
}

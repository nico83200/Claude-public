import Sortable from 'sortablejs';

/**
 * Préparation de séance : glisser-déposer des exercices entre les sections
 * (échauffement, travail principal…) et depuis la bibliothèque.
 * Chaque dépôt envoie la disposition complète au serveur (source de vérité).
 */
export function sessionPlanner(url) {
    return {
        saving: false,
        error: null,
        init() {
            const zones = this.$root.querySelectorAll('[data-phase]');
            zones.forEach((zone) => Sortable.create(zone, {
                group: { name: 'session', pull: true, put: true },
                handle: '[data-handle]',
                draggable: '[data-token]',
                animation: 150,
                ghostClass: 'drag-ghost',
                chosenClass: 'drag-chosen',
                emptyInsertThreshold: 24,
                onEnd: (e) => { if (e.from !== e.to || e.oldIndex !== e.newIndex) this.save(false); },
                onAdd: (e) => { if (e.item.dataset.token?.startsWith('ex:')) this.save(true); },
            }));
            const library = this.$root.querySelector('[data-library]');
            if (library) {
                Sortable.create(library, {
                    group: { name: 'session', pull: 'clone', put: false },
                    sort: false,
                    handle: '[data-handle]',
                    draggable: '[data-token]',
                    animation: 150,
                    ghostClass: 'drag-ghost',
                    onClone: (e) => { e.clone.querySelectorAll('input').forEach((i) => { i.checked = false; }); },
                });
            }
            // Retour à la même position après rechargement (ajout depuis la bibliothèque).
            try {
                const y = sessionStorage.getItem('jackcie-planner-scroll');
                if (y !== null) { sessionStorage.removeItem('jackcie-planner-scroll'); window.scrollTo(0, Number(y)); }
            } catch { /* ignore */ }
        },
        layout() {
            const out = {};
            this.$root.querySelectorAll('[data-phase]').forEach((zone) => {
                out[zone.dataset.phase] = [...zone.querySelectorAll(':scope > [data-token]')].map((el) => el.dataset.token);
            });
            return out;
        },
        async save(reload) {
            this.saving = true;
            this.error = null;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({ layout: this.layout() }),
                });
                if (!res.ok) throw new Error(String(res.status));
                if (reload) {
                    try { sessionStorage.setItem('jackcie-planner-scroll', String(window.scrollY)); } catch { /* ignore */ }
                    window.location.reload();
                }
            } catch {
                this.error = 'Le déplacement n\'a pas pu être enregistré. La page va être rechargée.';
                setTimeout(() => window.location.reload(), 1800);
            } finally {
                this.saving = false;
            }
        },
    };
}

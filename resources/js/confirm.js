/**
 * Confirmation dans l'application, à la place de la boîte native confirm() du navigateur
 * (d'apparence étrangère dans l'application installée). Sur un formulaire, ou sur l'un de ses boutons :
 *   data-confirm="Question ?"       texte affiché
 *   data-confirm-ok="Supprimer"     libellé du bouton de confirmation (« Confirmer » par défaut)
 *   data-confirm-tone="neutral"     bouton vert au lieu de rouge (action non destructrice)
 *   data-confirm-if="champ"         ne demander que si la case à cocher « champ » est cochée
 * Le formulaire n'est envoyé qu'après confirmation. Sans JavaScript, il est envoyé directement.
 */
let dialog = null;

function build() {
    const d = document.createElement('dialog');
    d.className = 'jc-confirm';
    d.setAttribute('aria-labelledby', 'jc-confirm-text');
    d.innerHTML = `<form method="dialog" class="jc-confirm-box">
        <p id="jc-confirm-text" class="jc-confirm-text"></p>
        <div class="jc-confirm-actions">
            <button value="cancel" class="btn-secondary" data-confirm-cancel>Annuler</button>
            <button value="ok" data-confirm-accept></button>
        </div>
    </form>`;
    // Toucher le fond sombre annule, comme la touche Échap.
    d.addEventListener('click', (e) => { if (e.target === d) d.close('cancel'); });
    document.body.appendChild(d);

    return d;
}

/** Ouvre la fenêtre et résout true si l'utilisateur confirme. */
export function askConfirm(message, { ok, tone } = {}) {
    if (typeof HTMLDialogElement === 'undefined') return Promise.resolve(window.confirm(message));
    dialog ??= build();
    dialog.querySelector('.jc-confirm-text').textContent = message;
    const accept = dialog.querySelector('[data-confirm-accept]');
    accept.textContent = ok || 'Confirmer';
    accept.className = tone === 'neutral' ? 'btn-primary' : 'btn-danger';
    dialog.returnValue = '';

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
        dialog.showModal();
        // Le focus va sur « Annuler » : un appui réflexe sur Entrée ne déclenche pas l'action.
        dialog.querySelector('[data-confirm-cancel]').focus();
    });
}

export function startConfirm() {
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        const submitter = e.submitter;
        const source = submitter?.hasAttribute?.('data-confirm') ? submitter : (form.hasAttribute?.('data-confirm') ? form : null);
        if (!source) return;
        // Second envoi, après « Confirmer » : on le laisse partir.
        if (form.dataset.confirmed) { delete form.dataset.confirmed; return; }
        const onlyIf = source.dataset.confirmIf;
        if (onlyIf && !form.elements[onlyIf]?.checked) return;

        // Rien d'autre ne réagit à l'envoi tant que la réponse n'est pas donnée.
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!(await askConfirm(source.dataset.confirm, { ok: source.dataset.confirmOk, tone: source.dataset.confirmTone }))) return;
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter?.form === form ? submitter : undefined);
    }, true);
}

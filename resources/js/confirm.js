/**
 * Confirmation dans l'application, à la place de la boîte native confirm() du navigateur
 * (d'apparence étrangère dans l'application installée). Sur un formulaire, ou sur l'un de ses boutons :
 *   data-confirm="Question ?"       texte affiché
 *   data-confirm-ok="Supprimer"     libellé du bouton de confirmation (« Confirmer » par défaut)
 *   data-confirm-tone="neutral"     bouton vert au lieu de rouge (action non destructrice)
 *   data-confirm-if="champ"         ne demander que si la case à cocher « champ » est cochée
 *   data-confirm-check="champ"      case à cocher proposée dans la fenêtre (ex. « Supprimer aussi le soin ») :
 *   data-confirm-check-label="…"    cochée, le formulaire est envoyé avec champ=1
 * Le formulaire n'est envoyé qu'après confirmation. Sans JavaScript, il est envoyé directement.
 */
let dialog = null;

function build() {
    const d = document.createElement('dialog');
    d.className = 'jc-confirm';
    d.setAttribute('aria-labelledby', 'jc-confirm-text');
    d.innerHTML = `<form method="dialog" class="jc-confirm-box">
        <p id="jc-confirm-text" class="jc-confirm-text"></p>
        <label class="jc-confirm-check" hidden><input type="checkbox" class="size-5 rounded"> <span></span></label>
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

/** Ouvre la fenêtre et résout true si l'utilisateur confirme (avec check : { ok, checked }). */
export function askConfirm(message, { ok, tone, check } = {}) {
    if (typeof HTMLDialogElement === 'undefined') {
        const yes = window.confirm(message);
        return Promise.resolve(check ? { ok: yes, checked: false } : yes);
    }
    dialog ??= build();
    dialog.querySelector('.jc-confirm-text').textContent = message;
    const box = dialog.querySelector('.jc-confirm-check');
    box.hidden = !check;
    box.querySelector('input').checked = false;
    box.querySelector('span').textContent = check || '';
    const accept = dialog.querySelector('[data-confirm-accept]');
    accept.textContent = ok || 'Confirmer';
    accept.className = tone === 'neutral' ? 'btn-primary' : 'btn-danger';
    dialog.returnValue = '';

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => {
            const yes = dialog.returnValue === 'ok';
            resolve(check ? { ok: yes, checked: yes && box.querySelector('input').checked } : yes);
        }, { once: true });
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
        const field = source.dataset.confirmCheck;
        const answer = await askConfirm(source.dataset.confirm, { ok: source.dataset.confirmOk, tone: source.dataset.confirmTone, check: field ? source.dataset.confirmCheckLabel : null });
        if (!(field ? answer.ok : answer)) return;
        if (field) {
            form.querySelector(`input[type=hidden][name="${field}"][data-confirm-added]`)?.remove();
            if (answer.checked) form.insertAdjacentHTML('beforeend', `<input type="hidden" name="${field}" value="1" data-confirm-added>`);
        }
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter?.form === form ? submitter : undefined);
    }, true);
}

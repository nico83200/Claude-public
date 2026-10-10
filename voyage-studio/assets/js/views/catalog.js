// Catalogue de départ (destinations / fournisseurs) et pré-remplissage d'une fiche destination.

import { api } from '../api.js';
import { refreshRefs } from '../app.js';
import { $, $$, esc, modal, state, toast, errorToast, enumLabel, fillForm } from '../ui.js';

export async function openCatalog(entity) {
  const items = await api.get(`catalog/${entity}`);
  const groupKey = entity === 'destinations' ? (i) => i.region : (i) => enumLabel('suppliers', 'type', i.type);
  const groups = new Map();
  for (const i of items) {
    const k = groupKey(i) || 'Autres';
    if (!groups.has(k)) groups.set(k, []);
    groups.get(k).push(i);
  }
  const available = items.filter((i) => !i.exists).length;
  return modal({
    title: entity === 'destinations' ? '📚 Catalogue de destinations' : '📚 Catalogue de fournisseurs',
    wide: true,
    submitLabel: 'Importer la sélection',
    body: `<p class="muted">${items.length} fiches prêtes à l'emploi${entity === 'destinations' ? ' (saisons, budget, vol, fuseau, formalités indicatives à vérifier)' : ' (catégorie, site, notes — commissions selon vos contrats)'}.
        ${available < items.length ? `${items.length - available} déjà présente(s).` : ''}</p>
      <div class="toolbar"><input type="search" id="cat-q" placeholder="Filtrer…"><label class="inline"><input type="checkbox" id="cat-all" checked> Tout cocher</label></div>
      <div class="cat-groups">${[...groups.entries()].map(([g, list]) => `<fieldset class="cat-group"><legend>${esc(g)}</legend>
        ${list.map((i) => `<label class="cat-item ${i.exists ? 'exists' : ''}" data-text="${esc((i.nom + ' ' + (i.pays || '') + ' ' + (i.notes || '')).toLowerCase())}">
          <input type="checkbox" name="names" value="${esc(i.nom)}" ${i.exists ? 'disabled' : 'checked'}>
          <span><b>${esc(i.nom)}</b>${i.exists ? ' <span class="badge gray">déjà présent</span>' : ''}<br><small class="muted">${esc(entity === 'destinations' ? `${i.pays} · vol ${i.vol_duree} · ~${i.budget_jour} €/j` : i.site)}</small></span></label>`).join('')}
      </fieldset>`).join('')}</div>`,
    onOpen: (form) => {
      $('#cat-q', form).oninput = (e) => {
        const q = e.target.value.toLowerCase();
        $$('.cat-item', form).forEach((l) => { l.hidden = q && !l.dataset.text.includes(q); });
      };
      $('#cat-all', form).onchange = (e) => $$('input[name=names]:not(:disabled)', form).forEach((c) => { c.checked = e.target.checked; });
    },
    onSubmit: async (form) => {
      const names = $$('input[name=names]:checked:not(:disabled)', form).map((c) => c.value);
      if (!names.length) { toast('Rien de sélectionné', 'error'); return false; }
      const r = await api.post(`catalog/${entity}`, { names });
      await refreshRefs(entity);
      toast(`${r.imported} fiche(s) importée(s)`);
      return r;
    },
  });
}

/** Bouton « Pré-remplir » à côté du nom d'une destination : catalogue d'abord (hors ligne), sinon IA. */
export function attachDestinationPrefill(form, { overwrite = false } = {}) {
  const input = form.elements.nom;
  if (!input) return;
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn small ai inline-add';
  btn.textContent = '✨ Pré-remplir les infos';
  btn.title = 'Depuis le catalogue intégré, sinon par l\'assistant IA';
  input.closest('label').appendChild(btn);
  btn.onclick = async () => {
    const nom = input.value.trim();
    if (!nom) { toast('Saisissez d\'abord le nom de la destination', 'error'); input.focus(); return; }
    btn.disabled = true;
    const label = btn.textContent;
    try {
      let data = await api.get('catalog/lookup', { q: nom });
      let source = 'catalogue';
      if (!data && state.ai.enabled) {
        btn.textContent = '✨ Recherche IA en cours…';
        data = await api.post('ai/destination', { nom });
        source = 'IA';
      }
      if (!data) {
        toast('Destination absente du catalogue. Activez l\'assistant IA (Réglages) pour pré-remplir n\'importe quelle destination.', 'info', 7000);
        return;
      }
      delete data.nom;
      const n = fillForm(form, data, { onlyEmpty: !overwrite });
      toast(n ? `${n} champ(s) pré-rempli(s) (${source}) — relisez avant d'enregistrer` : 'Les champs étaient déjà remplis');
    } catch (e) {
      errorToast(e);
    } finally {
      btn.disabled = false;
      btn.textContent = label;
    }
  };
}

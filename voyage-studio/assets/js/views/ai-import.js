// Import IA : capture d'écran / PDF / e-mail → prestations pré-remplies, relues par l'agent avant ajout.

import { api } from '../api.js';
import { $, $$, esc, modal, state, toast, downscaleImage, fileToBase64, ITEM_ICONS, money } from '../ui.js';
import { itemDuration } from '../time.js';
import { airport } from '../airports.js';

const ACCEPT = 'image/png,image/jpeg,image/webp,image/gif,application/pdf';

export function openAiImport({ trip, optionId }) {
  const files = [];
  return modal({
    title: '📷 Importer des prestations avec l\'IA',
    wide: true,
    submitLabel: 'Analyser',
    body: `
      <div class="ai-step" id="ai-step1">
        <p class="muted">Déposez une <b>capture d'écran</b> (site de compagnie, comparateur, extranet hôtel…), un <b>PDF</b> de confirmation,
          ou <b>collez</b> une capture avec <kbd>Ctrl</kbd>+<kbd>V</kbd>. Vous pouvez aussi coller le texte d'un e-mail.</p>
        <label class="dropzone" id="dropzone" tabindex="0">
          <input type="file" id="ai-files" accept="${ACCEPT}" multiple hidden>
          <span class="dz-ico">⬆</span><span>Glissez vos fichiers ici ou <u>parcourez</u></span>
          <small class="muted">PNG, JPEG, WebP, PDF — 5 fichiers max.</small>
        </label>
        <div class="thumbs" id="thumbs"></div>
        <label class="field span2"><span class="lbl">Texte (facultatif)</span><textarea name="text" rows="4" placeholder="Collez ici un e-mail de confirmation, un devis fournisseur…"></textarea></label>
      </div>
      <div class="ai-step" id="ai-step2" hidden></div>`,
    onOpen: (form) => {
      const thumbs = $('#thumbs', form);
      const addFiles = async (list) => {
        for (const f of list) {
          if (files.length >= 5) break;
          if (!ACCEPT.split(',').includes(f.type)) { toast(`Format non pris en charge : ${f.name}`, 'error'); continue; }
          let prepared;
          try {
            prepared = f.type === 'application/pdf' ? null : await downscaleImage(f);
          } catch { /* réduction impossible : envoi du fichier original */ }
          prepared ||= { type: f.type, data: await fileToBase64(f), name: f.name };
          files.push(prepared);
        }
        thumbs.innerHTML = files.map((f, i) => `<div class="thumb">${f.type.startsWith('image/') ? `<img src="data:${f.type};base64,${f.data}" alt="">` : '<span class="pdf">PDF</span>'}
          <small>${esc(f.name || 'capture')}</small><button type="button" class="icon-btn" data-rm="${i}" aria-label="Retirer">✕</button></div>`).join('');
      };
      const dz = $('#dropzone', form);
      $('#ai-files', form).onchange = (e) => addFiles([...e.target.files]);
      dz.ondragover = (e) => { e.preventDefault(); dz.classList.add('over'); };
      dz.ondragleave = () => dz.classList.remove('over');
      dz.ondrop = (e) => { e.preventDefault(); dz.classList.remove('over'); addFiles([...e.dataTransfer.files]); };
      thumbs.onclick = (e) => {
        const b = e.target.closest('[data-rm]');
        if (b) { files.splice(+b.dataset.rm, 1); addFiles([]); }
      };
      form.addEventListener('paste', (e) => {
        const imgs = [...(e.clipboardData?.items || [])].filter((i) => i.type.startsWith('image/')).map((i) => {
          const f = i.getAsFile();
          return new File([f], `capture-${files.length + 1}.png`, { type: f.type });
        });
        if (imgs.length) { e.preventDefault(); addFiles(imgs); }
      });
    },
    onSubmit: async (form) => {
      const step2 = $('#ai-step2', form);
      // 2e soumission : création des prestations cochées
      if (!step2.hidden) {
        const rows = $$('[data-row]', step2).filter((r) => $('input[type=checkbox]', r).checked);
        if (!rows.length) { toast('Aucune prestation cochée', 'error'); return false; }
        for (const r of rows) {
          const it = JSON.parse(r.dataset.row);
          for (const inp of $$('[data-f]', r)) it[inp.dataset.f] = inp.value;
          if (it.prix_unitaire) it.prix_unitaire = String(it.prix_unitaire).replace(',', '.');
          delete it._fournisseur;
          await api.post('items', { ...it, option_id: optionId });
        }
        toast(`${rows.length} prestation(s) ajoutée(s) — vérifiez prix et marge`);
        return true;
      }
      const text = form.elements.text.value;
      if (!files.length && !text.trim()) { toast('Ajoutez une capture, un PDF ou un texte', 'error'); return false; }
      const btn = $('button[type=submit]', form);
      btn.textContent = 'Analyse en cours… (10-30 s)';
      let res;
      try {
        res = await api.post('ai/extract', { trip_id: trip.id, files, text });
      } finally {
        btn.textContent = 'Analyser';
      }
      if (!res.items.length) { toast('Aucune prestation reconnue dans ce document', 'error'); return false; }
      $('#ai-step1', form).hidden = true;
      step2.hidden = false;
      step2.innerHTML = `
        ${res.commentaire ? `<p class="alert info">${esc(res.commentaire)}</p>` : ''}
        <p class="muted">Vérifiez et corrigez si besoin avant l'ajout. Les autres détails restent modifiables ensuite.</p>
        <div class="ai-results">${res.items.map((it) => {
          for (const [code, tz] of [['lieu_depart', 'tz_depart'], ['lieu_arrivee', 'tz_arrivee']]) {
            const a = airport(it[code]);
            if (a && !it[tz]) it[tz] = a.tz;
          }
          if (!it.duree_min) { const d = itemDuration(it); if (d) it.duree_min = d; }
          return `<div class="ai-row" data-row='${esc(JSON.stringify(it))}'>
            <input type="checkbox" checked aria-label="Ajouter">
            <span class="tl-ico">${ITEM_ICONS[it.type] || '•'}</span>
            <div class="ai-fields">
              <input data-f="libelle" value="${esc(it.libelle)}" aria-label="Libellé">
              <div class="ai-mini">
                <label>Début<input type="date" data-f="date_debut" value="${esc(it.date_debut || '')}"></label>
                <label>Fin<input type="date" data-f="date_fin" value="${esc(it.date_fin || '')}"></label>
                <label>Prix<input data-f="prix_unitaire" inputmode="decimal" value="${esc(it.prix_unitaire ?? '')}"></label>
                <label>Devise<input data-f="devise" maxlength="3" value="${esc(it.devise || state.settings.devise_base)}"></label>
              </div>
              <small class="muted">${esc(state.schema.units[it.unite || 'forfait'] || '')}${it.taxes ? ` · taxes ${money(it.taxes, it.devise)}` : ''}${it.supplier_id ? ' · fournisseur reconnu ✔' : it._fournisseur ? ` · fournisseur « ${esc(it._fournisseur)} » non répertorié` : ''}${it.notes ? ` · ${esc(it.notes.replace('[Import IA] ', '').replace(/^Fournisseur indiqué : [^\n]*\n?/, '').replace(/\n/g, ' · '))}` : ''}</small>
            </div></div>`;
        }).join('')}</div>`;
      btn.textContent = `Ajouter au dossier`;
      return false;
    },
  });
}

/** Résumé compact du dossier envoyé à l'IA pour l'analyse (sans données personnelles sensibles). */
export function aiSummary(data, opt, computed) {
  const t = data.trip;
  return {
    voyage: t.titre,
    demande_client: t.demande,
    destination: data.destination ? { nom: data.destination.nom, pays: data.destination.pays, hors_ue: !!+data.destination.hors_ue, formalites: data.destination.formalites } : null,
    dates: { depart: t.date_depart, retour: t.date_retour, flexibilite: t.flexibilite },
    ville_depart: t.ville_depart,
    voyageurs: { adultes: t.nb_adultes, enfants: t.nb_enfants, bebes: t.nb_bebes, nommes: data.travelers.length },
    budget: t.budget,
    variante: opt.nom,
    prestations: (opt.items || []).filter((i) => i.statut !== 'annule').map((i) => ({
      type: i.type, libelle: i.libelle, optionnel: !!+i.optionnel, debut: [i.date_debut, i.heure_debut].filter(Boolean).join(' '),
      fin: [i.date_fin, i.heure_fin].filter(Boolean).join(' '), de: i.lieu_depart, vers: i.lieu_arrivee, compagnie: i.compagnie,
      numero: i.numero, escales: i.escales, categorie: i.categorie, chambre: i.chambre, pension: i.pension, statut: i.statut,
    })),
    programme: (opt.days || []).map((d) => `J${d.jour} ${d.titre}`),
    prix: { total: computed.totals.vente, par_personne: computed.totals.parPers, marge_pct: Math.round(computed.totals.tauxMarque) },
  };
}

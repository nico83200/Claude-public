// Helpers d'interface : échappement, formats, modales, notifications, formulaires générés depuis le schéma.

import { searchAirports } from './airports.js';

export const state = { schema: null, settings: {}, currencies: [], user: null, ai: { enabled: false }, refs: {} };

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (s) => (s === null || s === undefined ? '' : String(s).replace(/[&<>"']/g, (c) => ESC[c]));
export const nl2br = (s) => esc(s).replace(/\n/g, '<br>');

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

const nf = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const nf0 = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
export function money(n, cur, decimals = true) {
  if (n === null || n === undefined || n === '' || isNaN(+n)) return '—';
  cur = cur || state.settings.devise_base || 'EUR';
  const sym = { EUR: '€', USD: '$', GBP: '£', CHF: 'CHF', JPY: '¥' }[cur] || cur;
  return `${(decimals ? nf : nf0).format(+n)} ${sym}`;
}
export const pct = (n, d = 1) => (n === null || n === undefined || isNaN(n) ? '—' : `${(+n).toFixed(d).replace('.', ',')} %`);
export const num = (n, d = 0) => (n === null || n === undefined || isNaN(+n) ? '—' : (+n).toLocaleString('fr-FR', { maximumFractionDigits: d }));
export function date(d, long = false) {
  if (!d) return '';
  const [y, m, day] = d.slice(0, 10).split('-');
  if (!long) return `${day}/${m}/${y}`;
  return new Date(Date.UTC(+y, +m - 1, +day)).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
}
export const dateShort = (d) => (d ? new Date(d + 'T00:00:00Z').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', timeZone: 'UTC' }) : '');

export const ITEM_ICONS = {
  vol: '✈️', train: '🚆', hebergement: '🏨', croisiere: '🚢', transfert: '🚐', location: '🚗',
  activite: '🎟️', assurance: '🛡️', visa: '🛂', frais: '🧾', autre: '📌',
};

export const STATUS_COLORS = {
  prospect: 'gray', devis: 'blue', option: 'amber', confirme: 'green', solde: 'teal', termine: 'gray', annule: 'red',
  a_demander: 'gray', demande: 'blue', confirme_item: 'green',
};

export function badge(entity, field, value) {
  if (!value) return '';
  const label = state.schema?.entities?.[entity]?.fields?.[field]?.options?.[value] ?? value;
  const color = entity === 'items' && value === 'confirme' ? 'green' : entity === 'items' && value === 'option' ? 'amber' : STATUS_COLORS[value] || 'gray';
  return `<span class="badge ${color}">${esc(label)}</span>`;
}

export function enumLabel(entity, field, value) {
  return state.schema?.entities?.[entity]?.fields?.[field]?.options?.[value] ?? value ?? '';
}

export function refLabel(entity, id) {
  if (!id) return '';
  const r = (state.refs[entity] || []).find((x) => x.id === +id);
  if (!r) return `#${id}`;
  const title = state.schema.entities[entity].title;
  return title.map((f) => r[f]).filter(Boolean).join(' ');
}

/* ------------------------------------------------------------------ Notifications */

export function toast(msg, type = 'ok', ms = 3500) {
  let box = $('#toasts');
  if (!box) {
    box = document.createElement('div');
    box.id = 'toasts';
    document.body.appendChild(box);
  }
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.setAttribute('role', 'status');
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(() => el.classList.add('out'), ms);
  setTimeout(() => el.remove(), ms + 400);
}

export function errorToast(e) {
  toast(e?.message || String(e), 'error', 6000);
}

/* ------------------------------------------------------------------ Modales */

export function modal({ title, body, submitLabel = 'Enregistrer', wide = false, onSubmit = null, onOpen = null, footer = null, cancelLabel = 'Annuler' }) {
  return new Promise((resolve) => {
    const wrap = document.createElement('div');
    wrap.className = 'modal-backdrop';
    wrap.innerHTML = `
      <div class="modal ${wide ? 'wide' : ''}" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <form novalidate>
          <header><h2 id="modal-title">${esc(title)}</h2><button type="button" class="icon-btn" data-close aria-label="Fermer">✕</button></header>
          <div class="modal-body">${body}</div>
          <footer>${footer ?? `<button type="button" class="btn" data-close>${esc(cancelLabel)}</button>${onSubmit ? `<button type="submit" class="btn primary">${esc(submitLabel)}</button>` : ''}`}</footer>
        </form>
      </div>`;
    document.body.appendChild(wrap);
    document.body.classList.add('modal-open');
    const form = $('form', wrap);
    const close = (val) => {
      wrap.remove();
      if (!$('.modal-backdrop')) document.body.classList.remove('modal-open');
      document.removeEventListener('keydown', onKey);
      resolve(val);
    };
    const onKey = (e) => {
      if (e.key === 'Escape' && wrap === $$('.modal-backdrop').pop()) close(null);
    };
    document.addEventListener('keydown', onKey);
    wrap.addEventListener('mousedown', (e) => {
      if (e.target === wrap) close(null);
    });
    $$('[data-close]', wrap).forEach((b) => b.addEventListener('click', () => close(null)));
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!onSubmit) return close(true);
      const btn = $('button[type=submit]', form);
      btn && (btn.disabled = true);
      try {
        const res = await onSubmit(form, close);
        if (res !== false) close(res ?? true);
      } catch (err) {
        showFieldErrors(form, err);
      } finally {
        btn && (btn.disabled = false);
      }
    });
    onOpen && onOpen(form, close);
    const first = $('input:not([type=hidden]):not([type=checkbox]),select,textarea', form);
    first && setTimeout(() => first.focus(), 30);
  });
}

export function confirmDialog(message, { danger = true, label = 'Confirmer' } = {}) {
  return modal({
    title: 'Confirmation', body: `<p>${esc(message)}</p>`, submitLabel: label, onSubmit: () => true,
  }).then((r) => {
    void danger;
    return !!r;
  });
}

export function showFieldErrors(form, err) {
  $$('.field-error', form).forEach((e) => e.remove());
  $$('.invalid', form).forEach((e) => e.classList.remove('invalid'));
  if (err?.fields) {
    for (const [k, msg] of Object.entries(err.fields)) {
      const input = form.elements[k];
      const target = input?.closest?.('label') || null;
      if (target) {
        target.classList.add('invalid');
        target.insertAdjacentHTML('beforeend', `<span class="field-error">${esc(msg)}</span>`);
      } else {
        toast(`${k} : ${msg}`, 'error');
      }
    }
    toast(err.message, 'error');
  } else {
    errorToast(err);
  }
}

/* ------------------------------------------------------------------ Formulaires */

function inputFor(name, f, value, entity) {
  const v = value ?? f.default ?? '';
  const req = f.required ? 'required' : '';
  switch (f.type) {
    case 'text':
      return `<textarea name="${name}" rows="${f.wide ? 4 : 3}" ${req}>${esc(v)}</textarea>`;
    case 'bool':
      return `<input type="checkbox" name="${name}" value="1" ${+v ? 'checked' : ''}>`;
    case 'int':
      return `<input type="number" step="1" name="${name}" value="${esc(v)}" ${req}>`;
    case 'float':
      return `<input type="text" inputmode="decimal" name="${name}" value="${esc(v === '' ? '' : String(v).replace('.', ','))}" ${req}>`;
    case 'date':
      return `<input type="date" name="${name}" value="${esc(v)}" ${req}>`;
    case 'time':
      return `<input type="time" name="${name}" value="${esc(v)}" ${req}>`;
    case 'email':
      return `<input type="email" name="${name}" value="${esc(v)}" ${req}>`;
    case 'enum':
      return `<select name="${name}" ${req}>${Object.entries(f.options).map(([k, l]) => `<option value="${esc(k)}" ${String(v) === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
    case 'fk': {
      const rows = state.refs[f.ref] || [];
      return `<select name="${name}" ${req}><option value="">—</option>${rows.map((r) => `<option value="${r.id}" ${+v === r.id ? 'selected' : ''}>${esc(refLabel(f.ref, r.id))}</option>`).join('')}</select>`;
    }
    default: {
      const list = entity === 'items' && ['lieu_depart', 'lieu_arrivee'].includes(name) ? 'list="dl-airports"' : '';
      const cur = name === 'devise' ? 'list="dl-currencies" maxlength="3" style="text-transform:uppercase"' : '';
      return `<input type="text" name="${name}" value="${esc(v)}" ${req} ${list} ${cur} autocomplete="off">`;
    }
  }
}

function monthsWidget(name, value) {
  const months = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
  const v = (value || '000000000000').padEnd(12, '0');
  return `<div class="months-widget" data-months="${name}"><input type="hidden" name="${name}" value="${esc(v)}">
    ${months.map((m, i) => `<button type="button" class="m lvl${v[i]}" data-i="${i}" title="Cliquer pour changer">${m}</button>`).join('')}
    <span class="muted small">Cliquez : rouge = déconseillé, orange = correct, vert = idéal</span></div>`;
}

/**
 * Génère un formulaire à partir de la définition d'entité.
 * opts.type : (items) filtre les champs pertinents ; opts.exclude : champs à masquer.
 */
export function entityForm(entity, values = {}, opts = {}) {
  const def = state.schema.entities[entity];
  const sections = {};
  for (const [name, f] of Object.entries(def.fields)) {
    if (f.hidden || (opts.exclude || []).includes(name)) continue;
    const sec = f.section || '';
    (sections[sec] = sections[sec] || []).push([name, f]);
  }
  const html = Object.entries(sections).map(([sec, fields]) => {
    const inner = fields.map(([name, f]) => {
      const typesAttr = f.types ? `data-types="${f.types.join(' ')}"` : '';
      const hiddenByType = opts.type && f.types && !f.types.includes(opts.type) ? 'hidden' : '';
      const help = f.help ? `<small class="help">${esc(f.help)}</small>` : '';
      if (f.widget === 'months') return `<div class="field span2" ${typesAttr}><span class="lbl">${esc(f.label)}</span>${monthsWidget(name, values[name])}</div>`;
      if (f.type === 'bool') return `<label class="field check ${f.wide ? 'span2' : ''}" ${typesAttr} ${hiddenByType}>${inputFor(name, f, values[name], entity)} <span>${esc(f.label)}</span>${help}</label>`;
      return `<label class="field ${f.wide ? 'span2' : ''}" ${typesAttr} ${hiddenByType}><span class="lbl">${esc(f.label)}${f.required ? ' *' : ''}</span>${inputFor(name, f, values[name], entity)}${help}</label>`;
    }).join('');
    return `<fieldset>${sec ? `<legend>${esc(sec)}</legend>` : ''}<div class="form-grid">${inner}</div></fieldset>`;
  }).join('');
  return html + datalists();
}

export function datalists() {
  if ($('#dl-currencies')) return '';
  return `<datalist id="dl-currencies">${state.currencies.map((c) => `<option value="${esc(c.code)}">`).join('')}</datalist>`;
}

/** Comportements dynamiques des formulaires (widget mois, autocomplétion aéroports). */
export function enhanceForm(form) {
  $$('.months-widget', form).forEach((w) => {
    w.addEventListener('click', (e) => {
      const b = e.target.closest('button.m');
      if (!b) return;
      const input = $('input', w);
      const arr = input.value.split('');
      const i = +b.dataset.i;
      arr[i] = String((+arr[i] + 1) % 3);
      input.value = arr.join('');
      b.className = `m lvl${arr[i]}`;
    });
  });
  $$('input[list=dl-airports]', form).forEach((inp) => {
    inp.addEventListener('input', () => {
      let dl = $('#dl-airports');
      if (!dl) {
        dl = document.createElement('datalist');
        dl.id = 'dl-airports';
        document.body.appendChild(dl);
      }
      dl.innerHTML = searchAirports(inp.value).map((a) => `<option value="${a.code}">${esc(a.name)} — ${esc(a.country)}</option>`).join('');
    });
  });
}

export function readForm(form, entity = null) {
  const out = {};
  const def = entity ? state.schema.entities[entity].fields : null;
  for (const el of form.elements) {
    if (!el.name || el.disabled) continue;
    if (el.closest('[hidden]')) continue;
    if (el.type === 'checkbox') out[el.name] = el.checked ? 1 : 0;
    else if (el.type === 'radio') {
      if (el.checked) out[el.name] = el.value;
    } else out[el.name] = el.value;
    if (def?.[el.name]?.type === 'float' && typeof out[el.name] === 'string') out[el.name] = out[el.name].replace(/\s/g, '').replace(',', '.');
  }
  return out;
}

export function debounce(fn, ms = 300) {
  let t;
  return (...a) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...a), ms);
  };
}

export function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result).split(',')[1]);
    r.onerror = reject;
    r.readAsDataURL(file);
  });
}

/** Réduit une image (capture d'écran) avant envoi : moins de données, moins de coût IA. */
export async function downscaleImage(file, maxSide = 1600) {
  if (!file.type.startsWith('image/') || file.type === 'image/gif') return { type: file.type, data: await fileToBase64(file), name: file.name };
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise((res, rej) => {
      const i = new Image();
      i.onload = () => res(i);
      i.onerror = rej;
      i.src = url;
    });
    const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
    if (scale === 1 && file.size < 1.5e6) return { type: file.type, data: await fileToBase64(file), name: file.name };
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * scale);
    c.height = Math.round(img.height * scale);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    const data = c.toDataURL('image/jpeg', 0.88).split(',')[1];
    return { type: 'image/jpeg', data, name: file.name };
  } finally {
    URL.revokeObjectURL(url);
  }
}

export function emptyState(icon, text, action = '') {
  return `<div class="empty"><div class="empty-icon">${icon}</div><p>${text}</p>${action}</div>`;
}

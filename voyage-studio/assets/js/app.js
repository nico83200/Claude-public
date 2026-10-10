// Point d'entrée : authentification, mise en page, routeur (hash) et chargement paresseux des vues.

import { api, setCsrf } from './api.js';
import { $, esc, state, toast, errorToast, debounce } from './ui.js';

const NAV = [
  ['#/', '📊', 'Tableau de bord'],
  ['#/trips', '🧳', 'Dossiers'],
  ['#/clients', '👥', 'Clients'],
  ['#/suppliers', '🏢', 'Fournisseurs'],
  ['#/destinations', '🌍', 'Destinations'],
  ['#/tools', '🧮', 'Outils'],
  ['#/settings', '⚙️', 'Réglages'],
];

const ROUTES = [
  [/^\/$/, () => import('./views/dashboard.js')],
  [/^\/trips$/, () => import('./views/trips.js')],
  [/^\/trips\/(\d+)$/, () => import('./views/trip.js')],
  [/^\/trips\/(\d+)\/compare$/, () => import('./views/compare.js')],
  [/^\/trips\/(\d+)\/quote\/(\d+)$/, () => import('./views/quote.js')],
  [/^\/(clients|suppliers|destinations)$/, () => import('./views/entities.js')],
  [/^\/(clients|suppliers|destinations)\/(\d+)$/, () => import('./views/entity.js')],
  [/^\/destinations\/compare$/, () => import('./views/destinations-compare.js')],
  [/^\/tools$/, () => import('./views/tools.js')],
  [/^\/settings$/, () => import('./views/settings.js')],
];

export async function refreshRefs(...entities) {
  const list = entities.length ? entities : ['clients', 'suppliers', 'destinations'];
  await Promise.all(list.map(async (e) => {
    state.refs[e] = await api.get(e, { limit: 5000 });
  }));
}

export function hashParams() {
  return new URLSearchParams((location.hash.split('?')[1]) || '');
}

export function navigate(hash) {
  if (location.hash === hash) route();
  else location.hash = hash;
}

async function boot() {
  try {
    const me = await api.get('auth/me');
    setCsrf(me.csrf);
    if (!me.user) return renderLogin();
    await loadApp();
  } catch (e) {
    $('#app').innerHTML = `<div class="boot error">${esc(e.message)}</div>`;
  }
}

async function loadApp() {
  const b = await api.get('bootstrap');
  Object.assign(state, { schema: b.schema, settings: b.settings, currencies: b.currencies, user: b.user, ai: b.ai });
  await refreshRefs();
  renderLayout();
  route();
}

function renderLogin() {
  const installed = new URLSearchParams(location.search).has('installed');
  $('#app').innerHTML = `
    <main class="auth-page"><form class="auth-card" id="login">
      <h1 class="brand">✈ VoyageStudio</h1>
      <p class="muted">Studio de création de voyages</p>
      ${installed ? '<p class="alert ok">Installation terminée : connectez-vous.</p>' : ''}
      <label class="field"><span class="lbl">E-mail</span><input type="email" name="email" required autocomplete="username"></label>
      <label class="field"><span class="lbl">Mot de passe</span><input type="password" name="password" required autocomplete="current-password"></label>
      <p class="alert error" id="login-error" hidden></p>
      <button class="btn primary block" type="submit">Se connecter</button>
    </form></main>`;
  $('#login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const btn = $('button', f);
    btn.disabled = true;
    try {
      const r = await api.post('auth/login', { email: f.email.value, password: f.password.value });
      setCsrf(r.csrf);
      history.replaceState(null, '', location.pathname + (location.hash || '#/'));
      await loadApp();
    } catch (err) {
      const el = $('#login-error');
      el.textContent = err.message;
      el.hidden = false;
    } finally {
      btn.disabled = false;
    }
  });
  $('input[name=email]').focus();
}

function renderLayout() {
  const s = state.settings;
  document.documentElement.style.setProperty('--brand', s.agence_couleur || '#0f766e');
  $('#app').innerHTML = `
    <div class="layout">
      <aside class="sidebar" id="sidebar">
        <div class="brand-block">${s.agence_logo ? `<img src="${esc(s.agence_logo)}" alt="">` : '<span class="logo">✈</span>'}<span>${esc(s.agence_nom || 'VoyageStudio')}</span></div>
        <nav>${NAV.map(([h, i, l]) => `<a href="${h}" data-nav="${h}"><span class="ico">${i}</span>${esc(l)}</a>`).join('')}</nav>
        <div class="sidebar-foot">
          <span class="muted small">${esc(state.user?.email || '')}</span>
          <button class="btn ghost small" id="logout">Déconnexion</button>
        </div>
      </aside>
      <div class="main-col">
        <header class="topbar">
          <button class="icon-btn menu-btn" id="menu-btn" aria-label="Menu">☰</button>
          <div class="search-box">
            <input type="search" id="global-search" placeholder="Rechercher un dossier, client, fournisseur…" autocomplete="off" aria-label="Recherche">
            <div class="search-results" id="search-results" hidden></div>
          </div>
          <button class="btn primary" id="new-trip">+ Nouveau dossier</button>
        </header>
        <main id="view" class="view"></main>
      </div>
    </div>`;
  $('#logout').addEventListener('click', async () => {
    await api.post('auth/logout').then((r) => setCsrf(r.csrf)).catch(() => {});
    renderLogin();
  });
  $('#menu-btn').addEventListener('click', () => $('#sidebar').classList.toggle('open'));
  $('#sidebar').addEventListener('click', (e) => e.target.closest('a') && $('#sidebar').classList.remove('open'));
  $('#new-trip').addEventListener('click', async () => {
    const { newTripDialog } = await import('./views/trips.js');
    newTripDialog();
  });

  const input = $('#global-search');
  const box = $('#search-results');
  const run = debounce(async () => {
    const q = input.value.trim();
    if (q.length < 2) {
      box.hidden = true;
      return;
    }
    const res = await api.get('search', { q }).catch(() => []);
    box.innerHTML = res.length
      ? res.map((r) => `<a href="#/${r.entity}/${r.id}"><span class="muted small">${esc(r.type)}</span> ${esc(r.label)}</a>`).join('')
      : '<div class="muted pad">Aucun résultat</div>';
    box.hidden = false;
  }, 250);
  input.addEventListener('input', run);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') $('a', box)?.click();
    if (e.key === 'Escape') box.hidden = true;
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-box')) box.hidden = true;
    if (e.target.closest('#search-results a')) {
      box.hidden = true;
      input.value = '';
    }
  });
  // Raccourci clavier « / » pour rechercher
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.target.closest('input,textarea,select,[contenteditable]')) {
      e.preventDefault();
      input.focus();
    }
  });
}

let routeSeq = 0;
async function route() {
  const old = $('#view');
  if (!old) return;
  // Nouvel élément à chaque navigation : aucun écouteur d'une vue précédente ne survit.
  const view = old.cloneNode(false);
  old.replaceWith(view);
  const path = ((location.hash || '#/').slice(1) || '/').split('?')[0];
  const seq = ++routeSeq;
  document.querySelectorAll('[data-nav]').forEach((a) => {
    const h = a.dataset.nav.slice(1);
    a.classList.toggle('active', h === '/' ? path === '/' : path.startsWith(h));
  });
  document.body.classList.toggle('print-view', path.includes('/quote/'));
  document.title = 'VoyageStudio';
  for (const [re, loader] of ROUTES) {
    const m = path.match(re);
    if (!m) continue;
    view.innerHTML = '<div class="loading">Chargement…</div>';
    try {
      const mod = await loader();
      if (seq !== routeSeq) return;
      await mod.render(view, m.slice(1));
      window.scrollTo(0, 0);
    } catch (e) {
      console.error(e);
      if (seq === routeSeq) view.innerHTML = `<div class="alert error">${esc(e.message)}</div>`;
    }
    return;
  }
  view.innerHTML = '<div class="alert error">Page introuvable</div>';
}

window.addEventListener('hashchange', route);
window.addEventListener('vs:unauthorized', () => {
  toast('Session expirée, reconnectez-vous', 'error');
  renderLogin();
});
window.addEventListener('unhandledrejection', (e) => errorToast(e.reason));

boot();

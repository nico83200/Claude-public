#!/usr/bin/env python3
"""Génère le site statique jackcie.fr dans site/ à partir de scripts/site/pages/*.html.

Usage : python3 scripts/site/build.py
Chaque page commence par un bloc de métadonnées entre <!-- --> (titre, description, menu).
Raccourcis utilisables dans les pages : {{icon:nom}}, {{app}}, {{signup:source}}, {{login}}, {{year}}.
"""
import datetime
import html
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[2]
SRC = ROOT / 'scripts/site/pages'
OUT = ROOT / 'site'
SITE = 'https://jackcie.fr'
APP = 'https://app.jackcie.fr'
INSTAGRAM = 'https://www.instagram.com/jackcieapp/'
EMAIL = 'contact@jackcie.fr'

# Fenêtre promotionnelle « Spécial EquitaLyon » : affichée une fois par visiteur jusqu'à la date de fin (incluse).
# Pour l'arrêter plus tôt : PROMO = None, puis régénérer le site.
PROMO = {
    'code': 'EQUITA2026',
    'url': APP + '/register?code=EQUITA2026&src=site',
    'until': '2026-11-30',
}

ICONS = {
    'check': '<path d="M4.5 12.75l6 6 9-13.5"/>',
    'check-circle': '<path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'x': '<path d="M6 18L18 6M6 6l12 12"/>',
    'menu': '<path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>',
    'arrow': '<path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>',
    'heart': '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
    'calendar': '<path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>',
    'bolt': '<path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>',
    'wallet': '<path d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/>',
    'share': '<path d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>',
    'mic': '<path d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>',
    'sparkles': '<path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>',
    'phone': '<path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>',
    'bell': '<path d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>',
    'shield': '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
    'users': '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
    'receipt': '<path d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>',
    'camera': '<path d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>',
    'scale': '<path d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z"/>',
    'alert': '<path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
    'doc': '<path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
    'offline': '<path d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>',
    'lock': '<path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>',
    'building': '<path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>',
    'mail': '<path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>',
    'chart': '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
    'instagram': '<path d="M7.5 3h9A4.5 4.5 0 0121 7.5v9a4.5 4.5 0 01-4.5 4.5h-9A4.5 4.5 0 013 16.5v-9A4.5 4.5 0 017.5 3zM15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM17.25 6.75h.008v.008h-.008V6.75z"/>',
    'qr': '<path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/>',
}

NAV = [('/', 'Propriétaires', 'home'), ('/ecuries', 'Écuries', 'ecuries'), ('/tarifs', 'Tarifs', 'tarifs'), ('/conseils', 'Conseils', 'conseils'), ('/contact', 'Contact', 'contact')]


def icon(name, cls=''):
    return f'<svg{" class=" + chr(34) + cls + chr(34) if cls else ""} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{ICONS[name]}</svg>'


def expand(text):
    text = re.sub(r'\{\{icon:([a-z-]+)\}\}', lambda m: icon(m.group(1)), text)
    text = re.sub(r'\{\{signup:([a-z0-9-]+)\}\}', lambda m: f'{APP}/register?src=site-{m.group(1)}', text)
    return (text.replace('{{app}}', APP).replace('{{login}}', APP + '/login').replace('{{year}}', str(datetime.date.today().year))
            .replace('{{email}}', EMAIL).replace('{{instagram}}', INSTAGRAM))


def layout(meta, body):
    path = meta['path']
    url = SITE + path
    nav = ''.join(f'<a href="{href}"{" aria-current=" + chr(34) + "page" + chr(34) if meta.get("nav") == key else ""}>{label}</a>' for href, label, key in NAV)
    schema = meta.get('schema', '')
    return f'''<!doctype html>
<html lang="fr" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{html.escape(meta["title"])}</title>
<meta name="description" content="{html.escape(meta["description"])}">
<link rel="canonical" href="{url}">
<meta name="theme-color" content="#fbfaf6">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="Jack&amp;Cie">
<meta property="og:title" content="{html.escape(meta.get("og_title", meta["title"]))}">
<meta property="og:description" content="{html.escape(meta["description"])}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE}/img/og-{meta.get("og", "accueil")}.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/png" sizes="32x32" href="/img/brand/favicon-32.png">
<link rel="apple-touch-icon" href="/img/brand/apple-touch-icon.png">
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/site.css?v={VERSION}">
{"<meta name=" + chr(34) + "robots" + chr(34) + " content=" + chr(34) + "noindex" + chr(34) + ">" if meta.get("noindex") else ""}
{schema}
</head>
<body>
<a class="skip" href="#contenu">Aller au contenu</a>
{promo_banner()}
<a class="promo-bar" id="code-bar" href="{APP}/register" hidden>
  <span class="promo-bar-text">🎁 Code <span class="promo-bar-code" data-code-value></span> : il sera appliqué à la création de votre compte</span>
  <span class="promo-bar-cta">Créer mon compte {icon("arrow")}</span>
</a>
<header class="header">
  <div class="wrap">
    <a href="/" class="logo" aria-label="Jack&amp;Cie, accueil"><img src="/img/brand/jackcie-wordmark.webp" alt="Jack&amp;Cie" width="130" height="38"></a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav" aria-label="Ouvrir le menu">{icon("menu")}</button>
    <nav class="nav" id="nav" aria-label="Navigation principale">
      {nav}
      <a href="{{{{login}}}}" class="login">Se connecter</a>
      <a href="{{{{signup:menu}}}}" class="btn btn-primary">Essayer gratuitement</a>
    </nav>
  </div>
</header>
<main id="contenu">
{body}
</main>
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="brand">
        <img src="/img/brand/jackcie-wordmark.webp" alt="Jack&amp;Cie" width="136" height="40">
        <p>L'application qui suit votre cheval au quotidien&nbsp;: santé, séances, agenda, budget et partage, avec un assistant à qui parler.</p>
        <a class="social" href="{{{{instagram}}}}" target="_blank" rel="noopener">{icon("instagram")} @jackcieapp</a>
      </div>
      <div>
        <h4>Jack&amp;Cie</h4>
        <ul><li><a href="/">Pour les propriétaires</a></li><li><a href="/ecuries">Pour les écuries</a></li><li><a href="/tarifs">Tarifs</a></li><li><a href="/tarifs#faq">Questions fréquentes</a></li><li><a href="/conseils">Conseils pratiques</a></li></ul>
      </div>
      <div>
        <h4>Application</h4>
        <ul><li><a href="{{{{signup:footer}}}}">Créer un compte</a></li><li><a href="{{{{login}}}}">Se connecter</a></li><li><a href="{{{{app}}}}/conditions">Conditions d'utilisation</a></li><li><a href="{{{{app}}}}/confidentialite">Confidentialité de l'application</a></li></ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul><li><a href="/contact">Nous écrire</a></li><li><a href="mailto:{{{{email}}}}">{{{{email}}}}</a></li><li><a href="/mentions-legales">Mentions légales</a></li><li><a href="/confidentialite">Confidentialité du site</a></li></ul>
      </div>
    </div>
    <div class="footer-bottom"><span>© {{{{year}}}} Jack&amp;Cie — une application NLapps</span><span>Conçue en France, pour les cavaliers.</span></div>
  </div>
</footer>
{promo_popup(meta)}
<script src="/js/site.js?v={VERSION}" defer></script>
</body>
</html>
'''


def promo_banner():
    """Bandeau permanent de l'offre, en haut de toutes les pages, jusqu'à la date de fin (masqué ensuite par le script)."""
    if not PROMO:
        return ''
    return f'''<a class="promo-bar" id="promo-bar" href="{PROMO['url']}" data-promo="{PROMO['code']}" data-until="{PROMO['until']}" hidden>
  <span class="promo-bar-event">EQUITA<em>LYON</em> 2026</span>
  <span class="promo-bar-text"><b>30 jours offerts</b><span class="promo-bar-long"> · toutes les fonctionnalités, sans carte bancaire</span> · code <span class="promo-bar-code">{PROMO['code']}</span></span>
  <span class="promo-bar-cta">J'en profite {icon("arrow")}</span>
</a>'''


def promo_popup(meta):
    """Fenêtre promotionnelle (masquée sans JavaScript, jamais sur les pages légales ni le contact)."""
    if not PROMO or meta.get('nav') == 'contact' or meta.get('noindex') or meta.get('priority') == '0.2':
        return ''
    perks = ''.join(f'<li>{icon("check")}{t}</li>' for t in ['Assistant vocal, santé, séances, budget', 'Agenda partagé, fiche d\'urgence, partage', 'Sans carte bancaire, sans engagement'])
    return f'''<div class="promo" id="promo" data-promo="{PROMO['code']}" data-until="{PROMO['until']}" role="dialog" aria-modal="true" aria-labelledby="promo-title" hidden>
  <div class="promo-backdrop" data-promo-close></div>
  <div class="promo-card">
    <button type="button" class="promo-x" data-promo-close aria-label="Fermer">{icon("x")}</button>
    <div class="promo-band"><span class="promo-kicker">Offre spéciale salon</span><span class="promo-event">EQUITA<em>LYON</em> 2026</span></div>
    <div class="promo-body">
      <img src="/img/brand/jackcie-logo.webp" alt="Jack&amp;Cie" width="150" height="128" class="promo-logo">
      <p class="promo-title" id="promo-title">30 jours <em>offerts</em></p>
      <p class="promo-lead"><b>Toutes les fonctionnalités</b>, sans carte bancaire.</p>
      <div class="promo-code"><span>Votre code</span><b>{PROMO['code']}</b></div>
      <ul class="promo-perks">{perks}</ul>
      <a href="{PROMO['url']}" class="btn btn-gold btn-lg promo-cta" data-promo-cta>J'en profite {icon("arrow")}</a>
      <p class="promo-note">Le code s'active tout seul à la création du compte. Offre valable une fois par compte sur l'offre Propriétaire ; à la fin des 30 jours, sans souscription, le compte passe à l'offre gratuite.</p>
    </div>
  </div>
</div>'''


def parse(file):
    raw = file.read_text(encoding='utf-8')
    m = re.match(r'\s*<!--(.*?)-->\s*', raw, re.S)
    meta = json.loads(m.group(1))
    return meta, raw[m.end():]


VERSION = datetime.date.today().strftime('%Y%m%d')

if __name__ == '__main__':
    pages = []
    for file in sorted(SRC.glob('*.html')):
        meta, body = parse(file)
        out = OUT / (meta['file'])
        out.write_text(expand(layout(meta, body)), encoding='utf-8')
        pages.append(meta)
        print('✓', meta['file'])
    today = datetime.date.today().isoformat()
    urls = ''.join(f'  <url><loc>{SITE}{p["path"]}</loc><lastmod>{today}</lastmod><priority>{p.get("priority", "0.5")}</priority></url>\n' for p in pages if not p.get('noindex'))
    (OUT / 'sitemap.xml').write_text(f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n{urls}</urlset>\n', encoding='utf-8')
    print('✓ sitemap.xml')

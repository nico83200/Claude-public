/* Bouton « œil » pour afficher / masquer les mots de passe (boutique, compte, administration). */
(function () {
  var eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  var style = document.createElement('style');
  style.textContent = '.pw-wrap{position:relative;display:block}.pw-wrap input{padding-right:2.9rem !important}' +
    '.pw-toggle{position:absolute;right:.35rem;top:50%;transform:translateY(-50%);width:2.3rem;height:2.3rem;display:grid;place-items:center;border:0;background:none;color:inherit;opacity:.6;cursor:pointer;border-radius:50%;padding:0}' +
    '.pw-toggle:hover,.pw-toggle:focus-visible{opacity:1;background:rgba(90,42,58,.08)}';
  document.head.appendChild(style);
  var init = function (input) {
    if (input.dataset.pwToggle) return;
    input.dataset.pwToggle = '1';
    var wrap = document.createElement('span');
    wrap.className = 'pw-wrap';
    wrap.style.marginTop = getComputedStyle(input).marginTop;
    input.style.marginTop = '0';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    var set = function (visible) {
      input.type = visible ? 'text' : 'password';
      btn.innerHTML = visible ? eyeOff : eye;
      btn.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    };
    set(false);
    btn.addEventListener('click', function () { set(input.type === 'password'); input.focus(); });
    wrap.appendChild(btn);
    // Toujours remasqué à l'envoi (le navigateur ne mémorise pas le mot de passe en clair)
    if (input.form) input.form.addEventListener('submit', function () { set(false); });
  };
  var run = function () { document.querySelectorAll('input[type="password"]').forEach(init); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();

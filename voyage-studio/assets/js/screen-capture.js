// Capture d'écran depuis l'application (API Screen Capture du navigateur) + recadrage à la souris.
// Fonctionne sur ordinateur (Chrome, Edge, Firefox, Safari récent) et uniquement en HTTPS.

import { $, modal, toast } from './ui.js';

export const canCapture = () => !!navigator.mediaDevices?.getDisplayMedia && window.isSecureContext;

/** Capture une image de l'onglet / fenêtre / écran choisi par l'utilisateur. */
async function grabFrame() {
  const stream = await navigator.mediaDevices.getDisplayMedia({
    video: { displaySurface: 'browser' }, audio: false, selfBrowserSurface: 'exclude', preferCurrentTab: false,
  });
  try {
    const video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.srcObject = stream;
    await video.play();
    // laisse le temps à l'onglet capturé de s'afficher entièrement
    await new Promise((r) => setTimeout(r, 400));
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    return canvas;
  } finally {
    stream.getTracks().forEach((t) => t.stop());
  }
}

/** Ouvre la fenêtre de recadrage ; renvoie { type, data (base64), name } ou null. */
function cropDialog(source) {
  let sel = null; // sélection en pixels de l'image source
  return modal({
    title: '✂️ Recadrer la capture',
    wide: true,
    submitLabel: 'Utiliser cette capture',
    body: `<p class="muted small">Tracez un rectangle sur la zone utile (prix, dates, détails) pour une analyse plus précise — ou gardez l'image entière.</p>
      <div class="crop-wrap"><canvas id="crop-view"></canvas><div class="crop-sel" id="crop-sel" hidden></div></div>
      <p><button type="button" class="btn small" id="crop-reset">Toute l'image</button></p>`,
    onOpen: (form) => {
      const view = $('#crop-view', form);
      const box = $('#crop-sel', form);
      const maxW = Math.min(920, form.clientWidth - 40);
      const scale = Math.min(1, maxW / source.width);
      view.width = Math.round(source.width * scale);
      view.height = Math.round(source.height * scale);
      view.getContext('2d').drawImage(source, 0, 0, view.width, view.height);
      let start = null;
      // Coordonnées en pixels CSS affichés, converties en pixels de l'image source
      const pos = (e) => {
        const r = view.getBoundingClientRect();
        return { x: Math.max(0, Math.min(r.width, e.clientX - r.left)), y: Math.max(0, Math.min(r.height, e.clientY - r.top)) };
      };
      const draw = (a, b) => {
        const k = source.width / view.getBoundingClientRect().width;
        const x = Math.min(a.x, b.x); const y = Math.min(a.y, b.y);
        const w = Math.abs(a.x - b.x); const h = Math.abs(a.y - b.y);
        Object.assign(box.style, { left: `${x}px`, top: `${y}px`, width: `${w}px`, height: `${h}px` });
        box.hidden = w < 5 || h < 5;
        sel = box.hidden ? null : { x: x * k, y: y * k, w: w * k, h: h * k };
      };
      view.onpointerdown = (e) => { start = pos(e); view.setPointerCapture(e.pointerId); };
      view.onpointermove = (e) => start && draw(start, pos(e));
      view.onpointerup = (e) => { if (start) draw(start, pos(e)); start = null; };
      $('#crop-reset', form).onclick = () => { sel = null; box.hidden = true; };
    },
    onSubmit: () => {
      const s = sel || { x: 0, y: 0, w: source.width, h: source.height };
      // limite à 1600 px de côté : suffisant pour la lecture, moins coûteux pour l'IA
      const k = Math.min(1, 1600 / Math.max(s.w, s.h));
      const out = document.createElement('canvas');
      out.width = Math.round(s.w * k);
      out.height = Math.round(s.h * k);
      out.getContext('2d').drawImage(source, s.x, s.y, s.w, s.h, 0, 0, out.width, out.height);
      const stamp = new Date().toLocaleTimeString('fr-FR').replace(/:/g, 'h').slice(0, 5);
      return { type: 'image/jpeg', data: out.toDataURL('image/jpeg', 0.9).split(',')[1], name: `capture-${stamp}.jpg` };
    },
  });
}

/** Capture + recadrage. Renvoie l'image prête pour l'import IA, ou null si annulé. */
export async function captureScreen() {
  if (!canCapture()) {
    toast('Capture d\'écran indisponible : utilisez un navigateur d\'ordinateur et une adresse en HTTPS.', 'error', 7000);
    return null;
  }
  let canvas;
  try {
    canvas = await grabFrame();
  } catch (e) {
    if (e?.name !== 'NotAllowedError' && e?.name !== 'AbortError') toast(`Capture impossible : ${e.message}`, 'error');
    return null;
  }
  const r = await cropDialog(canvas);
  return r && r !== true ? r : null;
}

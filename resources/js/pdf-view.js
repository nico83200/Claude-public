/**
 * Aperçu d'un PDF dans la visionneuse (justificatifs) : chaque page dessinée dans un canevas.
 * PDF.js n'est chargé qu'à l'ouverture d'un PDF (version « legacy », compatible avec les iPhone plus anciens).
 * Les navigateurs Android n'affichent pas les PDF dans une page : d'où ce rendu plutôt qu'un <iframe>.
 */
let lib = null;

async function pdfjs() {
    if (!lib) {
        const [mod, worker] = await Promise.all([
            import('pdfjs-dist/legacy/build/pdf.min.mjs'),
            import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'),
        ]);
        mod.GlobalWorkerOptions.workerSrc = worker.default;
        lib = mod;
    }
    return lib;
}

/** Affiche le PDF dans `box` ; renvoie une fonction d'arrêt (fermeture ou changement de document). */
export function renderPdf(url, box, onError) {
    let cancelled = false;
    let doc = null;
    box.innerHTML = '<p class="jc-lb-loading">Ouverture du document…</p>';
    (async () => {
        try {
            const { getDocument } = await pdfjs();
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            doc = await getDocument({ data: new Uint8Array(await res.arrayBuffer()), isEvalSupported: false }).promise;
            if (cancelled) return;
            box.innerHTML = '';
            const width = Math.min(box.clientWidth || window.innerWidth, 900);
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            for (let n = 1; n <= Math.min(doc.numPages, 30) && !cancelled; n++) {
                const page = await doc.getPage(n);
                const base = page.getViewport({ scale: 1 });
                const viewport = page.getViewport({ scale: (width / base.width) * ratio });
                const canvas = document.createElement('canvas');
                canvas.className = 'jc-lb-page';
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);
                canvas.style.width = Math.floor(viewport.width / ratio) + 'px';
                canvas.setAttribute('aria-label', `Page ${n} sur ${doc.numPages}`);
                box.appendChild(canvas);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            }
            if (doc.numPages > 30 && !cancelled) box.insertAdjacentHTML('beforeend', '<p class="jc-lb-loading">Seules les 30 premières pages sont affichées : téléchargez le document pour la suite.</p>');
        } catch (e) {
            if (!cancelled) onError?.(e);
        }
    })();
    return () => { cancelled = true; doc?.destroy?.(); };
}

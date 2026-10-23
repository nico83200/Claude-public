"use client";

import { useEffect, useRef, useState } from "react";

/** Lecteur de QR code plein écran (caméra arrière) ; renvoie le texte du premier QR code lu. */
export function ScanQr({ onLu, onFermer }: { onLu: (texte: string) => void; onFermer: () => void }) {
  const video = useRef<HTMLVideoElement>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  useEffect(() => {
    let flux: MediaStream | null = null;
    let fini = false;
    let minuterie: ReturnType<typeof setTimeout>;
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d", { willReadFrequently: true });

    (async () => {
      try {
        const jsQR = (await import("jsqr")).default;
        flux = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
        if (fini) return flux.getTracks().forEach((t) => t.stop());
        const v = video.current!;
        v.srcObject = flux;
        await v.play();
        const lire = () => {
          if (fini) return;
          if (v.videoWidth && ctx) {
            // Image réduite : plus rapide, et largement suffisant pour un QR code de carte de visite.
            const k = Math.min(1, 640 / v.videoWidth);
            canvas.width = Math.round(v.videoWidth * k);
            canvas.height = Math.round(v.videoHeight * k);
            ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const qr = jsQR(img.data, img.width, img.height, { inversionAttempts: "attemptBoth" });
            if (qr?.data) {
              fini = true;
              onLu(qr.data);
              return;
            }
          }
          minuterie = setTimeout(lire, 120);
        };
        lire();
      } catch (e) {
        setErreur(e instanceof DOMException && e.name === "NotAllowedError"
          ? "Accès à la caméra refusé : autorisez-le dans les réglages de l’application."
          : "Caméra indisponible sur cet appareil.");
      }
    })();
    return () => {
      fini = true;
      clearTimeout(minuterie);
      flux?.getTracks().forEach((t) => t.stop());
    };
  }, [onLu]);

  return (
    <div className="scan-qr" role="dialog" aria-label="Scanner un QR code">
      <video ref={video} playsInline muted />
      <div className="scan-qr-cadre" aria-hidden />
      <div className="scan-qr-bas">
        <p>{erreur ?? "Visez le QR code imprimé sur la carte"}</p>
        <button className="btn btn-secondary" onClick={onFermer}>Annuler</button>
      </div>
    </div>
  );
}

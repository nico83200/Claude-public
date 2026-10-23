/** Dimensions d'une image PNG ou JPEG en data URL (lues dans l'en-tête, sans décoder l'image). */
export function dimensionsImage(dataUrl: string | null): { l: number; h: number } | null {
  const m = dataUrl?.match(/^data:image\/(png|jpeg);base64,(.+)$/);
  if (!m) return null;
  // Fonctionne côté serveur et dans le navigateur (aperçu de l'éditeur) : atob plutôt que Buffer.
  const debut = m[2].slice(0, 80_000);
  const brut = atob(debut.slice(0, debut.length - (debut.length % 4)));
  const b = Uint8Array.from(brut, (c) => c.charCodeAt(0));
  const u16 = (i: number) => (b[i] << 8) | b[i + 1];
  const u32 = (i: number) => ((b[i] << 24) >>> 0) + (b[i + 1] << 16) + (b[i + 2] << 8) + b[i + 3];
  if (m[1] === "png") return b.length >= 24 ? { l: u32(16), h: u32(20) } : null;
  // JPEG : premier segment SOFn (C0–CF sauf C4, C8, CC).
  for (let i = 2; i + 9 < b.length; ) {
    if (b[i] !== 0xff) return null;
    const marqueur = b[i + 1];
    if (marqueur >= 0xc0 && marqueur <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marqueur)) return { l: u16(i + 7), h: u16(i + 5) };
    i += 2 + u16(i + 2);
  }
  return null;
}

/** Forme d'un logo pour la mise en page du bandeau : « allonge » au-delà de 3,2 fois plus large que haut. */
export function formeImage(dataUrl: string | null): "allonge" | "" {
  const d = dimensionsImage(dataUrl);
  return d && d.h > 0 && d.l / d.h > 3.2 ? "allonge" : "";
}

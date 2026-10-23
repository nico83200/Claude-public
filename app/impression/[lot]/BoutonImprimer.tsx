"use client";

export function BoutonImprimer({ texte }: { texte: string }) {
  return <button className="btn btn-lg" onClick={() => window.print()}>{texte}</button>;
}

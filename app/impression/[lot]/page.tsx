import Link from "next/link";
import { notFound } from "next/navigation";
import { requireAdmin } from "@/lib/auth";
import { cardSvg } from "@/lib/cardSvg";
import { basicDesign, sanitizeDesign, type CardDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";
import { all } from "@/lib/db";
import { BoutonImprimer } from "./BoutonImprimer";

export const metadata = { title: "Impression du lot", robots: { index: false } };
export const dynamic = "force-dynamic";

type Format = "carte" | "a4";
type Faces = "rv" | "verso" | "recto";
type Carte = { id: string; design: CardDesign };

const FORMATS: Record<Format, string> = { carte: "Imprimante à cartes / PDF carte par carte", a4: "Planches A4 (10 cartes)" };
const FACES: Record<Faces, string> = { rv: "Recto et verso", verso: "Verso seul (QR code)", recto: "Recto seul" };

/** Une face de carte, au format exact 85,6 × 54 mm. */
const Face = ({ c, side }: { c: Carte; side: "recto" | "verso" }) => (
  <div className="imp-carte" dangerouslySetInnerHTML={{ __html: cardSvg(c.design, { side, qrUrl: `${APP_URL}/q/${c.id}` }) }} />
);

export default async function Impression({ params, searchParams }: { params: Promise<{ lot: string }>; searchParams: Promise<{ format?: string; faces?: string; tout?: string }> }) {
  await requireAdmin();
  const { lot } = await params;
  const sp = await searchParams;
  const format: Format = sp.format === "a4" ? "a4" : "carte";
  const faces: Faces = sp.faces === "verso" || sp.faces === "recto" ? sp.faces : "rv";
  const tout = sp.tout === "1";
  const lignes = all<{ id: string; design: string | null; variant: string | null; status: string }>(
    `SELECT c.id, o.design, c.variant, c.status FROM cards c LEFT JOIN orders o ON o.id = c.order_id
     WHERE c.batch_id = ? ORDER BY c.order_id, c.id`, lot);
  if (lignes.length === 0) notFound();
  // Par défaut, seulement les cartes pas encore encodées (celles qui restent à fabriquer).
  const cartes: Carte[] = lignes
    .filter((l) => tout || l.status === "provisioned")
    .map((l) => ({ id: l.id, design: l.design ? sanitizeDesign(JSON.parse(l.design)) : basicDesign(l.variant === "blanc" ? "blanc" : "noir") }));
  const lien = (o: Partial<{ format: Format; faces: Faces; tout: boolean }>) => {
    const q = new URLSearchParams({ format: o.format ?? format, faces: o.faces ?? faces });
    if (o.tout ?? tout) q.set("tout", "1");
    return `/impression/${lot}?${q}`;
  };
  const sides: ("recto" | "verso")[] = faces === "rv" ? ["recto", "verso"] : [faces];

  // A4 : 2 colonnes × 5 lignes. Au verso, les colonnes sont inversées pour tomber en face du recto
  // (impression recto-verso, retournement bord long).
  const planches: Carte[][] = [];
  for (let i = 0; i < cartes.length; i += 10) planches.push(cartes.slice(i, i + 10));

  return (
    <main className={`imp imp-format-${format}`}>
      <style>{format === "carte"
        ? "@page { size: 85.6mm 54mm; margin: 0; }"
        : "@page { size: A4; margin: 0; }"}</style>

      <div className="imp-outils">
        <div className="container" style={{ maxWidth: 900 }}>
          <p className="muted" style={{ margin: "0 0 4px" }}><Link href="/admin/production">← Production</Link></p>
          <h1 style={{ fontSize: "1.9rem", margin: "0 0 6px" }}>Impression du lot <code style={{ fontSize: "1.2rem" }}>{lot}</code></h1>
          <p className="muted" style={{ marginTop: 0 }}>
            {cartes.length} carte{cartes.length > 1 ? "s" : ""} {tout ? "(tout le lot)" : "à fabriquer"} · chaque verso porte le QR code unique de sa carte.
          </p>
          <div className="row" style={{ marginBottom: 10 }}>
            {(Object.keys(FORMATS) as Format[]).map((f) => <Link key={f} href={lien({ format: f })} className={`btn btn-sm ${f === format ? "" : "btn-secondary"}`}>{FORMATS[f]}</Link>)}
          </div>
          <div className="row" style={{ marginBottom: 10 }}>
            {(Object.keys(FACES) as Faces[]).map((f) => <Link key={f} href={lien({ faces: f })} className={`btn btn-sm ${f === faces ? "" : "btn-secondary"}`}>{FACES[f]}</Link>)}
            <Link href={lien({ tout: !tout })} className="btn btn-sm btn-ghost">{tout ? "Seulement les cartes à fabriquer" : "Inclure les cartes déjà encodées"}</Link>
          </div>
          <div className="panel" style={{ padding: 18, margin: "14px 0" }}>
            {format === "carte" ? (
              <ol style={{ margin: 0, paddingLeft: 20 }}>
                <li><strong>Imprimante à cartes PVC</strong> (Evolis, Zebra…) chargée de cartes NFC blanches : touchez « Imprimer », choisissez l’imprimante,
                  format <strong>CR80</strong>, <strong>recto-verso</strong>, marges <strong>aucune</strong>, échelle <strong>100 %</strong>. Une page = une face.</li>
                <li><strong>Imprimeur</strong> : choisissez « Enregistrer au format PDF » et envoyez le fichier (une page par face, 85,6 × 54 mm, données variables déjà intégrées).</li>
              </ol>
            ) : (
              <p style={{ margin: 0 }}>
                Planches A4 de 10 cartes, recto puis verso (retournement bord long), pour une épreuve papier ou un imprimeur qui découpe.
                Dans la fenêtre d’impression : marges <strong>aucune</strong>, échelle <strong>100 %</strong>, recto-verso <strong>bord long</strong>.
              </p>
            )}
          </div>
          {cartes.length > 0
            ? <BoutonImprimer texte={`Imprimer ${cartes.length} carte${cartes.length > 1 ? "s" : ""}`} />
            : <div className="alert alert-ok">Toutes les cartes du lot sont déjà encodées. <Link href={lien({ tout: true })}>Tout réimprimer</Link></div>}
          <h2 style={{ fontSize: "1.3rem", margin: "28px 0 10px" }}>Aperçu</h2>
        </div>
      </div>

      <div className="imp-feuilles">
        {format === "carte"
          ? cartes.flatMap((c) => sides.map((s) => (
            <section className="imp-page" key={`${c.id}-${s}`} data-legende={`${c.id} · ${s}`}><Face c={c} side={s} /></section>
          )))
          : planches.flatMap((p, n) => sides.map((s) => (
            <section className="imp-page imp-a4" key={`${n}-${s}`} data-legende={`Planche ${n + 1} · ${s}`}>
              {Array.from({ length: 10 }, (_, i) => {
                // Au verso, la carte de la colonne gauche passe à droite (et inversement).
                const j = s === "verso" ? i + (i % 2 === 0 ? 1 : -1) : i;
                const c = p[j];
                return <div className="imp-case" key={i}>{c && <Face c={c} side={s} />}</div>;
              })}
            </section>
          )))}
      </div>
    </main>
  );
}

import Link from "next/link";
import { notFound } from "next/navigation";
import QRCode from "qrcode";
import { LogoMark } from "@/components/Logo";
import { requireAdmin } from "@/lib/auth";
import { APP_URL } from "@/lib/config";
import { all, get } from "@/lib/db";
import { REMISE_ENTOURAGE, codeDe } from "@/lib/promo";
import type { Shipping, User } from "@/lib/types";
import { BoutonImprimer } from "./BoutonImprimer";

export const metadata = { title: "Courriers d’accompagnement", robots: { index: false } };
export const dynamic = "force-dynamic";

type Courrier = { cle: string; prenom: string; commande: string | null; cartes: { id: string; code: string }[]; codePromo: string | null };

/** Courriers A5 glissés avec les cartes : codes d'activation, mode d'emploi, code « entourage ». */
export default async function Courriers({ searchParams }: { searchParams: Promise<{ lot?: string; commande?: string }> }) {
  await requireAdmin();
  const { lot, commande } = await searchParams;
  const lignes = all<{ id: string; activation_code: string; order_id: string | null; user_id: string | null; shipping: string | null }>(
    `SELECT c.id, c.activation_code, c.order_id, o.user_id, o.shipping FROM cards c LEFT JOIN orders o ON o.id = c.order_id
     WHERE ${commande ? "c.order_id = ?" : "c.batch_id = ?"} ORDER BY c.order_id, c.created_at, c.id`, commande ?? lot ?? "");
  if (lignes.length === 0) notFound();

  const courriers: Courrier[] = [];
  for (const l of lignes) {
    if (!l.order_id) {
      // Carte de stock : un courrier par carte, sans destinataire connu.
      courriers.push({ cle: l.id, prenom: "", commande: null, cartes: [{ id: l.id, code: l.activation_code }], codePromo: null });
      continue;
    }
    let c = courriers.find((x) => x.commande === l.order_id);
    if (!c) {
      const u = get<User>("SELECT * FROM users WHERE id = ?", l.user_id ?? "");
      const nom = (l.shipping ? (JSON.parse(l.shipping) as Shipping).name : "") || u?.name || "";
      c = { cle: l.order_id, prenom: nom.trim().split(/\s+/)[0] ?? "", commande: l.order_id, cartes: [], codePromo: u ? codeDe(u) : null };
      courriers.push(c);
    }
    c.cartes.push({ id: l.id, code: l.activation_code });
  }
  const qrs = new Map<string, string>();
  for (const c of courriers) {
    if (c.codePromo && !qrs.has(c.codePromo)) {
      qrs.set(c.codePromo, await QRCode.toString(`${APP_URL}/carte-basic?code=${encodeURIComponent(c.codePromo)}`, { type: "svg", margin: 0, errorCorrectionLevel: "M" }));
    }
  }
  const site = APP_URL.replace(/^https?:\/\//, "");

  return (
    <main className="courriers">
      <style>{"@page { size: A5; margin: 0; }"}</style>
      <div className="imp-outils">
        <div className="container" style={{ maxWidth: 900 }}>
          <p className="muted" style={{ margin: "0 0 4px" }}><Link href={lot ? `/admin/expedition/${lot}` : `/admin/commandes/${commande}`}>← Retour</Link></p>
          <h1 style={{ fontSize: "1.9rem", margin: "0 0 6px" }}>Courriers d’accompagnement</h1>
          <p className="muted" style={{ marginTop: 0 }}>
            {courriers.length} courrier{courriers.length > 1 ? "s" : ""} au format A5 (une feuille A4 pliée en deux convient aussi) · imprimez-les puis glissez chacun avec sa carte.
            Dans la fenêtre d’impression : format A5 (ou A4 « 2 pages par feuille »), marges aucune.
          </p>
          <BoutonImprimer texte={`Imprimer ${courriers.length} courrier${courriers.length > 1 ? "s" : ""}`} />
          <h2 style={{ fontSize: "1.3rem", margin: "28px 0 10px" }}>Aperçu</h2>
        </div>
      </div>
      <div className="imp-feuilles">
        {courriers.map((c) => (
          <section className="imp-page courrier" key={c.cle}>
            <header className="courrier-tete"><LogoMark size={30} /><span>Tapikard</span></header>
            <h1>{c.prenom ? `Bonjour ${c.prenom},` : "Bonjour,"}</h1>
            <p className="courrier-intro">
              {c.cartes.length > 1 ? `Voici vos ${c.cartes.length} cartes Tapikard. Elles partagent` : "Voici votre carte Tapikard. Elle partage"} vos coordonnées d’un simple geste,
              sans application, et votre profil reste modifiable à vie.
            </p>
            <ol className="courrier-etapes">
              <li><strong>Activez-la.</strong> Approchez la carte du haut, au dos de votre téléphone (ou scannez le QR code au verso) : la page d’activation s’ouvre.</li>
              <li><strong>Complétez votre profil.</strong> Photo, coordonnées, liens… Tout se modifie ensuite depuis {site}, rubrique « Mon espace ».</li>
              <li><strong>Partagez.</strong> Approchez votre carte du téléphone de votre interlocuteur : votre fiche s’ouvre et s’enregistre. Il peut aussi vous laisser la sienne.</li>
            </ol>
            <div className="courrier-codes">
              <strong>{c.cartes.length > 1 ? "Codes d’activation" : "Code d’activation"}</strong>
              <table><tbody>
                {c.cartes.map((k, i) => (
                  <tr key={k.id}><td>{c.cartes.length > 1 ? `Carte ${i + 1}` : "Carte"} <span className="muted">· {k.id}</span></td><td><code>{k.code}</code></td></tr>
                ))}
              </tbody></table>
              <small>{c.commande ? "Demandé seulement si la carte est activée depuis un autre compte que le vôtre (par exemple une carte offerte à un collègue)." : "Demandé à l’activation : gardez-le avec la carte."}</small>
            </div>
            {c.codePromo && (
              <div className="courrier-promo">
                <div>
                  <strong>Pour vos proches : −{REMISE_ENTOURAGE * 100} % supplémentaires</strong>
                  <p>Offrez-leur votre code personnel, valable sur leur carte Tapikard :</p>
                  <div className="courrier-promo-code">{c.codePromo}</div>
                </div>
                <div className="courrier-qr" dangerouslySetInnerHTML={{ __html: qrs.get(c.codePromo)! }} />
              </div>
            )}
            <footer className="courrier-pied">Une question ? bonjour@tapikard.com · {site}{c.commande ? ` · Commande ${c.commande}` : ""}</footer>
          </section>
        ))}
      </div>
    </main>
  );
}

import Link from "next/link";
import { CardPreview } from "@/components/CardPreview";
import { BarreAchat } from "@/components/BarreAchat";
import {
  BASIC_PRIX_REFERENCE_CENTS, BASIC_UNIT_CENTS, BASIC_VARIANTS, QUANTITIES, REMISE_LANCEMENT,
  basicCommandable, basicDesign, basicPriceCents, enRupture, euros, libelleRupture, type BasicVariant,
} from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { APP_URL } from "@/lib/config";

// Le QR code des cartes affichées est réel : il ouvre le profil d'exemple.
const demoQr = `${APP_URL}/exemple`;
const REMISE = `−${Math.round(REMISE_LANCEMENT * 100)} %`;
const ECONOMIE = euros(BASIC_PRIX_REFERENCE_CENTS - BASIC_UNIT_CENTS);

const Icon = ({ d }: { d: string }) => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden><path d={d} /></svg>
);
const TRUST = [
  ["M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM11 18h2", "iPhone et Android, sans application"],
  ["M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4", "Puce NTAG 424 DNA infalsifiable"],
  ["M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5", "Profil modifiable à vie, sans réimprimer"],
  ["M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z", "Imprimée et encodée en France"],
];

const COMPARAISON: [string, string, string][] = [
  ["Coordonnées enregistrées", "Recopiées à la main… ou jamais", "En un geste, directement dans le téléphone"],
  ["Changement de poste ou de numéro", "Toute la boîte à réimprimer", "Modifié en 10 secondes depuis votre espace"],
  ["Contact de la personne rencontrée", "Vous ne l’avez pas", "Elle vous laisse le sien, vous le recevez par email"],
  ["Durée de vie", "Une centaine de cartes, puis on recommence", "Une seule carte, pendant des années"],
];

const FAQ = [
  ["Faut-il une application ?", "Non. Les iPhone (XS et plus récents) et la quasi-totalité des Android lisent la carte nativement. Pour les autres, le QR code au verso ouvre le même profil."],
  ["Pourquoi 40 % de remise ?", `C’est notre offre de lancement : la carte Basic est à ${euros(BASIC_UNIT_CENTS)} au lieu de ${euros(BASIC_PRIX_REFERENCE_CENTS)}, remise appliquée immédiatement, sans code promo. Elle peut s’arrêter à tout moment.`],
  ["Que contient la carte ?", "Une carte PVC noir mat ou blanc au design Tapikard, une puce NFC sécurisée et un QR code de secours au verso. Votre profil en ligne (photo, coordonnées, liens, rendez-vous) est inclus à vie, sans abonnement."],
  ["Puis-je modifier mes informations après réception ?", "Oui, à tout moment et autant de fois que vous le souhaitez depuis votre espace. La carte n’a jamais besoin d’être réimprimée."],
  ["Que se passe-t-il si je perds ma carte ?", "Déclarez-la perdue depuis votre espace : elle n’affiche plus aucune donnée personnelle. Vous pouvez en commander une autre reliée au même profil."],
  ["Comment récupérer les coordonnées des personnes qui me scannent ?", "Après avoir enregistré votre contact, votre interlocuteur peut vous laisser le sien en quelques secondes. Vous le recevez par email avec sa fiche à ajouter à votre carnet, et dans votre espace (export Excel, synchronisation CRM)."],
  ["Et si la carte ne me convient pas ?", "Vous disposez de 14 jours après réception pour exercer votre droit de rétractation : écrivez-nous et nous vous remboursons."],
  ["Mes données sont-elles protégées ?", "Hébergement en Europe, vous choisissez chaque information publiée, et les statistiques ne conservent ni adresse IP ni cookie de suivi."],
];

function Prix({ clair = false }: { clair?: boolean }) {
  return (
    <div className={`offre-prix${clair ? " clair" : ""}`}>
      <span className="offre-remise">{REMISE}</span>
      <s aria-label={`Prix de référence ${euros(BASIC_PRIX_REFERENCE_CENTS)}`}>{euros(BASIC_PRIX_REFERENCE_CENTS)}</s>
      <strong>{euros(BASIC_UNIT_CENTS)}</strong>
    </div>
  );
}

export default function Home() {
  const r = ruptures();
  const dispo = basicCommandable(r);
  const coloris = (Object.keys(BASIC_VARIANTS) as BasicVariant[]).filter((v) => !enRupture(r, "basic", v));
  const cta = dispo ? `Je commande ma carte · ${euros(BASIC_UNIT_CENTS)}` : "Bientôt de retour en stock";

  return (
    <>
      {/* --- Hero : l'offre ---------------------------------------------------------- */}
      <section className="hero dark">
        <div className="container">
          <div>
            <span className="badge-lancement">Offre de lancement · {REMISE} immédiatement</span>
            <h1>Votre carte de visite, <em>d’un simple geste.</em></h1>
            <p className="lead">
              Approchez la carte d’un téléphone : vos coordonnées s’affichent et s’enregistrent. Sans application,
              modifiables à vie, et vous récupérez le contact de la personne en face.
            </p>
            <Prix clair />
            <p className="hero-economie">Vous économisez {ECONOMIE} · livraison offerte · profil inclus à vie</p>
            <div className="hero-actions">
              {dispo
                ? <Link href="/carte-basic" className="btn btn-light btn-lg">{cta}</Link>
                : <span className="btn btn-outline-light btn-lg" aria-disabled>{cta}</span>}
              <Link href="/exemple" className="btn btn-outline-light btn-lg">Voir un profil exemple</Link>
            </div>
            <ul className="garanties clair">
              <li>Paiement sécurisé</li>
              <li>Livraison offerte</li>
              <li>Rétractation 14 jours</li>
            </ul>
            {coloris.length > 0 && (
              <p className="hero-note">
                <span className="swatch-row">Disponible en {coloris.map((v) => (
                  <span key={v} className="swatch-row"><span className="swatch-dot" style={{ background: BASIC_VARIANTS[v].background }} /> {BASIC_VARIANTS[v].label.toLowerCase()}</span>
                ))}</span>
              </p>
            )}
          </div>
          <div className="hero-cards" aria-hidden>
            <CardPreview className="back" design={basicDesign("blanc")} side="recto" qrUrl={demoQr} />
            <CardPreview className="front" design={basicDesign("noir")} side="recto" qrUrl={demoQr} />
          </div>
        </div>
      </section>

      <div className="trust">
        <div className="container">
          {TRUST.map(([d, t]) => <div className="trust-item" key={t}><Icon d={d} />{t}</div>)}
        </div>
      </div>

      {/* --- Le problème ------------------------------------------------------------- */}
      <section className="section">
        <div className="container">
          <div className="section-title">
            <span className="eyebrow">Pourquoi passer au NFC</span>
            <h2 style={{ marginTop: 14 }}>La carte papier finit <em>au fond d’une poche.</em></h2>
            <p>La vôtre s’enregistre directement dans le téléphone de votre interlocuteur, et vous rapporte son contact.</p>
          </div>
          <div className="comparaison">
            <div className="comparaison-ligne comparaison-tete"><span /><span>Carte papier</span><span>Carte Tapikard</span></div>
            {COMPARAISON.map(([critere, papier, nfc]) => (
              <div className="comparaison-ligne" key={critere}>
                <span>{critere}</span>
                <span className="non">{papier}</span>
                <span className="oui">{nfc}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* --- La carte ------------------------------------------------------------------ */}
      <section className="section" style={{ paddingTop: 0 }}>
        <div className="container spotlight">
          <div className="spotlight-visual">
            <CardPreview design={basicDesign("noir")} side="recto" qrUrl={demoQr} />
            <CardPreview design={basicDesign("noir")} side="verso" qrUrl={demoQr} />
          </div>
          <div>
            <span className="eyebrow">La carte Basic</span>
            <h2 style={{ marginTop: 14 }}>Tout l’essentiel, <em>rien de superflu.</em></h2>
            <p className="muted" style={{ fontSize: "1.05rem" }}>
              Une carte sobre et élégante, prête à l’emploi. Pas de maquette à valider : vous commandez, vous recevez,
              vous scannez votre carte pour l’activer et vous complétez votre profil en deux minutes.
            </p>
            <ul className="includes">
              <li>Carte PVC noir mat ou blanc, finition mate</li>
              <li>Puce NFC sécurisée NTAG 424 DNA et QR code de secours</li>
              <li>Profil en ligne illimité : photo, coordonnées, liens, rendez-vous</li>
              <li>Échange de coordonnées avec les personnes qui vous scannent</li>
              <li>Statistiques, export des contacts, désactivation en cas de perte</li>
            </ul>
            <Prix />
            <div className="row" style={{ marginTop: 18 }}>
              {enRupture(r, "basic", "noir")
                ? <span className="btn btn-secondary btn-lg" aria-disabled style={{ opacity: .6, cursor: "default" }}>Noir mat : {libelleRupture(r, "basic", "noir").toLowerCase()}</span>
                : <Link href="/carte-basic?couleur=noir" className="btn btn-lg">Commander en noir mat</Link>}
              {enRupture(r, "basic", "blanc")
                ? <span className="btn btn-secondary btn-lg" aria-disabled style={{ opacity: .6, cursor: "default" }}>Blanc : {libelleRupture(r, "basic", "blanc").toLowerCase()}</span>
                : <Link href="/carte-basic?couleur=blanc" className="btn btn-secondary btn-lg">En blanc</Link>}
            </div>
          </div>
        </div>
      </section>

      {/* --- Fonctionnement -------------------------------------------------------- */}
      <section className="section" id="fonctionnement" style={{ paddingTop: 24 }}>
        <div className="container">
          <div className="section-title">
            <span className="eyebrow">Comment ça marche</span>
            <h2 style={{ marginTop: 14 }}>Trois gestes, <em>et c’est fait.</em></h2>
          </div>
          <div className="steps">
            {[
              ["Commandez", "Choisissez le noir mat ou le blanc. Votre carte est imprimée, encodée et vérifiée dans notre atelier, puis expédiée gratuitement."],
              ["Activez", "À réception, scannez votre carte : votre compte s’ouvre. Ajoutez votre photo, vos coordonnées et vos liens."],
              ["Partagez", "Approchez la carte d’un téléphone. Votre contact s’enregistre en un geste, et vous recevez le sien en retour."],
            ].map(([t, d], i) => (
              <div className="step" key={t}>
                <div className="step-num">0{i + 1}</div>
                <h3>{t}</h3>
                <p className="muted" style={{ margin: 0 }}>{d}</p>
              </div>
            ))}
          </div>
          <div style={{ textAlign: "center", marginTop: 36 }}>
            <Link href="/exemple" className="btn btn-secondary btn-lg">Voir ce que verra votre interlocuteur</Link>
          </div>
        </div>
      </section>

      {/* --- Échange de coordonnées -------------------------------------------------- */}
      <section className="section dark">
        <div className="container grid grid-2" style={{ alignItems: "center", gap: 56 }}>
          <div>
            <span className="eyebrow">Échange de coordonnées</span>
            <h2 style={{ marginTop: 14 }}>Ne perdez plus <em>aucun contact.</em></h2>
            <p className="lead">Votre interlocuteur enregistre votre fiche… et dans la foulée, il vous laisse la sienne. Vous la recevez aussitôt, prête à relancer.</p>
            <ul className="checks" style={{ marginTop: 24 }}>
              <li>Formulaire pré-rempli par son téléphone, en quelques secondes</li>
              <li>Notification par email avec sa fiche contact à ajouter en un clic</li>
              <li>Suivi de vos prospects : statut, notes, export Excel</li>
              <li>Synchronisation CRM — HubSpot, Pipedrive, Google Sheets — via Zapier ou Make</li>
              <li>Consentement RGPD recueilli à chaque échange</li>
            </ul>
          </div>
          <ol className="steps" style={{ gridTemplateColumns: "1fr", listStyle: "none", padding: 0, margin: 0, gap: 12 }}>
            {[
              "Vous approchez votre carte du téléphone de votre interlocuteur.",
              "Votre profil s’ouvre ; il touche « Ajouter aux contacts ».",
              "Tapikard lui propose aussitôt de vous laisser ses coordonnées.",
              "Vous recevez son contact par email et dans votre espace.",
            ].map((t, i) => (
              <li key={t} className="row" style={{ flexWrap: "nowrap", gap: 18, padding: "18px 22px", borderRadius: 16, background: "var(--night-2)", border: "1px solid var(--night-line)" }}>
                <span style={{ fontFamily: "var(--font-display)", fontSize: "1.8rem", color: "#a99bff", lineHeight: 1 }}>{i + 1}</span>
                <span>{t}</span>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* --- Sécurité ---------------------------------------------------------------- */}
      <section className="section-tight">
        <div className="container">
          <div className="panel" style={{ padding: "40px clamp(24px, 5vw, 56px)", display: "grid", gap: 32, gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))", alignItems: "center" }}>
            <div>
              <span className="eyebrow">Sécurité</span>
              <h2 style={{ marginTop: 14, marginBottom: 0 }}>Infalsifiable, <em>pour de vrai.</em></h2>
            </div>
            <p className="muted" style={{ margin: 0 }}>
              Chaque carte embarque une puce <strong style={{ color: "var(--ink)" }}>NTAG 424 DNA</strong> qui signe une URL unique à chaque scan (AES-128).
              Une copie de la carte ou un lien recopié sont détectés : seul le scan de la vraie carte affiche le badge « Carte authentique ».
              Données hébergées en Europe, aucun cookie de suivi pour vos visiteurs.
            </p>
          </div>
        </div>
      </section>

      {/* --- L'offre ------------------------------------------------------------------- */}
      <section className="section" id="offre">
        <div className="container">
          <div className="section-title">
            <span className="eyebrow">Offre de lancement</span>
            <h2 style={{ marginTop: 14 }}>{REMISE} sur votre carte, <em>tout de suite.</em></h2>
            <p>Remise appliquée automatiquement, sans code. Profil en ligne inclus à vie, sans abonnement. Livraison offerte.</p>
          </div>
          <div className="offre-packs">
            {QUANTITIES.filter((q) => q.qty <= 10).map((q) => {
              const p = basicPriceCents(q.qty);
              const vedette = q.qty === 1;
              return (
                <div className={`offre-pack${vedette ? " vedette" : ""}`} key={q.qty}>
                  {vedette ? <span className="badge badge-brand">Le plus choisi</span> : <span className="badge">Équipe</span>}
                  <h3>{q.qty} carte{q.qty > 1 ? "s" : ""}</h3>
                  <s className="muted">{euros(p.reference)}</s>
                  <div className="offre-pack-prix">{euros(p.total)}</div>
                  <small className="muted">{q.qty > 1 ? `${euros(p.unit)} la carte · ` : ""}vous économisez {euros(p.economie)}</small>
                  {dispo
                    ? <Link href={`/carte-basic?quantite=${q.qty}`} className={`btn btn-block ${vedette ? "" : "btn-secondary"}`}>Commander</Link>
                    : <span className="btn btn-secondary btn-block" aria-disabled style={{ opacity: .6 }}>Bientôt de retour</span>}
                </div>
              );
            })}
          </div>
          <ul className="garanties" style={{ justifyContent: "center", marginTop: 26 }}>
            <li>Paiement sécurisé</li>
            <li>Livraison offerte en France</li>
            <li>Rétractation 14 jours</li>
            <li>Sans abonnement</li>
          </ul>
          <p className="muted" style={{ textAlign: "center", marginTop: 14, fontSize: ".92rem" }}>Plus de 10 cartes pour votre équipe ? <a href="mailto:bonjour@tapikard.com">Écrivez-nous</a> pour un devis.</p>
        </div>
      </section>

      {/* --- FAQ ---------------------------------------------------------------------------- */}
      <section className="section" style={{ paddingTop: 0 }}>
        <div className="container">
          <div className="section-title">
            <h2>Questions fréquentes</h2>
          </div>
          <div className="faq-list">
            {FAQ.map(([q, a]) => (
              <details className="faq" key={q}>
                <summary>{q}</summary>
                <p>{a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* --- Appel final ---------------------------------------------------------------------- */}
      <section className="container">
        <div className="cta-band dark">
          <div>
            <h2 style={{ marginBottom: 12 }}>Votre prochaine rencontre <em>mérite mieux qu’un bout de papier.</em></h2>
            <Prix clair />
            <p className="lead" style={{ margin: "14px 0 26px" }}>Livraison offerte. Profil modifiable à vie. Offre de lancement limitée dans le temps.</p>
            <div className="row">
              {dispo && <Link href="/carte-basic" className="btn btn-light btn-lg">{cta}</Link>}
              <Link href="/exemple" className="btn btn-outline-light btn-lg">Voir un exemple</Link>
            </div>
          </div>
          <CardPreview design={basicDesign("noir")} side="verso" qrUrl={demoQr} style={{ transform: "rotate(-4deg)", maxWidth: 420, justifySelf: "center" }} />
        </div>
      </section>

      {dispo && <BarreAchat />}
    </>
  );
}

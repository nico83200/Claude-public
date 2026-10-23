import Link from "next/link";
import { CardPreview } from "@/components/CardPreview";
import { BASIC_UNIT_CENTS, DEFAULT_DESIGN, MATERIALS, basicDesign, euros, type CardDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";

// Le QR code des cartes affichées est réel : il ouvre le profil d'exemple.
const demoQr = `${APP_URL}/exemple`;
const prix = (c: number) => euros(c).replace(",00", "");

const SHOWCASE: { design: CardDesign; titre: string; detail: string }[] = [
  { design: { ...DEFAULT_DESIGN }, titre: "PVC blanc · Moderne", detail: "Impression quadri, vos couleurs et votre logo" },
  {
    design: { ...DEFAULT_DESIGN, template: "classique", material: "bambou", background: "#d9b483", ink: "#3b2614", name: "Jules Bernard", title: "Architecte", company: "Bernard & Associés", phone: "+33 6 45 12 78 90", email: "jules@bernard-associes.fr", website: "bernard-associes.fr" },
    titre: "Bambou · Classique", detail: "Bois naturel gravé au laser",
  },
  {
    design: { ...DEFAULT_DESIGN, template: "minimal", material: "metal_noir", background: "#1f2024", ink: "#d6b25e", name: "Léa Fontaine", title: "Fondatrice", company: "Maison Fontaine", phone: "+33 6 01 02 03 04", email: "lea@maisonfontaine.fr", website: "maisonfontaine.fr" },
    titre: "Métal noir · Minimal", detail: "Acier brossé, gravure or ou argent",
  },
];

const Icon = ({ d }: { d: string }) => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden><path d={d} /></svg>
);
const TRUST = [
  ["M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM11 18h2", "iPhone et Android, sans application"],
  ["M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4", "Puce NTAG 424 DNA infalsifiable"],
  ["M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5", "Profil modifiable à vie, sans réimprimer"],
  ["M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z", "Imprimée et encodée en France"],
];

const FAQ = [
  ["Faut-il une application ?", "Non. Les iPhone (XS et plus récents) et la quasi-totalité des Android lisent la carte nativement. Pour les autres, le QR code au verso ouvre le même profil."],
  ["Quelle différence entre la Basic et la carte personnalisée ?", "La puce, le QR code et le profil en ligne sont identiques. La Basic porte le design Tapikard (noir mat ou blanc) et part sans BAT à valider ; la personnalisée reçoit votre logo, vos couleurs et vos informations imprimées."],
  ["Puis-je modifier mes informations après réception ?", "Oui, à tout moment et autant de fois que vous le souhaitez depuis votre espace. La carte n’a jamais besoin d’être réimprimée."],
  ["Que se passe-t-il si je perds ma carte ?", "Déclarez-la perdue depuis votre espace : elle n’affiche plus aucune donnée personnelle. Vous pouvez en commander une autre reliée au même profil."],
  ["Comment récupérer les coordonnées des personnes qui me scannent ?", "Après avoir enregistré votre contact, votre interlocuteur peut vous laisser le sien en quelques secondes. Vous le recevez par email avec sa fiche à ajouter à votre carnet, et dans votre espace (export Excel, synchronisation CRM)."],
  ["Mes données sont-elles protégées ?", "Hébergement en Europe, vous choisissez chaque information publiée, et les statistiques ne conservent ni adresse IP ni cookie de suivi."],
];

export default function Home() {
  return (
    <>
      {/* --- Hero : la carte Basic ------------------------------------------------ */}
      <section className="hero dark">
        <div className="container">
          <div>
            <span className="eyebrow">Carte Basic · prête à l’emploi</span>
            <h1>Votre carte de visite, <em>d’un simple geste.</em></h1>
            <p className="lead">
              Une carte NFC sobre et élégante, noir mat ou blanc. Approchez-la d’un téléphone : vos coordonnées
              s’affichent et s’enregistrent. Sans application, modifiables à vie.
            </p>
            <div className="hero-price">
              <strong>{prix(BASIC_UNIT_CENTS)}</strong>
              <span>la carte · livraison offerte<br />dégressif dès 2 cartes</span>
            </div>
            <div className="hero-actions">
              <Link href="/carte-basic" className="btn btn-light btn-lg">Commander la carte Basic</Link>
              <Link href="/exemple" className="btn btn-outline-light btn-lg">Voir un profil exemple</Link>
            </div>
            <p className="hero-note">
              <span className="swatch-row">Disponible en <span className="swatch-dot" style={{ background: "#141418" }} /> noir mat <span className="swatch-dot" style={{ background: "#fff" }} /> blanc</span>
              <br />Votre logo et vos couleurs ? <Link href="/creer">Carte personnalisée</Link>, dès {prix(MATERIALS.pvc_blanc.unitCents)}.
            </p>
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

      {/* --- La Basic en détail --------------------------------------------------- */}
      <section className="section">
        <div className="container spotlight">
          <div className="spotlight-visual">
            <CardPreview design={basicDesign("noir")} side="recto" qrUrl={demoQr} />
            <CardPreview design={basicDesign("noir")} side="verso" qrUrl={demoQr} />
          </div>
          <div>
            <span className="eyebrow">La carte Basic</span>
            <h2 style={{ marginTop: 14 }}>Tout l’essentiel, <em>rien de superflu.</em></h2>
            <p className="muted" style={{ fontSize: "1.05rem" }}>
              La même technologie que nos cartes sur mesure, avec le design Tapikard. Pas de maquette, pas de BAT :
              vous commandez, vous recevez, vous scannez votre carte pour l’activer et vous complétez votre profil.
            </p>
            <div className="price-tag"><strong>{prix(BASIC_UNIT_CENTS)}</strong><span className="muted">TTC la carte · livraison offerte</span></div>
            <ul className="includes">
              <li>Carte PVC noir mat ou blanc, finition mate</li>
              <li>Puce NFC sécurisée NTAG 424 DNA et QR code de secours</li>
              <li>Profil en ligne illimité : photo, coordonnées, liens, rendez-vous</li>
              <li>Échange de coordonnées avec les personnes qui vous scannent</li>
              <li>Statistiques, export des contacts, désactivation en cas de perte</li>
            </ul>
            <div className="row">
              <Link href="/carte-basic?couleur=noir" className="btn btn-lg">Commander en noir mat</Link>
              <Link href="/carte-basic?couleur=blanc" className="btn btn-secondary btn-lg">En blanc</Link>
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
              ["Commandez", "Choisissez le noir mat ou le blanc. Votre carte est imprimée, encodée et vérifiée dans notre atelier, puis expédiée."],
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

      {/* --- Cartes personnalisées --------------------------------------------------- */}
      <section className="section">
        <div className="container">
          <div className="section-title">
            <span className="eyebrow">Sur mesure</span>
            <h2 style={{ marginTop: 14 }}>Ou une carte <em>à votre image.</em></h2>
            <p>Votre logo, vos couleurs, vos informations imprimées. PVC, bambou ou métal, avec un BAT à valider avant impression.</p>
          </div>
          <div className="showcase">
            {SHOWCASE.map((s) => (
              <figure key={s.titre}>
                <CardPreview design={s.design} side="recto" qrUrl={demoQr} />
                <figcaption><strong>{s.titre}</strong>{s.detail}</figcaption>
              </figure>
            ))}
          </div>
          <div style={{ textAlign: "center", marginTop: 40 }}>
            <Link href="/creer" className="btn btn-secondary btn-lg">Personnaliser ma carte</Link>
          </div>
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

      {/* --- Tarifs -------------------------------------------------------------------- */}
      <section className="section" id="tarifs">
        <div className="container">
          <div className="section-title">
            <span className="eyebrow">Tarifs</span>
            <h2 style={{ marginTop: 14 }}>Payez la carte, <em>le profil est offert.</em></h2>
            <p>Profil en ligne inclus à vie. Livraison offerte. −10 % dès 2 cartes, −20 % dès 5, −30 % dès 10, −40 % dès 25.</p>
          </div>
          <div className="plans">
            <div className="plan plan-featured">
              <span className="badge badge-brand" style={{ alignSelf: "flex-start" }}>Prête à l’emploi</span>
              <h3 style={{ marginTop: 14 }}>Carte Basic</h3>
              <div className="price">{prix(BASIC_UNIT_CENTS)}</div>
              <p>Design Tapikard noir mat ou blanc, NFC + QR code. Sans BAT.</p>
              <Link href="/carte-basic" className="btn btn-light btn-block">Commander</Link>
            </div>
            {(Object.keys(MATERIALS) as (keyof typeof MATERIALS)[]).map((m) => (
              <div className="plan" key={m}>
                <span className="badge" style={{ alignSelf: "flex-start" }}>Personnalisée</span>
                <h3 style={{ marginTop: 14 }}>{MATERIALS[m].label}</h3>
                <div className="price">{prix(MATERIALS[m].unitCents)}</div>
                <p>{MATERIALS[m].description}</p>
                <Link href={`/creer?matiere=${m}`} className="btn btn-secondary btn-block">Personnaliser</Link>
              </div>
            ))}
          </div>
          <p className="muted" style={{ textAlign: "center", marginTop: 22, fontSize: ".92rem" }}>Équipes et entreprises : charte imposée, gestion centralisée, facturation — <a href="mailto:bonjour@tapikard.com">contactez-nous</a>.</p>
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
            <h2 style={{ marginBottom: 12 }}>Prête en quelques jours, <em>utile pendant des années.</em></h2>
            <p className="lead" style={{ marginBottom: 26 }}>La carte Basic à {prix(BASIC_UNIT_CENTS)}, livraison offerte. Votre profil reste modifiable à vie.</p>
            <div className="row">
              <Link href="/carte-basic" className="btn btn-light btn-lg">Commander la carte Basic</Link>
              <Link href="/creer" className="btn btn-outline-light btn-lg">Personnaliser</Link>
            </div>
          </div>
          <CardPreview design={basicDesign("noir")} side="verso" qrUrl={demoQr} style={{ transform: "rotate(-4deg)", maxWidth: 420, justifySelf: "center" }} />
        </div>
      </section>
    </>
  );
}

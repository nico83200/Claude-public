import Link from "next/link";
import { CardPreview } from "@/components/CardPreview";
import { BASIC_UNIT_CENTS, DEFAULT_DESIGN, MATERIALS, basicDesign, euros } from "@/lib/catalog";

const demoQr = "https://tapikard.com/exemple";

export default function Home() {
  return (
    <>
      <section className="hero">
        <div className="container">
          <div>
            <span className="badge badge-brand">Puce NFC sécurisée NTAG 424 DNA</span>
            <h1 style={{ marginTop: 16 }}>Votre carte de visite,<br />partagée d’un simple geste.</h1>
            <p className="lead">
              Une carte imprimée à votre image, avec une puce NFC. Approchez-la d’un téléphone : vos coordonnées s’affichent
              et s’enregistrent en un clic. Sans application. Modifiable à vie, sans réimpression.
            </p>
            <div className="row" style={{ marginTop: 24 }}>
              <Link href="/creer" className="btn btn-lg">Créer ma carte</Link>
              <Link href="/exemple" className="btn btn-lg btn-secondary">Voir un profil exemple</Link>
            </div>
            <p className="muted" style={{ marginTop: 16, fontSize: ".9rem" }}>
              Carte Basic dès {euros(BASIC_UNIT_CENTS)} · Personnalisée dès {euros(MATERIALS.pvc_blanc.unitCents)} · Profil en ligne gratuit à vie
            </p>
          </div>
          <div className="hero-cards" aria-hidden>
            <CardPreview className="back" design={{ ...DEFAULT_DESIGN }} side="verso" qrUrl={demoQr} />
            <CardPreview className="front" design={{ ...DEFAULT_DESIGN }} side="recto" qrUrl={demoQr} />
          </div>
        </div>
      </section>

      <section className="section" id="fonctionnement">
        <div className="container">
          <div className="section-title">
            <h2>Comment ça marche</h2>
            <p className="muted">Trois étapes, et vous ne distribuez plus jamais une carte obsolète.</p>
          </div>
          <div className="grid grid-3">
            {[
              ["Personnalisez", "Choisissez la matière, le modèle, vos couleurs et votre logo. Saisissez vos coordonnées : votre profil en ligne est créé en même temps."],
              ["Recevez", "Nous imprimons, encodons et vérifions chaque puce dans notre atelier. Validez le BAT, vos cartes partent sous quelques jours."],
              ["Partagez", "Approchez la carte d’un téléphone ou faites scanner le QR code. Votre contact s’enregistre en un clic, et vous suivez vos statistiques."],
            ].map(([t, d], i) => (
              <div className="panel" key={t}>
                <div className="step-num">{i + 1}</div>
                <h3>{t}</h3>
                <p className="muted" style={{ margin: 0 }}>{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container grid grid-2" style={{ alignItems: "center" }}>
          <div>
            <h2>Une carte, mille mises à jour</h2>
            <p className="muted">La puce contient un lien vers votre profil, pas vos coordonnées figées. Changez de poste, de numéro ou de photo : la carte que vous avez donnée l’an dernier affiche toujours vos informations à jour.</p>
            <ul className="checks">
              <li>Bouton « Ajouter aux contacts » (vCard avec photo)</li>
              <li>Appel, email, WhatsApp, itinéraire et liens en un geste</li>
              <li>Formulaire d’échange : vos interlocuteurs vous laissent leurs coordonnées</li>
              <li>Statistiques de scans, contacts reçus et export CSV</li>
              <li>Carte perdue ? Désactivez-la en un clic</li>
            </ul>
          </div>
          <div className="panel">
            <h3>Infalsifiable, pour de vrai</h3>
            <p className="muted">Chaque carte embarque une puce <strong>NTAG 424 DNA</strong>. À chaque scan, elle signe cryptographiquement une URL unique (AES-128). Une carte copiée ou un lien recopié sont détectés : seul le scan de la vraie carte affiche le badge « Carte authentique ».</p>
            <p className="muted" style={{ margin: 0 }}>Données hébergées en Europe, aucun cookie de suivi pour vos visiteurs.</p>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="section-title">
            <h2>Deux façons de commencer</h2>
            <p className="muted">Même puce NFC sécurisée, même QR code, même profil en ligne illimité.</p>
          </div>
          <div className="grid grid-2">
            <div className="panel">
              <CardPreview design={basicDesign("noir")} side="recto" qrUrl={demoQr} />
              <h3 style={{ marginTop: 18 }}>Carte Basic — {euros(BASIC_UNIT_CENTS)}</h3>
              <p className="muted">Prête à l’emploi, design Tapikard noir ou blanc. Pas de BAT : expédiée plus vite. Vous complétez votre profil à réception.</p>
              <Link href="/carte-basic" className="btn btn-block">Commander la Basic</Link>
            </div>
            <div className="panel">
              <CardPreview design={{ ...DEFAULT_DESIGN }} side="recto" qrUrl={demoQr} />
              <h3 style={{ marginTop: 18 }}>Carte personnalisée — dès {euros(MATERIALS.pvc_blanc.unitCents)}</h3>
              <p className="muted">Votre logo, vos couleurs, vos informations imprimées. PVC, bambou ou métal, avec BAT à valider avant impression.</p>
              <Link href="/creer" className="btn btn-block btn-secondary">Personnaliser ma carte</Link>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container grid grid-2" style={{ alignItems: "center" }}>
          <div>
            <span className="badge badge-brand">Nouveau</span>
            <h2 style={{ marginTop: 12 }}>Récupérez les coordonnées de ceux qui vous scannent</h2>
            <p className="muted">Votre interlocuteur enregistre votre contact… et dans la foulée, il peut vous laisser le sien. Fini les cartes de visite perdues au fond d’une poche.</p>
            <ul className="checks">
              <li>Formulaire pré-rempli par le téléphone du visiteur : 10 secondes</li>
              <li>Notification par email avec sa fiche contact à ajouter en un clic</li>
              <li>Il reçoit votre fiche contact par email en retour</li>
              <li>Suivi de vos prospects : statut, notes, export Excel</li>
              <li>Synchronisation CRM : HubSpot, Pipedrive, Google Sheets… via Zapier ou Make</li>
              <li>Consentement RGPD recueilli à chaque échange</li>
            </ul>
          </div>
          <div className="panel">
            <h3>Comment ça se passe</h3>
            <ol style={{ paddingLeft: 20, margin: 0 }} className="muted">
              <li style={{ marginBottom: 8 }}>Vous approchez votre carte du téléphone de votre interlocuteur.</li>
              <li style={{ marginBottom: 8 }}>Votre profil s’ouvre ; il touche « Ajouter aux contacts ».</li>
              <li style={{ marginBottom: 8 }}>Tapikard lui propose aussitôt d’échanger ses coordonnées.</li>
              <li>Vous recevez son contact par email et dans votre espace, prêt à relancer.</li>
            </ol>
          </div>
        </div>
      </section>

      <section className="section" id="tarifs">
        <div className="container">
          <div className="section-title">
            <h2>Tarifs</h2>
            <p className="muted">Payez la carte une fois. Le profil en ligne est inclus à vie.</p>
          </div>
          <div className="grid grid-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))" }}>
            <div className="panel">
              <span className="badge">Prête à l’emploi</span>
              <h3 style={{ marginTop: 8 }}>Basic</h3>
              <div className="price">{euros(BASIC_UNIT_CENTS)}</div>
              <p className="muted" style={{ fontSize: ".9rem" }}>Design Tapikard noir ou blanc, NFC + QR code</p>
              <Link href="/carte-basic" className="btn btn-block btn-secondary">Choisir</Link>
            </div>
            {(Object.keys(MATERIALS) as (keyof typeof MATERIALS)[]).map((m, i) => (
              <div className={`panel ${i === 0 ? "plan-featured" : ""}`} key={m}>
                {i === 0 && <span className="badge badge-brand">Le plus choisi</span>}
                <h3 style={{ marginTop: 8 }}>{MATERIALS[m].label}</h3>
                <div className="price">{euros(MATERIALS[m].unitCents)}</div>
                <p className="muted" style={{ fontSize: ".9rem" }}>{MATERIALS[m].description}</p>
                <Link href={`/creer?matiere=${m}`} className={`btn btn-block ${i === 0 ? "" : "btn-secondary"}`}>Choisir</Link>
              </div>
            ))}
          </div>
          <p className="muted" style={{ textAlign: "center", marginTop: 18 }}>
            Dégressif : −10 % dès 2 cartes, −20 % dès 5, −30 % dès 10, −40 % dès 25. Offre équipes et entreprises sur demande.
          </p>
        </div>
      </section>

      <section className="section">
        <div className="container" style={{ maxWidth: 760 }}>
          <h2 style={{ textAlign: "center" }}>Questions fréquentes</h2>
          {[
            ["Faut-il une application ?", "Non. Les iPhone (XS et plus récents) et la quasi-totalité des Android lisent la carte nativement. Pour les autres, le QR code au verso ouvre le même profil."],
            ["Puis-je modifier mes informations après l’impression ?", "Oui, à tout moment et autant de fois que vous le souhaitez depuis votre espace. La carte n’a pas besoin d’être réimprimée."],
            ["Que se passe-t-il si je perds ma carte ?", "Déclarez-la perdue depuis votre espace : elle n’affiche plus aucune donnée personnelle. Vous pouvez en commander une autre reliée au même profil."],
            ["La carte métal fonctionne-t-elle vraiment ?", "Oui. Nos cartes métal intègrent un inlay ferrite qui isole l’antenne du métal."],
            ["Mes données sont-elles protégées ?", "Hébergement en Europe, vous choisissez chaque information publiée, et les statistiques ne conservent ni adresse IP ni cookie de suivi."],
          ].map(([q, a]) => (
            <details className="faq" key={q}>
              <summary>{q}</summary>
              <p>{a}</p>
            </details>
          ))}
          <div style={{ textAlign: "center", marginTop: 32 }}>
            <Link href="/creer" className="btn btn-lg">Créer ma carte maintenant</Link>
          </div>
        </div>
      </section>
    </>
  );
}

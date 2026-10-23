import { formeImage } from "@/lib/image";
import { reseauDe } from "@/lib/reseaux";
import { parseLinks, type Profile } from "@/lib/types";
import { IconChat, IconGlobe, IconLink, IconMail, IconPhone, IconPin, IconShield, IconUserPlus } from "./Icons";
import { LeadForm } from "./LeadForm";
import { LogoMark } from "./Logo";
import { SaveContact } from "./SaveContact";
import { TrackedLink } from "./TrackedLink";

type Verification = "verified" | "replay" | null;

const telHref = (n: string) => `tel:${n.replace(/[^\d+]/g, "")}`;
const waHref = (n: string) => `https://wa.me/${n.replace(/[^\d]/g, "").replace(/^0/, "33")}`;
const webHref = (w: string) => (/^https?:\/\//.test(w) ? w : `https://${w}`);

/** Profil public affiché au scan. Sans hook : utilisable côté serveur et dans l'aperçu de l'éditeur. */
export function ProfileView({ profile, cardId, verification = null, preview = false, vcardHref }: {
  profile: Profile; cardId?: string; verification?: Verification; preview?: boolean;
  /** Lien vCard pour un profil sans carte (page d'exemple). */
  vcardHref?: string;
}) {
  const dark = !!profile.dark;
  const vars = {
    "--p-accent": profile.accent,
    "--p-bg": dark ? "#0f0f14" : "#f6f6f9",
    "--p-surface": dark ? "#1b1b23" : "#ffffff",
    "--p-ink": dark ? "#f4f4f6" : "#14121f",
    "--p-muted": dark ? "#a1a1b0" : "#666277",
    "--p-line": dark ? "#2a2a35" : "#e7e5ef",
  } as React.CSSProperties;
  const tous = parseLinks(profile);
  // Réseaux sociaux reconnus (LinkedIn, Instagram…) : affichés en logos ; les autres liens restent en liste.
  const reseaux = tous.map((l) => ({ ...l, r: reseauDe(l.url) })).filter((l) => l.r);
  const links = tous.filter((l) => !reseauDe(l.url));
  const mainPhone = profile.mobile || profile.phone;
  const firstName = profile.display_name.split(" ")[0] || profile.display_name;
  const initials = profile.display_name.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
  const actions = [
    mainPhone && { label: "Appeler", href: telHref(mainPhone), icon: <IconPhone /> },
    profile.email && { label: "Email", href: `mailto:${profile.email}`, icon: <IconMail /> },
    profile.mobile && { label: "WhatsApp", href: waHref(profile.mobile), icon: <IconChat /> },
    profile.address && { label: "Itinéraire", href: `https://maps.google.com/?q=${encodeURIComponent(profile.address)}`, icon: <IconPin /> },
    !profile.address && profile.website && { label: "Site web", href: webHref(profile.website), icon: <IconGlobe /> },
  ].filter(Boolean).slice(0, 4) as { label: string; href: string; icon: React.ReactNode }[];

  return (
    <div className="profile-page" style={vars}>
      {profile.logo ? (
        <div className={`p-cover p-cover-logo ${formeImage(profile.logo)}`}>
          <img src={profile.logo} alt={profile.company ? `Logo ${profile.company}` : "Logo"} />
        </div>
      ) : <div className="p-cover" />}
      <div className="p-wrap">
        {profile.avatar ? <img className="p-avatar" src={profile.avatar} alt="" /> : <div className="p-avatar">{initials}</div>}
        <h1 className="p-name">{profile.display_name || "Votre nom"}</h1>
        {(profile.title || profile.company) && <p className="p-title">{[profile.title, profile.company].filter(Boolean).join(" · ")}</p>}
        {verification === "verified" && <span className="p-badge ok"><IconShield /> Carte authentique</span>}
        {verification === "replay" && <span className="p-badge no">Lien déjà utilisé — rescannez la carte pour l’authentifier</span>}
        {profile.bio && <p className="p-bio">{profile.bio}</p>}

        {actions.length > 0 && (
          <div className="p-actions" style={{ gridTemplateColumns: `repeat(${actions.length}, 1fr)` }}>
            {actions.map((a) => <TrackedLink key={a.label} cardId={cardId} target={a.label} href={a.href} className="p-action">{a.icon}{a.label}</TrackedLink>)}
          </div>
        )}

        {vcardHref ? (
          <a className="p-save" href={vcardHref}><IconUserPlus /> Ajouter aux contacts</a>
        ) : preview || !cardId ? (
          <span className="p-save"><IconUserPlus /> Ajouter aux contacts</span>
        ) : (
          <SaveContact cardId={cardId} ownerFirstName={firstName} leadCapture={!!profile.lead_capture} contact={{
            nom: profile.display_name, mobile: profile.mobile, telephone: profile.phone, email: profile.email, entreprise: profile.company,
            fonction: profile.title, adresse: profile.address, site: profile.website,
          }} />
        )}

        {reseaux.length > 0 && (
          <div className="p-reseaux">
            {reseaux.map((l, i) => (
              <TrackedLink key={i} cardId={cardId} target={l.r!.nom} href={l.url} className={`p-reseau p-reseau-${l.r!.id}`} style={{ background: l.r!.couleur }} aria-label={l.r!.nom} title={l.r!.nom}>
                <svg viewBox="0 0 24 24" aria-hidden><path d={l.r!.d} /></svg>
              </TrackedLink>
            ))}
          </div>
        )}

        <div className="p-list">
          {profile.mobile && <a className="p-item" href={telHref(profile.mobile)}><IconPhone /><span><span className="k">Mobile</span><span className="v">{profile.mobile}</span></span></a>}
          {profile.phone && <a className="p-item" href={telHref(profile.phone)}><IconPhone /><span><span className="k">Téléphone</span><span className="v">{profile.phone}</span></span></a>}
          {profile.email && <a className="p-item" href={`mailto:${profile.email}`}><IconMail /><span><span className="k">Email</span><span className="v">{profile.email}</span></span></a>}
          {profile.website && <TrackedLink cardId={cardId} target="Site web" className="p-item" href={webHref(profile.website)}><IconGlobe /><span><span className="k">Site web</span><span className="v">{profile.website}</span></span></TrackedLink>}
          {profile.address && <a className="p-item" href={`https://maps.google.com/?q=${encodeURIComponent(profile.address)}`} target="_blank" rel="noopener noreferrer"><IconPin /><span><span className="k">Adresse</span><span className="v">{profile.address}</span></span></a>}
        </div>

        {links.length > 0 && (
          <>
            <div className="p-section-title">Liens</div>
            <div className="p-list" style={{ marginTop: 0 }}>
              {links.map((l, i) => (
                <TrackedLink key={i} cardId={cardId} target={l.label} href={l.url} className="p-item"><IconLink /><span className="v">{l.label}</span></TrackedLink>
              ))}
            </div>
          </>
        )}

        {!!profile.lead_capture && (
          <>
            <div className="p-section-title">Restons en contact</div>
            <LeadForm cardId={cardId} ownerFirstName={firstName} preview={preview} />
          </>
        )}

        <div className="p-footer">
          <LogoMark size={15} />Carte de visite NFC <a href="/">Tapikard</a> · <a href="/confidentialite">Confidentialité</a>
        </div>
      </div>
    </div>
  );
}

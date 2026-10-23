"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export type CarteMobile = { id: string; titre: string; detail: string };
export type LotMobile = { id: string; note: string | null; cartes: CarteMobile[] };

type Pont = {
  disponible(): string;
  transceive(hex: string): string;
  vibrer(ms: number): void;
  version(): string;
  /** UID de la carte posée en ce moment (vide s'il n'y en a pas). */
  carteActuelle?(): string;
  reglagesNfc?(): void;
  reglerSite?(): void;
};
declare global {
  interface Window {
    TapikardNfc?: Pont;
    tapikardCarte?: (uid: string, compatible: boolean) => void;
  }
}

type Reponse = { session?: string; apdu?: string; termine?: boolean; resultat?: Record<string, unknown>; erreur?: string; journal?: string[] };

async function appel(corps: object): Promise<Reponse> {
  const r = await fetch("/api/encodage-mobile", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(corps) });
  return r.json().catch(() => ({ erreur: `Réponse inattendue du serveur (${r.status})` }));
}

/** Relaie les APDU entre le serveur et la carte posée sur le téléphone. */
async function relayer(pont: Pont, depart: object, etape: (n: number) => void): Promise<Reponse> {
  try {
    let r = await appel(depart);
    let n = 0;
    while (r.apdu && r.session) {
      const rep = pont.transceive(r.apdu);
      etape(++n);
      r = await appel({ action: "reponse", session: r.session, reponse: rep.startsWith("ERR:") ? rep.slice(4) : rep });
    }
    return r;
  } catch {
    return { erreur: "Connexion au site perdue : vérifiez le réseau du téléphone et réessayez." };
  }
}

type Etat =
  | { t: "attente" }
  | { t: "encours"; echanges: number }
  | { t: "ok"; message: string; detail?: string }
  | { t: "erreur"; message: string };

export function EncodageMobile({ lots, clesOk }: { lots: LotMobile[]; clesOk: boolean }) {
  const [pont, setPont] = useState<Pont | null | undefined>(undefined);
  const [mode, setMode] = useState<"lots" | "encodage" | "verifier">("lots");
  const [file, setFile] = useState<CarteMobile[]>([]);
  const [index, setIndex] = useState(0);
  const [faites, setFaites] = useState<string[]>([]);
  const [etat, setEtat] = useState<Etat>({ t: "attente" });
  const occupe = useRef(false);
  const dernierUid = useRef<string | null>(null);

  useEffect(() => {
    setPont(window.TapikardNfc ?? null);
  }, []);

  const courante = file[index];

  const surCarte = useCallback(async (uid: string, compatible: boolean) => {
    if (!pont || occupe.current) return;
    if (!compatible) {
      setEtat({ t: "erreur", message: `Carte non compatible (UID ${uid}) : ce n’est pas une NTAG 424 DNA (MIFARE Classic ?).` });
      pont.vibrer(400);
      return;
    }
    if (mode === "verifier") {
      occupe.current = true;
      setEtat({ t: "encours", echanges: 0 });
      const r = await relayer(pont, { action: "verifier", uid }, (n) => setEtat({ t: "encours", echanges: n }));
      occupe.current = false;
      if (r.termine && r.resultat) {
        const c = r.resultat.carte as { id: string; statut: string } | null;
        setEtat({ t: "ok", message: "Signature authentique", detail: `UID ${r.resultat.uid} · scan n°${r.resultat.compteur}${c ? ` · carte ${c.id} (${c.statut})` : " · puce inconnue du site"}` });
        pont.vibrer(80);
      } else {
        setEtat({ t: "erreur", message: r.erreur ?? "Lecture impossible" });
        pont.vibrer(400);
      }
      return;
    }
    if (mode !== "encodage" || !courante) return;
    if (uid === dernierUid.current) return; // carte précédente encore posée
    occupe.current = true;
    setEtat({ t: "encours", echanges: 0 });
    const r = await relayer(pont, { action: "demarrer", cardId: courante.id, uid }, (n) => setEtat({ t: "encours", echanges: n }));
    occupe.current = false;
    if (r.termine) {
      dernierUid.current = uid;
      pont.vibrer(80);
      setFaites((f) => [...f, courante.id]);
      setEtat({ t: "ok", message: `${courante.titre} : carte prête`, detail: `UID ${uid}${(r.resultat as { reparation?: boolean })?.reparation ? " · reprise d’une carte déjà configurée" : ""}` });
      setTimeout(() => {
        setIndex((i) => i + 1);
        setEtat({ t: "attente" });
      }, 1400);
    } else {
      pont.vibrer(400);
      setEtat({ t: "erreur", message: r.erreur ?? "Encodage impossible" });
    }
  }, [pont, mode, courante]);

  useEffect(() => {
    window.tapikardCarte = (uid, compatible) => { void surCarte(uid, compatible); };
    return () => { window.tapikardCarte = undefined; };
  }, [surCarte]);

  /** Repart de zéro ; si une carte est déjà posée sur le téléphone, on la traite sans attendre qu'elle soit retirée. */
  const relancer = (avecCarteEnPlace = true) => {
    setEtat({ t: "attente" });
    const uid = avecCarteEnPlace && pont ? pont.carteActuelle?.() : "";
    if (uid) setTimeout(() => window.tapikardCarte?.(uid, true), 200);
  };

  if (pont === undefined) return null;

  if (!pont) {
    return (
      <div className="container" style={{ paddingTop: 30, maxWidth: 560 }}>
        <h1 style={{ fontSize: "2rem" }}>Encodage <em>mobile</em></h1>
        <p className="lead">Cette page s’utilise dans l’application <strong>Tapikard Encodeur</strong>, sur un téléphone Android équipé du NFC.</p>
        <a className="btn btn-lg btn-block" href="/telechargements/tapikard-encodeur.apk">Télécharger l’application (Android)</a>
        <p className="muted" style={{ fontSize: ".9rem", marginTop: 14 }}>
          Ouvrez le fichier téléchargé sur votre téléphone et autorisez l’installation (« Sources inconnues »), puis connectez-vous dans l’application avec le code reçu par email.
        </p>
      </div>
    );
  }

  const nfc = pont.disponible();
  const bandeau = (
    <>
      {!clesOk && <div className="alert alert-danger" style={{ margin: "0 0 14px" }}>Le serveur n’a pas les clés d’encodage (SUN_K_META, SUN_MASTER_K1, SUN_MASTER_K0).</div>}
      {nfc !== "ok" && (
        <div className="alert alert-warn" style={{ margin: "0 0 14px" }}>
          {nfc === "desactive" ? "Le NFC est désactivé sur ce téléphone." : "Ce téléphone n’a pas de NFC."}
          {nfc === "desactive" && pont.reglagesNfc && <> <button className="btn btn-sm" style={{ marginLeft: 8 }} onClick={() => pont.reglagesNfc?.()}>Activer le NFC</button></>}
        </div>
      )}
    </>
  );

  const statut = (
    <div className="panel" style={{ textAlign: "center", padding: "28px 18px", marginBottom: 16, borderColor: etat.t === "ok" ? "var(--ok)" : etat.t === "erreur" ? "var(--danger)" : undefined }}>
      {etat.t === "attente" && <><div className="nfc-pulse" aria-hidden /><p style={{ fontWeight: 600, margin: "14px 0 0" }}>{mode === "verifier" ? "Posez une carte au dos du téléphone" : "Posez la carte au dos du téléphone"}</p><small className="muted">Gardez-la immobile jusqu’au ✓</small></>}
      {etat.t === "encours" && <><div className="nfc-pulse actif" aria-hidden /><p style={{ fontWeight: 600, margin: "14px 0 0" }}>{mode === "verifier" ? "Lecture…" : "Encodage…"} ne bougez pas la carte</p><small className="muted">{etat.echanges} échange{etat.echanges > 1 ? "s" : ""} avec la puce</small></>}
      {etat.t === "ok" && <><div style={{ fontSize: "3rem", color: "var(--ok)", lineHeight: 1 }}>✓</div><p style={{ fontWeight: 700, margin: "10px 0 2px" }}>{etat.message}</p>{etat.detail && <small className="muted">{etat.detail}</small>}</>}
      {etat.t === "erreur" && <><div style={{ fontSize: "2.4rem", color: "var(--danger)", lineHeight: 1 }}>✕</div><p style={{ fontWeight: 600, margin: "10px 0 6px" }}>{etat.message}</p><button className="btn btn-sm btn-secondary" onClick={() => relancer()}>Réessayer</button></>}
    </div>
  );

  if (mode === "verifier") {
    return (
      <div className="container" style={{ paddingTop: 8, maxWidth: 560 }}>
        {bandeau}
        <h1 style={{ fontSize: "1.9rem" }}>Vérifier une carte</h1>
        {statut}
        <button className="btn btn-secondary btn-block" onClick={() => { setMode("lots"); setEtat({ t: "attente" }); }}>Retour</button>
      </div>
    );
  }

  if (mode === "encodage") {
    const fini = index >= file.length;
    return (
      <div className="container" style={{ paddingTop: 8, maxWidth: 560 }}>
        {bandeau}
        <div className="row between" style={{ marginBottom: 10 }}>
          <span className="eyebrow">Carte {Math.min(index + 1, file.length)} / {file.length}</span>
          <span className="badge badge-ok">{faites.length} encodée{faites.length > 1 ? "s" : ""}</span>
        </div>
        {fini ? (
          <div className="panel" style={{ textAlign: "center", padding: 28 }}>
            <div style={{ fontSize: "3rem", color: "var(--ok)" }}>✓</div>
            <h2 style={{ fontSize: "1.8rem" }}>Lot terminé</h2>
            <p className="muted">{faites.length} carte{faites.length > 1 ? "s" : ""} encodée{faites.length > 1 ? "s" : ""} et enregistrée{faites.length > 1 ? "s" : ""}.</p>
          </div>
        ) : (
          <>
            <div style={{ marginBottom: 14 }}>
              <small className="muted">Carte attendue (QR code imprimé)</small>
              <div style={{ fontFamily: "ui-monospace, Menlo, monospace", fontSize: "1.9rem", fontWeight: 700, letterSpacing: ".04em" }}>{courante.id}</div>
              <div style={{ fontWeight: 600 }}>{courante.titre}</div>
              <small className="muted">{courante.detail}</small>
            </div>
            {statut}
            <div className="row" style={{ flexWrap: "nowrap" }}>
              <button className="btn btn-secondary" style={{ flex: 1 }} disabled={etat.t === "encours"} onClick={() => { setIndex((i) => i + 1); setEtat({ t: "attente" }); }}>Passer cette carte</button>
            </div>
          </>
        )}
        <button className="btn btn-ghost btn-block" style={{ marginTop: 10 }} disabled={etat.t === "encours"} onClick={() => { setMode("lots"); setEtat({ t: "attente" }); }}>← Revenir aux lots</button>
      </div>
    );
  }

  return (
    <div className="container" style={{ paddingTop: 8, maxWidth: 560 }}>
      {bandeau}
      <h1 style={{ fontSize: "2rem", marginBottom: 6 }}>Encoder des <em>cartes</em></h1>
      <p className="muted" style={{ marginTop: 0 }}>Choisissez un lot : l’application vous indique la carte à poser, dans l’ordre.</p>
      {lots.length === 0 && <div className="panel"><p className="muted" style={{ margin: 0 }}>Aucune carte à encoder. Créez un lot dans Back-office → Production.</p></div>}
      <div className="stack" style={{ gap: 10 }}>
        {lots.map((l) => (
          <div className="panel" key={l.id} style={{ padding: 16 }}>
            <div className="row between">
              <div>
                <strong style={{ fontFamily: "ui-monospace, Menlo, monospace" }}>{l.id}</strong>
                {l.note && <><br /><small className="muted">{l.note}</small></>}
              </div>
              <span className="badge badge-brand">{l.cartes.length} à encoder</span>
            </div>
            <button className="btn btn-block" style={{ marginTop: 12 }} disabled={!clesOk} onClick={() => { setFile(l.cartes); setIndex(0); setFaites([]); dernierUid.current = null; setMode("encodage"); relancer(); }}>
              Encoder ce lot
            </button>
          </div>
        ))}
      </div>
      <button className="btn btn-secondary btn-block" style={{ marginTop: 16 }} disabled={!clesOk} onClick={() => { setMode("verifier"); relancer(); }}>Vérifier une carte</button>
      {pont.reglerSite && <button className="btn btn-ghost btn-block" style={{ marginTop: 6 }} onClick={() => pont.reglerSite?.()}>Changer l’adresse du site</button>}
      <p className="muted" style={{ fontSize: ".8rem", textAlign: "center", marginTop: 18 }}>Application {pont.version()}</p>
    </div>
  );
}

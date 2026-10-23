"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ScanQr } from "@/components/ScanQr";

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

/** Identifiant de carte lu dans un QR code imprimé (https://…/q/<carte>), ou l'identifiant seul. */
function idDepuisQr(texte: string): string | null {
  const m = texte.trim().match(/\/q\/([A-Za-z0-9_-]{4,40})(?:[/?#]|$)/) ?? texte.trim().match(/^([A-Za-z0-9_-]{6,40})$/);
  return m ? m[1] : null;
}

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
  // Carte DESFire : programmation en NFC simple à confirmer, puis carte programmée mais non sécurisée.
  | { t: "confirmer"; puce: string; uid: string }
  | { t: "alerte"; message: string; detail: string; grave?: string; suivante?: boolean }
  | { t: "erreur"; message: string };

const NON_SECURISEE = "La carte porte une adresse fixe, comme le QR code : elle peut être copiée et n’affiche pas le badge « Carte authentique ».";

export function EncodageMobile({ lots, clesOk }: { lots: LotMobile[]; clesOk: boolean }) {
  const [pont, setPont] = useState<Pont | null | undefined>(undefined);
  const [mode, setMode] = useState<"lots" | "encodage" | "verifier">("lots");
  const [file, setFile] = useState<CarteMobile[]>([]);
  const [index, setIndex] = useState(0);
  const [faites, setFaites] = useState<string[]>([]);
  const [simples, setSimples] = useState(0);
  const desfireAcceptees = useRef(false);
  const [scan, setScan] = useState(false);
  const [parScan, setParScan] = useState(false);
  const [messageScan, setMessageScan] = useState<string | null>(null);
  const [etat, setEtat] = useState<Etat>({ t: "attente" });
  const occupe = useRef(false);
  const dernierUid = useRef<string | null>(null);

  useEffect(() => {
    setPont(window.TapikardNfc ?? null);
  }, []);

  const courante = file[index];

  const surCarte = useCallback(async (uid: string, compatible: boolean, forcer = false) => {
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
        const carte = c ? ` · carte ${c.id} (${c.statut})` : " · puce inconnue du site";
        if (r.resultat.simple) {
          setEtat({ t: "alerte", message: "Carte NFC simple — non sécurisée à 100 %", detail: `${r.resultat.puce} · UID ${r.resultat.uid}${carte} · ${r.resultat.url}. ${NON_SECURISEE}` });
          pont.vibrer(200);
        } else {
          setEtat({ t: "ok", message: "Signature authentique", detail: `UID ${r.resultat.uid} · scan n°${r.resultat.compteur}${carte}` });
          pont.vibrer(80);
        }
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
    const r = await relayer(pont, { action: "demarrer", cardId: courante.id, uid, nonSecurisee: forcer || desfireAcceptees.current }, (n) => setEtat({ t: "encours", echanges: n }));
    occupe.current = false;
    const res = r.resultat as { confirmer?: string; simple?: boolean; verrouillee?: boolean; puce?: string; reparation?: boolean } | undefined;
    if (r.termine && res?.confirmer) {
      pont.vibrer(250);
      setEtat({ t: "confirmer", puce: res.confirmer, uid });
    } else if (r.termine && res?.simple) {
      dernierUid.current = uid;
      pont.vibrer(250);
      setFaites((f) => [...f, courante.id]);
      setSimples((x) => x + 1);
      setEtat({
        t: "alerte", suivante: true,
        message: `${courante.titre} : programmée en NFC simple`,
        detail: `${res.puce} · UID ${uid}. ${NON_SECURISEE}`,
        grave: res.verrouillee ? undefined : "La puce n’a pas pu être verrouillée : quelqu’un d’équipé pourrait la réécrire. Gardez-la pour un usage personnel ou mettez-la de côté.",
      });
    } else if (r.termine) {
      dernierUid.current = uid;
      pont.vibrer(80);
      setFaites((f) => [...f, courante.id]);
      setEtat({ t: "ok", message: `${courante.titre} : carte prête`, detail: `UID ${uid}${res?.reparation ? " · reprise d’une carte déjà configurée" : ""}` });
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

  const surQr = useCallback((texte: string) => {
    setScan(false);
    const id = idDepuisQr(texte);
    if (!id) {
      setMessageScan(`Ce QR code n’est pas celui d’une carte Tapikard (${texte.slice(0, 60)}).`);
      return;
    }
    pont?.vibrer(60);
    const connue = lots.flatMap((l) => l.cartes).find((c) => c.id === id);
    setMessageScan(null);
    setFile([connue ?? { id, titre: `Carte ${id}`, detail: "Hors des lots à encoder (déjà encodée ?)" }]);
    setIndex(0);
    setFaites([]);
    setSimples(0);
    dernierUid.current = null;
    setParScan(true);
    setMode("encodage");
    setEtat({ t: "attente" });
  }, [lots, pont]);

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
    <div className="panel" style={{ textAlign: "center", padding: "28px 18px", marginBottom: 16, borderColor: etat.t === "ok" ? "var(--ok)" : etat.t === "erreur" ? "var(--danger)" : etat.t === "confirmer" || etat.t === "alerte" ? "var(--warn)" : undefined, background: etat.t === "confirmer" || etat.t === "alerte" ? "var(--warn-soft)" : undefined }}>
      {etat.t === "attente" && <><div className="nfc-pulse" aria-hidden /><p style={{ fontWeight: 600, margin: "14px 0 0" }}>{mode === "verifier" ? "Posez une carte au dos du téléphone" : "Posez la carte au dos du téléphone"}</p><small className="muted">Gardez-la immobile jusqu’au ✓</small></>}
      {etat.t === "encours" && <><div className="nfc-pulse actif" aria-hidden /><p style={{ fontWeight: 600, margin: "14px 0 0" }}>{mode === "verifier" ? "Lecture…" : "Encodage…"} ne bougez pas la carte</p><small className="muted">{etat.echanges} échange{etat.echanges > 1 ? "s" : ""} avec la puce</small></>}
      {etat.t === "ok" && <><div style={{ fontSize: "3rem", color: "var(--ok)", lineHeight: 1 }}>✓</div><p style={{ fontWeight: 700, margin: "10px 0 2px" }}>{etat.message}</p>{etat.detail && <small className="muted">{etat.detail}</small>}</>}
      {etat.t === "confirmer" && (
        <>
          <div style={{ fontSize: "2.6rem", color: "var(--warn)", lineHeight: 1 }}>⚠</div>
          <p style={{ fontWeight: 700, margin: "10px 0 6px" }}>Carte non sécurisée à 100 %</p>
          <p style={{ margin: "0 0 8px", fontSize: ".95rem" }}>
            Cette carte est une <strong>{etat.puce}</strong>, pas une NTAG 424 DNA. Elle peut être programmée en <strong>NFC simple</strong> et fonctionnera sur tous les téléphones. {NON_SECURISEE}
          </p>
          <small className="muted" style={{ display: "block", marginBottom: 14 }}>À réserver au dépannage (rupture de NTAG 424 DNA). Tout le contenu actuel de la puce sera effacé.</small>
          <div className="stack" style={{ gap: 8 }}>
            <button className="btn btn-block" onClick={() => void surCarte(etat.uid, true, true)}>Programmer cette carte quand même</button>
            {file.length - index > 1 && <button className="btn btn-secondary btn-block" onClick={() => { desfireAcceptees.current = true; void surCarte(etat.uid, true, true); }}>Accepter les DESFire pour tout ce lot</button>}
            <button className="btn btn-ghost btn-block" onClick={() => setEtat({ t: "attente" })}>Annuler</button>
          </div>
        </>
      )}
      {etat.t === "alerte" && (
        <>
          <div style={{ fontSize: "2.6rem", color: "var(--warn)", lineHeight: 1 }}>⚠</div>
          <p style={{ fontWeight: 700, margin: "10px 0 6px" }}>{etat.message}</p>
          <small style={{ display: "block" }}>{etat.detail}</small>
          {etat.grave && <div className="alert alert-danger" style={{ margin: "12px 0 0", textAlign: "left" }}>{etat.grave}</div>}
          {etat.suivante && <button className="btn btn-block" style={{ marginTop: 14 }} onClick={() => { setIndex((i) => i + 1); setEtat({ t: "attente" }); }}>J’ai compris · carte suivante</button>}
        </>
      )}
      {etat.t === "erreur" && <><div style={{ fontSize: "2.4rem", color: "var(--danger)", lineHeight: 1 }}>✕</div><p style={{ fontWeight: 600, margin: "10px 0 6px" }}>{etat.message}</p><button className="btn btn-sm btn-secondary" onClick={() => relancer()}>Réessayer</button></>}
    </div>
  );

  const scanneur = scan ? <ScanQr onLu={surQr} onFermer={() => setScan(false)} /> : null;
  const boutonScan = (texte: string, classe = "btn") => (
    <button className={`${classe} btn-block`} disabled={!clesOk || etat.t === "encours"} onClick={() => { setMessageScan(null); setScan(true); }}>
      <span aria-hidden>▣ </span>{texte}
    </button>
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
        {scanneur}
        {bandeau}
        <div className="row between" style={{ marginBottom: 10 }}>
          <span className="eyebrow">{parScan ? "Carte scannée" : `Carte ${Math.min(index + 1, file.length)} / ${file.length}`}</span>
          <span className="badge badge-ok">{faites.length} encodée{faites.length > 1 ? "s" : ""}</span>
        </div>
        {fini && parScan ? (
          <div className="panel" style={{ textAlign: "center", padding: 28 }}>
            <div style={{ fontSize: "3rem", color: faites.length ? "var(--ok)" : "var(--muted)" }}>{faites.length ? "✓" : "–"}</div>
            <h2 style={{ fontSize: "1.6rem" }}>{faites.length ? `Carte ${file[0]?.id} encodée` : "Carte passée"}</h2>
            {simples > 0 && <div className="alert alert-warn" style={{ margin: "0 0 14px" }}>⚠ Programmée en NFC simple (DESFire, non sécurisée à 100 %).</div>}
            {boutonScan("Scanner la carte suivante", "btn btn-lg")}
          </div>
        ) : fini ? (
          <div className="panel" style={{ textAlign: "center", padding: 28 }}>
            <div style={{ fontSize: "3rem", color: "var(--ok)" }}>✓</div>
            <h2 style={{ fontSize: "1.8rem" }}>Lot terminé</h2>
            <p className="muted">{faites.length} carte{faites.length > 1 ? "s" : ""} encodée{faites.length > 1 ? "s" : ""} et enregistrée{faites.length > 1 ? "s" : ""}.</p>
            {simples > 0 && <div className="alert alert-warn" style={{ margin: 0 }}>⚠ Dont {simples} en NFC simple (DESFire, non sécurisée{simples > 1 ? "s" : ""} à 100 %).</div>}
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
              {parScan ? boutonScan("Scanner une autre carte", "btn btn-secondary") : <button className="btn btn-secondary" style={{ flex: 1 }} disabled={etat.t === "encours"} onClick={() => { setIndex((i) => i + 1); setEtat({ t: "attente" }); }}>Passer cette carte</button>}
            </div>
          </>
        )}
        <button className="btn btn-ghost btn-block" style={{ marginTop: 10 }} disabled={etat.t === "encours"} onClick={() => { setMode("lots"); setParScan(false); setEtat({ t: "attente" }); }}>← Revenir aux lots</button>
      </div>
    );
  }

  return (
    <div className="container" style={{ paddingTop: 8, maxWidth: 560 }}>
      {scanneur}
      {bandeau}
      <h1 style={{ fontSize: "2rem", marginBottom: 6 }}>Encoder des <em>cartes</em></h1>
      <p className="muted" style={{ marginTop: 0 }}>Scannez le QR code imprimé sur la carte, ou choisissez un lot : l’application indique alors la carte à poser, dans l’ordre.</p>
      {messageScan && <div className="alert alert-warn" style={{ margin: "0 0 12px" }}>{messageScan}</div>}
      <div style={{ marginBottom: 16 }}>{boutonScan("Scanner le QR code d’une carte", "btn btn-lg")}</div>
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
            <button className="btn btn-block" style={{ marginTop: 12 }} disabled={!clesOk} onClick={() => { setFile(l.cartes); setIndex(0); setFaites([]); setSimples(0); desfireAcceptees.current = false; dernierUid.current = null; setParScan(false); setMode("encodage"); relancer(false); }}>
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

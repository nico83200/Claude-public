// Relais APDU entre le serveur et le téléphone : une tâche d'encodage asynchrone s'exécute ici,
// et chaque APDU qu'elle envoie est remis au téléphone ; la réponse de la carte revient par la requête suivante.
// Sessions en mémoire (une seule instance du site, comme sur Hostinger).
import { randomBytes } from "node:crypto";
import type { Transmit } from "./ntag424.ts";

const PREFIXE = `${process.pid.toString(36)}.`;

export type Etape<R> = { type: "apdu"; apdu: Buffer } | { type: "fin"; resultat: R } | { type: "erreur"; message: string };

type Differe<T> = { promise: Promise<T>; resolve: (v: T) => void; reject: (e: Error) => void };
function differe<T>(): Differe<T> {
  let resolve!: (v: T) => void, reject!: (e: Error) => void;
  const promise = new Promise<T>((a, b) => { resolve = a; reject = b; });
  return { promise, resolve, reject };
}

export class Relais<R> {
  // Préfixé par le processus : permet de diagnostiquer un hébergement qui répartirait les requêtes sur plusieurs processus.
  readonly id = `${PREFIXE}${randomBytes(16).toString("hex")}`;
  readonly userId: string;
  derniereActivite = Date.now();
  private etape = differe<Etape<R>>();
  private reponse?: Differe<Buffer>;
  journal: string[] = [];

  constructor(userId: string, tache: (t: Transmit, log: (m: string) => void) => Promise<R>) {
    this.userId = userId;
    tache(this.transmit, (m) => this.journal.push(m)).then(
      (resultat) => this.etape.resolve({ type: "fin", resultat }),
      (e: unknown) => this.etape.resolve({ type: "erreur", message: e instanceof Error ? e.message : String(e) }),
    );
  }

  private transmit: Transmit = (apdu) => {
    this.reponse = differe<Buffer>();
    this.etape.resolve({ type: "apdu", apdu });
    return this.reponse.promise;
  };

  prochaineEtape(): Promise<Etape<R>> {
    this.derniereActivite = Date.now();
    return Promise.race([
      this.etape.promise,
      new Promise<Etape<R>>((ok) => setTimeout(() => ok({ type: "erreur", message: "délai dépassé côté serveur" }), 15_000)),
    ]);
  }

  /** Réponse de la carte (ou erreur de communication côté téléphone). */
  fournir(reponse: Buffer | Error) {
    const r = this.reponse;
    this.reponse = undefined;
    this.etape = differe<Etape<R>>();
    if (!r) return;
    if (reponse instanceof Error) r.reject(reponse);
    else r.resolve(reponse);
  }

  abandonner() {
    this.fournir(new Error("encodage interrompu"));
  }
}

const g = globalThis as unknown as { __relais?: Map<string, Relais<unknown>> };
const sessions = (g.__relais ??= new Map());

export function ouvrir<R>(relais: Relais<R>) {
  // Ménage : sessions inactives depuis plus de 2 minutes.
  for (const [id, r] of sessions) if (Date.now() - r.derniereActivite > 120_000) { r.abandonner(); sessions.delete(id); }
  sessions.set(relais.id, relais as Relais<unknown>);
}
export function trouver(id: string, userId: string): Relais<unknown> | undefined {
  const r = sessions.get(id);
  return r && r.userId === userId ? r : undefined;
}
/** La session a été ouverte par un autre processus du serveur (hébergement multi-processus). */
export function autreProcessus(id: string) {
  return id.includes(".") && !id.startsWith(PREFIXE);
}
export function fermer(id: string) {
  sessions.delete(id);
}

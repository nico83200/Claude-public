import { all, run, tx } from "./db";
import { STOCK_OPTIONS, stockKey, type Ruptures } from "./catalog";

/** Ruptures en cours (clé « type:id » → message). */
export function ruptures(): Ruptures {
  return Object.fromEntries(all<{ cle: string; message: string | null }>("SELECT cle, message FROM stock_ruptures").map((r) => [r.cle, r.message ?? ""]));
}

export function rupturesDetaillees() {
  return Object.fromEntries(all<{ cle: string; message: string | null; depuis: string }>("SELECT * FROM stock_ruptures").map((r) => [r.cle, r]));
}

const CLES_VALIDES = new Set(STOCK_OPTIONS.flatMap((g) => g.items.map((i) => stockKey(g.kind, i.id))));

/** Remplace l'état des ruptures. Une option déjà en rupture garde sa date de début. */
export function enregistrerRuptures(etat: { cle: string; rupture: boolean; message: string }[]) {
  tx(() => {
    for (const e of etat) {
      if (!CLES_VALIDES.has(e.cle)) continue;
      if (e.rupture) {
        run("INSERT INTO stock_ruptures (cle, message) VALUES (?, ?) ON CONFLICT(cle) DO UPDATE SET message = excluded.message", e.cle, e.message.trim().slice(0, 120) || null);
      } else {
        run("DELETE FROM stock_ruptures WHERE cle = ?", e.cle);
      }
    }
  });
}

/** Nombre de commandes pas encore en production qui utilisent chaque option (pour décider en connaissance de cause). */
export function commandesEnAttenteParOption(): Record<string, number> {
  const rows = all<{ product: string; material: string; finish: string; template: string | null }>(
    `SELECT product, material, finish, json_extract(design, '$.template') template FROM orders
     WHERE status IN ('pending_payment', 'paid', 'proof_approved')`,
  );
  const n: Record<string, number> = {};
  const inc = (k: string) => (n[k] = (n[k] ?? 0) + 1);
  for (const o of rows) {
    if (o.product === "basic") {
      inc(stockKey("basic", o.material === "pvc_blanc" ? "blanc" : "noir"));
    } else {
      inc(stockKey("material", o.material));
      inc(stockKey("finish", o.finish));
      if (o.template) inc(stockKey("template", o.template));
    }
  }
  return n;
}

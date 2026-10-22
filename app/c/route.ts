import { get, run } from "@/lib/db";
import { redirectTo } from "@/lib/redirect";
import { recordScan } from "@/lib/scans";
import { makeScanToken } from "@/lib/scanToken";
import { sunKeys, verifySun } from "@/lib/sun";
import type { Card } from "@/lib/types";

// Point d'entrée des scans NFC : https://tapikard.com/c?e=<PICCData chiffré>&m=<CMAC>
export async function GET(req: Request) {
  const url = new URL(req.url);
  const e = url.searchParams.get("e") ?? "";
  const m = url.searchParams.get("m") ?? "";
  const go = (path: string) => redirectTo(path);

  let uid: string, counter: number;
  try {
    const { kMeta, kFileForUid } = sunKeys();
    ({ uid, counter } = verifySun(kMeta, kFileForUid, e, m));
  } catch {
    return go("/carte-invalide");
  }
  const card = get<Card>("SELECT * FROM cards WHERE uid = ?", uid);
  if (!card) return go("/carte-invalide");

  // Anti-rejeu : le compteur de la puce doit avoir augmenté depuis le dernier scan accepté.
  const fresh = run("UPDATE cards SET last_counter = ? WHERE id = ? AND last_counter < ?", counter, card.id, counter).changes > 0;
  if (!fresh) {
    if (card.status === "active") await recordScan(card.id, card.profile_id, "nfc", false);
    return go(`/q/${card.id}?rejeu=1`);
  }
  if (card.status === "active") await recordScan(card.id, card.profile_id, "nfc", true);
  return go(`/q/${card.id}?t=${makeScanToken(card.id)}`);
}

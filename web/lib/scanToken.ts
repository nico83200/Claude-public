import { timingSafeEqual } from "node:crypto";
import { sign } from "./auth";

const TTL_MS = 30 * 60_000;
const mac = (cardId: string, ts: string) => sign(`scan.${cardId}.${ts}`).slice(0, 16);

/** Jeton court transmis de /c (scan NFC vérifié) vers /q/<id> : le rafraîchissement garde le badge « authentique ». */
export function makeScanToken(cardId: string): string {
  const ts = Date.now().toString(36);
  return `${ts}.${mac(cardId, ts)}`;
}

export function isValidScanToken(cardId: string, token: string | undefined): boolean {
  const [ts, sig] = (token ?? "").split(".");
  if (!ts || !sig) return false;
  const age = Date.now() - parseInt(ts, 36);
  if (!(age >= 0 && age < TTL_MS)) return false;
  const expected = Buffer.from(mac(cardId, ts));
  const given = Buffer.from(sig);
  return expected.length === given.length && timingSafeEqual(expected, given);
}

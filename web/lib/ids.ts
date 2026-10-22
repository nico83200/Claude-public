import { randomBytes, randomInt } from "node:crypto";

const B62 = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

/** Identifiant aléatoire base62 (non séquentiel : empêche l'énumération). */
export function randomId(len = 12): string {
  let s = "";
  for (let i = 0; i < len; i++) s += B62[randomInt(62)];
  return s;
}

/** Code d'activation lisible (sans 0/O/1/I), imprimé sur l'encart d'emballage. */
export function activationCode(): string {
  const A = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  let s = "";
  for (let i = 0; i < 8; i++) s += A[randomInt(A.length)];
  return `${s.slice(0, 4)}-${s.slice(4)}`;
}

export function randomToken(): string {
  return randomBytes(32).toString("base64url");
}

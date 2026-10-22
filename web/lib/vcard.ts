import type { Profile } from "./types";
import { parseLinks } from "./types";

const esc = (s: string) => s.replace(/\\/g, "\\\\").replace(/\n/g, "\\n").replace(/([,;])/g, "\\$1");

/** vCard 3.0 (la plus compatible iOS / Android), lignes repliées à 75 caractères. */
export function buildVCard(p: Profile): string {
  const parts = p.display_name.trim().split(/\s+/);
  const last = parts.length > 1 ? parts.pop()! : "";
  const first = parts.join(" ");
  const lines = ["BEGIN:VCARD", "VERSION:3.0", `N:${esc(last)};${esc(first)};;;`, `FN:${esc(p.display_name)}`];
  if (p.company) lines.push(`ORG:${esc(p.company)}`);
  if (p.title) lines.push(`TITLE:${esc(p.title)}`);
  if (p.mobile) lines.push(`TEL;TYPE=CELL:${p.mobile}`);
  if (p.phone) lines.push(`TEL;TYPE=WORK,VOICE:${p.phone}`);
  if (p.email) lines.push(`EMAIL;TYPE=INTERNET,WORK:${p.email}`);
  if (p.website) lines.push(`URL:${/^https?:\/\//.test(p.website) ? p.website : `https://${p.website}`}`);
  if (p.address) lines.push(`ADR;TYPE=WORK:;;${esc(p.address)};;;;`);
  for (const l of parseLinks(p)) lines.push(`URL;TYPE=${esc(l.label).replace(/[^A-Za-z0-9-]/g, "") || "OTHER"}:${l.url}`);
  if (p.bio) lines.push(`NOTE:${esc(p.bio)}`);
  const photo = p.avatar?.match(/^data:image\/(png|jpeg);base64,(.+)$/);
  if (photo) lines.push(`PHOTO;ENCODING=b;TYPE=${photo[1] === "png" ? "PNG" : "JPEG"}:${photo[2]}`);
  lines.push("END:VCARD");
  return lines.map(fold).join("\r\n") + "\r\n";
}

function fold(line: string): string {
  if (line.length <= 75) return line;
  const out = [line.slice(0, 75)];
  for (let i = 75; i < line.length; i += 74) out.push(" " + line.slice(i, i + 74));
  return out.join("\r\n");
}

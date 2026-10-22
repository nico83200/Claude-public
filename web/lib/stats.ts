import { all, get } from "./db";

export type Stats = {
  daily: { label: string; value: number }[];
  views: number; nfc: number; qr: number; verified: number; vcf: number; clicks: number; leads: number;
  countries: { k: string; n: number }[];
  devices: { k: string; n: number }[];
  links: { k: string; n: number }[];
};

/** Statistiques des cartes d'un utilisateur sur N jours (optionnellement pour un seul profil). */
export function userStats(userId: string, days = 30, profileId?: string): Stats {
  const where = `card_id IN (SELECT id FROM cards WHERE user_id = ?) AND at >= datetime('now', ?) ${profileId ? "AND profile_id = ?" : ""}`;
  const params = [userId, `-${days} days`, ...(profileId ? [profileId] : [])];
  const byDay = new Map(
    all<{ d: string; n: number }>(`SELECT date(at) d, COUNT(*) n FROM scan_events WHERE ${where} AND action = 'view' GROUP BY d`, ...params).map((r) => [r.d, r.n]),
  );
  const daily = Array.from({ length: days }, (_, i) => {
    const dt = new Date(Date.now() - (days - 1 - i) * 86_400_000);
    const key = dt.toISOString().slice(0, 10);
    return { label: `${dt.getUTCDate()}/${dt.getUTCMonth() + 1}`, value: byDay.get(key) ?? 0 };
  });
  const t = get<Record<string, number>>(
    `SELECT
       SUM(action = 'view') views, SUM(action = 'view' AND channel = 'nfc') nfc, SUM(action = 'view' AND channel = 'qr') qr,
       SUM(action = 'view' AND verified = 1) verified, SUM(action = 'vcf') vcf, SUM(action = 'click') clicks, SUM(action = 'lead') leads
     FROM scan_events WHERE ${where}`,
    ...params,
  ) ?? {};
  const group = (col: string, extra = "") =>
    all<{ k: string; n: number }>(`SELECT COALESCE(${col}, 'Inconnu') k, COUNT(*) n FROM scan_events WHERE ${where} ${extra} GROUP BY k ORDER BY n DESC LIMIT 6`, ...params);
  return {
    daily,
    views: t.views ?? 0, nfc: t.nfc ?? 0, qr: t.qr ?? 0, verified: t.verified ?? 0, vcf: t.vcf ?? 0, clicks: t.clicks ?? 0, leads: t.leads ?? 0,
    countries: group("country", "AND action = 'view'"),
    devices: group("device", "AND action = 'view'"),
    links: group("target", "AND action = 'click'"),
  };
}

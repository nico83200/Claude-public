import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { BarChart } from "@/components/BarChart";
import { all } from "@/lib/db";
import { userStats } from "@/lib/stats";

export const metadata = { title: "Statistiques" };

const PERIODS = [7, 30, 90];

function Ranking({ title, rows, total }: { title: string; rows: { k: string; n: number }[]; total: number }) {
  return (
    <div className="panel">
      <h3>{title}</h3>
      {rows.length === 0 ? <p className="muted">Pas encore de données.</p> : rows.map((r) => (
        <div key={r.k} style={{ marginBottom: 10 }}>
          <div className="row between" style={{ fontSize: ".92rem" }}><span>{r.k}</span><strong>{r.n}</strong></div>
          <div style={{ height: 6, background: "var(--surface-2)", borderRadius: 99 }}><div style={{ height: 6, width: `${(100 * r.n) / Math.max(1, total)}%`, background: "var(--brand)", borderRadius: 99 }} /></div>
        </div>
      ))}
    </div>
  );
}

export default async function StatsPage({ searchParams }: { searchParams: Promise<{ jours?: string; profil?: string }> }) {
  const sp = await searchParams;
  const user = await requireUser("/compte/statistiques");
  const days = PERIODS.includes(Number(sp.jours)) ? Number(sp.jours) : 30;
  const profiles = all<{ id: string; label: string }>("SELECT id, label FROM profiles WHERE user_id = ?", user.id);
  const profile = profiles.find((p) => p.id === sp.profil)?.id;
  const s = userStats(user.id, days, profile);
  const q = (o: Record<string, string | number | undefined>) => "?" + new URLSearchParams(Object.entries({ jours: days, profil: profile, ...o }).filter(([, v]) => v !== undefined) as [string, string][]).toString();

  return (
    <div className="stack">
      <div className="page-head">
        <h1>Statistiques</h1>
        <div className="row">
          {profiles.length > 1 && (
            <span className="row" style={{ gap: 4 }}>
              <Link className={`btn btn-sm ${!profile ? "" : "btn-secondary"}`} href={q({ profil: undefined })}>Tous</Link>
              {profiles.map((p) => <Link key={p.id} className={`btn btn-sm ${profile === p.id ? "" : "btn-secondary"}`} href={q({ profil: p.id })}>{p.label}</Link>)}
            </span>
          )}
          <span className="row" style={{ gap: 4 }}>
            {PERIODS.map((d) => <Link key={d} className={`btn btn-sm ${d === days ? "" : "btn-secondary"}`} href={q({ jours: d })}>{d} j</Link>)}
          </span>
        </div>
      </div>
      <div className="grid grid-4">
        {[
          ["Vues du profil", s.views, `${s.nfc} NFC · ${s.qr} QR`],
          ["Scans authentifiés", s.verified, "Signature NTAG 424 vérifiée"],
          ["Ajouts aux contacts", s.vcf, s.views ? `${Math.round((100 * s.vcf) / s.views)} % des vues` : "—"],
          ["Contacts reçus", s.leads, `${s.clicks} clics sur vos liens`],
        ].map(([l, v, sub]) => (
          <div className="panel" key={l as string}><div className="kpi-label">{l}</div><div className="kpi">{v}</div><small className="muted">{sub}</small></div>
        ))}
      </div>
      <div className="panel"><h3>Vues par jour</h3><BarChart data={s.daily} /></div>
      <div className="grid grid-3">
        <Ranking title="Pays" rows={s.countries} total={s.views} />
        <Ranking title="Appareils" rows={s.devices} total={s.views} />
        <Ranking title="Liens les plus cliqués" rows={s.links} total={s.clicks} />
      </div>
      <p className="muted" style={{ fontSize: ".85rem" }}>Statistiques anonymes : ni adresse IP ni cookie ne sont conservés.</p>
    </div>
  );
}

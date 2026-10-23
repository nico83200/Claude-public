import { STOCK_OPTIONS, stockKey } from "@/lib/catalog";
import { commandesEnAttenteParOption, rupturesDetaillees } from "@/lib/stock";
import { adminEnregistrerStocks } from "../actions";

export const metadata = { title: "Stocks" };

export default async function AdminStocks({ searchParams }: { searchParams: Promise<{ enregistre?: string }> }) {
  const { enregistre } = await searchParams;
  const ruptures = rupturesDetaillees();
  const enAttente = commandesEnAttenteParOption();
  const nb = Object.keys(ruptures).length;

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h1>Stocks</h1>
          <p className="muted" style={{ margin: 0 }}>
            Cochez une option en rupture : elle reste visible sur le site, mais ne peut plus être commandée.
            Le message facultatif est affiché aux clients (ex. « Retour prévu fin novembre »).
          </p>
        </div>
        <span className={`badge ${nb ? "badge-warn" : "badge-ok"}`}>{nb ? `${nb} option${nb > 1 ? "s" : ""} en rupture` : "Tout est disponible"}</span>
      </div>
      {enregistre && <div className="alert alert-ok">Stocks enregistrés : le site est à jour.</div>}

      <form action={adminEnregistrerStocks} className="stack">
        {STOCK_OPTIONS.map((g) => (
          <div className="panel" key={g.kind} style={{ padding: 0, overflow: "hidden" }}>
            <h3 style={{ padding: "18px 22px 0" }}>{g.titre}</h3>
            <table>
              <thead><tr><th style={{ width: "34%" }}>Option</th><th style={{ width: 130 }}>En rupture</th><th>Message aux clients</th><th style={{ width: 150 }}>À produire</th></tr></thead>
              <tbody>
                {g.items.map((i) => {
                  const cle = stockKey(g.kind, i.id);
                  const r = ruptures[cle];
                  const n = enAttente[cle] ?? 0;
                  return (
                    <tr key={cle} style={r ? { background: "var(--warn-soft)" } : undefined}>
                      <td>
                        <strong>{i.label}</strong>
                        {i.detail && <><br /><small className="muted">{i.detail}</small></>}
                        {r && <><br /><small style={{ color: "var(--warn)" }}>En rupture depuis le {new Date(r.depuis + "Z").toLocaleDateString("fr-FR")}</small></>}
                      </td>
                      <td>
                        <label className="check" style={{ margin: 0, alignItems: "center" }}>
                          <input type="checkbox" name={`rupture:${cle}`} defaultChecked={!!r} style={{ marginTop: 0 }} />
                          <span>Rupture</span>
                        </label>
                      </td>
                      <td><input type="text" name={`message:${cle}`} defaultValue={r?.message ?? ""} maxLength={120} placeholder="Retour prévu…" aria-label={`Message pour ${i.label}`} /></td>
                      <td>{n ? <span className="badge badge-brand">{n} commande{n > 1 ? "s" : ""}</span> : <small className="muted">—</small>}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ))}
        <div className="row between">
          <small className="muted">« À produire » : commandes payées ou en attente, pas encore en production. Elles ne sont pas annulées par une rupture.</small>
          <button className="btn btn-lg">Enregistrer les stocks</button>
        </div>
      </form>
    </div>
  );
}

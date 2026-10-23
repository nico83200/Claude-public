import { currentUser } from "@/lib/auth";
import { expediteur } from "@/lib/colissimo";
import { getOrder } from "@/lib/orders";
import type { Shipping } from "@/lib/types";

const h = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

/** Étiquette Colissimo (PDF) si elle a été générée ; sinon étiquette d'adresse 10 × 15 cm à imprimer. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const { id } = await params;
  const o = getOrder(id);
  if (!o) return new Response("Commande introuvable", { status: 404 });
  if (o.etiquette_pdf) {
    return new Response(Buffer.from(o.etiquette_pdf, "base64"), {
      headers: { "Content-Type": "application/pdf", "Content-Disposition": `inline; filename="etiquette-${o.tracking ?? id}.pdf"` },
    });
  }
  const s = JSON.parse(o.shipping) as Shipping;
  const exp = expediteur();
  const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette ${h(id)}</title>
<style>
@page { size: 100mm 150mm; margin: 0; }
body { margin: 0; font-family: Arial, Helvetica, sans-serif; }
.e { width: 100mm; height: 150mm; box-sizing: border-box; padding: 8mm; display: flex; flex-direction: column; }
.exp { font-size: 9pt; color: #333; border-bottom: .3mm solid #000; padding-bottom: 4mm; }
.dest { margin-top: 14mm; font-size: 14pt; line-height: 1.35; }
.dest strong { font-size: 16pt; }
.ref { margin-top: auto; font-size: 8pt; color: #555; }
@media screen { body { background: #ddd; } .e { background: #fff; margin: 20px auto; box-shadow: 0 4px 20px rgba(0,0,0,.2); } .aide { text-align: center; font-size: 13px; } }
@media print { .aide { display: none; } }
</style></head><body>
<p class="aide">Étiquette d’adresse 10 × 15 cm — imprimez-la (Ctrl/Cmd + P, marges : aucune) et collez-la sur l’enveloppe.
Pour une étiquette Colissimo avec suivi automatique, configurez le contrat Colissimo.</p>
<div class="e">
  <div class="exp"><strong>Expéditeur</strong><br>${exp ? `${h(exp.nom)}<br>${h(exp.adresse)}<br>${h(exp.cp)} ${h(exp.ville)}` : "Tapikard"}</div>
  <div class="dest"><strong>${h(s.name)}</strong><br>${h(s.address)}${s.address2 ? `<br>${h(s.address2)}` : ""}<br><strong>${h(s.zip)} ${h(s.city.toUpperCase())}</strong><br>${h(s.country === "FR" || !s.country ? "FRANCE" : s.country)}</div>
  <div class="ref">Commande ${h(id)} · ${o.quantity} carte${o.quantity > 1 ? "s" : ""}</div>
</div></body></html>`;
  return new Response(html, { headers: { "Content-Type": "text/html; charset=utf-8" } });
}

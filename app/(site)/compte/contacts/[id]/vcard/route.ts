import { currentUser } from "@/lib/auth";
import { get } from "@/lib/db";
import { buildLeadVCard } from "@/lib/vcard";
import type { Lead } from "@/lib/types";

export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user) return new Response("Non connecté", { status: 401 });
  const { id } = await params;
  const lead = get<Lead>("SELECT l.* FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE l.id = ? AND p.user_id = ?", Number(id), user.id);
  if (!lead) return new Response("Introuvable", { status: 404 });
  const name = lead.name.normalize("NFD").replace(/[^\w]+/g, "-").replace(/^-|-$/g, "") || "contact";
  return new Response(buildLeadVCard(lead), {
    headers: { "Content-Type": "text/vcard; charset=utf-8", "Content-Disposition": `attachment; filename="${name}.vcf"` },
  });
}

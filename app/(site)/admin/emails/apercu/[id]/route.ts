import { currentUser } from "@/lib/auth";
import { emailParId } from "@/lib/emails/catalogue";
import type { Outil } from "@/lib/emails/campagnes";

/** Aperçu HTML d'un email (iframe du back-office) ; ?telecharger=1 pour récupérer le fichier d'une campagne. */
export async function GET(req: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const { id } = await params;
  const sp = new URL(req.url).searchParams;
  const trouve = emailParId(id, (sp.get("outil") ?? "brevo") as Outil);
  if (!trouve) return new Response("Email inconnu", { status: 404 });
  const headers: Record<string, string> = { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" };
  if (sp.get("telecharger") === "1") headers["Content-Disposition"] = `attachment; filename="tapikard-${id}.html"`;
  return new Response(trouve.email.html, { headers });
}

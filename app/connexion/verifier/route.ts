import { consumeLoginToken, startSession } from "@/lib/auth";
import { redirectTo } from "@/lib/redirect";

export async function GET(req: Request) {
  const url = new URL(req.url);
  const userId = consumeLoginToken(url.searchParams.get("token") ?? "");
  if (!userId) return redirectTo("/connexion?erreur=lien");
  await startSession(userId);
  return redirectTo(url.searchParams.get("next") ?? "/compte");
}

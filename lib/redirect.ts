/**
 * Redirection vers un chemin relatif (`Location: /compte`).
 * Derrière un proxy (Hostinger), `req.url` contient l'adresse interne du serveur (http://0.0.0.0:3000) :
 * une URL absolue construite à partir de `req.url` enverrait le visiteur vers une adresse injoignable.
 * Un chemin relatif est résolu par le navigateur sur le domaine qu'il est en train de consulter.
 */
export function redirectTo(path: string, status: 302 | 303 | 307 = 302): Response {
  const safe = path.startsWith("/") && !path.startsWith("//") ? path : "/";
  return new Response(null, { status, headers: { Location: safe, "Cache-Control": "no-store" } });
}

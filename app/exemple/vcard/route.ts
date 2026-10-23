import { DEMO_PROFILE } from "@/lib/demoProfile";
import { buildVCard } from "@/lib/vcard";

export function GET() {
  return new Response(buildVCard(DEMO_PROFILE), {
    headers: { "Content-Type": "text/vcard; charset=utf-8", "Content-Disposition": 'attachment; filename="Camille-Martin-exemple.vcf"' },
  });
}

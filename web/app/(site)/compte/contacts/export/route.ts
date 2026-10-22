import { currentUser } from "@/lib/auth";
import { toCsv } from "@/lib/csv";
import { all } from "@/lib/db";
import type { Lead } from "@/lib/types";

export async function GET() {
  const user = await currentUser();
  if (!user) return new Response("Non connecté", { status: 401 });
  const leads = all<Lead & { profile_label: string }>(
    "SELECT l.*, p.label profile_label FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE p.user_id = ? ORDER BY l.created_at DESC", user.id);
  const csv = toCsv([
    ["Date", "Nom", "Email", "Téléphone", "Entreprise", "Message", "Profil", "Carte"],
    ...leads.map((l) => [l.created_at, l.name, l.email, l.phone, l.company, l.message, l.profile_label, l.card_id]),
  ]);
  return new Response(csv, { headers: { "Content-Type": "text/csv; charset=utf-8", "Content-Disposition": 'attachment; filename="contacts-tapikard.csv"' } });
}

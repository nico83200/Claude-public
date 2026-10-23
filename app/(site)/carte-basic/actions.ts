"use server";

import { redirect } from "next/navigation";
import { findOrCreateUser, normalizeEmail } from "@/lib/auth";
import { BASIC_VARIANTS, QUANTITIES, basicDesign, basicPriceCents, enRupture, libelleRupture, type BasicVariant } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { STRIPE_ENABLED } from "@/lib/config";
import { get, run } from "@/lib/db";
import { randomId } from "@/lib/ids";
import { createCheckoutSession } from "@/lib/stripe";
import type { Order } from "@/lib/types";

export type BasicOrderState = { error?: string };

export async function createBasicOrder(_prev: BasicOrderState, form: FormData): Promise<BasicOrderState> {
  const variant = String(form.get("variant") ?? "") as BasicVariant;
  const qty = Number(form.get("quantity"));
  const email = normalizeEmail(String(form.get("email") ?? ""));
  const str = (k: string, max: number) => String(form.get(k) ?? "").trim().slice(0, max);
  const shipping = { name: str("name", 80), address: str("address", 160), zip: str("zip", 10), city: str("city", 80), country: "FR" };
  if (!(variant in BASIC_VARIANTS)) return { error: "Choisissez une couleur." };
  if (!QUANTITIES.some((q) => q.qty === qty)) return { error: "Quantité invalide." };
  const r = ruptures();
  if (enRupture(r, "basic", variant)) return { error: `Carte Basic ${BASIC_VARIANTS[variant].label.toLowerCase()} : ${libelleRupture(r, "basic", variant).toLowerCase()}.` };
  if (!email) return { error: "Adresse email invalide." };
  if (!shipping.name) return { error: "Indiquez votre nom." };
  if (!STRIPE_ENABLED && (!shipping.address || !/^\d{5}$/.test(shipping.zip) || !shipping.city)) {
    return { error: "Adresse de livraison incomplète (code postal à 5 chiffres)." };
  }
  if (form.get("cgv") !== "on") return { error: "Merci d’accepter les conditions." };

  const user = findOrCreateUser(email, shipping.name);
  // Profil minimal, complété par le client à la réception (il apparaît dans son espace dès maintenant).
  let profileId = get<{ id: string }>("SELECT id FROM profiles WHERE user_id = ? ORDER BY created_at LIMIT 1", user.id)?.id;
  if (!profileId) {
    profileId = randomId(12);
    run("INSERT INTO profiles (id, user_id, display_name, email) VALUES (?, ?, ?, ?)", profileId, user.id, shipping.name, email);
  }
  const design = basicDesign(variant);
  const orderId = `cmd_${randomId(16)}`;
  run(
    "INSERT INTO orders (id, user_id, profile_id, product, design, material, finish, quantity, amount_cents, shipping) VALUES (?, ?, ?, 'basic', ?, ?, 'mat', ?, ?, ?)",
    orderId, user.id, profileId, JSON.stringify(design), design.material, qty, basicPriceCents(qty).total, JSON.stringify(shipping),
  );

  if (STRIPE_ENABLED) {
    let url: string;
    try {
      const session = await createCheckoutSession(get<Order>("SELECT * FROM orders WHERE id = ?", orderId)!, email);
      run("UPDATE orders SET stripe_session_id = ? WHERE id = ?", session.id, orderId);
      url = session.url;
    } catch (e) {
      console.error(e);
      return { error: "Le paiement est momentanément indisponible. Réessayez dans un instant." };
    }
    redirect(url);
  }
  redirect(`/commande/${orderId}`);
}

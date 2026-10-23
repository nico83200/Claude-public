"use server";

import { redirect } from "next/navigation";
import { findOrCreateUser, normalizeEmail } from "@/lib/auth";
import { priceCents, sanitizeDesign, verifierStock, QUANTITIES } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { OFFRE_PERSONNALISEE, STRIPE_ENABLED } from "@/lib/config";
import { get, run } from "@/lib/db";
import { randomId } from "@/lib/ids";
import { createCheckoutSession } from "@/lib/stripe";
import type { Order } from "@/lib/types";

export type CreateOrderState = { error?: string };

export async function createOrder(_prev: CreateOrderState, form: FormData): Promise<CreateOrderState> {
  if (!OFFRE_PERSONNALISEE) return { error: "La carte personnalisée n’est pas encore disponible : découvrez la carte Basic." };
  let payload: Record<string, unknown>;
  try {
    payload = JSON.parse(String(form.get("payload") ?? "{}"));
  } catch {
    return { error: "Données invalides." };
  }
  const design = sanitizeDesign(payload.design);
  const email = normalizeEmail(String(payload.email ?? ""));
  const qty = Number(payload.quantity);
  const s = (payload.shipping ?? {}) as Record<string, string>;
  const shipping = {
    name: String(s.name ?? "").trim().slice(0, 80),
    address: String(s.address ?? "").trim().slice(0, 160),
    zip: String(s.zip ?? "").trim().slice(0, 10),
    city: String(s.city ?? "").trim().slice(0, 80),
    country: "FR",
  };
  if (!design.name) return { error: "Indiquez le nom à imprimer sur la carte." };
  const rupture = verifierStock(design, ruptures());
  if (rupture) return { error: rupture };
  if (!email) return { error: "Adresse email invalide." };
  if (!QUANTITIES.some((q) => q.qty === qty)) return { error: "Quantité invalide." };
  if (!STRIPE_ENABLED && (!shipping.name || !shipping.address || !/^\d{5}$/.test(shipping.zip) || !shipping.city)) {
    return { error: "Adresse de livraison incomplète (code postal à 5 chiffres)." };
  }
  if (payload.consent !== true) return { error: "Merci d’accepter la publication de votre profil." };

  const user = findOrCreateUser(email, design.name);
  if (!user.name) run("UPDATE users SET name = ? WHERE id = ?", design.name, user.id);

  // Le profil en ligne est pré-rempli à partir des informations imprimées.
  const profileId = randomId(12);
  run(
    `INSERT INTO profiles (id, user_id, display_name, title, company, phone, email, website, accent, avatar)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)`,
    profileId, user.id, design.name, design.title || null, design.company || null, design.phone || null,
    design.email || email, design.website || null, design.accent,
  );

  const { total } = priceCents(design.material, design.finish, qty);
  const orderId = `cmd_${randomId(16)}`;
  run(
    "INSERT INTO orders (id, user_id, profile_id, design, material, finish, quantity, amount_cents, shipping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    orderId, user.id, profileId, JSON.stringify(design), design.material, design.finish, qty, total, JSON.stringify(shipping),
  );

  if (STRIPE_ENABLED) {
    const order = get<Order>("SELECT * FROM orders WHERE id = ?", orderId)!;
    let url: string;
    try {
      const session = await createCheckoutSession(order, email);
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

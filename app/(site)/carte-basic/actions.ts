"use server";

import { redirect } from "next/navigation";
import { findOrCreateUser, normalizeEmail } from "@/lib/auth";
import { BASIC_VARIANTS, QUANTITIES, basicDesign, basicPriceCents, enRupture, libelleRupture, type BasicVariant } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { STRIPE_ENABLED } from "@/lib/config";
import { get, run } from "@/lib/db";
import { randomId } from "@/lib/ids";
import { createCheckoutSession } from "@/lib/stripe";
import { appliquerRemise, verifierCode } from "@/lib/promo";
import type { Order } from "@/lib/types";

export type BasicOrderState = { error?: string };

/** Vérification du code promo depuis le formulaire (avant la commande). */
export async function verifierCodePromo(code: string, email: string): Promise<{ ok: boolean; remise?: number; message: string }> {
  const r = verifierCode(code, email ? normalizeEmail(email) : null);
  return r.ok ? { ok: true, remise: r.code.remise, message: `Code ${r.code.code} : −${Math.round(r.code.remise * 100)} % supplémentaires` } : { ok: false, message: r.erreur };
}

export async function createBasicOrder(_prev: BasicOrderState, form: FormData): Promise<BasicOrderState> {
  const variant = String(form.get("variant") ?? "") as BasicVariant;
  const qty = Number(form.get("quantity"));
  const email = normalizeEmail(String(form.get("email") ?? ""));
  const str = (k: string, max: number) => String(form.get(k) ?? "").trim().slice(0, max);
  const shipping = {
    name: str("name", 80), address: str("address", 160), address2: str("address2", 160), zip: str("zip", 10), city: str("city", 80),
    country: "FR", phone: str("phone", 20),
  };
  if (!(variant in BASIC_VARIANTS)) return { error: "Choisissez une couleur." };
  if (!QUANTITIES.some((q) => q.qty === qty)) return { error: "Quantité invalide." };
  const r = ruptures();
  if (enRupture(r, "basic", variant)) return { error: `Carte Basic ${BASIC_VARIANTS[variant].label.toLowerCase()} : ${libelleRupture(r, "basic", variant).toLowerCase()}.` };
  if (!email) return { error: "Adresse email invalide." };
  if (!shipping.name) return { error: "Indiquez votre nom." };
  if (!shipping.address || !/^\d{5}$/.test(shipping.zip) || !shipping.city) {
    return { error: "Adresse de livraison incomplète (code postal à 5 chiffres)." };
  }
  if (shipping.phone && !/^[+\d][\d .-]{8,}$/.test(shipping.phone)) return { error: "Numéro de téléphone invalide." };
  let promo: { code: string; remise: number } | null = null;
  const saisi = str("code_promo", 30);
  if (saisi) {
    const v = verifierCode(saisi, email);
    if (!v.ok) return { error: v.erreur };
    promo = { code: v.code.code, remise: v.code.remise };
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
  const { total, remiseCents } = appliquerRemise(basicPriceCents(qty).total, promo?.remise ?? 0);
  const orderId = `cmd_${randomId(16)}`;
  run(
    "INSERT INTO orders (id, user_id, profile_id, product, design, material, finish, quantity, amount_cents, shipping, code_promo, remise_cents) VALUES (?, ?, ?, 'basic', ?, ?, 'mat', ?, ?, ?, ?, ?)",
    orderId, user.id, profileId, JSON.stringify(design), design.material, qty, total, JSON.stringify(shipping), promo?.code ?? null, remiseCents,
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

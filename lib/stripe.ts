import { createHmac, timingSafeEqual } from "node:crypto";
import { APP_URL } from "./config";
import type { Order } from "./types";

/** Crée une session Stripe Checkout (API REST, sans SDK). */
export async function createCheckoutSession(order: Order, email: string): Promise<{ id: string; url: string }> {
  const body = new URLSearchParams({
    mode: "payment",
    customer_email: email,
    "line_items[0][quantity]": "1",
    "line_items[0][price_data][currency]": "eur",
    "line_items[0][price_data][unit_amount]": String(order.amount_cents),
    "line_items[0][price_data][product_data][name]": `Cartes Tapikard × ${order.quantity}`,
    "metadata[order_id]": order.id,
    success_url: `${APP_URL}/commande/${order.id}?paiement=ok`,
    cancel_url: `${APP_URL}/commande/${order.id}?paiement=annule`,
    "shipping_address_collection[allowed_countries][0]": "FR",
  });
  const res = await fetch("https://api.stripe.com/v1/checkout/sessions", {
    method: "POST",
    headers: { Authorization: `Bearer ${process.env.STRIPE_SECRET_KEY}`, "Content-Type": "application/x-www-form-urlencoded" },
    body,
  });
  if (!res.ok) throw new Error(`Stripe : ${res.status} ${await res.text()}`);
  return res.json();
}

/** Vérifie l'en-tête Stripe-Signature (t=…,v1=…) — tolérance 5 minutes. */
export function verifyStripeSignature(payload: string, header: string | null): boolean {
  const secret = process.env.STRIPE_WEBHOOK_SECRET;
  if (!secret || !header) return false;
  const parts = Object.fromEntries(header.split(",").map((p) => p.split("=") as [string, string]));
  const t = Number(parts.t);
  if (!t || Math.abs(Date.now() / 1000 - t) > 300) return false;
  const expected = createHmac("sha256", secret).update(`${t}.${payload}`).digest("hex");
  return header
    .split(",")
    .filter((p) => p.startsWith("v1="))
    .some((p) => {
      const sig = Buffer.from(p.slice(3));
      return sig.length === expected.length && timingSafeEqual(sig, Buffer.from(expected));
    });
}

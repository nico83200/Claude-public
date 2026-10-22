export type User = { id: string; email: string; name: string | null; is_admin: number; created_at: string };

export type ProfileLink = { label: string; url: string };

export type Profile = {
  id: string; user_id: string; label: string;
  display_name: string; title: string | null; company: string | null; bio: string | null;
  phone: string | null; mobile: string | null; email: string | null; website: string | null; address: string | null;
  avatar: string | null; links: string; accent: string; dark: number;
  lead_capture: number; is_public: number; created_at: string; updated_at: string;
};

export type OrderStatus = "pending_payment" | "paid" | "proof_approved" | "in_production" | "shipped" | "delivered" | "cancelled";

export type Shipping = { name: string; address: string; zip: string; city: string; country: string };

export type Order = {
  id: string; user_id: string; profile_id: string | null; status: OrderStatus;
  design: string; material: string; finish: string; quantity: number; amount_cents: number;
  shipping: string; proof_comment: string | null; tracking: string | null; stripe_session_id: string | null;
  created_at: string; paid_at: string | null; proof_approved_at: string | null; shipped_at: string | null;
};

export type CardStatus = "provisioned" | "encoded" | "shipped" | "active" | "disabled" | "lost";

export type Card = {
  id: string; order_id: string | null; user_id: string | null; profile_id: string | null; batch_id: string | null;
  label: string | null; uid: string | null; status: CardStatus; activation_code: string; last_counter: number;
  created_at: string; encoded_at: string | null; activated_at: string | null;
};

export type Lead = {
  id: number; profile_id: string; card_id: string | null; name: string; email: string | null;
  phone: string | null; company: string | null; message: string | null; created_at: string;
};

export const ORDER_STATUS_LABEL: Record<OrderStatus, string> = {
  pending_payment: "En attente de paiement",
  paid: "Payée — BAT à valider",
  proof_approved: "BAT validé",
  in_production: "En production",
  shipped: "Expédiée",
  delivered: "Livrée",
  cancelled: "Annulée",
};

export const CARD_STATUS_LABEL: Record<CardStatus, string> = {
  provisioned: "À produire",
  encoded: "Encodée",
  shipped: "Expédiée",
  active: "Active",
  disabled: "Désactivée",
  lost: "Perdue / volée",
};

export function parseLinks(p: Pick<Profile, "links">): ProfileLink[] {
  try {
    const v = JSON.parse(p.links);
    return Array.isArray(v) ? v : [];
  } catch {
    return [];
  }
}

import { CARD_STATUS_LABEL, ORDER_STATUS_LABEL, type CardStatus, type OrderStatus } from "@/lib/types";

const ORDER_TONE: Record<OrderStatus, string> = {
  pending_payment: "badge-warn", paid: "badge-warn", proof_approved: "badge-brand", in_production: "badge-brand",
  shipped: "badge-ok", delivered: "badge-ok", cancelled: "badge-danger", refunded: "badge-danger",
};
const CARD_TONE: Record<CardStatus, string> = {
  provisioned: "", encoded: "badge-brand", shipped: "badge-brand", active: "badge-ok", disabled: "badge-warn", lost: "badge-danger",
};

export const OrderBadge = ({ status }: { status: OrderStatus }) => <span className={`badge ${ORDER_TONE[status]}`}>{ORDER_STATUS_LABEL[status]}</span>;
export const CardBadge = ({ status }: { status: CardStatus }) => <span className={`badge ${CARD_TONE[status]}`}>{CARD_STATUS_LABEL[status]}</span>;

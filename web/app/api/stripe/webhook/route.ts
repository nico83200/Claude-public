import { NextResponse } from "next/server";
import { markOrderPaid } from "@/lib/orders";
import { verifyStripeSignature } from "@/lib/stripe";
import { run } from "@/lib/db";

export async function POST(req: Request) {
  const payload = await req.text();
  if (!verifyStripeSignature(payload, req.headers.get("stripe-signature"))) return NextResponse.json({ error: "Signature invalide" }, { status: 400 });
  const event = JSON.parse(payload);
  if (event.type === "checkout.session.completed" && event.data?.object?.payment_status === "paid") {
    const session = event.data.object;
    const orderId = session.metadata?.order_id;
    if (orderId) {
      const addr = session.shipping_details?.address ?? session.collected_information?.shipping_details?.address;
      const name = session.shipping_details?.name ?? session.collected_information?.shipping_details?.name;
      if (addr) {
        run("UPDATE orders SET shipping = ? WHERE id = ?", JSON.stringify({
          name: name ?? "", address: [addr.line1, addr.line2].filter(Boolean).join(", "), zip: addr.postal_code ?? "", city: addr.city ?? "", country: addr.country ?? "FR",
        }), orderId);
      }
      markOrderPaid(orderId);
    }
  }
  return NextResponse.json({ received: true });
}

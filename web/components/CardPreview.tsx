import { cardSvg } from "@/lib/cardSvg";
import type { CardDesign } from "@/lib/catalog";

/** Aperçu de carte (SVG généré par lib/cardSvg, échappé). Utilisable côté client et serveur. */
export function CardPreview({ design, side, qrUrl, className, style }: { design: CardDesign; side: "recto" | "verso"; qrUrl: string; className?: string; style?: React.CSSProperties }) {
  return <div className={`card-frame ${className ?? ""}`} style={style} dangerouslySetInnerHTML={{ __html: cardSvg(design, { side, qrUrl }) }} />;
}

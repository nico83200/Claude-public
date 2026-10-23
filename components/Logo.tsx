import Link from "next/link";
import { BRAND_NAME, brandMark } from "@/lib/brand";

/** Symbole Tapikard (même dessin que sur la carte Basic et le favicon). */
export function LogoMark({ size = 30 }: { size?: number }) {
  return (
    <svg className="logo-mark" width={size} height={size} viewBox="0 0 140 140" aria-hidden
      dangerouslySetInnerHTML={{ __html: brandMark(0, 0, 140) }} />
  );
}

/** Logo complet : symbole + mot-symbole. `tone="light"` sur fond sombre. */
export function Logo({ size = 30, tone = "dark", href = "/" }: { size?: number; tone?: "dark" | "light"; href?: string | null }) {
  const content = (
    <>
      <LogoMark size={size} />
      <span className="logo-word" style={{ fontSize: size * 0.62 }}>{BRAND_NAME}</span>
    </>
  );
  const cls = `logo ${tone === "light" ? "logo-light" : ""}`;
  return href ? <Link href={href} className={cls} aria-label={`${BRAND_NAME} — accueil`}>{content}</Link> : <span className={cls}>{content}</span>;
}

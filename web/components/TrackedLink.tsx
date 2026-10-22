"use client";

/** Lien externe dont le clic est comptabilisé (sendBeacon, sans cookie). */
export function TrackedLink({ cardId, target, href, className, children }: { cardId?: string; target: string; href: string; className?: string; children: React.ReactNode }) {
  return (
    <a
      href={href}
      className={className}
      target={href.startsWith("http") ? "_blank" : undefined}
      rel="noopener noreferrer"
      onClick={() => {
        if (!cardId) return;
        const body = JSON.stringify({ cardId, action: "click", target });
        navigator.sendBeacon?.("/api/evenements", new Blob([body], { type: "application/json" }));
      }}
    >
      {children}
    </a>
  );
}

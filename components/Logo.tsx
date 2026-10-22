import Link from "next/link";

export function LogoMark({ size = 30 }: { size?: number }) {
  return (
    <span className="logo-mark" style={{ width: size, height: size }} aria-hidden>
      <svg width={size * 0.6} height={size * 0.6} viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round">
        <circle cx="6" cy="12" r="1.6" fill="#fff" stroke="none" />
        <path d="M10 8a5 5 0 0 1 0 8" />
        <path d="M14 5a9 9 0 0 1 0 14" />
        <path d="M18 2.5a13 13 0 0 1 0 19" />
      </svg>
    </span>
  );
}

export function Logo() {
  return (
    <Link href="/" className="logo">
      <LogoMark /> Tapikard
    </Link>
  );
}

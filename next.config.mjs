// Configuration en JavaScript (et non TypeScript) : sur les hébergements mutualisés au système ancien
// (Hostinger : glibc < 2.29), le compilateur natif SWC ne se charge pas et next.config.ts ne peut pas être compilé.

/** @type {import('next').NextConfig} */
const nextConfig = {
  // NEXT_OUTPUT=standalone : build autonome pour un VPS (`node server.js`, voir deploy/build-package.sh).
  // Sans cette variable (Hostinger), build classique lancé avec `npm start`.
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  // Logos et photos de profil sont envoyés en data URL via les server actions.
  experimental: { serverActions: { bodySizeLimit: "2mb" } },
};

export default nextConfig;

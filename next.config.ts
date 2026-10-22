import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // NEXT_OUTPUT=standalone : build autonome pour un VPS (`node server.js`, voir deploy/build-package.sh).
  // Sans cette variable (Hostinger), build classique lancé avec `npm start`.
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  // Logos et photos de profil sont envoyés en data URL via les server actions.
  experimental: { serverActions: { bodySizeLimit: "2mb" } },
};

export default nextConfig;

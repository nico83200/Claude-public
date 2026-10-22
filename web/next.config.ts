import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Logos et photos de profil sont envoyés en data URL via les server actions.
  experimental: { serverActions: { bodySizeLimit: "2mb" } },
};

export default nextConfig;

// Données de démonstration (désactiver avec SEED_DEMO=0 en production).
import { get, run } from "./db";
import { DEFAULT_DESIGN, priceCents } from "./catalog";

const SHIPPING = JSON.stringify({ name: "Camille Martin", address: "12 rue des Arts", zip: "69002", city: "Lyon", country: "FR" });

export function seedDemo() {
  if (get("SELECT id FROM users LIMIT 1")) return;
  run("INSERT INTO users (id, email, name, is_admin) VALUES ('u_admin', 'admin@tapikard.com', 'Admin Tapikard', 1)");
  run("INSERT INTO users (id, email, name) VALUES ('u_demo', 'demo@tapikard.com', 'Camille Martin')");
  run("INSERT INTO users (id, email, name) VALUES ('u_client2', 'jules@exemple.fr', 'Jules Bernard')");

  run(
    `INSERT INTO profiles (id, user_id, label, display_name, title, company, bio, phone, mobile, email, website, address, links, accent)
     VALUES ('p_demo', 'u_demo', 'Professionnel', 'Camille Martin', 'Directrice commerciale', 'Atelier Nova',
       'J''aide les marques à créer des espaces qui marquent les esprits.', '+33 4 78 00 00 00', '+33 6 12 34 56 78',
       'camille@ateliernova.fr', 'ateliernova.fr', '12 rue des Arts, 69002 Lyon', ?, '#5b3df5')`,
    JSON.stringify([
      { label: "LinkedIn", url: "https://www.linkedin.com/" },
      { label: "Portfolio", url: "https://ateliernova.fr/realisations" },
      { label: "Prendre rendez-vous", url: "https://calendly.com/" },
    ]),
  );
  run(
    `INSERT INTO profiles (id, user_id, label, display_name, title, mobile, email, accent, dark, lead_capture)
     VALUES ('p_demo_perso', 'u_demo', 'Personnel', 'Camille Martin', 'Photographe amatrice', '+33 6 12 34 56 78', 'camille.m@exemple.fr', '#0ea5e9', 1, 0)`,
  );
  run("INSERT INTO profiles (id, user_id, display_name, title, company, email) VALUES ('p_jules', 'u_client2', 'Jules Bernard', 'Architecte', 'Bernard & Associés', 'jules@exemple.fr')");

  const design = (over: object) => JSON.stringify({ ...DEFAULT_DESIGN, ...over });
  const o = (id: string, user: string, profile: string, status: string, material: string, qty: number, extra = "") => {
    run(
      `INSERT INTO orders (id, user_id, profile_id, status, design, material, finish, quantity, amount_cents, shipping, created_at, paid_at, proof_approved_at, shipped_at)
       VALUES (?, ?, ?, ?, ?, ?, 'mat', ?, ?, ?, datetime('now', '-40 days'), ${status === "pending_payment" ? "NULL" : "datetime('now', '-39 days')"},
       ${["proof_approved", "in_production", "shipped", "delivered"].includes(status) ? "datetime('now', '-38 days')" : "NULL"}, ${["shipped", "delivered"].includes(status) ? "datetime('now', '-35 days')" : "NULL"})`,
      id, user, profile, status, design({ material, ...(extra ? JSON.parse(extra) : {}) }), material, qty, priceCents(material as "pvc_blanc", "mat", qty).total, SHIPPING,
    );
  };
  o("cmd_demoLivree0001", "u_demo", "p_demo", "delivered", "pvc_blanc", 2);
  o("cmd_demoBatAValider", "u_demo", "p_demo", "paid", "metal_noir", 1, JSON.stringify({ template: "minimal", ink: "#d4d4d8" }));
  o("cmd_julesProd00001", "u_client2", "p_jules", "proof_approved", "bambou", 5, JSON.stringify({ template: "classique", name: "Jules Bernard", title: "Architecte", company: "Bernard & Associés", ink: "#3b2614" }));

  // Carte active dont l'UID correspond au vecteur de test NXP AN12196 :
  // /c?e=EF963FF7828658A599F3041510671E88&m=94EED9EE65337086 (avec SUN_FACTORY_KEYS / dev sans clés).
  run(`INSERT INTO cards (id, order_id, user_id, profile_id, label, uid, status, activation_code, last_counter, encoded_at, activated_at)
       VALUES ('demo424sun', 'cmd_demoLivree0001', 'u_demo', 'p_demo', 'Carte bureau', '04DE5F1EACC040', 'active', 'DEMO-0001', 0, datetime('now', '-36 days'), datetime('now', '-34 days'))`);
  run(`INSERT INTO cards (id, order_id, user_id, profile_id, label, uid, status, activation_code, last_counter, encoded_at)
       VALUES ('demoAttente', 'cmd_demoLivree0001', 'u_demo', 'p_demo', 'Carte de secours', '04A1B2C3D4E5F6', 'shipped', 'DEMO-0002', 0, datetime('now', '-36 days'))`);
  run("INSERT INTO cards (id, order_id, user_id, profile_id, label, activation_code) VALUES ('demoBatMetal', 'cmd_demoBatAValider', 'u_demo', 'p_demo', 'Ma carte', 'DEMO-0003')");
  for (let i = 1; i <= 5; i++) {
    run("INSERT INTO cards (id, order_id, user_id, profile_id, label, activation_code) VALUES (?, 'cmd_julesProd00001', 'u_client2', 'p_jules', ?, ?)", `julesCarte${i}`, `Carte ${i}`, `JULE-000${i}`);
  }

  // Historique de scans sur 30 jours (pseudo-aléatoire déterministe)
  let seed = 7;
  const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  const countries = ["FR", "FR", "FR", "FR", "BE", "CH", "CA", "DE"];
  for (let day = 29; day >= 0; day--) {
    const n = Math.floor(rnd() * 7) + (day < 10 ? 3 : 1);
    for (let k = 0; k < n; k++) {
      const nfc = rnd() < 0.75;
      const when = `datetime('now', '-${day} days', '-${Math.floor(rnd() * 600)} minutes')`;
      const ctx = [nfc ? "nfc" : "qr", nfc ? 1 : 0, countries[Math.floor(rnd() * countries.length)], rnd() < 0.6 ? "iOS" : "Android"] as const;
      run(`INSERT INTO scan_events (card_id, profile_id, at, channel, verified, action, country, device) VALUES ('demo424sun', 'p_demo', ${when}, ?, ?, 'view', ?, ?)`, ...ctx);
      if (rnd() < 0.45) run(`INSERT INTO scan_events (card_id, profile_id, at, channel, verified, action, country, device) VALUES ('demo424sun', 'p_demo', ${when}, ?, ?, 'vcf', ?, ?)`, ...ctx);
      if (rnd() < 0.3) run(`INSERT INTO scan_events (card_id, profile_id, at, channel, verified, action, target, country, device) VALUES ('demo424sun', 'p_demo', ${when}, ?, ?, 'click', ?, ?, ?)`, ctx[0], ctx[1], rnd() < 0.6 ? "LinkedIn" : "Portfolio", ctx[2], ctx[3]);
    }
  }
  const leads = [
    ["Thomas Leroy", "thomas.leroy@exemple.fr", "+33 6 98 76 54 32", "Groupe Leroy", "Ravi de notre échange au salon, on se rappelle la semaine prochaine ?", 2],
    ["Sarah Benali", "sarah@studio-b.fr", null, "Studio B", "Intéressée par vos aménagements de showroom.", 6],
    ["Marc Dubois", "marc.dubois@exemple.com", "+33 7 11 22 33 44", null, null, 12],
  ] as const;
  for (const [name, email, phone, company, message, days] of leads) {
    run(`INSERT INTO leads (profile_id, card_id, name, email, phone, company, message, created_at) VALUES ('p_demo', 'demo424sun', ?, ?, ?, ?, ?, datetime('now', '-${days} days'))`, name, email, phone, company, message);
    run(`INSERT INTO scan_events (card_id, profile_id, at, channel, verified, action) VALUES ('demo424sun', 'p_demo', datetime('now', '-${days} days'), 'nfc', 1, 'lead')`);
  }
}

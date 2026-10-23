import type { DatabaseSync, SQLInputValue } from "node:sqlite";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { randomInt } from "node:crypto";
import { seedDemo } from "./seed";

// node:sqlite est chargé hors bundler (module natif de Node ≥ 22).
const { DatabaseSync: Database } = process.getBuiltinModule("node:sqlite") as typeof import("node:sqlite");

const SCHEMA = `
CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  name TEXT,
  is_admin INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS login_tokens (
  token_hash TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  used INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS profiles (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL DEFAULT 'Professionnel',
  display_name TEXT NOT NULL DEFAULT '',
  title TEXT, company TEXT, bio TEXT,
  phone TEXT, mobile TEXT, email TEXT, website TEXT, address TEXT,
  avatar TEXT,
  links TEXT NOT NULL DEFAULT '[]',
  accent TEXT NOT NULL DEFAULT '#5b3df5',
  dark INTEGER NOT NULL DEFAULT 0,
  lead_capture INTEGER NOT NULL DEFAULT 1,
  is_public INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS orders (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id),
  profile_id TEXT REFERENCES profiles(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'pending_payment',
  design TEXT NOT NULL,
  material TEXT NOT NULL, finish TEXT NOT NULL,
  quantity INTEGER NOT NULL,
  amount_cents INTEGER NOT NULL,
  shipping TEXT NOT NULL,
  proof_comment TEXT,
  tracking TEXT,
  stripe_session_id TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  paid_at TEXT, proof_approved_at TEXT, shipped_at TEXT
);
CREATE TABLE IF NOT EXISTS batches (
  id TEXT PRIMARY KEY,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  note TEXT
);
CREATE TABLE IF NOT EXISTS cards (
  id TEXT PRIMARY KEY,
  order_id TEXT REFERENCES orders(id),
  user_id TEXT REFERENCES users(id),
  profile_id TEXT REFERENCES profiles(id) ON DELETE SET NULL,
  batch_id TEXT REFERENCES batches(id),
  label TEXT,
  uid TEXT UNIQUE,
  status TEXT NOT NULL DEFAULT 'provisioned',
  activation_code TEXT NOT NULL,
  last_counter INTEGER NOT NULL DEFAULT -1,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  encoded_at TEXT, activated_at TEXT
);
CREATE TABLE IF NOT EXISTS scan_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  card_id TEXT REFERENCES cards(id) ON DELETE CASCADE,
  profile_id TEXT,
  at TEXT NOT NULL DEFAULT (datetime('now')),
  channel TEXT NOT NULL,
  verified INTEGER NOT NULL DEFAULT 0,
  action TEXT NOT NULL DEFAULT 'view',
  target TEXT,
  country TEXT,
  device TEXT
);
CREATE INDEX IF NOT EXISTS scan_events_profile_at ON scan_events(profile_id, at);
CREATE TABLE IF NOT EXISTS stock_ruptures (
  cle TEXT PRIMARY KEY,          -- « type:id », ex. material:bambou, finish:soft_touch, template:minimal, basic:blanc
  message TEXT,                  -- affiché aux clients, ex. « Retour prévu fin novembre »
  depuis TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS leads (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  profile_id TEXT NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
  card_id TEXT,
  name TEXT NOT NULL, email TEXT, phone TEXT, company TEXT, message TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS codes_promo (
  code TEXT PRIMARY KEY,             -- ex. K7QM-2XRA : code « entourage » d'un client, aléatoire
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  remise REAL NOT NULL DEFAULT 0.25,
  actif INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS retours (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id TEXT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  motif TEXT NOT NULL,               -- defaut | erreur | retractation | non_recu | autre
  note TEXT,
  cartes TEXT NOT NULL DEFAULT '[]', -- identifiants des cartes retournées (JSON)
  statut TEXT NOT NULL DEFAULT 'ouvert', -- ouvert | rembourse | refuse
  montant_cents INTEGER NOT NULL DEFAULT 0,
  mode TEXT,                         -- stripe | manuel
  stripe_refund_id TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  rembourse_at TEXT
);
`;

// Évolutions du schéma appliquées aux bases existantes (déploiements déjà en ligne) : ajout de colonnes uniquement.
const COLUMNS: [table: string, column: string, definition: string][] = [
  ["orders", "product", "TEXT NOT NULL DEFAULT 'custom'"], // 'custom' (personnalisée) | 'basic' (prête à l'emploi)
  ["cards", "variant", "TEXT"], // carte Basic de stock : 'noir' | 'blanc'
  ["cards", "puce", "TEXT"], // 'desfire' | 'ntag21x' : puce de dépannage en NFC simple (URL fixe, non sécurisée) ; vide : NTAG 424 DNA
  ["profiles", "logo", "TEXT"], // logo affiché à la place du bandeau coloré (data URL PNG/JPEG)
  ["profiles", "logo_fond", "TEXT NOT NULL DEFAULT 'clair'"], // fond du bandeau logo : clair | couleur | sombre
  ["orders", "archived_at", "TEXT"],
  ["codes_promo", "debut", "TEXT"], // début du mois de validité : expédition de la commande
  ["codes_promo", "aleatoire", "INTEGER NOT NULL DEFAULT 0"], // 1 : code aléatoire (les premiers codes contenaient le prénom)
  ["orders", "code_promo", "TEXT"], // code « entourage » utilisé à la commande
  ["orders", "remise_cents", "INTEGER NOT NULL DEFAULT 0"], // remise apportée par ce code
  ["orders", "etiquette_pdf", "TEXT"], // étiquette Colissimo (PDF en base64) // commande archivée : masquée de la liste par défaut
  ["orders", "refunded_cents", "INTEGER NOT NULL DEFAULT 0"], // total remboursé
  ["leads", "status", "TEXT NOT NULL DEFAULT 'nouveau'"],
  ["leads", "note", "TEXT"],
  ["users", "webhook_url", "TEXT"],
  ["login_tokens", "code_hash", "TEXT"], // code à 6 chiffres (connexion depuis un autre appareil, ex. l'application)
];

function migrate(d: DatabaseSync) {
  for (const [table, column, definition] of COLUMNS) {
    const cols = d.prepare(`PRAGMA table_info(${table})`).all() as { name: string }[];
    if (!cols.some((c) => c.name === column)) d.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${definition}`);
  }
  // Codes créés avant la validité à partir de l'expédition : le mois part de l'expédition déjà faite, s'il y en a une.
  d.exec(`UPDATE codes_promo SET debut = (SELECT MIN(o.shipped_at) FROM orders o WHERE o.user_id = codes_promo.user_id AND o.shipped_at >= codes_promo.created_at)
           WHERE debut IS NULL AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id = codes_promo.user_id AND o.shipped_at >= codes_promo.created_at)`);
  // Codes entourage de la première version (prénom + 4 caractères) : remplacés par un code aléatoire
  // s'ils n'ont encore servi à aucune commande ; ceux déjà utilisés restent valables.
  const anciens = d.prepare("SELECT code FROM codes_promo WHERE aleatoire = 0 AND code NOT IN (SELECT code_promo FROM orders WHERE code_promo IS NOT NULL)").all() as { code: string }[];
  const A = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  for (const { code } of anciens) {
    let nouveau: string;
    do {
      const c = Array.from({ length: 8 }, () => A[randomInt(A.length)]).join("");
      nouveau = `${c.slice(0, 4)}-${c.slice(4)}`;
    } while (d.prepare("SELECT 1 FROM codes_promo WHERE code = ?").get(nouveau));
    d.prepare("UPDATE codes_promo SET code = ?, aleatoire = 1 WHERE code = ?").run(nouveau, code);
  }
}

const g = globalThis as unknown as { __tapikardDb?: DatabaseSync };

export function db(): DatabaseSync {
  if (g.__tapikardDb) return g.__tapikardDb;
  // En production, la base est par défaut hors du dossier de l'application : les hébergeurs
  // (Hostinger…) remplacent ce dossier à chaque déploiement.
  const file =
    process.env.DATABASE_PATH ??
    (process.env.NODE_ENV === "production" ? path.join(os.homedir(), "tapikard-data", "tapikard.db") : path.join(process.cwd(), "data", "tapikard.db"));
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const d = new Database(file);
  d.exec("PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;");
  d.exec(SCHEMA);
  migrate(d);
  g.__tapikardDb = d;
  const demo = process.env.SEED_DEMO ?? (process.env.NODE_ENV === "production" ? "0" : "1");
  if (demo === "1") seedDemo();
  return d;
}

type P = SQLInputValue | boolean | undefined;
const norm = (ps: P[]) => ps.map((p) => (p === undefined ? null : typeof p === "boolean" ? (p ? 1 : 0) : p));

// node:sqlite renvoie des objets sans prototype, que React refuse de transmettre aux composants client.
const plain = <T>(row: unknown) => ({ ...(row as object) }) as T;

export function all<T>(sql: string, ...params: P[]): T[] {
  return db().prepare(sql).all(...norm(params)).map((r) => plain<T>(r));
}
export function get<T>(sql: string, ...params: P[]): T | undefined {
  const row = db().prepare(sql).get(...norm(params));
  return row ? plain<T>(row) : undefined;
}
export function run(sql: string, ...params: P[]) {
  return db().prepare(sql).run(...norm(params));
}
export function tx<T>(fn: () => T): T {
  const d = db();
  d.exec("BEGIN");
  try {
    const r = fn();
    d.exec("COMMIT");
    return r;
  } catch (e) {
    d.exec("ROLLBACK");
    throw e;
  }
}

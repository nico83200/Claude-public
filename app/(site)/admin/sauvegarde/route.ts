import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { currentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { randomId } from "@/lib/ids";

/** Copie cohérente de la base (VACUUM INTO), téléchargeable par un administrateur. */
export async function GET() {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const tmp = path.join(os.tmpdir(), `tapikard-${randomId(8)}.db`);
  try {
    db().prepare("VACUUM INTO ?").run(tmp);
    const data = fs.readFileSync(tmp);
    const date = new Date().toISOString().slice(0, 16).replace(/[:T]/g, "-");
    return new Response(data, {
      headers: {
        "Content-Type": "application/vnd.sqlite3",
        "Content-Disposition": `attachment; filename="tapikard-${date}.db"`,
        "Cache-Control": "no-store",
      },
    });
  } finally {
    fs.rmSync(tmp, { force: true });
  }
}

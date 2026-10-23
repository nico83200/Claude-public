import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { brandMarkSvg } from "../lib/brand.ts";

test("Le favicon est exactement le logo de la marque (lib/brand.ts)", () => {
  assert.equal(fs.readFileSync(new URL("../app/icon.svg", import.meta.url), "utf8").trim(), brandMarkSvg());
});

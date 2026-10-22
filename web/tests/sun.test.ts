import { test } from "node:test";
import assert from "node:assert/strict";
import { aesCmac, diversifyKey, verifySun } from "../lib/sun.ts";

test("AES-CMAC — vecteurs RFC 4493", () => {
  const k = Buffer.from("2b7e151628aed2a6abf7158809cf4f3c", "hex");
  assert.equal(aesCmac(k, Buffer.alloc(0)).toString("hex"), "bb1d6929e95937287fa37d129b756746");
  assert.equal(aesCmac(k, Buffer.from("6bc1bee22e409f96e93d7e117393172a", "hex")).toString("hex"), "070a16b46b4d4144f79bdd9dd04a287c");
  const m40 = Buffer.from("6bc1bee22e409f96e93d7e117393172aae2d8a571e03ac9c9eb76fac45af8e5130c81c46a35ce411", "hex");
  assert.equal(aesCmac(k, m40).toString("hex"), "dfa66747de9ae63030ca32611497c827");
});

test("SUN — vecteur NXP AN12196 (clés d'usine)", () => {
  const zero = Buffer.alloc(16);
  const r = verifySun(zero, () => zero, "EF963FF7828658A599F3041510671E88", "94EED9EE65337086");
  assert.deepEqual(r, { uid: "04DE5F1EACC040", counter: 61 });
});

test("SUN — signature falsifiée refusée", () => {
  const zero = Buffer.alloc(16);
  assert.throws(() => verifySun(zero, () => zero, "EF963FF7828658A599F3041510671E88", "94EED9EE65337087"));
});

test("Diversification identique à tools/sun_verify.py", () => {
  const master = Buffer.from("00112233445566778899aabbccddeeff", "hex");
  const uid = Buffer.from("04DE5F1EACC040", "hex");
  assert.equal(diversifyKey(master, uid, 1).toString("hex"), "d4b21c66aa83e7987cf053633991d424");
});

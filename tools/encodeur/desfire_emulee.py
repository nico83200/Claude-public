"""
MIFARE DESFire EV2 émulée (côté carte), pour tester la programmation en « NFC simple » sans carte.
Implémente ce dont lib/desfire.ts a besoin : GetVersion, SelectApplication, AuthenticateISO (clé DES d'usine)
et ChangeKey EV1 de la clé maître, AuthenticateEV2First et messagerie sécurisée EV2, FormatPICC,
CreateApplication, CreateStdDataFile, WriteData, ChangeFileSettings, ChangeKeySettings, ChangeKey,
et la lecture NFC Forum Type 4 (sélection ISO, ReadBinary).

Limite : écrit d'après la documentation NXP, pas validé sur une vraie puce. La dérivation de la clé de session
après l'authentification DES est paramétrable (« des » ou « 2k3des ») pour tester les deux hypothèses.
"""
from __future__ import annotations

import os

from cryptography.hazmat.decrepit.ciphers.algorithms import TripleDES
from cryptography.hazmat.primitives.ciphers import Cipher, modes

import ntag424 as n

VERSIONS = {
    "desfire_ev2": (bytes.fromhex("04010112001805"), bytes.fromhex("04010102011805")),
    "desfire_ev3": (bytes.fromhex("04010133001805"), bytes.fromhex("04010103001805")),
    "desfire_ev1": (bytes.fromhex("04010101001805"), bytes.fromhex("04010101001805")),
}


def _tdes(enc: bool, cle: bytes, iv: bytes, data: bytes) -> bytes:
    c = Cipher(TripleDES(cle), modes.CBC(iv))
    o = c.encryptor() if enc else c.decryptor()
    return o.update(data) + o.finalize()


class DesfireEmulee:
    def __init__(self, uid: bytes, puce: str = "desfire_ev2", derivation: str = "des"):
        self.uid = uid
        self.version = VERSIONS[puce]
        self.derivation = derivation
        self.picc_cle = ("des", bytes(16))                       # clé maître d'usine : DES 00…00
        self.picc_reglages = 0x0F                                # création d'applications libre
        self.apps: dict[bytes, dict] = {}
        self.sel = None                                          # None : niveau carte, sinon AID
        self.fichier_iso = None
        self.auth = None
        self.etape = None

    # --- utilitaires -----------------------------------------------------------
    @staticmethod
    def _sw(sw: int, data: bytes = b"") -> bytes:
        return data + sw.to_bytes(2, "big")

    def _app(self) -> dict | None:
        return self.apps.get(self.sel) if self.sel else None

    def _iv(self, label: bytes) -> bytes:
        s = self.auth
        return n.aes_ecb_encrypt(s["k_enc"], label + s["ti"] + s["ctr"].to_bytes(2, "little") + bytes(8))

    def _mac(self, prefix: int, data: bytes) -> bytes:
        s = self.auth
        return n.mac_truncate(n.cmac(s["k_mac"], bytes([prefix]) + s["ctr"].to_bytes(2, "little") + s["ti"] + data))

    def _reponse(self) -> bytes:
        self.auth["ctr"] += 1
        return self._sw(0x9100, self._mac(0x00, b""))

    def _reset(self):
        self.auth, self.etape, self.fichier_iso = None, None, None

    def url_lue(self) -> str:
        for app in self.apps.values():
            if app["df"] == n.NDEF_AID:
                return n.parse_uri_file(bytes(app["fichiers"][2]["data"]))
        raise ValueError("pas d'application NFC")

    # --- APDU ---------------------------------------------------------------------
    def transmit(self, apdu: bytes) -> bytes:
        cla, ins = apdu[0], apdu[1]
        if cla == 0x00:
            return self._iso(apdu)
        if cla != 0x90:
            return self._sw(0x6E00)
        if len(apdu) == 5:
            data = b""
        else:
            lc = apdu[4]
            data = apdu[5:5 + lc]
            if len(apdu) != 5 + lc + 1:
                return self._sw(0x917E)
        return self._natif(ins, data)

    def _iso(self, apdu: bytes) -> bytes:
        ins, p1, p2 = apdu[1], apdu[2], apdu[3]
        if ins == 0xA4:
            cible = apdu[5:5 + apdu[4]]
            if p1 == 0x04:
                for aid, app in self.apps.items():
                    if app["df"] == cible:
                        self._reset()
                        self.sel = aid
                        return self._sw(0x9000)
                return self._sw(0x6A82)
            app = self._app()
            if p1 == 0x00 and app:
                for no, f in app["fichiers"].items():
                    if f["fid"] == int.from_bytes(cible, "big"):
                        self.fichier_iso = no
                        return self._sw(0x9000)
            return self._sw(0x6A82)
        app = self._app()
        if ins == 0xB0 and app and self.fichier_iso:
            f = app["fichiers"][self.fichier_iso]
            if f["acces"]["read"] != 0xE:
                return self._sw(0x6982)
            off = (p1 << 8) | p2
            return self._sw(0x9000, bytes(f["data"][off:off + (apdu[4] or 256)]))
        return self._sw(0x6D00)

    def _cle_niveau(self, key_no: int):
        if self.sel is None:
            return self.picc_cle if key_no == 0 else None
        app = self._app()
        return ("aes", app["cle"]) if app and key_no == 0 else None

    def _natif(self, ins: int, data: bytes) -> bytes:
        # Trames de suite (GetVersion, authentifications)
        if ins == 0xAF and self.etape:
            e, self.etape = self.etape, None
            if e[0] == "version":
                if e[1] == 1:
                    self.etape = ("version", 2)
                    return self._sw(0x91AF, self.version[1])
                return self._sw(0x9100, self.uid + bytes.fromhex("BA34999F50031200"))
            if e[0] == "1a":
                _, cle, rnd_b, ek = e
                clair = _tdes(False, cle, ek, data)
                rnd_a = clair[:8]
                if clair[8:] != n.rotl(rnd_b):
                    return self._sw(0x91AE)
                a, b = rnd_a, rnd_b
                session = (a[:4] + b[:4]) * 2 if self.derivation == "des" else a[:4] + b[:4] + a[4:8] + b[4:8]
                self.auth = {"type": "ev1", "session": session, "key_no": 0}
                return self._sw(0x9100, _tdes(True, cle, data[8:16], n.rotl(rnd_a)))
            if e[0] == "71":
                _, key_no, cle, rnd_b = e
                clair = n.aes_cbc_decrypt(cle, bytes(16), data)
                rnd_a = clair[:16]
                if clair[16:] != n.rotl(rnd_b):
                    return self._sw(0x91AE)
                ti = os.urandom(4)
                sv1, sv2 = n.session_vectors(rnd_a, rnd_b)
                self.auth = {"type": "ev2", "ti": ti, "k_enc": n.cmac(cle, sv1), "k_mac": n.cmac(cle, sv2), "ctr": 0, "key_no": key_no}
                return self._sw(0x9100, n.aes_cbc_encrypt(cle, bytes(16), ti + n.rotl(rnd_a) + bytes(12)))
        self.etape = None

        if ins == 0x60:
            self.etape = ("version", 1)
            return self._sw(0x91AF, self.version[0])
        if ins == 0x5A:
            aid = bytes(data)
            if aid != bytes(3) and aid not in self.apps:
                return self._sw(0x91A0)
            self._reset()
            self.sel = None if aid == bytes(3) else aid
            return self._sw(0x9100)
        if ins == 0x1A:
            cle = self._cle_niveau(data[0])
            self.auth = None
            if not cle or cle[0] != "des":
                return self._sw(0x91AE)
            rnd_b = os.urandom(8)
            ek = _tdes(True, cle[1], bytes(8), rnd_b)
            self.etape = ("1a", cle[1], rnd_b, ek)
            return self._sw(0x91AF, ek)
        if ins == 0x71:
            cle = self._cle_niveau(data[0])
            self.auth = None
            if not cle or cle[0] != "aes":
                return self._sw(0x91AE)
            rnd_b = os.urandom(16)
            self.etape = ("71", data[0], cle[1], rnd_b)
            return self._sw(0x91AF, n.aes_cbc_encrypt(cle[1], bytes(16), rnd_b))

        # Commandes sans authentification (création libre, fichiers en accès libre)
        if ins == 0xCA and self.auth is None:
            if self.sel is not None or not self.picc_reglages & 0x04:
                return self._sw(0x91AE)
            aid, ks1, ks2, fid, df = data[:3], data[3], data[4], int.from_bytes(data[5:7], "little"), data[7:]
            if aid in self.apps or any(a["df"] == df for a in self.apps.values()):
                return self._sw(0x91DE)
            if ks2 >> 6 != 2 or not ks2 & 0x20:
                return self._sw(0x919E)
            self.apps[aid] = {"df": df, "fid": fid, "cle": bytes(16), "reglages": ks1, "fichiers": {}}
            return self._sw(0x9100)
        if ins == 0xCD and self.auth is None:
            app = self._app()
            if not app or not app["reglages"] & 0x04:
                return self._sw(0x91AE)
            no, fid, ar0, ar1, taille = data[0], int.from_bytes(data[1:3], "little"), data[4], data[5], int.from_bytes(data[6:9], "little")
            if no in app["fichiers"]:
                return self._sw(0x91DE)
            app["fichiers"][no] = {"fid": fid, "acces": {"rw": ar0 >> 4, "change": ar0 & 0xF, "read": ar1 >> 4, "write": ar1 & 0xF},
                                   "data": bytearray(taille)}
            return self._sw(0x9100)
        if ins == 0x3D and self.auth is None:
            app = self._app()
            f = app and app["fichiers"].get(data[0])
            if not f or f["acces"]["write"] != 0xE:
                return self._sw(0x919D)
            off, lg = int.from_bytes(data[1:4], "little"), int.from_bytes(data[4:7], "little")
            if off + lg > len(f["data"]):
                return self._sw(0x91BE)
            f["data"][off:off + lg] = data[7:7 + lg]
            return self._sw(0x9100)

        if self.auth is None:
            return self._sw(0x91AE)

        # Session EV1 (clé DES d'usine) : FormatPICC en clair, ChangeKey chiffré en 3DES.
        if self.auth["type"] == "ev1":
            if ins == 0xFC and self.sel is None:
                self.apps = {}
                return self._sw(0x9100, os.urandom(8))
            if ins == 0xC4 and self.sel is None and data[0] == 0x80:
                clair = _tdes(False, self.auth["session"], bytes(8), data[1:])
                nouvelle, ver, crc = clair[:16], clair[16], clair[17:21]
                if crc != n.crc32nk(bytes([0xC4, 0x80]) + nouvelle + bytes([ver])):
                    self.auth = None
                    return self._sw(0x911E)
                self.picc_cle = ("aes", nouvelle)
                self.auth = None
                return self._sw(0x9100)
            return self._sw(0x911C)

        # Session EV2 : commandes MACées ou chiffrées.
        en_tete_len = {0xFC: 0, 0x54: 0, 0x5F: 1, 0xC4: 1}.get(ins)
        if en_tete_len is None:
            return self._sw(0x911C)
        corps, mac = data[:-8], data[-8:]
        if mac != self._mac(ins, corps):
            self.auth = None
            return self._sw(0x911E)
        en_tete, chiffre = corps[:en_tete_len], corps[en_tete_len:]
        clair = n.unpad(n.aes_cbc_decrypt(self.auth["k_enc"], self._iv(b"\xA5\x5A"), chiffre)) if chiffre else b""
        if ins == 0xFC:
            if self.sel is not None:
                return self._sw(0x919D)
            self.apps = {}
            return self._reponse()
        if ins == 0x54:
            if self.sel is None:
                self.picc_reglages = clair[0]
            else:
                self._app()["reglages"] = clair[0]
            return self._reponse()
        if ins == 0x5F:
            f = self._app()["fichiers"].get(en_tete[0])
            if not f:
                return self._sw(0x91F0)
            ar0, ar1 = clair[1], clair[2]
            f["acces"] = {"rw": ar0 >> 4, "change": ar0 & 0xF, "read": ar1 >> 4, "write": ar1 & 0xF}
            return self._reponse()
        if ins == 0xC4:
            if en_tete[0] & 0x0F != 0:
                return self._sw(0x9140)
            if self.sel is None:
                if en_tete[0] != 0x80:
                    return self._sw(0x919E)
                self.picc_cle = ("aes", clair[:16])
            else:
                self._app()["cle"] = clair[:16]
            self.auth = None                                     # la clé de session a changé : pas de MAC
            return self._sw(0x9100)
        return self._sw(0x911C)

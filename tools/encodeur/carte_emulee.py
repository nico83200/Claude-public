"""
Carte NTAG 424 DNA émulée (côté carte), pour tester l'encodeur sans lecteur ni carte.
Implémente ce dont l'encodage a besoin : sélection, lecture/écriture ISO avec miroir SUN,
AuthenticateEV2First, messagerie sécurisée, GetCardUID, ChangeFileSettings, GetFileSettings,
ChangeKey (avec contrôle CRC) et WriteData.
"""
from __future__ import annotations

import os

import ntag424 as n


# Réponses GetVersion (trames matériel et logiciel) des puces émulées.
VERSIONS = {
    "ntag424": (bytes.fromhex("04040230001105"), bytes.fromhex("04040201021105")),
    "desfire_ev3": (bytes.fromhex("04010133001805"), bytes.fromhex("04010103001805")),
}


class CarteEmulee:
    def __init__(self, uid: bytes, puce: str = "ntag424"):
        self.uid = uid
        self.puce = puce                                        # « desfire_ev3 » : carte vierge sans application NDEF
        self.version_etape = 0
        self.cles = [bytes(16)] * 5
        self.fichier = bytearray(256)                          # fichier NDEF vide (NLEN = 0)
        self.acces = {"read": 0xE, "write": 0xE, "rw": 0xE, "change": 0x0}
        self.sdm = None                                         # dict de la configuration SUN
        self.compteur_sdm = 0
        self.app = False
        self.fichier_iso = None
        self.session = None                                     # dict : ti, k_enc, k_mac, ctr, key_no
        self.auth_en_cours = None

    # --- utilitaires -----------------------------------------------------------
    @staticmethod
    def _sw(sw: int, data: bytes = b"") -> bytes:
        return data + sw.to_bytes(2, "big")

    def _iv(self, label: bytes, ctr: int) -> bytes:
        s = self.session
        return n.aes_ecb_encrypt(s["k_enc"], label + s["ti"] + ctr.to_bytes(2, "little") + bytes(8))

    def _mac(self, prefix: int, ctr: int, data: bytes) -> bytes:
        s = self.session
        return n.mac_truncate(n.cmac(s["k_mac"], bytes([prefix]) + ctr.to_bytes(2, "little") + s["ti"] + data))

    def _reponse(self, data: bytes = b"", full: bool = True) -> bytes:
        s = self.session
        s["ctr"] += 1
        body = n.aes_cbc_encrypt(s["k_enc"], self._iv(b"\x5A\xA5", s["ctr"]), n.pad(data)) if (full and data) else data
        return self._sw(0x9100, body + self._mac(0x00, s["ctr"], body))

    def _contenu_lu(self) -> bytes:
        """Contenu du fichier tel que lu par un téléphone : miroir SUN appliqué si configuré."""
        f = bytearray(self.fichier)
        if self.sdm:
            self.compteur_sdm += 1
            ctr = self.compteur_sdm.to_bytes(3, "little")
            picc = bytes([0xC7]) + self.uid + ctr + os.urandom(5)
            e = n.aes_cbc_encrypt(self.cles[self.sdm["meta"]], bytes(16), picc).hex().upper().encode()
            f[self.sdm["picc"]:self.sdm["picc"] + 32] = e
            sv2 = bytes.fromhex("3CC300010080") + self.uid + ctr
            k_ses = n.cmac(self.cles[self.sdm["file"]], sv2)
            m = n.mac_truncate(n.cmac(k_ses, bytes(f[self.sdm["mac_in"]:self.sdm["mac"]]))).hex().upper().encode()
            f[self.sdm["mac"]:self.sdm["mac"] + 16] = m
        return bytes(f)

    def lire_comme_un_telephone(self) -> str:
        self.session = None
        return n.parse_uri_file(self._contenu_lu())

    # --- APDU ---------------------------------------------------------------------
    def transmit(self, apdu: bytes) -> bytes:
        cla, ins = apdu[0], apdu[1]
        if cla == 0x00:
            return self._iso(apdu)
        if cla == 0x90:
            if len(apdu) == 5:                                  # sans données : CLA INS P1 P2 Le
                return self._natif(ins, b"") if apdu[4] == 0x00 else self._sw(0x917E)
            lc = apdu[4]
            data = apdu[5:5 + lc]
            if len(apdu) != 5 + lc + 1 or apdu[-1] != 0x00:
                return self._sw(0x917E)
            return self._natif(ins, data)
        return self._sw(0x6E00)

    def _iso(self, apdu: bytes) -> bytes:
        ins, p1, p2 = apdu[1], apdu[2], apdu[3]
        if ins == 0xA4:
            lc = apdu[4]
            cible = apdu[5:5 + lc]
            if p1 == 0x04 and cible == n.NDEF_AID and self.puce == "ntag424":
                self.app, self.fichier_iso, self.session = True, None, None
                return self._sw(0x9000)
            if p1 == 0x00 and self.app and cible in (b"\xE1\x03", b"\xE1\x04"):
                self.fichier_iso = int.from_bytes(cible, "big")
                return self._sw(0x9000)
            return self._sw(0x6A82)
        if self.fichier_iso != n.NDEF_FILE_ID:
            return self._sw(0x6986)
        offset = (p1 << 8) | p2
        if ins == 0xB0:
            if self.acces["read"] != 0xE:
                return self._sw(0x6982)
            le = apdu[4] or 256
            return self._sw(0x9000, self._contenu_lu()[offset:offset + le])
        if ins == 0xD6:
            lc = apdu[4]
            if len(apdu) != 5 + lc:
                return self._sw(0x6700)                         # comme le silicium : pas de Le
            if self.acces["write"] != 0xE:
                return self._sw(0x6982)
            self.fichier[offset:offset + lc] = apdu[5:5 + lc]
            return self._sw(0x9000)
        return self._sw(0x6D00)

    def _natif(self, ins: int, data: bytes) -> bytes:
        if ins == 0x60:                                         # GetVersion (niveau PICC)
            self.version_etape = 1
            return self._sw(0x91AF, VERSIONS[self.puce][0])
        if ins == 0xAF and self.version_etape:
            self.version_etape += 1
            if self.version_etape == 2:
                return self._sw(0x91AF, VERSIONS[self.puce][1])
            self.version_etape = 0
            return self._sw(0x9100, self.uid + bytes.fromhex("BA34999F50031200"))
        self.version_etape = 0
        if not self.app:
            return self._sw(0x91CA)
        if ins == 0x71:
            key_no = data[0]
            if key_no > 4:
                return self._sw(0x9140)
            rnd_b = os.urandom(16)
            self.auth_en_cours = (key_no, rnd_b)
            self.session = None
            return self._sw(0x91AF, n.aes_cbc_encrypt(self.cles[key_no], bytes(16), rnd_b))
        if ins == 0xAF and self.auth_en_cours:
            key_no, rnd_b = self.auth_en_cours
            self.auth_en_cours = None
            plain = n.aes_cbc_decrypt(self.cles[key_no], bytes(16), data)
            rnd_a, rnd_b_rot = plain[:16], plain[16:]
            if rnd_b_rot != n.rotl(rnd_b):
                return self._sw(0x91AE)
            ti = os.urandom(4)
            sv1, sv2 = n.session_vectors(rnd_a, rnd_b)
            k = self.cles[key_no]
            self.session = {"ti": ti, "k_enc": n.cmac(k, sv1), "k_mac": n.cmac(k, sv2), "ctr": 0, "key_no": key_no}
            return self._sw(0x9100, n.aes_cbc_encrypt(k, bytes(16), ti + n.rotl(rnd_a) + bytes(12)))
        if self.session is None:
            return self._sw(0x91AE)
        s = self.session

        if ins == 0x8D:                                         # WriteData, fichier en CommMode.Plain
            fichier, offset, longueur = data[0], int.from_bytes(data[1:4], "little"), int.from_bytes(data[4:7], "little")
            if fichier != n.NDEF_FILE_NO or self.acces["write"] not in (0xE, s["key_no"]):
                return self._sw(0x919D)
            self.fichier[offset:offset + longueur] = data[7:7 + longueur]
            s["ctr"] += 1
            return self._sw(0x9100)

        en_tete_len = {0x51: 0, 0x5F: 1, 0xC4: 1, 0xF5: 1}.get(ins)
        if en_tete_len is None:
            return self._sw(0x911C)
        corps, mac = data[:-8], data[-8:]
        if mac != self._mac(ins, s["ctr"], corps):
            self.session = None
            return self._sw(0x911E)
        en_tete, chiffre = corps[:en_tete_len], corps[en_tete_len:]
        if ins == 0xF5:
            return self._reponse(self._parametres(), full=False)
        clair = n.unpad(n.aes_cbc_decrypt(s["k_enc"], self._iv(b"\xA5\x5A", s["ctr"]), chiffre)) if chiffre else b""

        if ins == 0x51:
            return self._reponse(self.uid)
        if ins == 0x5F:
            if s["key_no"] != self.acces["change"]:
                return self._sw(0x919D)
            return self._changer_parametres(clair)
        if ins == 0xC4:
            key_no = en_tete[0]
            if s["key_no"] != 0:
                return self._sw(0x919D)
            if key_no == s["key_no"]:
                self.cles[key_no] = clair[:16]
                self.session = None                             # la session se termine, pas de MAC
                return self._sw(0x9100)
            nouvelle = bytes(a ^ b for a, b in zip(clair[:16], self.cles[key_no]))
            if clair[17:21] != n.crc32nk(nouvelle):
                return self._sw(0x911E)
            self.cles[key_no] = nouvelle
            return self._reponse()
        return self._sw(0x911C)

    def _changer_parametres(self, d: bytes) -> bytes:
        option, ar1, ar2 = d[0], d[1], d[2]
        acces = {"rw": ar1 >> 4, "change": ar1 & 0xF, "read": ar2 >> 4, "write": ar2 & 0xF}
        sdm = None
        if option & 0x40:
            opts, sa1, sa2 = d[3], d[4], d[5]
            meta, file_ = sa2 >> 4, sa2 & 0xF
            if opts != 0xC1 or meta > 4 or file_ > 4:           # seule la configuration Tapikard est émulée
                return self._sw(0x919E)
            o = lambda i: int.from_bytes(d[i:i + 3], "little")
            sdm = {"picc": o(6), "mac_in": o(9), "mac": o(12), "meta": meta, "file": file_, "ctr_ret": sa1 & 0xF}
            if len(d) != 15 or sdm["picc"] + 32 > 256 or sdm["mac"] + 16 > 256 or sdm["mac_in"] > sdm["mac"]:
                return self._sw(0x919E)
        self.acces, self.sdm = acces, sdm
        return self._reponse()

    def _parametres(self) -> bytes:
        a = self.acces
        base = bytes([0x00, 0x40 if self.sdm else 0x00, (a["rw"] << 4) | a["change"], (a["read"] << 4) | a["write"]]) + (256).to_bytes(3, "little")
        if not self.sdm:
            return base
        s = self.sdm
        o3 = lambda v: v.to_bytes(3, "little")
        return base + bytes([0xC1, 0xF0 | s["ctr_ret"], (s["meta"] << 4) | s["file"]]) + o3(s["picc"]) + o3(s["mac_in"]) + o3(s["mac"])

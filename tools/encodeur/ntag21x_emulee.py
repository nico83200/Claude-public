"""
NTAG213/215/216 émulée (côté carte, commandes NFC-A brutes), pour tester lib/ntag21x.ts sans carte :
GET_VERSION, READ, WRITE (ACK 0x0A / NAK 0x00), PWD_AUTH, protection en écriture (AUTH0, AUTHLIM),
Capability Container programmable une seule fois.
"""
from __future__ import annotations

MODELES = {"ntag213": (0x0F, 45, 0x29, 0x12), "ntag215": (0x11, 135, 0x83, 0x3E), "ntag216": (0x13, 231, 0xE3, 0x6D)}


class Ntag21xEmulee:
    def __init__(self, uid: bytes, modele: str = "ntag215", cc_usine: bool = True):
        self.uid = uid
        self.taille, self.nb_pages, self.config, cc = MODELES[modele]
        self.mem = bytearray(4 * self.nb_pages)
        self.mem[0:3] = uid[0:3]
        self.mem[3] = 0x88 ^ uid[0] ^ uid[1] ^ uid[2]
        self.mem[4:8] = uid[3:7]
        self.mem[8] = uid[3] ^ uid[4] ^ uid[5] ^ uid[6]
        if cc_usine:
            self.mem[12:16] = bytes([0xE1, 0x10, cc, 0x00])
        c = 4 * self.config
        self.mem[c:c + 4] = bytes([0x04, 0x00, 0x00, 0xFF])          # CFG0 : AUTH0 = FF (pas de protection)
        self.mem[c + 4:c + 8] = bytes([0x00, 0x05, 0x00, 0x00])      # CFG1
        self.mem[c + 8:c + 12] = b"\xFF" * 4                          # PWD d'usine
        self.authentifiee = False
        self.echecs = 0

    def page(self, p: int) -> bytes:
        return bytes(self.mem[4 * p:4 * p + 4])

    def transmit(self, cmd: bytes) -> bytes:
        NAK = b"\x00"
        if cmd[0] == 0x60:
            return bytes([0x00, 0x04, 0x04, 0x02, 0x01, 0x00, self.taille, 0x03])
        if cmd[0] == 0x30:
            p = cmd[1]
            if p >= self.nb_pages:
                return NAK
            out = bytearray()
            for i in range(4):
                q = (p + i) % self.nb_pages
                out += b"\x00" * 4 if q in (self.config + 2, self.config + 3) else self.page(q)   # PWD et PACK lus à 0
            return bytes(out)
        if cmd[0] == 0x1B:
            access = self.mem[4 * self.config + 4]
            if (access & 0x07) and self.echecs >= (access & 0x07):
                return NAK
            if cmd[1:5] == self.page(self.config + 2):
                self.authentifiee, self.echecs = True, 0
                return self.page(self.config + 3)[:2]
            self.echecs += 1
            return NAK
        if cmd[0] == 0xA2 and len(cmd) == 6:
            p, data = cmd[1], cmd[2:6]
            auth0 = self.mem[4 * self.config + 3]
            if p < 2 or p >= self.nb_pages or (p >= auth0 and not self.authentifiee):
                return NAK
            if p in (2, 3):                                           # verrous et CC : bits ajoutés seulement
                cur = self.page(p)
                data = bytes(a | b for a, b in zip(cur, data)) if p == 3 else cur[:2] + bytes(a | b for a, b in zip(cur[2:], data[2:]))
            self.mem[4 * p:4 * p + 4] = data
            return b"\x0A"
        return NAK
